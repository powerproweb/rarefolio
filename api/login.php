<?php
define('UD_USER', 'adminprimejj');
define('UD_PASS', 'VYAaRM4NycMzjXBL');
define('UD_SESSION_NAME', 'RF_UD_AUTH');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /under-development.html');
    exit;
}

$username = isset($_POST['username']) ? trim($_POST['username']) : '';
$password = isset($_POST['password']) ? $_POST['password'] : '';

if ($username === UD_USER && $password === UD_PASS) {
    session_name(UD_SESSION_NAME);
    session_start();
    $_SESSION['rf_authenticated'] = true;
    $_SESSION['rf_user']          = $username;
    $_SESSION['rf_login_time']    = time();

    setcookie('rf_ud_auth', '1', [
        'expires'  => 0,
        'path'     => '/',
        'secure'   => true,
        'httponly'  => true,
        'samesite' => 'Lax',
    ]);

    header('Location: /index.html');
    exit;
}

header('Location: /under-development.html?error=1');
exit;