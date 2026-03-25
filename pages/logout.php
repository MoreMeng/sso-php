<?php
/**
 * logout.php — ออกจากระบบ / Destroy SSO session and redirect to login
 */

require_once __DIR__ . '/../conf/sso-config.php';

session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'secure'   => isset($_SERVER['HTTPS']),
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();
session_unset();
session_destroy();

// ลบ session cookie ออกจาก browser
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

header('Location: ' . BASE_PATH . '/?page=login');
exit;
