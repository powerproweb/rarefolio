<?php
/**
 * Under-development gate login.
 *
 * Credentials are read from api/_config.php (gitignored) as the constants
 * UD_USER and UD_PASS. There is intentionally NO in-source fallback:
 * if _config.php is missing, or UD_USER / UD_PASS are not defined or
 * empty, this endpoint fails closed and logs an operator alert.
 *
 * Password comparison uses hash_equals() for timing-safe equality.
 */
declare(strict_types=1);

$configPath = __DIR__ . '/_config.php';
if (is_file($configPath)) {
    require_once $configPath;
}

if (!defined('UD_SESSION_NAME')) {
    define('UD_SESSION_NAME', 'RF_UD_AUTH');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /under-development.html');
    exit;
}

$udUser = defined('UD_USER') ? (string) UD_USER : '';
$udPass = defined('UD_PASS') ? (string) UD_PASS : '';

if ($udUser === '' || $udPass === '') {
    error_log('[api/login.php] UD_USER or UD_PASS not defined in api/_config.php; login is fail-closed until the server config is restored.');
    header('Location: /under-development.html?error=config');
    exit;
}

$username = isset($_POST['username']) ? trim((string) $_POST['username']) : '';
$password = isset($_POST['password']) ? (string) $_POST['password']      : '';

if (hash_equals($udUser, $username) && hash_equals($udPass, $password)) {
    session_name(UD_SESSION_NAME);
    session_start();
    $_SESSION['rf_authenticated'] = true;
    $_SESSION['rf_user']          = $username;
    $_SESSION['rf_login_time']    = time();

    setcookie('rf_ud_auth', '1', [
        'expires'  => 0,
        'path'     => '/',
        'secure'   => true,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    header('Location: /index.html');
    exit;
}

header('Location: /under-development.html?error=1');
exit;
