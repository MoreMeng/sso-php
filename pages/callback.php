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
// Helper: debug output (instead of redirect)
// ──────────────────────────────────────────────────────────────
function debugOutput(string $status, string $message, $data = null): void
{
    echo "<h2>OAuth2 Debug: " . htmlspecialchars($status) . "</h2>";
    echo "<p><strong>Message:</strong> " . htmlspecialchars($message) . "</p>";
    if ($data !== null) {
        echo "<pre>";
        echo htmlspecialchars(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        echo "</pre>";
    }
}

// ──────────────────────────────────────────────────────────────
// Helper: display profile data in readable table format
// ──────────────────────────────────────────────────────────────
function displayProfileData(array $profile): void
{
    echo "<h3>📋 Profile Data from Provider ID API</h3>";
    echo "<table border='1' cellpadding='10' style='border-collapse: collapse; font-family: Arial, sans-serif; margin: 20px 0;'>";
    echo "<tr style='background-color: #4CAF50; color: white;'><th style='padding: 15px;'>Field</th><th style='padding: 15px;'>Value</th></tr>";

    $rowCount = 0;
    foreach ($profile as $key => $value) {
        $bgcolor = ($rowCount++ % 2 == 0) ? '#f9f9f9' : '#ffffff';

        if (is_array($value) || is_object($value)) {
            $valueStr = json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        } else {
            $valueStr = (string)$value;
        }

        $displayValue = htmlspecialchars(substr($valueStr, 0, 300));
        if (strlen($valueStr) > 300) {
            $displayValue .= '...';
        }

        echo "<tr style='background-color: $bgcolor;'>";
        echo "<td style='padding: 10px; font-weight: bold; color: #333;'>" . htmlspecialchars($key) . "</td>";
        echo "<td style='padding: 10px; color: #555;'>" . $displayValue . "</td>";
        echo "</tr>";
    }

    echo "</table>";
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
    debugOutput('ERROR', 'Missing code or state parameter', ['code' => $code, 'state' => $state]);
    exit;
}

$stored_state = isset($_SESSION['oauth_state'])      ? $_SESSION['oauth_state']      : null;
$state_time   = isset($_SESSION['oauth_state_time']) ? $_SESSION['oauth_state_time'] : 0;

// Clear state from session immediately (single-use)
unset($_SESSION['oauth_state'], $_SESSION['oauth_state_time']);

if (!$stored_state || !hash_equals($stored_state, $state)) {
    debugOutput('ERROR', 'CSRF state mismatch', ['stored_state' => $stored_state, 'returned_state' => $state]);
    exit;
}

// State token หมดอายุหลังจาก 10 นาที
if (time() - $state_time > 600) {
    debugOutput('ERROR', 'State token expired', ['expires_at' => date('Y-m-d H:i:s', $state_time + 600)]);
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

debugOutput('TOKEN_REQUEST', 'Requesting to: ' . PROVIDER_ID_TOKEN_URL, [
    'method' => 'POST',
    'body' => $token_body,
    'response_code' => $token_http,
]);

if ($token_err || $token_http !== 200) {
    debugOutput('ERROR', 'Token exchange failed (HTTP ' . $token_http . ')', ['http_code' => $token_http, 'error' => $token_err, 'response' => $token_response]);
    exit;
}

$token_data   = json_decode($token_response, true);

// Provider ID returns data in { "status": "...", "data": { "access_token": ... } }
$token_data_unwrapped = isset($token_data['data']) ? $token_data['data'] : $token_data;
$access_token = isset($token_data_unwrapped['access_token']) ? $token_data_unwrapped['access_token'] : null;

debugOutput('TOKEN_RESPONSE', 'HTTP ' . $token_http, $token_data_unwrapped);

if (!$access_token) {
    debugOutput('ERROR', 'No access_token in response', $token_data);
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

debugOutput('PROFILE_REQUEST', 'Requesting to: ' . PROVIDER_ID_USER_INFO_URL, [
    'method' => 'GET',
    'headers' => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . substr($access_token, 0, 50) . '...',
        'client-id: ' . PROVIDER_ID_CLIENT_ID,
        'secret-key: ' . PROVIDER_ID_CLIENT_SECRET,
    ],
    'response_code' => $profile_http,
]);

if ($profile_err || $profile_http !== 200) {
    debugOutput('ERROR', 'Profile fetch failed (HTTP ' . $profile_http . ')', ['http_code' => $profile_http, 'error' => $profile_err, 'response' => $profile_response]);
    exit;
}

$profile = json_decode($profile_response, true);

// Support both: { "data": { ... } } and { ... } structures
$profile_unwrapped = isset($profile['data']) ? $profile['data'] : $profile;

debugOutput('PROFILE_RESPONSE', 'HTTP ' . $profile_http, $profile_unwrapped);

// Display profile data in readable format
displayProfileData($profile_unwrapped);

// ──────────────────────────────────────────────────────────────
// Step 4 — Validate profile data
// ตรวจสอบว่ามีเลขบัตรประชาชน (cid) อยู่ในข้อมูล
// ──────────────────────────────────────────────────────────────
if (empty($profile_unwrapped['cid'])) {
    debugOutput('ERROR', 'No CID in profile', $profile_unwrapped);
    exit;
}

// ──────────────────────────────────────────────────────────────
// Step 5 — Create session
// สร้าง session หลังจาก login สำเร็จ
// ──────────────────────────────────────────────────────────────

// Regenerate session ID to prevent session fixation
session_regenerate_id(true);

$_SESSION['sso_logged_in'] = true;
$_SESSION['sso_user'] = [
    'cid'         => $profile_unwrapped['cid'],
    'name_th'     => isset($profile_unwrapped['name_th'])       ? $profile_unwrapped['name_th']       : '',
    'name_eng'    => isset($profile_unwrapped['name_eng'])      ? $profile_unwrapped['name_eng']      : '',
    'email'       => isset($profile_unwrapped['email'])         ? $profile_unwrapped['email']         : '',
    'mobile'      => isset($profile_unwrapped['mobile_number']) ? $profile_unwrapped['mobile_number'] : '',
    'provider_id' => isset($profile_unwrapped['id'])            ? $profile_unwrapped['id']            : '',
    'login_at'    => date('Y-m-d H:i:s'),
    'login_ip'    => $_SERVER['REMOTE_ADDR'] ?? '',
    'access_token'=> $access_token,
];
$_SESSION['sso_expires_at'] = time() + SSO_SESSION_LIFETIME;

// ──────────────────────────────────────────────────────────────
// DEBUG: Show session data (instead of redirecting)
// ──────────────────────────────────────────────────────────────
debugOutput('SUCCESS', 'Session created successfully', $_SESSION);
echo "<p><strong>Session ID:</strong> " . htmlspecialchars(session_id()) . "</p>";

// Uncomment below to actually redirect (when ready)
/*
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
*/
