<?php
/**
 * login.php — หน้าเข้าสู่ระบบด้วย Health ID (หมอพร้อมดิจิทัลไอดี)
 * Login page — redirects user to Health ID OAuth2 authorization endpoint
 */

require_once __DIR__ . '/../conf/sso-config.php';

// ถ้า login แล้วและ session ยังไม่หมดอายุ — redirect ต่อไป
if (
    isset($_SESSION['sso_logged_in'], $_SESSION['sso_expires_at']) &&
    $_SESSION['sso_logged_in'] === true &&
    time() < $_SESSION['sso_expires_at']
) {
    $dest = 'profile';  // default destination
    if (!empty($_SESSION['sso_continue_url'])) {
        $dest = $_SESSION['sso_continue_url'];
        unset($_SESSION['sso_continue_url']);
    }
    header('Location: ' . $dest);
    exit;
}

// เก็บ URL ที่จะ redirect หลัง login
if (isset($_GET['continue'])) {
    $_SESSION['sso_continue_url'] = $_GET['continue'];
} else {
    // ถ้าไม่มี continue parameter ให้ set default ไปยัง profile page
    $_SESSION['sso_continue_url'] = 'profile';
}

// สร้าง CSRF state token — Generate CSRF state token
$state = bin2hex(random_bytes(32));
$_SESSION['oauth_state']      = $state;
$_SESSION['oauth_state_time'] = time();

// สร้าง Authorization URL สำหรับ Health ID
// Health ID format: {URL}/oauth/redirect?client_id=...&redirect_uri=...&response_type=code&state=...
$auth_url = PROVIDER_ID_AUTHORIZATION_URL . '?' . http_build_query([
    'client_id'     => PROVIDER_ID_CLIENT_ID,
    'redirect_uri'  => OAUTH_REDIRECT_URI,
    'response_type' => 'code',
    'state'         => $state,
]);

// Auto-redirect to Health ID OAuth authorization endpoint
header('Location: ' . $auth_url);
exit;
?>
