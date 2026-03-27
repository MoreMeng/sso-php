<?php
/**
 * callback.php — OAuth2 Callback Handler
 * รับ code จาก Health ID, แลก token, ดึง profile, สร้าง session
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
    header('Location: ' . BASE_PATH . '/?page=profile');
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
        $user['account_id'] ?? '-',
        $user['name_th']    ?? '-',
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
    header('Location: ' . BASE_PATH . '/?page=login&error=state');
    exit;
}

$stored_state = isset($_SESSION['oauth_state'])      ? $_SESSION['oauth_state']      : null;
$state_time   = isset($_SESSION['oauth_state_time']) ? $_SESSION['oauth_state_time'] : 0;

// Clear state from session immediately (single-use)
unset($_SESSION['oauth_state'], $_SESSION['oauth_state_time']);

if (!$stored_state || !hash_equals($stored_state, $state)) {
    header('Location: ' . BASE_PATH . '/?page=login&error=csrf');
    exit;
}

// State token หมดอายุหลังจาก 10 นาที
if (time() - $state_time > 600) {
    header('Location: ' . BASE_PATH . '/?page=login&error=csrf');
    exit;
}

// ──────────────────────────────────────────────────────────────
// Step 2 — Exchange code for access_token
// แลก authorization code เป็น access token
// Health ID ใช้ client_id + client_secret ใน POST body (ไม่ใช้ Basic Auth)
// ──────────────────────────────────────────────────────────────
$token_body = http_build_query([
    'grant_type'    => 'authorization_code',
    'code'          => $code,
    'redirect_uri'  => OAUTH_REDIRECT_URI,
    'client_id'     => PROVIDER_ID_CLIENT_ID,
    'client_secret' => PROVIDER_ID_CLIENT_SECRET,
]);

$ch = curl_init(PROVIDER_ID_TOKEN_URL);
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $token_body,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 15,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/x-www-form-urlencoded',
        'Accept: application/json',
    ],
]);

$token_response = curl_exec($ch);
$token_http     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$token_err      = curl_error($ch);
curl_close($ch);

if ($token_err || $token_http !== 200) {
    header('Location: ' . BASE_PATH . '/?page=login&error=token');
    exit;
}

$token_data   = json_decode($token_response, true);

// Provider ID returns data in { "status": "...", "data": { "access_token": ... } }
$token_data_unwrapped = isset($token_data['data']) ? $token_data['data'] : $token_data;
$access_token = isset($token_data_unwrapped['access_token']) ? $token_data_unwrapped['access_token'] : null;

if (!$access_token) {
    header('Location: ' . BASE_PATH . '/?page=login&error=token');
    exit;
}

// ──────────────────────────────────────────────────────────────
// Step 3 — Fetch user profile
// ดึงข้อมูลผู้ใช้จาก Health ID API โดยใช้ Bearer token
// ──────────────────────────────────────────────────────────────
$ch = curl_init(PROVIDER_ID_USER_INFO_URL);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 15,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $access_token,
    ],
]);

$profile_response = curl_exec($ch);
$profile_http     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$profile_err      = curl_error($ch);
curl_close($ch);

if ($profile_err || $profile_http !== 200) {
    header('Location: ' . BASE_PATH . '/?page=login&error=profile');
    exit;
}

$profile = json_decode($profile_response, true);

// Support both: { "data": { ... } } and { ... } structures
$profile_unwrapped = isset($profile['data']) ? $profile['data'] : $profile;

// ──────────────────────────────────────────────────────────────
// Step 4 — Validate profile data
// ตรวจสอบว่ามี account_id อยู่ในข้อมูล
// ──────────────────────────────────────────────────────────────
if (empty($profile_unwrapped['account_id'])) {
    header('Location: ' . BASE_PATH . '/?page=login&error=no_cid');
    exit;
}

// ──────────────────────────────────────────────────────────────
// Step 5 — Create session
// สร้าง session หลังจาก login สำเร็จ
// ──────────────────────────────────────────────────────────────

// Regenerate session ID to prevent session fixation
session_regenerate_id(true);

// สร้างชื่อเต็มจากชื่อ-นามสกุล
$title_th   = $profile_unwrapped['account_title_th']  ?? '';
$title_eng  = $profile_unwrapped['account_title_eng'] ?? '';
$fname_th   = $profile_unwrapped['first_name_th']     ?? '';
$mname_th   = $profile_unwrapped['middle_name_th']    ?? '';
$lname_th   = $profile_unwrapped['last_name_th']      ?? '';
$fname_eng  = $profile_unwrapped['first_name_eng']    ?? '';
$mname_eng  = $profile_unwrapped['middle_name_eng']   ?? '';
$lname_eng  = $profile_unwrapped['last_name_eng']     ?? '';

$name_th  = trim($title_th . $fname_th . ($mname_th ? ' ' . $mname_th : '') . ' ' . $lname_th);
$name_eng = trim($title_eng . ' ' . $fname_eng . ($mname_eng ? ' ' . $mname_eng : '') . ' ' . $lname_eng);

$_SESSION['sso_logged_in'] = true;
$_SESSION['sso_user'] = [
    // Primary identifiers
    'account_id'     => $profile_unwrapped['account_id']       ?? '',
    'hash_cid'       => $profile_unwrapped['hash_id_card_num'] ?? '',

    // Security level
    'ial_level'      => $profile_unwrapped['ial']['level']     ?? 0,

    // Personal info — full name
    'name_th'        => $name_th,
    'name_eng'       => $name_eng,
    'title_th'       => $title_th,
    'title_eng'      => $title_eng,
    'first_name_th'  => $fname_th,
    'last_name_th'   => $lname_th,
    'first_name_eng' => $fname_eng,
    'last_name_eng'  => $lname_eng,

    // Additional info
    'mobile_number'  => $profile_unwrapped['mobile_number'] ?? '',
    'gender_th'      => $profile_unwrapped['gender_th']     ?? '',
    'birth_date'     => $profile_unwrapped['birth_date']    ?? '',

    // Metadata
    'login_at'       => date('Y-m-d H:i:s'),
    'login_ip'       => $_SERVER['REMOTE_ADDR'] ?? '',
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
    header('Location: ' . BASE_PATH . '/?page=profile');
    exit;
}

safeRedirect($continue);
