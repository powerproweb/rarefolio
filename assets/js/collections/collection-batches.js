/* collection-batches.js
   Vanilla JS batch renderer for collection pages that contain:
   - #nftGrid
   - #batchSelect
   - #batchLabel
   - #prevBatch
   - #nextBatch

   Data source:
   - /assets/data/collections/qd-silverbar-01.json
*/

(() => {
  const grid = document.getElementById('nftGrid');
  const batchSelect = document.getElementById('batchSelect');
  const batchLabel = document.getElementById('batchLabel');
  const prevBtn = document.getElementById('prevBatch');
  const nextBtn = document.getElementById('nextBatch');

  // If the page doesn't have the batch UI, do nothing.
  if (!grid || !batchSelect || !batchLabel || !prevBtn || !nextBtn) return;

  // Per-page config file (later: upgrade to data-config on <body>).
  const CONFIG_URL = '/assets/data/collections/qd-silverbar-01.json';

  /** @type {any} */
  let cfg = null;
  let currentBatch = 1;

  function padNumber(n, digits) {
    return String(n).padStart(digits, '0');
  }

  function buildNftSlug(prefix, index, digits) {
    return `${prefix}-${padNumber(index, digits)}`;
  }

  function resolveTemplate(str, map) {
    return str.replace(/\{(\w+)\}/g, (_, k) => (map[k] ?? ''));
  }

  function getBatchRange(batchNum) {
    const size = cfg.batch.size;
    const startIndex = cfg.nft.startIndex + (batchNum - 1) * size;
    const endIndex = startIndex + size - 1;
    return { startIndex, endIndex };
  }

  function setDisabled(btn, disabled) {
    if (!btn) return;
    btn.disabled = !!disabled;
    btn.setAttribute('aria-disabled', disabled ? 'true' : 'false');
  }

  function renderBatch(batchNum) {
    const { startIndex, endIndex } = getBatchRange(batchNum);

    batchLabel.textContent = `${cfg.batch.labelPrefix} ${batchNum} • IDs ${startIndex}–${endIndex}`;

    // Clear grid
    grid.innerHTML = '';

    for (let i = startIndex; i <= endIndex; i++) {
      const slug = buildNftSlug(cfg.nft.slugPrefix, i, cfg.nft.idDigits);

      const imgSrc = resolveTemplate(cfg.assets.cardImageTemplate, { id: slug });
      const detailUrl = resolveTemplate(cfg.links.detailPageTemplate, {
        slug,
        serial: cfg.serial,
        set: cfg.set
      });

      const cardHtml = `
        <article class="cnft-card tilt">
          <div class="cnft-media">
            <img src="${imgSrc}" alt="${slug}" onerror="this.onerror=null;this.src='${cfg.assets.fallbackImage}';" />
          </div>
          <div class="cnft-body">
            <div class="cnft-meta">
              <span class="badge">Bar Serial • ${cfg.serial}</span>
              <span class="token">Token • ${slug}</span>
            </div>
            <h3 class="cnft-title">${slug.toUpperCase()}</h3>
            <p class="cnft-desc">Mapped unit of Bar ${cfg.serial}. Deterministic batch render.</p>
            <div class="cnft-actions">
              <a class="btn primary" href="${detailUrl}">View</a>
            </div>
          </div>
        </article>
      `;

      grid.insertAdjacentHTML('beforeend', cardHtml);
    }

    // Update controls
    batchSelect.value = String(batchNum);
    setDisabled(prevBtn, batchNum <= 1);
    setDisabled(nextBtn, batchNum >= cfg.batch.count);

    currentBatch = batchNum;
  }

  function initBatchUI() {
    // Populate dropdown
    batchSelect.innerHTML = '';
    for (let b = 1; b <= cfg.batch.count; b++) {
      const opt = document.createElement('option');
      opt.value = String(b);
      opt.textContent = `${cfg.batch.labelPrefix} ${b}`;
      batchSelect.appendChild(opt);
    }

    batchSelect.addEventListener('change', () => {
      const v = parseInt(batchSelect.value, 10);
      if (Number.isFinite(v)) renderBatch(v);
    });

    prevBtn.addEventListener('click', () => {
      if (currentBatch > 1) renderBatch(currentBatch - 1);
    });

    nextBtn.addEventListener('click', () => {
      if (currentBatch < cfg.batch.count) renderBatch(currentBatch + 1);
    });
  }

  async function loadConfig() {
    const res = await fetch(CONFIG_URL, { cache: 'no-store' });
    if (!res.ok) throw new Error(`Config load failed (${res.status}) for ${CONFIG_URL}`);
    return await res.json();
  }

  function getInitialBatchFromUrl(maxBatch) {
    const params = new URLSearchParams(window.location.search);
    const b = parseInt(params.get('batch') || '1', 10);
    if (!Number.isFinite(b)) return 1;
    return Math.min(Math.max(b, 1), maxBatch);
  }

  (async () => {
    try {
      cfg = await loadConfig();
      initBatchUI();
      renderBatch(getInitialBatchFromUrl(cfg.batch.count));
    } catch (err) {
      console.error(err);
      batchLabel.textContent = 'Batch system failed to load. Check console.';
      setDisabled(prevBtn, true);
      setDisabled(nextBtn, true);
    }
  })();
})();
