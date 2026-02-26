<?php
/**
 * callback.php — OAuth2 Callback Handler
 * รับ code จาก Provider ID, แลก token, ดึง profile, สร้าง session
 *
 * Flow:
 *  1. Validate state (CSRF)
 *  2. Exchange code → access_token
 *  3. Fetch user profile
 *  4. Create PHP session
 *  5. Redirect to destination
 */

session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'secure'   => isset($_SERVER['HTTPS']),
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

require_once __DIR__ . '/conf/sso-config.php';

// ──────────────────────────────────────────────────────────────
// Helper: redirect to login with error code
// ──────────────────────────────────────────────────────────────
function fail(string $code): void
{
    header('Location: /sso-simple/login.php?error=' . urlencode($code));
    exit;
}

// ──────────────────────────────────────────────────────────────
// Helper: write access log
// ──────────────────────────────────────────────────────────────
function logAccess(array $user): void
{
    $logDir  = __DIR__ . '/logs';
    $logFile = $logDir . '/access.log';

    if (!is_dir($logDir)) {
        mkdir($logDir, 0750, true);
    }

    $line = implode(' | ', [
        date('Y-m-d H:i:s'),
        $user['cid']     ?? '-',
        $user['name_th'] ?? '-',
        $_SERVER['REMOTE_ADDR'] ?? '-',
    ]) . "\n";

    file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
}

// ──────────────────────────────────────────────────────────────
// Step 1 — ตรวจสอบ state (CSRF protection)
// ──────────────────────────────────────────────────────────────
$code  = isset($_GET['code'])  ? $_GET['code']  : null;
$state = isset($_GET['state']) ? $_GET['state'] : null;

if (!$code || !$state) {
    fail('state');
}

$stored_state = isset($_SESSION['oauth_state'])      ? $_SESSION['oauth_state']      : null;
$state_time   = isset($_SESSION['oauth_state_time']) ? $_SESSION['oauth_state_time'] : 0;

// Clear state from session immediately (single-use)
unset($_SESSION['oauth_state'], $_SESSION['oauth_state_time']);

if (!$stored_state || !hash_equals($stored_state, $state)) {
    fail('csrf');
}

// State token หมดอายุหลังจาก 10 นาที
if (time() - $state_time > 600) {
    fail('csrf');
}

// ──────────────────────────────────────────────────────────────
// Step 2 — Exchange code for access_token
// แลก authorization code เป็น access token ผ่าน HTTP Basic Auth
// ──────────────────────────────────────────────────────────────
$token_body = http_build_query([
    'grant_type'   => 'authorization_code',
    'code'         => $code,
    'redirect_uri' => OAUTH_REDIRECT_URI,
]);

$basic_auth = base64_encode(PROVIDER_ID_CLIENT_ID . ':' . PROVIDER_ID_CLIENT_SECRET);

$ch = curl_init(PROVIDER_ID_TOKEN_URL);
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $token_body,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 15,
    CURLOPT_HTTPHEADER     => [
        'Authorization: Basic ' . $basic_auth,
        'Content-Type: application/x-www-form-urlencoded',
        'Accept: application/json',
    ],
]);

$token_response = curl_exec($ch);
$token_http     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$token_err      = curl_error($ch);
curl_close($ch);

if ($token_err || $token_http !== 200) {
    fail('token');
}

$token_data   = json_decode($token_response, true);
$access_token = isset($token_data['access_token']) ? $token_data['access_token'] : null;

if (!$access_token) {
    fail('token');
}

// ──────────────────────────────────────────────────────────────
// Step 3 — Fetch user profile
// ดึงข้อมูลผู้ใช้จาก Provider ID API โดยใช้ Bearer token
// ──────────────────────────────────────────────────────────────
$ch = curl_init(PROVIDER_ID_USER_INFO_URL);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 15,
    CURLOPT_HTTPHEADER     => [
        'Authorization: Bearer ' . $access_token,
        'Accept: application/json',
    ],
]);

$profile_response = curl_exec($ch);
$profile_http     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$profile_err      = curl_error($ch);
curl_close($ch);

if ($profile_err || $profile_http !== 200) {
    fail('profile');
}

$profile = json_decode($profile_response, true);

// ──────────────────────────────────────────────────────────────
// Step 4 — Validate profile data
// ตรวจสอบว่ามีเลขบัตรประชาชน (cid) อยู่ในข้อมูล
// ──────────────────────────────────────────────────────────────
if (empty($profile['cid'])) {
    fail('no_cid');
}

// ──────────────────────────────────────────────────────────────
// Step 5 — Create session
// สร้าง session หลังจาก login สำเร็จ
// ──────────────────────────────────────────────────────────────

// Regenerate session ID to prevent session fixation
session_regenerate_id(true);

$_SESSION['sso_logged_in'] = true;
$_SESSION['sso_user'] = [
    'cid'         => $profile['cid'],
    'name_th'     => isset($profile['name_th'])       ? $profile['name_th']       : '',
    'name_eng'    => isset($profile['name_eng'])      ? $profile['name_eng']      : '',
    'email'       => isset($profile['email'])         ? $profile['email']         : '',
    'mobile'      => isset($profile['mobile_number']) ? $profile['mobile_number'] : '',
    'provider_id' => isset($profile['id'])            ? $profile['id']            : '',
    'login_at'    => date('Y-m-d H:i:s'),
    'login_ip'    => $_SERVER['REMOTE_ADDR'] ?? '',
    'access_token'=> $access_token,
];
$_SESSION['sso_expires_at'] = time() + SSO_SESSION_LIFETIME;

// ──────────────────────────────────────────────────────────────
// Step 6 — Log access (optional)
// ──────────────────────────────────────────────────────────────
logAccess($_SESSION['sso_user']);

// ──────────────────────────────────────────────────────────────
// Step 7 — Redirect to destination
// ──────────────────────────────────────────────────────────────
$redirect = DEFAULT_REDIRECT_URL;
if (!empty($_SESSION['sso_continue_url'])) {
    $redirect = $_SESSION['sso_continue_url'];
    unset($_SESSION['sso_continue_url']);
}

header('Location: ' . $redirect);
exit;
