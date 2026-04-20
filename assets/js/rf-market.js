/**
 * rf-market.js — Rarefolio main-site client for the marketplace API.
 *
 * Single point of contact for all fetch() calls to market.rarefolio.io.
 * Centralizes: base URL, timeouts, error handling, and light in-memory
 * caching for the current page load.
 *
 * Configure the base URL by adding this to any page BEFORE loading this file:
 *
 *   <script>window.RF_MARKET_BASE = 'https://market.rarefolio.io';</script>
 *
 * Defaults to same-origin "/market-api" which is a useful local-dev fallback
 * when you reverse-proxy the marketplace under the main domain during testing.
 *
 * Public API (window.RFMarket):
 *   getToken(cnftId)              -> Promise<object>
 *   getBar(barSerial)             -> Promise<object>
 *   getListings({bar,limit,offset}) -> Promise<object>
 *   health()                      -> Promise<object>
 *   clearCache()                  -> void
 */
(function () {
  'use strict';

  var BASE = (typeof window.RF_MARKET_BASE === 'string' && window.RF_MARKET_BASE) || '/market-api';
  var PATH = '/api/v1';
  var DEFAULT_TIMEOUT_MS = 7000;

  // Per-page-load micro-cache keyed by full URL. Keeps the same page from
  // hammering the API when multiple UI widgets ask for the same data.
  var _cache = new Map();

  function _url(pathAndQuery) {
    var base = BASE.replace(/\/+$/, '');
    return base + PATH + pathAndQuery;
  }

  function _timeoutSignal(ms) {
    if (typeof AbortController !== 'function') return undefined;
    var c = new AbortController();
    setTimeout(function () { c.abort(); }, ms);
    return c.signal;
  }

  function _get(pathAndQuery, opts) {
    opts = opts || {};
    var full = _url(pathAndQuery);
    var useCache = opts.cache !== false;

    if (useCache && _cache.has(full)) {
      return _cache.get(full);
    }

    var promise = fetch(full, {
      method: 'GET',
      mode: 'cors',
      credentials: 'omit',
      headers: { 'Accept': 'application/json' },
      signal: _timeoutSignal(opts.timeoutMs || DEFAULT_TIMEOUT_MS)
    }).then(function (res) {
      return res.json().then(function (body) {
        if (!res.ok || !body || body.ok === false) {
          var err = new Error(
            (body && body.error && body.error.message) || ('HTTP ' + res.status)
          );
          err.status = res.status;
          err.code = body && body.error && body.error.code;
          throw err;
        }
        return body.data;
      });
    });

    if (useCache) _cache.set(full, promise);
    // Uncache failures so retries can happen
    promise.catch(function () { _cache.delete(full); });
    return promise;
  }

  function _enc(v) { return encodeURIComponent(String(v)); }

  var RFMarket = {
    /** Set/override the base URL after initial load */
    setBase: function (base) { BASE = String(base || '').replace(/\/+$/, ''); _cache.clear(); },

    clearCache: function () { _cache.clear(); },

    health: function (opts) {
      return _get('/health', opts);
    },

    getToken: function (cnftId, opts) {
      if (!cnftId) return Promise.reject(new Error('cnftId required'));
      return _get('/tokens/' + _enc(cnftId), opts);
    },

    getBar: function (barSerial, opts) {
      if (!barSerial) return Promise.reject(new Error('barSerial required'));
      return _get('/bars/' + _enc(barSerial), opts);
    },

    getListings: function (params, opts) {
      params = params || {};
      var q = [];
      if (params.bar)             q.push('bar='    + _enc(params.bar));
      if (params.limit != null)   q.push('limit='  + _enc(params.limit));
      if (params.offset != null)  q.push('offset=' + _enc(params.offset));
      var qs = q.length ? ('?' + q.join('&')) : '';
      return _get('/listings' + qs, opts);
    }
  };

  window.RFMarket = RFMarket;
})();
