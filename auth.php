<?php
header('X-Robots-Tag: noindex, nofollow, noarchive, nosnippet');
header('Cache-Control: no-store, no-cache, must-revalidate, private');
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');

session_set_cookie_params([
    'httponly' => true,
    'samesite' => 'Lax',
    'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
]);
session_start();

if (!file_exists(__DIR__ . '/data/installed.lock')) {
    header('Location: install.php');
    exit;
}

if (empty($_SESSION['uid'])) {
    header('Location: login.php');
    exit;
}

$timeout = 1800;
if (isset($_SESSION['last']) && time() - $_SESSION['last'] > $timeout) {
    session_unset();
    session_destroy();
    header('Location: login.php?timeout=1');
    exit;
}
$_SESSION['last'] = time();

function get_db() {
    $dbFile = __DIR__ . '/data/mails.db';
    $db = new PDO('sqlite:' . $dbFile);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    return $db;
}

function get_users_db() {
    $dbFile = __DIR__ . '/data/users.db';
    $db = new PDO('sqlite:' . $dbFile);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    return $db;
}