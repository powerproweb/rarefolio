/* ============================================================
   Rarefolio.io CNFT Collections Website (Static Template JS)
   - Mobile menu toggle
   - Multi-dropdown nav (hover on desktop, tap-to-open on mobile)
   - Back-to-top visibility
   - Active nav highlighting
   - Subtle card tilt
   - Silver Bar I demo slice:
       Bar Serial: E101837 (constant across the bar)
       40 batches x 8 CNFTs = 320 CNFTs total
       NFT IDs: qd-silver-0000001 ... qd-silver-0000320
   ============================================================ */

(function () {
  const $ = (sel, root = document) => root.querySelector(sel);
  const $$ = (sel, root = document) => Array.from(root.querySelectorAll(sel));

  // ------------------------------------------------------------
  // Sitewide NFT image watermarking (CSS overlay on wrappers)
  // - Watermarks only the images displayed in the DOM (not downloads)
  // - Designed to work with static pages + dynamically injected grids
  // ------------------------------------------------------------
  const WM_BRAND = "Rarefolio.io";

  const getWatermarkContext = () => {
    const params = new URLSearchParams(location.search);
    const bar = params.get("bar") || document.body?.dataset?.barSerial || "";
    const set = params.get("set") || document.body?.dataset?.set || "";
    return { bar, set };
  };

  const isLikelyNFTImage = (img) => {
    if (!img) return false;
    const src = (img.getAttribute("src") || "").toLowerCase();
    const alt = (img.getAttribute("alt") || "").toLowerCase();

    // Common patterns in this site:
    // - /assets/img/nfts/* (including placeholders)
    // - scnft_* series images
    // - token id in alt
    if (src.includes("/assets/img/nfts/")) return true;
    if (src.includes("scnft_")) return true;
    if (alt.startsWith("qd-silver-")) return true;
    return false;
  };

  const extractTokenFromContext = (img) => {
    const alt = (img.getAttribute("alt") || "").trim();
    if (/^qd-silver-\d{7}$/i.test(alt)) return alt;

    const src = (img.getAttribute("src") || "");
    const m = src.match(/(qd-silver-\d{7})/i);
    if (m && m[1]) return m[1];

    const card = img.closest(".cnft-card");
    const tokenEl = card ? card.querySelector(".token") : null;
    const tokenText = tokenEl ? (tokenEl.textContent || "").trim() : "";
    if (/^qd-silver-\d{7}$/i.test(tokenText)) return tokenText;

    return "";
  };

  const buildWatermarkText = ({ token, bar, set }) => {
    const parts = [WM_BRAND];
    if (token) parts.push(token);
    if (bar) parts.push(bar);
    if (set) parts.push(`SET ${set}`);
    return parts.join(" • ");
  };

  const applyNFTWatermarks = (root = document) => {
    const ctx = getWatermarkContext();
    const imgs = $$('img', root).filter(isLikelyNFTImage).filter(img => !img.closest('#qd-story'));
    if (!imgs.length) return;

    imgs.forEach((img) => {
      // Prefer the standard wrapper, otherwise use the immediate parent.
      const wrap = img.closest(".cnft-media") || img.parentElement;
      if (!wrap || wrap === document.body || wrap === document.documentElement) return;

      wrap.classList.add("wm-wrap");

      // Don't overwrite a page-specific watermark unless it is blank.
      if (!wrap.dataset.watermark || !String(wrap.dataset.watermark).trim()) {
        const token = extractTokenFromContext(img);
        wrap.dataset.watermark = buildWatermarkText({ token, ...ctx });
      }
    });
  };
  // ------------------------------------------------------------
  // Reusable collection video embeds
  // - Expects: .qd-video-embed[data-video-url]
  // - Supports: YouTube URLs and X/Twitter status URLs
  // ------------------------------------------------------------
  let qdTwitterWidgetsPromise = null;

  const parseUrlSafe = (raw) => {
    try {
      return new URL(String(raw || "").trim());
    } catch {
      return null;
    }
  };
  const hostIs = (host, domain) => host === domain || host.endsWith(`.${domain}`);

  const inferVideoPlatform = (rawPlatform, rawUrl) => {
    const explicit = String(rawPlatform || "").trim().toLowerCase();
    if (explicit === "youtube" || explicit === "x") return explicit;

    const u = parseUrlSafe(rawUrl);
    if (!u) return "";
    const host = u.hostname.replace(/^www\./i, "").toLowerCase();
    if (host === "youtu.be" || hostIs(host, "youtube.com") || hostIs(host, "youtube-nocookie.com")) return "youtube";
    if (hostIs(host, "x.com") || hostIs(host, "twitter.com")) return "x";
    return "";
  };

  const extractYoutubeId = (rawUrl) => {
    const u = parseUrlSafe(rawUrl);
    if (!u) return "";
    const host = u.hostname.replace(/^www\./i, "").toLowerCase();
    const pathParts = u.pathname.split("/").filter(Boolean);

    if (host === "youtu.be") return pathParts[0] || "";

    if (hostIs(host, "youtube.com") || hostIs(host, "youtube-nocookie.com")) {
      if (u.pathname === "/watch") return u.searchParams.get("v") || "";
      if (pathParts[0] === "embed" && pathParts[1]) return pathParts[1];
      if (pathParts[0] === "shorts" && pathParts[1]) return pathParts[1];
      if (pathParts[0] === "live" && pathParts[1]) return pathParts[1];
    }

    return "";
  };

  const youtubeEmbedUrlFor = (rawUrl) => {
    const id = extractYoutubeId(rawUrl);
    if (!id) return "";
    return `https://www.youtube-nocookie.com/embed/${encodeURIComponent(id)}?rel=0&modestbranding=1`;
  };

  const xPostUrlFor = (rawUrl) => {
    const u = parseUrlSafe(rawUrl);
    if (!u) return "";
    const host = u.hostname.replace(/^www\./i, "").toLowerCase();
    if (!(hostIs(host, "x.com") || hostIs(host, "twitter.com"))) return "";

    const parts = u.pathname.split("/").filter(Boolean);
    if (parts.length >= 3 && parts[1].toLowerCase() === "status") {
      const handle = parts[0];
      const statusId = parts[2];
      if (handle && statusId) return `https://x.com/${encodeURIComponent(handle)}/status/${encodeURIComponent(statusId)}`;
    }
    if (parts.length >= 4 && parts[0].toLowerCase() === "i" && parts[1].toLowerCase() === "web" && parts[2].toLowerCase() === "status") {
      const statusId = parts[3];
      if (statusId) return `https://x.com/i/web/status/${encodeURIComponent(statusId)}`;
    }
    return "";
  };

  const ensureTwitterWidgets = () => {
    if (window.twttr && window.twttr.widgets) return Promise.resolve(window.twttr);
    if (qdTwitterWidgetsPromise) return qdTwitterWidgetsPromise;

    const waitForWidgets = (resolve, reject, attemptsLeft) => {
      if (window.twttr && window.twttr.widgets) {
        resolve(window.twttr);
        return;
      }
      if (attemptsLeft <= 0) {
        reject(new Error("X widgets not available"));
        return;
      }
      setTimeout(() => waitForWidgets(resolve, reject, attemptsLeft - 1), 50);
    };

    qdTwitterWidgetsPromise = new Promise((resolve, reject) => {
      const existing = document.querySelector('script[src="https://platform.twitter.com/widgets.js"]');
      if (existing) {
        waitForWidgets(resolve, reject, 120);
        return;
      }

      const script = document.createElement("script");
      script.src = "https://platform.twitter.com/widgets.js";
      script.async = true;
      script.defer = true;
      script.onload = () => waitForWidgets(resolve, reject, 120);
      script.onerror = () => reject(new Error("Failed to load X widgets script"));
      document.head.appendChild(script);
    }).catch((err) => {
      qdTwitterWidgetsPromise = null;
      throw err;
    });

    return qdTwitterWidgetsPromise;
  };

  const renderVideoEmbed = (mount) => {
    if (!mount || mount.dataset.embedReady === "true") return;

    const rawUrl = String(mount.dataset.videoUrl || "").trim();
    if (!rawUrl) return;

    const platform = inferVideoPlatform(mount.dataset.videoPlatform || "", rawUrl);
    if (!platform) return;

    const title = String(mount.dataset.videoTitle || "Collection video").trim() || "Collection video";

    if (platform === "youtube") {
      const embedUrl = youtubeEmbedUrlFor(rawUrl);
      if (!embedUrl) return;

      mount.classList.add("is-youtube");
      mount.classList.remove("is-x");
      mount.innerHTML = "";

      const iframe = document.createElement("iframe");
      iframe.src = embedUrl;
      iframe.title = title;
      iframe.loading = "lazy";
      iframe.referrerPolicy = "strict-origin-when-cross-origin";
      iframe.allow = "accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share";
      iframe.setAttribute("allowfullscreen", "");
      mount.appendChild(iframe);
      mount.dataset.embedReady = "true";
      return;
    }

    if (platform === "x") {
      const postUrl = xPostUrlFor(rawUrl);
      if (!postUrl) return;

      mount.classList.remove("is-youtube");
      mount.classList.add("is-x");
      mount.innerHTML = "";

      const blockquote = document.createElement("blockquote");
      blockquote.className = "twitter-tweet";
      blockquote.setAttribute("data-theme", "dark");
      const postLink = document.createElement("a");
      postLink.href = postUrl;
      postLink.textContent = "View this post on X";
      blockquote.appendChild(postLink);
      mount.appendChild(blockquote);
      mount.dataset.embedReady = "true";

      ensureTwitterWidgets()
        .then(() => {
          if (window.twttr && window.twttr.widgets && typeof window.twttr.widgets.load === "function") {
            window.twttr.widgets.load(mount);
          }
        })
        .catch(() => {
          const fallback = document.createElement("p");
          fallback.className = "muted small qd-video-embed-fallback";
          fallback.style.margin = "10px 0 0";
          const fallbackLink = document.createElement("a");
          fallbackLink.href = postUrl;
          fallbackLink.target = "_blank";
          fallbackLink.rel = "noopener";
          fallbackLink.textContent = "Open this post on X";
          fallback.appendChild(fallbackLink);
          mount.appendChild(fallback);
        });
    }
  };

  const initVideoEmbeds = (root = document) => {
    $$(".qd-video-embed", root).forEach(renderVideoEmbed);
  };

  const nav = $(".nav");
  const toggle = $(".menu-toggle");

  const dropdowns = $$(".dropdown");

  // ------------------------------------------------------------
  // QD Mobile Menu Fix (integrated)
  // - Prevents "stuck open" nav on mobile
  // - Closes nav on link click, outside click, and Escape
  // - Keeps existing dropdown behavior intact
  // ------------------------------------------------------------
  const setNavOpen = (open) => {
    if (!toggle || !nav) return;
    nav.classList.toggle("open", !!open);
    toggle.setAttribute("aria-expanded", String(!!open));
    if (!open) closeAllDropdowns();
  };

  const isNavOpen = () => {
    if (!toggle || !nav) return false;
    return nav.classList.contains("open") || toggle.getAttribute("aria-expanded") === "true";
  };

  const isTouchUI = () => {
    try {
      return window.matchMedia("(hover: none), (pointer: coarse)").matches || window.innerWidth <= 900;
    } catch {
      return window.innerWidth <= 900;
    }
  };

  // Performance: tilt is visually nice, but if implemented naively it can slam GPU/CPU.
  // This implementation:
  // - disables on touch/coarse pointers and when users request reduced motion
  // - throttles updates to 1 per animation frame
  // - avoids calling getBoundingClientRect() on every mouse event
  const prefersReducedMotion = (() => {
    try {
      return window.matchMedia("(prefers-reduced-motion: reduce)").matches;
    } catch {
      return false;
    }
  })();

  const setupTilt = (root = document) => {
    if (prefersReducedMotion || isTouchUI()) return;

    $$(".tilt", root).forEach((card) => {
      // Guard: do not bind twice
      if (card.__qdTiltBound) return;
      card.__qdTiltBound = true;

      let rafId = 0;
      let lastX = 0;
      let lastY = 0;
      let rect = null;
      let rectTS = 0;

      const readRect = () => {
        const now = performance.now();
        // Refresh at most ~4 times/sec to keep things responsive during scroll/resize.
        if (!rect || (now - rectTS) > 250) {
          rect = card.getBoundingClientRect();
          rectTS = now;
        }
        return rect;
      };

      const onMove = (e) => {
        lastX = e.clientX;
        lastY = e.clientY;

        if (rafId) return;
        rafId = requestAnimationFrame(() => {
          rafId = 0;
          const r = readRect();
          const x = (lastX - r.left) / r.width - 0.5;
          const y = (lastY - r.top) / r.height - 0.5;
          const rotY = (x * 8).toFixed(2);
          const rotX = (-y * 6).toFixed(2);
          card.style.transform = `perspective(900px) rotateX(${rotX}deg) rotateY(${rotY}deg) translateZ(0)`;
        });
      };

      const onLeave = () => {
        if (rafId) cancelAnimationFrame(rafId);
        rafId = 0;
        rect = null;
        card.style.transform = "";
      };

      card.addEventListener("mousemove", onMove, { passive: true });
      card.addEventListener("mouseleave", onLeave, { passive: true });
    });
  };

  // Expose a small, safe surface for other scripts (e.g., data-driven renderers)
  // to re-bind tilt after they inject cards.
  window.__QD = window.__QD || {};
  window.__QD.setupTilt = setupTilt;

  const closeAllDropdowns = () => {
    dropdowns.forEach((dd) => {
      dd.classList.remove("open");
      const btn = $(".dropbtn", dd);
      if (btn) btn.setAttribute("aria-expanded", "false");
    });
  };

  // Mobile menu
  if (toggle && nav) {
    toggle.addEventListener("click", () => {
      setNavOpen(!isNavOpen());
    });
  }
// Dropdowns: hover handled via CSS; tap-to-open handled via JS
  dropdowns.forEach((dd) => {
    const btn = $(".dropbtn", dd);
    if (!btn) return;

    btn.addEventListener("click", (e) => {
      // Desktop: allow normal navigation.
      if (!isTouchUI()) return;

      // Mobile/touch: first tap opens menu; second tap can navigate via menu item.
      e.preventDefault();

      const willOpen = !dd.classList.contains("open");
      closeAllDropdowns();
      dd.classList.toggle("open", willOpen);
      btn.setAttribute("aria-expanded", String(willOpen));
    });

    // Keyboard affordances
    btn.addEventListener("keydown", (e) => {
      if (e.key === "Escape") {
        closeAllDropdowns();
        btn.blur();
      }
      if (e.key === "ArrowDown") {
        const first = dd.querySelector(".dropmenu a");
        if (first) {
          e.preventDefault();
          dd.classList.add("open");
          btn.setAttribute("aria-expanded", "true");
          first.focus();
        }
      }
    });

    dd.addEventListener("keydown", (e) => {
      if (e.key === "Escape") {
        closeAllDropdowns();
        btn.focus();
      }
    });
  });
  // Close nav on real link taps (mobile) to prevent sticky open menus
  if (nav && toggle) {
    nav.addEventListener("click", (e) => {
      if (!isTouchUI()) return;
      const a = e.target.closest("a");
      if (!a) return;
      // Keep dropdown toggles working (first tap opens submenu)
      if (a.classList.contains("dropbtn")) return;
      setNavOpen(false);
    });
  }

  // Close dropdowns when clicking outside
  document.addEventListener("click", (e) => {
    const hit = dropdowns.some((dd) => dd.contains(e.target));
    if (!hit) closeAllDropdowns();

    // Mobile: also close the whole nav if you tap outside the header/nav
    if (isTouchUI() && isNavOpen()) {
      const inTopbar = e.target.closest(".topbar-inner");
      if (!inTopbar) setNavOpen(false);
    }
  });
// If viewport changes to desktop, clear any mobile-open dropdown state
  window.addEventListener("resize", () => {
    if (!isTouchUI()) closeAllDropdowns();
  });

  // Active nav highlighting
  const currentFile = (location.pathname.split("/").pop() || "index.html").toLowerCase();

  // Mark any matching links active (including dropmenu links). Also mark the parent dropbtn.
  $$(".nav a").forEach((a) => {
    const href = a.getAttribute("href") || "";
    if (!href || href.startsWith("#") || href === "#") return;

    const targetFile = href.split("#")[0].split("?")[0].split("/").pop().toLowerCase();
    if (targetFile === currentFile) {
      a.classList.add("active");
      const parentDd = a.closest(".dropdown");
      if (parentDd) {
        const parentBtn = parentDd.querySelector(".dropbtn");
        if (parentBtn) parentBtn.classList.add("active");
      }
    }
  });

  // Back-to-top button show/hide
  const btt = $(".backtotop");
  const scrollToTopSlow = () => {
    const startY = window.scrollY || window.pageYOffset || 0;
    if (startY <= 0) {
      try { history.replaceState(null, "", window.location.pathname + window.location.search); } catch {}
      return;
    }

    const duration = 1200;
    const start = performance.now();
    const easeInOutCubic = (t) => (t < 0.5)
      ? 4 * t * t * t
      : 1 - Math.pow(-2 * t + 2, 3) / 2;

    const step = (now) => {
      const progress = Math.min((now - start) / duration, 1);
      const eased = easeInOutCubic(progress);
      const nextY = Math.round(startY * (1 - eased));
      window.scrollTo(0, nextY);
      if (progress < 1) {
        requestAnimationFrame(step);
        return;
      }
      try { history.replaceState(null, "", window.location.pathname + window.location.search); } catch {}
    };

    requestAnimationFrame(step);
  };
  window.__QD = window.__QD || {};
  window.__QD.scrollToTopSlow = scrollToTopSlow;
  const onScroll = () => {
    if (!btt) return;
    if (window.scrollY > 420) btt.classList.add("show");
    else btt.classList.remove("show");
  };
  window.addEventListener("scroll", onScroll, { passive: true });
  onScroll();
  const bttLink = btt ? btt.querySelector('a[href="#top"]') : null;
  if (bttLink) {
    bttLink.addEventListener("click", (e) => {
      e.preventDefault();
      scrollToTopSlow();
    });
  }

  // Watermark all NFT images currently in the DOM, and keep up with any
  // CNFT grid re-renders (qd-wire.js injects cards dynamically).
  applyNFTWatermarks(document);

  // Observe DOM changes so newly injected images get watermarked automatically.
  // Debounced to avoid layout thrash.
  (function observeWatermarks(){
    if (!document.body || typeof MutationObserver === "undefined") return;

    let pending = false;
    const schedule = () => {
      if (pending) return;
      pending = true;
      requestAnimationFrame(() => {
        pending = false;
        applyNFTWatermarks(document);
      });
    };

    const mo = new MutationObserver((mutations) => {
      // Fast exit: only react when nodes are added.
      for (const m of mutations) {
        if (m.addedNodes && m.addedNodes.length) {
          schedule();
          break;
        }
      }
    });

    mo.observe(document.body, { childList: true, subtree: true });
  })();

  // ------------------------------------------------------------
  // Story loader (collection pages + NFT detail)
  // - Expects: <div id="qd-story"></div>
  // - Source: <body data-story-src="/assets/stories/<file>.html">
  // - Per-item: <body data-story-item="3"> → extracts article[data-item="3"]
  //   from a single items.html that contains all 8 per-item articles.
  //   If data-story-item is 0 or absent, injects the full response.
  // ------------------------------------------------------------
  const normalizeNoEmDashHtml = (rawHtml) => {
    const emDash = String.fromCharCode(8212);
    const enDash = String.fromCharCode(8211);
    const doc = new DOMParser().parseFromString(String(rawHtml || ""), "text/html");
    const root = doc.body;
    if (!root) return String(rawHtml || "");
    const showText = (window.NodeFilter && window.NodeFilter.SHOW_TEXT) ? window.NodeFilter.SHOW_TEXT : 4;
    const walker = doc.createTreeWalker(root, showText);
    let node = walker.nextNode();
    while (node) {
      node.nodeValue = String(node.nodeValue || "")
        .split(emDash).join(", ")
        .split(enDash).join(", ")
        .replace(/\s{2,}/g, " ");
      node = walker.nextNode();
    }
    return root.innerHTML;
  };
  const loadStory = () => {
    const host = document.getElementById("qd-story");
    if (!host) return;

    const src = document.body?.dataset?.storySrc || host.dataset.storySrc || "";
    if (!src) return;

    const itemNum = parseInt(document.body?.dataset?.storyItem || "0", 10);

    // Provide a graceful placeholder while loading.
    if (!host.innerHTML.trim()) {
      host.innerHTML = '<p class="muted small" style="margin:0;">Loading story…</p>';
    }

    fetch(src, { cache: "default" })
      .then((r) => {
        if (!r.ok) throw new Error(String(r.status));
        return r.text();
      })
      .then((html) => {
        const normalizedHtml = normalizeNoEmDashHtml(html);
        // Per-item extraction: parse and find article[data-item="N"]
        if (itemNum >= 1 && itemNum <= 8) {
          try {
            const doc = new DOMParser().parseFromString(normalizedHtml, "text/html");
            const article = doc.querySelector('article[data-item="' + itemNum + '"]');
            if (article) {
              host.innerHTML = article.outerHTML;
              return;
            }
          } catch { /* fall through to full content */ }
        }
        host.innerHTML = normalizedHtml;
      })
      .catch(() => {
        host.innerHTML = '<p class="muted small" style="margin:0;">Story unavailable.</p>';
      });
  };
  // Expose for pages that set story src after initial load (e.g., nft.html detail view)
  window.__QD = window.__QD || {};
  window.__QD.loadStory = loadStory;

  // Load story content (if present) before binding effects.
  loadStory();

  // Render any configured collection video embeds (YouTube or X).
  initVideoEmbeds(document);

  // Subtle tilt for cards (optimized + guarded)
  setupTilt(document);

  // NOTE: Collection rendering is handled by /assets/js/qd-wire.js (data-driven renderer)
  // to keep layout scripts separated from data/templating logic.
})();
