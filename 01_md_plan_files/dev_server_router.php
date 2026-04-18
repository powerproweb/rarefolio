<?php
/**
 * Dev router for `php -S` — replicates the main-site .htaccess rewrites so
 * URLs that normally go through Apache mod_rewrite work against the
 * built-in PHP server.
 *
 * Use this file as the router argument to `php -S`:
 *
 *     php -S localhost:8000 -t M:\01_Warp_Projects\01_projects\01_rarefolio.io `
 *         M:\01_Warp_Projects\01_projects\01_rarefolio.io\01_md_plan_files\dev_server_router.php
 *
 * Handles three cases:
 *   1. Collection router:
 *        /collection/silverbar-NN/<slug>       -> _col/block.php?bar=NN&block=<slug>
 *      (query string pass-through: ?batch=89 is preserved)
 *   2. Clean URLs missing .html:
 *        /verify                               -> /verify.html
 *   3. Real on-disk files (CSS, JS, images, PDFs): pass through.
 *
 * Everything else falls back to 404.
 */

$root = dirname(__DIR__);
$uri  = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/';
$qs   = parse_url($_SERVER['REQUEST_URI'], PHP_URL_QUERY) ?? '';

// --- 1. Collection router: /collection/silverbar-NN/<slug>(/?)
if (preg_match('~^/collection/silverbar-(\d{2})/([a-z0-9-]+)/?$~', $uri, $m)) {
    $bar   = $m[1];
    $block = $m[2];
    // Parse incoming query string so block.php sees batch etc.
    parse_str($qs, $qparams);
    $qparams['bar']   = $bar;
    $qparams['block'] = $block;
    $_GET = array_merge($_GET, $qparams);
    // Fix $_SERVER so any code that reads it still sees the correct mapped path.
    $_SERVER['SCRIPT_NAME'] = '/_col/block.php';
    $_SERVER['PHP_SELF']    = '/_col/block.php';
    $_SERVER['QUERY_STRING']= http_build_query($qparams);
    $target = $root . '/_col/block.php';
    if (is_file($target)) {
        require $target;
        return true;
    }
}

// --- 2. Pass through real files on disk
$candidate = $root . $uri;
if ($uri !== '/' && is_file($candidate)) {
    return false;   // let the built-in server serve the file
}

// --- 3. Clean URL -> .html fallback
$htmlCandidate = $root . $uri . '.html';
if (is_file($htmlCandidate)) {
    $_SERVER['SCRIPT_NAME'] = $uri . '.html';
    require $htmlCandidate;
    return true;
}

// --- 4. 404
http_response_code(404);
$four = $root . '/404.html';
if (is_file($four)) {
    require $four;
} else {
    echo 'not found';
}
return true;
