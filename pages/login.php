<?php
/**
 * login.php — หน้าเข้าสู่ระบบด้วย Provider ID
 * Login page — redirects user to Provider ID OAuth2 authorization endpoint
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

// สร้าง Authorization URL — Build authorization URL
// Build authorization URL. We encode parameters except `scope` so the
// `scope` value is included verbatim (spaces not percent-encoded).
$scope = 'cid title_th title_eng name_th name_eng mobile_number email organization ial idp_permission offline_access';
$params = [
    'client_id'     => PROVIDER_ID_CLIENT_ID,
    'response_type' => 'code',
];
$query = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
// Build URL in this exact order: client_id, response_type, redirect_uri (verbatim),
// scope (spaces encoded as %20), state.
$auth_url = PROVIDER_ID_AUTHORIZATION_URL
    . '?' . $query
    . '&redirect_uri=' . OAUTH_REDIRECT_URI
    . '&scope=' . rawurlencode($scope)
    . '&state=' . $state;

// Auto-redirect to Provider ID OAuth authorization endpoint
header('Location: ' . $auth_url);
exit;
?>
