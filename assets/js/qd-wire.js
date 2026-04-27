/* ============================================================
   qd-wire.js — Data-driven wiring for Rarefolio CNFT pages

   Architecture (Apr 2026):
   - Static QD_BLOCKS map: instant fallback for Bar I batches 1-15
   - DB-driven API: scales to 5,000+ batches per bar, any bar size
   - Resolution chain: page override → static map → API → fallback
   - Story mode: shared (1 per block) OR per_item (up to 8 per block)
   - Stories served from /api/blocks/story.php for DB blocks,
     static /assets/stories/ files for legacy blocks

   URL params on nft.html:
     nft=qd-silver-0000001&bar=E101837&set=1&batch=1&col=collection-silverbar-01.html
     Optional:
       block=block00
       item=1..8
       story=/path/to/story.html

   ============================================================ */

(() => {
  const $ = (sel, root = document) => root.querySelector(sel);

  const pad = (n, digits) => String(n).padStart(digits, '0');

  const resolveTemplate = (str, map) => String(str || '').replace(/\{(\w+)\}/g, (_, k) => (map[k] ?? ''));

  function safeJsonParse(maybeJson, fallback) {
    if (!maybeJson) return fallback;
    try { return JSON.parse(maybeJson); } catch { return fallback; }
  }

  function getIntAttr(el, name, fallback) {
    const raw = el.getAttribute(name);
    const n = parseInt(raw || '', 10);
    return Number.isFinite(n) ? n : fallback;
  }

  function getStrAttr(el, name, fallback) {
    const v = el.getAttribute(name);
    return (v == null || v === '') ? fallback : v;
  }

  function parseTokenIndex(slug) {
    const m = String(slug || '').match(/-(\d{1,})$/);
    if (!m) return null;
    const n = parseInt(m[1], 10);
    return Number.isFinite(n) ? n : null;
  }

  /* -------------------- Static block map (Bar I fallback for batches 1-15) --------------------
     NOTE: For batches beyond 15 and for all other bars, blocks are resolved
     via the API (/api/blocks/resolve.php). This map is the instant offline fallback.
  */
  const QD_BLOCKS = {
    block00: { folder: 'scnft_zodiac_taurus',      label: 'Zodiac — Taurus',          story_mode: 'shared'   },
    block01: { folder: 'scnft_sp_inventors',       label: 'Steampunk — Inventors',    story_mode: 'per_item' },
    block02: { folder: 'scnft_zodiac_aries',       label: 'Zodiac — Aries',           story_mode: 'shared'   },
    block03: { folder: 'scnft_sp_robot_butler',    label: 'Steampunk — Robot Butler', story_mode: 'per_item' },
    block04: { folder: 'scnft_zodiac_gemini',      label: 'Zodiac — Gemini',          story_mode: 'shared'   },
    block05: { folder: 'scnft_zodiac_cancer',      label: 'Zodiac — Cancer',          story_mode: 'shared'   },
    block06: { folder: 'scnft_zodiac_leo',         label: 'Zodiac — Leo',             story_mode: 'shared'   },
    block07: { folder: 'scnft_zodiac_virgo',       label: 'Zodiac — Virgo',           story_mode: 'shared'   },
    block08: { folder: 'scnft_zodiac_libra',       label: 'Zodiac — Libra',           story_mode: 'shared'   },
    block09: { folder: 'scnft_zodiac_scorpio',     label: 'Zodiac — Scorpio',         story_mode: 'shared'   },
    block10: { folder: 'scnft_zodiac_sagittarius', label: 'Zodiac — Sagittarius',     story_mode: 'shared'   },
    block11: { folder: 'scnft_zodiac_capricorn',   label: 'Zodiac — Capricorn',       story_mode: 'shared'   },
    block12: { folder: 'scnft_zodiac_aquarius',    label: 'Zodiac — Aquarius',        story_mode: 'shared'   },
    block13: { folder: 'scnft_zodiac_pisces',      label: 'Zodiac — Pisces',          story_mode: 'shared'   },
    block14: { folder: 'scnft_new_series',         label: 'New Series',               story_mode: 'shared'   },
    // Founders Block 88 — 8 unique tokens (qd-silver-0000705 through 0000712)
    block88: { folder: 'scnft_founders',            label: 'Founders Block 88',        story_mode: 'per_item' },
  };

  /* ---- Sold tokens (static fallback) ----
     The Purchase button is now driven by the live marketplace API.
     This set is kept as an offline fallback only — add a token slug here
     if you need an instant SOLD state before the API reflects it.
  */
  const QD_SOLD = new Set([
    // 'qd-silver-0000009',
  ]);

  /* ---- Marketplace config ----
     RF_MARKET_BASE is set by the page (window.RF_MARKET_BASE) or falls back
     to the production marketplace URL. The buy page lives at
     {RF_MARKET_BASE}/buy.php?token={cnft_id}
  */
  const RF_MARKET_BASE = (window.RF_MARKET_BASE || 'https://market.rarefolio.io').replace(/\/$/, '');

  /**
   * Fetch the live sold/available status of a CNFT from the marketplace API.
   * Returns 'sold' | 'available' | null (null = API unavailable, use fallback).
   */
  async function fetchTokenStatus(cnftId) {
    try {
      const resp = await fetch(`${RF_MARKET_BASE}/api/v1/tokens/${encodeURIComponent(cnftId)}`, {
        signal: AbortSignal.timeout ? AbortSignal.timeout(4000) : undefined,
      });
      if (!resp.ok) return null;
      const j = await resp.json();
      const status = j?.data?.status?.primary_sale ?? j?.data?.status?.primary_sale_status ?? null;
      if (status === 'sold' || status === 'sold_pre_marketplace') return 'sold';
      return 'available';
    } catch {
      return null;  // network error — fall back to QD_SOLD
    }
  }

  /* ---- Per-item names for named-character blocks ----
     Key = story_block_id (DB format, e.g. 'E101837-block0002').
     Array is 0-indexed: index 0 = item 1, index 7 = item 8.
     To add a new block: add a new entry keyed by its story_block_id.
  */
  const QD_ITEM_NAMES = {
    'E101837-block0002': [ // Steampunk \u2014 Inventors Guild
      'Miss Nyla Vantress \u2014 The Stormglass Prodigy',
      'Elowen Thrice \u2014 Mistress of Clockwork Nerves',
      'Clara Penhalwick \u2014 The Brassheart Aeronaut',
      'Edmund Vale \u2014 The Iron Wit of Gallowmere',
      'Vivienne Sloane \u2014 Keeper of the Ember Circuit',
      'Octavius Bellmere \u2014 The Grand Old Gearsmith',
      'Thaddeus Crowle \u2014 The Furnace Baron',
      'Ludorian Marrow \u2014 Architect of the Impossible Hour',
    ],
    'E101837-block0004': [ // Steampunk \u2014 Robot Butler
      'Alistair Valecourt',
      'Edmund Aurellian',
      'Theodore Valemont',
      'Lucian Everford',
      'Julian Ashcombe',
      'Reginald Fairbourne',
      'Augustin Wrenhall',
      'Benedict Harrowvale',
    ],
  };
  const FOUNDERS_PERSON_NAMES = {
    'qd-silver-0000705': 'Anton Cherenko',
    'qd-silver-0000706': 'Peter Mednik',
    'qd-silver-0000707': 'Gregor Stoyan',
    'qd-silver-0000708': 'Zina Astrakhan',
    'qd-silver-0000709': 'Tanya Sokolova',
    'qd-silver-0000710': 'Niko Bashnev',
    'qd-silver-0000711': 'Misha Tolmach',
    'qd-silver-0000712': 'Sev Pravdin',
  };

  /* ---- Block slug map (block_id \u2192 URL slug for clean URLs) ---- */
  const BLOCK_SLUGS = {
    block00: 'taurus',      block01: 'inventors',     block02: 'aries',
    block03: 'robot-butler', block04: 'gemini',       block05: 'cancer',
    block06: 'leo',          block07: 'virgo',        block08: 'libra',
    block09: 'scorpio',      block10: 'sagittarius',  block11: 'capricorn',
    block12: 'aquarius',     block13: 'pisces',       block14: 'new-series',
    block88: 'founders',
  };

  function slugForBlock(blockMeta) {
    if (!blockMeta) return '';
    // Static blocks: use the lookup
    if (BLOCK_SLUGS[blockMeta.block_id]) return BLOCK_SLUGS[blockMeta.block_id];
    // DB blocks: derive from folder_slug (e.g. 'scnft_zodiac_gemini' → 'gemini')
    if (blockMeta.folder) {
      const parts = blockMeta.folder.split('_');
      return parts[parts.length - 1] || blockMeta.block_id;
    }
    return blockMeta.block_id;
  }

  /** Update page title, heading, and body data-* attrs when block changes. */
  function updatePageMeta(runtimeCfg, blockMeta, batchNum) {
    if (!blockMeta) return;
    const barNum = getStrAttr(document.body, 'data-bar-num', '01');
    const label = blockMeta.label || '';
    const collTitle = `Bar ${barNum} \u2022 ${label}`;

    // Update <title>
    document.title = `Silver Bar ${barNum} | ${label} | Tokenized Silver Bar CNFTs | Rarefolio.io`;

    // Update heading
    const heading = document.getElementById('qd-collection-heading');
    if (heading) heading.textContent = collTitle;

    // Update body data attributes
    const body = document.body;
    body.dataset.blockId = blockMeta.block_id;
    body.dataset.storyMode = blockMeta.story_mode || 'shared';
    body.dataset.collectionTitle = collTitle;

    // Update URL via pushState (no reload)
    const slug = slugForBlock(blockMeta);
    if (slug) {
      const newPath = `/collection/silverbar-${barNum}/${slug}`;
      const sp = new URLSearchParams();
      sp.set('batch', String(batchNum));
      const newUrl = `${newPath}?${sp.toString()}`;
      if (location.pathname + location.search !== newUrl) {
        history.pushState({ batch: batchNum, block: blockMeta.block_id }, '', newUrl);
      }
    }
  }

  function blockIdForBatch(batchNum) {
    const b = Number(batchNum);
    if (!Number.isFinite(b)) return null;
    // Static map: batch 1..15 → block00..block14 (Bar I only)
    if (b >= 1 && b <= 15) return 'block' + String(b - 1).padStart(2, '0');
    // Founders Block 88 = batch 89 on Bar I (tokens 0000705–0000712)
    if (b === 89) return 'block88';
    return null;
  }

  /** Sync resolution — static map only (Bar I batches 1-15). Used by legacy callers. */
  function resolveBlockId(runtimeCfg, batchNum) {
    if (runtimeCfg.blockId && QD_BLOCKS[runtimeCfg.blockId]) return runtimeCfg.blockId;

    if (Array.isArray(runtimeCfg.blockBatchRules) && batchNum) {
      for (const r of runtimeCfg.blockBatchRules) {
        const from = Number(r.from);
        const to = Number(r.to);
        const block = String(r.block || '');
        if (!QD_BLOCKS[block]) continue;
        if (Number.isFinite(from) && Number.isFinite(to) && batchNum >= from && batchNum <= to) return block;
      }
    }

    const inferred = blockIdForBatch(batchNum);
    if (inferred && QD_BLOCKS[inferred]) return inferred;

    return null;
  }

  /* ---- API-driven block resolution (scales to 5,000+ batches per bar) ---- */
  const _apiBlockCache = new Map(); // key: "barSerial:batchNum" → normalized meta or null

  /**
   * Fetch block metadata from the API. Caches per session.
   * Returns { block_id, folder, label, story_mode, _source:'api' } or null.
   */
  async function _fetchBlockFromApi(barSerial, batchNum) {
    const key = `${barSerial}:${batchNum}`;
    if (_apiBlockCache.has(key)) return _apiBlockCache.get(key);
    try {
      const url = `/api/blocks/resolve.php?bar=${encodeURIComponent(barSerial)}&batch=${encodeURIComponent(String(batchNum))}`;
      const res = await fetch(url, { cache: 'default' });
      if (!res.ok) { _apiBlockCache.set(key, null); return null; }
      const d = await res.json();
      const meta = {
        block_id:        d.block_id,
        folder:          d.folder_slug,
        label:           d.label,
        story_mode:      d.story_mode,
        character_names: Array.isArray(d.character_names) ? d.character_names : null,
        _source:         'api',
      };
      _apiBlockCache.set(key, meta);
      return meta;
    } catch {
      _apiBlockCache.set(key, null);
      return null;
    }
  }

  /**
   * Primary async block resolver. Resolution chain:
   *   1) Page-level override (static)
   *   2) Batch rules (static)
   *   3) Static QD_BLOCKS (Bar I batches 1-15)
   *   4) API /api/blocks/resolve.php (all bars, all batches)
   * Returns normalized meta: { block_id, folder, label, story_mode, _source }
   */
  async function getBlockMeta(runtimeCfg, batchNum) {
    if (!batchNum) return null;

    // Helper: build the DB-format block_id (e.g. 'E101837-block0001') from a
    // 0-indexed static key (e.g. 'block00') and the bar serial.
    const dbBlockId = (staticKey, serial) => {
      if (String(staticKey).toLowerCase() === 'block88') return 'block88';
      const idx = parseInt(String(staticKey).replace('block', ''), 10);
      if (!Number.isFinite(idx)) return String(staticKey);
      return `${serial}-block${String(idx + 1).padStart(4, '0')}`;
    };

    // Enrich static meta with DB-authoritative block_id/character names when available.
    // This preserves static labels/slugs while ensuring story lookups match editor data.
    const enrichStaticMeta = async (meta) => {
      const apiMeta = await _fetchBlockFromApi(runtimeCfg.serial, batchNum);
      if (!apiMeta) return meta;
      return {
        ...meta,
        story_block_id: apiMeta.block_id || meta.story_block_id,
        character_names: Array.isArray(apiMeta.character_names) ? apiMeta.character_names : (meta.character_names || null),
      };
    };

    // 1) Page-level override
    if (runtimeCfg.blockId && QD_BLOCKS[runtimeCfg.blockId]) {
      const s = QD_BLOCKS[runtimeCfg.blockId];
      return enrichStaticMeta({
        block_id: runtimeCfg.blockId,
        story_block_id: dbBlockId(runtimeCfg.blockId, runtimeCfg.serial),
        folder: s.folder,
        label: s.label,
        story_mode: s.story_mode,
        character_names: null,
        _source: 'static',
      });
    }

    // 2) Batch rules
    if (Array.isArray(runtimeCfg.blockBatchRules)) {
      for (const r of runtimeCfg.blockBatchRules) {
        const from = Number(r.from), to = Number(r.to), block = String(r.block || '');
        if (QD_BLOCKS[block] && Number.isFinite(from) && Number.isFinite(to) && batchNum >= from && batchNum <= to) {
          const s = QD_BLOCKS[block];
          return enrichStaticMeta({
            block_id: block,
            story_block_id: dbBlockId(block, runtimeCfg.serial),
            folder: s.folder,
            label: s.label,
            story_mode: s.story_mode,
            character_names: null,
            _source: 'static',
          });
        }
      }
    }

    // 3) Static map (Bar I batches 1-15)
    const staticId = blockIdForBatch(batchNum);
    if (staticId && QD_BLOCKS[staticId]) {
      const s = QD_BLOCKS[staticId];
      return enrichStaticMeta({
        block_id: staticId,
        story_block_id: dbBlockId(staticId, runtimeCfg.serial),
        folder: s.folder,
        label: s.label,
        story_mode: s.story_mode,
        character_names: null,
        _source: 'static',
      });
    }

    // 4) API
    return _fetchBlockFromApi(runtimeCfg.serial, batchNum);
  }

  /** Build the story URL for a resolved block meta. */
  function storyUrlForBlock(meta, itemNum) {
    if (!meta) return '';
    // All stories served via the API.
    // story_block_id holds the DB-format ID (e.g. 'E101837-block0001') for static blocks;
    // API blocks already use the full ID in block_id.
    const blockId = meta.story_block_id || meta.block_id;
    const item = (meta.story_mode === 'per_item' && itemNum >= 1 && itemNum <= 8) ? itemNum : 0;
    return `/api/blocks/story.php?block=${encodeURIComponent(blockId)}&item=${item}`;
  }

  async function loadConfigIfPresent(body) {
    const url = getStrAttr(body, 'data-config', '');
    if (!url) return null;
    try {
      const res = await fetch(url, { cache: 'no-store' });
      if (!res.ok) throw new Error(`Config load failed (${res.status}) for ${url}`);
      return await res.json();
    } catch (e) {
      console.warn('[QD] Config load failed:', e);
      return null;
    }
  }

  function buildRuntimeConfig({ body, cfg }) {
    // Defaults (safe)
    const defaults = {
      title: 'Rarefolio Silver Bar',
      serial: 'E101837',
      set: 1,
      nft: { slugPrefix: 'qd-silver', idDigits: 7, startIndex: 1 },
      batch: { size: 8, count: 5000, labelPrefix: 'Batch' },
      assets: { fallbackImage: '/assets/img/nfts/placeholder.jpg', cardImageTemplate: '' },
    };

    const merged = {
      title: cfg?.title ?? defaults.title,
      serial: cfg?.serial ?? defaults.serial,
      set: cfg?.set ?? defaults.set,
      nft: {
        slugPrefix: cfg?.nft?.slugPrefix ?? defaults.nft.slugPrefix,
        idDigits: cfg?.nft?.idDigits ?? defaults.nft.idDigits,
        startIndex: cfg?.nft?.startIndex ?? defaults.nft.startIndex,
      },
      batch: {
        size: cfg?.batch?.size ?? defaults.batch.size,
        count: cfg?.batch?.count ?? defaults.batch.count,
        labelPrefix: cfg?.batch?.labelPrefix ?? defaults.batch.labelPrefix,
      },
      assets: {
        fallbackImage: cfg?.assets?.fallbackImage ?? defaults.assets.fallbackImage,
        cardImageTemplate: cfg?.assets?.cardImageTemplate ?? defaults.assets.cardImageTemplate,
      },
      links: {
        detailPageTemplate: cfg?.links?.detailPageTemplate ?? 'nft.html?nft={slug}&bar={serial}&set={set}',
      },
      _sourceConfigUrl: getStrAttr(body, 'data-config', ''),
    };

    // Page-level overrides (authoritative)
    merged.title = getStrAttr(body, 'data-collection-title', merged.title);
    merged.serial = getStrAttr(body, 'data-bar-serial', merged.serial);
    merged.set = getIntAttr(body, 'data-set', merged.set);

    merged.nft.startIndex = getIntAttr(body, 'data-start-index', merged.nft.startIndex);
    merged.batch.size = getIntAttr(body, 'data-batch-size', merged.batch.size);
    merged.batch.count = getIntAttr(body, 'data-total-batches', merged.batch.count);

    // Optional: demo cap (prevents 5000-option dropdown, etc.)
    const demoCap = getIntAttr(body, 'data-demo-batches', 0);
    if (demoCap > 0) merged.batch.count = Math.min(merged.batch.count, demoCap);

    // Images
    const imgTpl = getStrAttr(body, 'data-image-template', '');
    if (imgTpl) merged.assets.cardImageTemplate = imgTpl;
    merged.assets.fallbackImage = getStrAttr(body, 'data-fallback-image', merged.assets.fallbackImage);

    // Optional range-based image map (mixed series by token id)
    merged.imageMap = safeJsonParse(getStrAttr(body, 'data-image-map', ''), null);

    // Optional batch-based image selection (legacy; supported)
    merged.imageBatchMap = safeJsonParse(getStrAttr(body, 'data-image-batch-map', ''), null);
    merged.imageBatchRules = safeJsonParse(getStrAttr(body, 'data-image-batch-rules', ''), null);

    // Series batch rules (legacy — kept for backward compat but no longer triggers redirects)
    merged.seriesBatchRules = safeJsonParse(getStrAttr(body, 'data-series-batch-rules', ''), null);

    // NEW: block routing
    merged.blockId = getStrAttr(body, 'data-block-id', '');
    merged.blockBatchRules = safeJsonParse(getStrAttr(body, 'data-block-batch-rules', ''), null);

    return merged;
  }

  function pageForBatch(runtimeCfg, batchNum) {
    const rules = runtimeCfg.seriesBatchRules;
    if (!batchNum || !Array.isArray(rules)) return null;
    for (const r of rules) {
      const from = Number(r.from);
      const to = Number(r.to);
      const page = r.page;
      if (!page) continue;
      if (Number.isFinite(from) && Number.isFinite(to) && batchNum >= from && batchNum <= to) return String(page);
    }
    return null;
  }

  function templateForSlug(runtimeCfg, slug, batchNum) {
    // 1) Batch-based selection (exact map)
    if (batchNum && runtimeCfg.imageBatchMap) {
      const t = runtimeCfg.imageBatchMap[String(batchNum)] || runtimeCfg.imageBatchMap[batchNum];
      if (t) return t;
    }

    // 2) Batch-based selection (range rules)
    if (batchNum && Array.isArray(runtimeCfg.imageBatchRules)) {
      for (const r of runtimeCfg.imageBatchRules) {
        const from = Number(r.from);
        const to = Number(r.to);
        if (Number.isFinite(from) && Number.isFinite(to) && batchNum >= from && batchNum <= to && r.tpl) {
          return r.tpl;
        }
      }
    }

    // 3) Token-index range map (mixed series by token id)
    const idx = parseTokenIndex(slug);
    if (runtimeCfg.imageMap && idx != null) {
      for (const r of runtimeCfg.imageMap) {
        const from = Number(r.from);
        const to = Number(r.to);
        if (Number.isFinite(from) && Number.isFinite(to) && idx >= from && idx <= to && r.tpl) {
          return r.tpl;
        }
      }
    }

    // 4) Block-driven default (Path A): derive from block mapping
    // NOTE: We keep the template here as the "clean" (no numeric prefix) folder path.
    // If your live image folders still have numeric prefixes (01_, 02_, ...),
    // rendering code will auto-fallback to the prefixed folder via onerror.
    if (batchNum) {
      const blockId = resolveBlockId(runtimeCfg, batchNum);
      const meta = blockId ? QD_BLOCKS[blockId] : null;
      if (meta?.folder) return `/assets/img/collection/${meta.folder}/{id}.jpg`;
    }

    // 5) Page-level template
    if (runtimeCfg.assets.cardImageTemplate) return runtimeCfg.assets.cardImageTemplate;

    // 6) None (caller should fall back)
    return '';
  }

  function imageForSlug(runtimeCfg, slug, batchNum) {
    const tpl = templateForSlug(runtimeCfg, slug, batchNum);
    if (tpl) return resolveTemplate(tpl, { id: slug });
    return runtimeCfg.assets.fallbackImage;
  }

  function prefixedFolderForBlock(blockId, baseFolder) {
    // block00 -> 01_*, block01 -> 02_*, ...
    const idx = parseInt(String(blockId || '').replace('block', ''), 10);
    if (!Number.isFinite(idx)) return '';
    const prefix = String(idx + 1).padStart(2, '0') + '_';
    return `${prefix}${baseFolder}`;
  }

  function blockImageCandidates(runtimeCfg, batchNum, slug, blockMetaOverride) {
    // Use pre-resolved meta if provided, otherwise fall back to sync static lookup
    let meta = blockMetaOverride;
    if (!meta) {
      const blockId = resolveBlockId(runtimeCfg, batchNum);
      meta = blockId ? { ...QD_BLOCKS[blockId], block_id: blockId } : null;
    }
    // Also check API cache if still no meta
    if (!meta) {
      const cached = _apiBlockCache.get(`${runtimeCfg.serial}:${batchNum}`);
      if (cached) meta = cached;
    }

    if (!meta?.folder) {
      const src = imageForSlug(runtimeCfg, slug, batchNum);
      return { src, altSrc: '' };
    }

    const clean = `/assets/img/collection/${meta.folder}/${slug}.jpg`;
    const pref = prefixedFolderForBlock(meta.block_id, meta.folder);
    const prefixed = pref ? `/assets/img/collection/${pref}/${slug}.jpg` : '';

    return { src: clean, altSrc: prefixed };
  }

  function getBatchFromUrl(maxBatch) {
    const sp = new URLSearchParams(location.search);
    const raw = sp.get('batch') || sp.get('set') || '1';
    const n = parseInt(raw, 10);
    if (!Number.isFinite(n)) return 1;
    return Math.min(Math.max(n, 1), maxBatch);
  }

  function setUrlBatch(batchNum) {
    const sp = new URLSearchParams(location.search);
    sp.set('batch', String(batchNum));
    sp.delete('set');
    history.replaceState({}, '', `${location.pathname}?${sp.toString()}`);
  }

  function bindTilt(root) {
    try {
      if (window.__QD?.setupTilt) window.__QD.setupTilt(root);
    } catch {
      // no-op
    }
  }

  function renderCollectionGrid(runtimeCfg) {
    const grid = $('#nftGrid');
    const batchNav = $('#batchNav');
    const batchLabel = $('#batchLabel');
    const prevBtn = $('#prevBatch');
    const nextBtn = $('#nextBatch');
    const firstBtn = $('#firstBatch');
    const lastBtn = $('#lastBatch');
    const jumpInput = $('#batchJumpInput');
    const jumpTotal = $('#batchJumpTotal');
    const jumpGo = $('#batchJumpGo');

    if (!grid) return;

    const BATCH_SIZE = runtimeCfg.batch.size;
    const TOTAL_BATCHES = runtimeCfg.batch.count;
    const START_INDEX = runtimeCfg.nft.startIndex;
    const DIGITS = runtimeCfg.nft.idDigits;
    const PREFIX = runtimeCfg.nft.slugPrefix;

    const makeSlug = (n) => `${PREFIX}-${pad(n, DIGITS)}`;
    const colPage = location.pathname.split('/').pop();

    // Update jump input max & label
    if (jumpInput) { jumpInput.max = String(TOTAL_BATCHES); jumpInput.min = '1'; }
    if (jumpTotal) jumpTotal.textContent = `/ ${TOTAL_BATCHES}`;

    const syncSeriesLinks = (batchNum) => {
      const links = document.querySelectorAll('a.qd-series-link[data-qdsync="batch"]');
      if (!links || !links.length) return;
      links.forEach((a) => {
        const raw = a.getAttribute('href') || '';
        if (!raw) return;
        try {
          const url = new URL(raw, location.href);
          url.searchParams.set('batch', String(batchNum));
          const qs = url.searchParams.toString();
          a.setAttribute('href', url.pathname + (qs ? ('?' + qs) : ''));
        } catch {
          // no-op
        }
      });
    };

    const buildViewLink = (slug, batchNum, itemIndex) => {
      const base = resolveTemplate(runtimeCfg.links.detailPageTemplate, {
        slug,
        serial: runtimeCfg.serial,
        set: runtimeCfg.set,
      });

      const sp = new URLSearchParams(base.split('?')[1] || '');
      sp.set('nft', slug);
      sp.set('bar', runtimeCfg.serial);
      sp.set('set', String(runtimeCfg.set));
      sp.set('batch', String(batchNum));

      // Build col param using clean URL if on the PHP template, else use current page
      const barNum = getStrAttr(document.body, 'data-bar-num', '');
      const blockId = resolveBlockId(runtimeCfg, batchNum);
      if (barNum && blockId) {
        const bSlug = BLOCK_SLUGS[blockId] || '';
        if (bSlug) {
          sp.set('col', `/collection/silverbar-${barNum}/${bSlug}`);
          sp.set('barnum', barNum);
        } else {
          sp.set('col', colPage);
        }
      } else {
        sp.set('col', colPage);
      }

      if (blockId) sp.set('block', blockId);
      if (itemIndex) sp.set('item', String(itemIndex));

      const imgTpl = templateForSlug(runtimeCfg, slug, batchNum);
      if (imgTpl) sp.set('img', imgTpl);

      if (runtimeCfg._sourceConfigUrl) sp.set('cfg', runtimeCfg._sourceConfigUrl);

      return `/nft?${sp.toString()}`;
    };

    /* ---- Windowed batch pill navigator ---- */
    const PILL_RADIUS = 8;

    const visibleBatches = (current) => {
      const set = new Set();
      set.add(1);
      set.add(TOTAL_BATCHES);
      for (let i = current - PILL_RADIUS; i <= current + PILL_RADIUS; i++) {
        if (i >= 1 && i <= TOTAL_BATCHES) set.add(i);
      }
      const sorted = Array.from(set).sort((a, b) => a - b);
      // Insert ellipsis markers where gaps exist
      const result = [];
      for (let i = 0; i < sorted.length; i++) {
        if (i > 0 && sorted[i] - sorted[i - 1] > 1) result.push(null); // null = ellipsis
        result.push(sorted[i]);
      }
      return result;
    };

    const buildBatchNav = (currentBatch) => {
      if (!batchNav) return;
      const items = visibleBatches(currentBatch);
      const frag = document.createDocumentFragment();
      items.forEach((val) => {
        if (val === null) {
          const span = document.createElement('span');
          span.className = 'batch-pill ellipsis';
          span.textContent = '\u2026';
          span.setAttribute('aria-hidden', 'true');
          frag.appendChild(span);
        } else {
          const btn = document.createElement('button');
          btn.type = 'button';
          btn.className = 'batch-pill' + (val === currentBatch ? ' active' : '');
          btn.textContent = String(val);
          btn.setAttribute('aria-label', `Batch ${val}`);
          if (val !== currentBatch) {
            btn.addEventListener('click', () => go(val));
          }
          frag.appendChild(btn);
        }
      });
      batchNav.innerHTML = '';
      batchNav.appendChild(frag);
    };

    const renderBatch = async (batchNum) => {
      const blockMeta = await getBlockMeta(runtimeCfg, batchNum);

      const startN = START_INDEX + (batchNum - 1) * BATCH_SIZE;
      const endN = startN + BATCH_SIZE - 1;
      const firstSlug = makeSlug(startN);
      const lastSlug = makeSlug(endN);
      const padW = Math.max(2, String(TOTAL_BATCHES).length);

      if (batchLabel) {
        const blockLabel = blockMeta?.label ? ` \u2022 ${blockMeta.block_id.toUpperCase()} \u2022 ${blockMeta.label}` : '';
        batchLabel.textContent = `Bar Serial: ${runtimeCfg.serial} \u2022 ${runtimeCfg.batch.labelPrefix} ${String(batchNum).padStart(padW, '0')} of ${TOTAL_BATCHES}${blockLabel} \u2022 ${firstSlug} \u2192 ${lastSlug}`;
      }

      // Update pill strip
      buildBatchNav(batchNum);

      // Sync jump input
      if (jumpInput) jumpInput.value = String(batchNum);

      syncSeriesLinks(batchNum);

      const cards = [];
      for (let i = 0; i < BATCH_SIZE; i++) {
        const n = startN + i;
        const slug = makeSlug(n);
        const itemIndex = i + 1;
        const { src: imgSrc, altSrc } = blockImageCandidates(runtimeCfg, batchNum, slug, blockMeta);
        const title = `${runtimeCfg.title} \u2014 ${slug}`;
        const viewLink = buildViewLink(slug, batchNum, itemIndex);

        // Look up per-item character name.
        // Priority: DB-backed character_names from block meta → legacy hardcoded QD_ITEM_NAMES → empty.
        const _storyBlockId = blockMeta?.story_block_id || blockMeta?.block_id || '';
        const _itemName = (blockMeta?.story_mode === 'per_item' && _storyBlockId)
          ? (blockMeta.character_names?.[itemIndex - 1] || QD_ITEM_NAMES[_storyBlockId]?.[itemIndex - 1] || '')
          : '';
        const _cardDesc = _itemName
          ? `<p class="cnft-desc"><strong class="mas_txt_clr">${_itemName}</strong><br><span class="muted small">Cardano \u00b7 Blockchain data pending</span></p>`
          : `<p class="cnft-desc muted small">Cardano \u00b7 ${blockMeta?.label || 'Silver Bar I'} \u00b7 Blockchain data pending</p>`;

        cards.push(`
          <article class="cnft-card tilt">
            <div class="cnft-media">
              <img
                src="${imgSrc}"
                ${altSrc ? `data-alt-src="${altSrc}"` : ''}
                alt="${title}"
                onerror="if(this.dataset.altSrc){const a=this.dataset.altSrc;this.dataset.altSrc='';this.src=a;}else{this.onerror=null;this.src='${runtimeCfg.assets.fallbackImage}';}"
              />
            </div>
            <div class="cnft-body">
              <div class="cnft-meta">
                <span class="badge">Bar Serial \u2022 ${runtimeCfg.serial}</span>
              </div>
              <h3 class="cnft-title">${title}</h3>
              ${_cardDesc}
              <div class="cnft-actions">
                <a class="btn primary" href="${viewLink}">View</a>
                <a class="btn rf-buy-btn" data-cnft-id="${slug}" href="${RF_MARKET_BASE}/buy.php?token=${encodeURIComponent(slug)}" target="_blank" rel="noopener">Purchase</a>
              </div>
            </div>
          </article>
        `);
      }

      grid.innerHTML = cards.join('');
      bindTilt(grid);

      // Non-blocking: live sold/available status per card.
      // fetchTokenStatus returns 'sold'|'available'|null (null = API unavailable).
      // Default state is already 'available' → buy.php; only sold needs updating.
      for (let _i = 0; _i < BATCH_SIZE; _i++) {
        (function (_slug) {
          fetchTokenStatus(_slug).then(function (live) {
            if (live !== 'sold') return;
            var btn = grid.querySelector('[data-cnft-id="' + _slug + '"]');
            if (!btn) return;
            btn.textContent = 'SOLD';
            btn.removeAttribute('href');
            btn.removeAttribute('target');
            btn.removeAttribute('rel');
            btn.classList.add('btn-sold');
            btn.setAttribute('aria-disabled', 'true');
            btn.setAttribute('title', 'This piece has found its keeper.');
          });
        }(makeSlug(startN + _i)));
      }

      if (prevBtn) prevBtn.disabled = batchNum <= 1;
      if (nextBtn) nextBtn.disabled = batchNum >= TOTAL_BATCHES;
      if (firstBtn) firstBtn.disabled = batchNum <= 1;
      if (lastBtn) lastBtn.disabled = batchNum >= TOTAL_BATCHES;

      // ---- Story loading for collection grid ----
      const storyHost = document.getElementById('qd-story');
      if (storyHost) {
        // For per_item blocks, default to item 1; for shared blocks, use 0 (shared)
        const defaultItem = (blockMeta?.story_mode === 'per_item') ? 1 : 0;
        const storySrc = blockMeta ? storyUrlForBlock(blockMeta, defaultItem) : '';
        if (storySrc) {
          document.body.dataset.storySrc = storySrc;
          if (defaultItem > 0) {
            document.body.dataset.storyItem = String(defaultItem);
          } else {
            delete document.body.dataset.storyItem;
          }
          if (window.__QD?.loadStory) window.__QD.loadStory();
        } else {
          delete document.body.dataset.storySrc;
          delete document.body.dataset.storyItem;
          storyHost.innerHTML = '<p class="muted small" style="margin:0;">No story available for this batch.</p>';
        }
      }
    };

    const go = async (batchNum) => {
      const b = Math.min(Math.max(batchNum, 1), TOTAL_BATCHES);

      // Resolve block for the target batch and update page metadata in-place
      const targetMeta = await getBlockMeta(runtimeCfg, b);
      if (targetMeta) {
        updatePageMeta(runtimeCfg, targetMeta, b);
      } else {
        // No block meta — just update the batch in the URL
        setUrlBatch(b);
      }

      await renderBatch(b);
    };

    // Bind controls
    if (prevBtn) prevBtn.addEventListener('click', () => go(getBatchFromUrl(TOTAL_BATCHES) - 1));
    if (nextBtn) nextBtn.addEventListener('click', () => go(getBatchFromUrl(TOTAL_BATCHES) + 1));
    if (firstBtn) firstBtn.addEventListener('click', () => go(1));
    if (lastBtn) lastBtn.addEventListener('click', () => go(TOTAL_BATCHES));

    // Jump-to-batch
    const doJump = () => {
      if (!jumpInput) return;
      const v = parseInt(jumpInput.value, 10);
      if (Number.isFinite(v)) go(v);
    };
    if (jumpGo) jumpGo.addEventListener('click', doJump);
    if (jumpInput) jumpInput.addEventListener('keydown', (e) => { if (e.key === 'Enter') doJump(); });

    go(getBatchFromUrl(TOTAL_BATCHES));
  }

  async function renderNftDetail(runtimeCfg) {
    const titleEl = document.getElementById('qd-nft-title');
    const tokenEl = document.getElementById('qd-token');
    const badgeEl = document.getElementById('qd-badge');
    const subEl = document.getElementById('qd-nft-sub');
    const topBlockEl = document.getElementById('qd-top-block-label');
    const topTitleNameEl = document.getElementById('qd-top-title-name');
    const imgEl = document.getElementById('qd-nft-img');
    const backEl = document.getElementById('qd-back');

    if (!titleEl || !tokenEl || !badgeEl || !subEl || !imgEl) return;

    const sp = new URLSearchParams(location.search);
    const nft = sp.get('nft') || `${runtimeCfg.nft.slugPrefix}-${pad(runtimeCfg.nft.startIndex, runtimeCfg.nft.idDigits)}`;
    const bar = sp.get('bar') || runtimeCfg.serial;
    const set = sp.get('set') || String(runtimeCfg.set);
    const batchFromUrl = sp.get('batch');

    // Infer batch if not provided
    let batch = 1;
    if (batchFromUrl) {
      batch = parseInt(batchFromUrl, 10) || 1;
    } else {
      const idx = parseTokenIndex(nft);
      if (idx != null) {
        const offset = idx - runtimeCfg.nft.startIndex;
        if (offset >= 0) batch = Math.floor(offset / runtimeCfg.batch.size) + 1;
      }
    }
    batch = Math.max(1, batch);

    const item = parseInt(sp.get('item') || '0', 10) || null;

    // Resolve block via full chain (static → API)
    const blockMeta = await getBlockMeta(runtimeCfg, batch);

    // Title: use character name for per_item blocks, block label for shared, slug as final fallback
    const _dStoryBlockId = blockMeta?.story_block_id || blockMeta?.block_id || '';
    // Priority: DB-backed character_names → legacy hardcoded QD_ITEM_NAMES → null.
    const _dItemName = (blockMeta?.story_mode === 'per_item' && item && _dStoryBlockId)
      ? (blockMeta.character_names?.[item - 1] || QD_ITEM_NAMES[_dStoryBlockId]?.[item - 1] || null)
      : null;
    const isFoundersBlock = /founders/i.test(blockMeta?.label || '') || String(blockMeta?.block_id || '').toLowerCase() === 'block88';
    titleEl.textContent = isFoundersBlock ? '' : (_dItemName || blockMeta?.label || nft).toUpperCase();

    if (topBlockEl) {
      topBlockEl.textContent = isFoundersBlock ? 'Founders Block' : (blockMeta?.label || 'Collection');
    }
    if (topTitleNameEl) {
      let topTitleName = _dItemName || nft.toUpperCase();
      if (_dItemName && blockMeta?.story_mode === 'per_item' && item) {
        const hasPrefix = /^(founders\s*#\d+|item\s*\d+)/i.test(_dItemName);
        if (!hasPrefix) {
          topTitleName = isFoundersBlock
            ? `Founder #${item} — ${_dItemName}`
            : `Item ${item} — ${_dItemName}`;
        }
      }
      if (isFoundersBlock) {
        topTitleName = topTitleName.replace(/^Founders\s*#/i, 'Founder #');
        const founderPersonName = FOUNDERS_PERSON_NAMES[nft] || '';
        if (founderPersonName && topTitleName.toLowerCase().indexOf(founderPersonName.toLowerCase()) === -1) {
          topTitleName = `${topTitleName} — ${founderPersonName}`;
        }
      }
      topTitleNameEl.textContent = topTitleName;
    }

    tokenEl.textContent = nft;
    // badgeEl is hidden in HTML; keep setting it for any JS that reads it
    badgeEl.textContent = `Bar Serial \u2022 ${bar}`;

    const blockLine = blockMeta?.label ? ` \u2022 ${blockMeta.block_id.toUpperCase()} \u2022 ${blockMeta.label}` : '';
    subEl.textContent = `Bar ${bar} \u2022 Set ${set} \u2022 ${runtimeCfg.batch.labelPrefix} ${batch}${blockLine}`;

    // Image resolution
    const imgTpl = sp.get('img');
    let imgSrc = '';
    let altSrc = '';
    if (imgTpl) {
      imgSrc = resolveTemplate(imgTpl, { id: nft });
    } else {
      const c = blockImageCandidates(runtimeCfg, batch, nft, blockMeta);
      imgSrc = c.src;
      altSrc = c.altSrc;
    }
    if (altSrc) imgEl.dataset.altSrc = altSrc;
    imgEl.onerror = function () {
      if (this.dataset.altSrc) {
        const a = this.dataset.altSrc;
        this.dataset.altSrc = '';
        this.src = a;
        return;
      }
      this.onerror = null;
      this.src = runtimeCfg.assets.fallbackImage;
    };
    imgEl.src = imgSrc;

    // Story loading
    try {
      const explicitStory = sp.get('story');
      let storySrc = explicitStory || '';

      if (!storySrc && blockMeta) {
        storySrc = storyUrlForBlock(blockMeta, item || 0);
      }

      if (storySrc) {
        document.body.dataset.storySrc = storySrc;
        // Tell the loader which per-item article to extract (0 = shared/full)
        document.body.dataset.storyItem = String(item || 0);
        if (window.__QD?.loadStory) window.__QD.loadStory();
      }
    } catch {
      // no-op
    }

    // Purchase / SOLD button
    const purchaseEl = document.getElementById('qd-purchase');
    if (purchaseEl) {
      // Set initial state from static fallback while API call is in flight
      if (QD_SOLD.has(nft)) {
        setSold(purchaseEl);
      } else {
        setAvailable(purchaseEl, nft);
      }

      // Non-blocking: update from live marketplace API
      fetchTokenStatus(nft).then(liveStatus => {
        if (liveStatus === 'sold') {
          setSold(purchaseEl);
        } else if (liveStatus === 'available') {
          setAvailable(purchaseEl, nft);
        }
        // null = API unavailable, keep the static fallback state
      });
    }

    function setSold(el) {
      el.textContent = 'SOLD';
      el.removeAttribute('href');
      el.classList.remove('primary');
      el.classList.add('btn-sold');
      el.setAttribute('aria-disabled', 'true');
      el.setAttribute('title', 'This piece has found its keeper.');
    }

    function setAvailable(el, cnftId) {
      el.textContent = 'Purchase';
      el.setAttribute('href', `${RF_MARKET_BASE}/buy.php?token=${encodeURIComponent(cnftId)}`);
      el.classList.add('primary');
      el.classList.remove('btn-sold');
      el.removeAttribute('aria-disabled');
      el.setAttribute('title', 'Purchase this piece on RareFolio Marketplace');
      el.setAttribute('target', '_blank');
      el.setAttribute('rel', 'noopener');
    }

    // Certificate links
    const certLinkEl = document.getElementById('qd-cert-link');
    const verifyLinkEl = document.getElementById('qd-verify-link');
    const downloadLinkEl = document.getElementById('qd-download-link');
    const cnftNum = nft.match(/(\d{7})$/);
    if (cnftNum) {
      const certId = `QDCERT-${bar}-${cnftNum[1]}`;
      if (certLinkEl) {
        certLinkEl.href = `/cert?cert=${encodeURIComponent(certId)}`;
        certLinkEl.style.display = '';
      }
      if (verifyLinkEl) {
        verifyLinkEl.href = `/verify?cert=${encodeURIComponent(certId)}`;
        verifyLinkEl.style.display = '';
      }
      if (downloadLinkEl) {
        downloadLinkEl.href = `/download.php?cert=${encodeURIComponent(certId)}`;
        downloadLinkEl.style.display = '';
      }
    }

    // Backlink (supports both new clean URLs and legacy col= param)
    if (backEl) {
      const colParam = sp.get('col') || '';
      if (colParam.startsWith('/collection/')) {
        backEl.href = `${colParam}?batch=${encodeURIComponent(String(batch))}`;
      } else if (blockMeta) {
        const barNum = sp.get('barnum') || '01';
        const bSlug = slugForBlock(blockMeta);
        backEl.href = bSlug
          ? `/collection/silverbar-${barNum}/${bSlug}?batch=${encodeURIComponent(String(batch))}`
          : `/collection-silverbar-01?batch=${encodeURIComponent(String(batch))}`;
      } else {
        const fallback = colParam || 'collection-silverbar-01';
        backEl.href = `${fallback}?batch=${encodeURIComponent(String(batch))}`;
      }
    }
  }

  async function init() {
    const body = document.body;
    const cfg = await loadConfigIfPresent(body);
    const runtimeCfg = buildRuntimeConfig({ body, cfg });

    // Collection pages
    renderCollectionGrid(runtimeCfg);

    // NFT detail page
    await renderNftDetail(runtimeCfg);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init, { once: true });
  } else {
    init();
  }
})();
