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

require_once __DIR__ . '/../conf/sso-config.php';

// ──────────────────────────────────────────────────────────────
// Helper: safe redirect — ป้องกัน open redirect โดยอนุญาตเฉพาะ same-host หรือ relative path
// ──────────────────────────────────────────────────────────────
function safeRedirect(string $url): void
{
    $parsed = parse_url($url);
    // Allow relative paths (no host component)
    if (!isset($parsed['host'])) {
        header('Location: ' . $url);
        exit;
    }
    // Allow same host only
    $requestHost = $_SERVER['HTTP_HOST'] ?? '';
    if ($parsed['host'] === $requestHost) {
        header('Location: ' . $url);
        exit;
    }
    // Fallback to profile page for untrusted URLs
    header('Location: /athweb/sso/?page=profile');
    exit;
}

// ──────────────────────────────────────────────────────────────
// Helper: write access log
// ──────────────────────────────────────────────────────────────
function logAccess(array $user): void
{
    $logDir  = __DIR__ . '/../logs';
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
    header('Location: /athweb/sso/?page=login&error=state');
    exit;
}

$stored_state = isset($_SESSION['oauth_state'])      ? $_SESSION['oauth_state']      : null;
$state_time   = isset($_SESSION['oauth_state_time']) ? $_SESSION['oauth_state_time'] : 0;

// Clear state from session immediately (single-use)
unset($_SESSION['oauth_state'], $_SESSION['oauth_state_time']);

if (!$stored_state || !hash_equals($stored_state, $state)) {
    header('Location: /athweb/sso/?page=login&error=csrf');
    exit;
}

// State token หมดอายุหลังจาก 10 นาที
if (time() - $state_time > 600) {
    header('Location: /athweb/sso/?page=login&error=csrf');
    exit;
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
    header('Location: /athweb/sso/?page=login&error=token');
    exit;
}

$token_data   = json_decode($token_response, true);

// Provider ID returns data in { "status": "...", "data": { "access_token": ... } }
$token_data_unwrapped = isset($token_data['data']) ? $token_data['data'] : $token_data;
$access_token = isset($token_data_unwrapped['access_token']) ? $token_data_unwrapped['access_token'] : null;

if (!$access_token) {
    header('Location: /athweb/sso/?page=login&error=token');
    exit;
}

// ──────────────────────────────────────────────────────────────
// Step 3 — Fetch user profile
// ดึงข้อมูลผู้ใช้จาก Provider ID API โดยใช้ Bearer token + client credentials
// ──────────────────────────────────────────────────────────────
$ch = curl_init(PROVIDER_ID_USER_INFO_URL);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 15,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $access_token,
        'client-id: ' . PROVIDER_ID_CLIENT_ID,
        'secret-key: ' . PROVIDER_ID_CLIENT_SECRET,
    ],
]);

$profile_response = curl_exec($ch);
$profile_http     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$profile_err      = curl_error($ch);
curl_close($ch);

if ($profile_err || $profile_http !== 200) {
    header('Location: /athweb/sso/?page=login&error=profile');
    exit;
}

$profile = json_decode($profile_response, true);

// Support both: { "data": { ... } } and { ... } structures
$profile_unwrapped = isset($profile['data']) ? $profile['data'] : $profile;

// ──────────────────────────────────────────────────────────────
// Step 4 — Validate profile data
// ตรวจสอบว่ามี provider_id อยู่ในข้อมูล
// ──────────────────────────────────────────────────────────────
if (empty($profile_unwrapped['provider_id'])) {
    header('Location: /athweb/sso/?page=login&error=no_cid');
    exit;
}

// ──────────────────────────────────────────────────────────────
// Step 5 — Create session
// สร้าง session หลังจาก login สำเร็จ
// ──────────────────────────────────────────────────────────────

// Regenerate session ID to prevent session fixation
session_regenerate_id(true);

// Process all organizations (user may have multiple affiliations)
$organizations = [];
if (isset($profile_unwrapped['organization']) && is_array($profile_unwrapped['organization'])) {
    foreach ($profile_unwrapped['organization'] as $org) {
        $organizations[] = [
            'hcode'       => $org['hcode'] ?? '',
            'hname_th'    => $org['hname_th'] ?? '',
            'hname_eng'   => $org['hname_eng'] ?? '',
            'position'    => $org['position'] ?? '',
            'position_id' => $org['position_id'] ?? '',
            'business_id' => $org['business_id'] ?? '',
        ];
    }
}

$_SESSION['sso_logged_in'] = true;
$_SESSION['sso_user'] = [
    // Primary identifiers
    'provider_id' => $profile_unwrapped['provider_id'] ?? '',
    'account_id'  => $profile_unwrapped['account_id'] ?? '',
    'hash_cid'    => $profile_unwrapped['hash_cid'] ?? '',

    // Security level
    'ial_level'   => $profile_unwrapped['ial_level'] ?? 0,

    // Personal info
    'name_th'     => $profile_unwrapped['name_th'] ?? '',
    'name_eng'    => $profile_unwrapped['name_eng'] ?? '',
    'email'       => $profile_unwrapped['email'] ?? '',

    // All organizations (user may have multiple affiliations)
    'organizations' => $organizations,

    // Metadata
    'login_at'    => date('Y-m-d H:i:s'),
    'login_ip'    => $_SERVER['REMOTE_ADDR'] ?? '',
];
$_SESSION['sso_expires_at'] = time() + SSO_SESSION_LIFETIME;

// ──────────────────────────────────────────────────────────────
// Step 6 — Log access
// ──────────────────────────────────────────────────────────────
logAccess($_SESSION['sso_user']);

// ──────────────────────────────────────────────────────────────
// Step 7 — Redirect to destination
// ถ้ามี sso_continue_url → redirect กลับไปยัง URL ต้นทาง
// ถ้า continue = 'profile' หรือไม่มี → redirect ไปยังหน้า profile
// ──────────────────────────────────────────────────────────────
$continue = '';
if (!empty($_SESSION['sso_continue_url'])) {
    $continue = $_SESSION['sso_continue_url'];
    unset($_SESSION['sso_continue_url']);
}

if ($continue === '' || $continue === 'profile') {
    header('Location: /athweb/sso/?page=profile');
    exit;
}

safeRedirect($continue);
