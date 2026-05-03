<?php
declare(strict_types=1);

/**
 * Rarefolio — page-level protection for Silver Shard Calculator
 * URL remains /collection-silverbar-calculator(.html) via .htaccess rewrite.
 */

$validUser = 'silvershard';
$validPass = 'silvershard8';

/**
 * Read HTTP Basic Auth credentials from PHP vars or forwarded auth header.
 *
 * @return array{0:string,1:string}
 */
function rf_get_basic_auth_credentials(): array
{
    if (isset($_SERVER['PHP_AUTH_USER'])) {
        return [
            (string)$_SERVER['PHP_AUTH_USER'],
            (string)($_SERVER['PHP_AUTH_PW'] ?? ''),
        ];
    }

    $authHeader = (string)($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
    if (stripos($authHeader, 'Basic ') === 0) {
        $decoded = base64_decode(substr($authHeader, 6), true);
        if ($decoded !== false) {
            $parts = explode(':', $decoded, 2);
            if (count($parts) === 2) {
                return [(string)$parts[0], (string)$parts[1]];
            }
        }
    }

    return ['', ''];
}

[$user, $pass] = rf_get_basic_auth_credentials();
$isAuthorized = hash_equals($validUser, $user) && hash_equals($validPass, $pass);

if (!$isAuthorized) {
    header('WWW-Authenticate: Basic realm="Rarefolio Silver Shard Calculator"');
    header('HTTP/1.1 401 Unauthorized');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    echo 'Authentication required.';
    exit;
}

$calculatorPath = __DIR__ . '/collection-silverbar-calculator.html';
if (!is_file($calculatorPath)) {
    http_response_code(500);
    echo 'Calculator page not found.';
    exit;
}

header('Content-Type: text/html; charset=utf-8');
readfile($calculatorPath);
exit;
