<?php
/**
 * login.php — หน้าเข้าสู่ระบบด้วย Provider ID
 * Login page — redirects user to Provider ID OAuth2 authorization endpoint
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

// ถ้า login แล้วและ session ยังไม่หมดอายุ — redirect ต่อไป
if (
    isset($_SESSION['sso_logged_in'], $_SESSION['sso_expires_at']) &&
    $_SESSION['sso_logged_in'] === true &&
    time() < $_SESSION['sso_expires_at']
) {
    $dest = isset($_GET['continue']) ? $_GET['continue'] : DEFAULT_REDIRECT_URL;
    header('Location: ' . $dest);
    exit;
}

// เก็บ URL ที่จะ redirect หลัง login
if (isset($_GET['continue'])) {
    $_SESSION['sso_continue_url'] = $_GET['continue'];
}

// รหัสข้อผิดพลาด — Error code mapping (Thai messages)
$error_messages = [
    'csrf'     => 'เกิดข้อผิดพลาดด้านความปลอดภัย (CSRF) กรุณาลองใหม่อีกครั้ง',
    'token'    => 'ไม่สามารถยืนยันตัวตนได้ กรุณาลองใหม่อีกครั้ง',
    'profile'  => 'ไม่สามารถดึงข้อมูลผู้ใช้ได้ กรุณาลองใหม่อีกครั้ง',
    'no_cid'   => 'ไม่พบเลขบัตรประชาชนในข้อมูลผู้ใช้ กรุณาติดต่อผู้ดูแลระบบ',
    'state'    => 'เกิดข้อผิดพลาดด้านความปลอดภัย กรุณาลองใหม่อีกครั้ง',
];

$error_code = isset($_GET['error']) ? htmlspecialchars($_GET['error']) : null;
$error_msg  = ($error_code && isset($error_messages[$error_code])) ? $error_messages[$error_code] : null;
$expired    = isset($_GET['expired']) && $_GET['expired'] === '1';

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
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เข้าสู่ระบบด้วย Provider ID</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Sarabun', 'Prompt', sans-serif;
            background: linear-gradient(135deg, #005bac 0%, #1e88e5 50%, #42a5f5 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }

        .card {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 8px 40px rgba(0,0,0,0.18);
            padding: 2.5rem 2rem;
            width: 100%;
            max-width: 420px;
            text-align: center;
        }

        .logo {
            width: 72px;
            height: 72px;
            border-radius: 50%;
            background: #005bac;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1.25rem;
        }

        .logo svg {
            width: 40px;
            height: 40px;
            fill: #fff;
        }

        h1 {
            font-size: 1.4rem;
            color: #1a237e;
            margin-bottom: 0.4rem;
            font-weight: 700;
        }

        .subtitle {
            font-size: 0.95rem;
            color: #546e7a;
            margin-bottom: 1.75rem;
        }

        .alert {
            border-radius: 8px;
            padding: 0.75rem 1rem;
            margin-bottom: 1.25rem;
            font-size: 0.92rem;
            text-align: left;
        }

        .alert-warning {
            background: #fff8e1;
            border-left: 4px solid #ffc107;
            color: #7b5800;
        }

        .alert-error {
            background: #ffebee;
            border-left: 4px solid #e53935;
            color: #b71c1c;
        }

        .btn-login {
            display: block;
            width: 100%;
            padding: 0.9rem 1.5rem;
            background: #005bac;
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 1.05rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: background 0.2s, transform 0.1s;
            margin-bottom: 1rem;
        }

        .btn-login:hover  { background: #003d7a; }
        .btn-login:active { transform: scale(0.98); }

        .provider-badge {
            display: inline-block;
            background: #e3f2fd;
            color: #1565c0;
            border-radius: 20px;
            padding: 0.25rem 0.85rem;
            font-size: 0.8rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
        }

        .footer-note {
            font-size: 0.78rem;
            color: #90a4ae;
            margin-top: 1.5rem;
            line-height: 1.6;
        }

        @media (max-width: 480px) {
            .card { padding: 2rem 1.25rem; }
            h1    { font-size: 1.2rem; }
        }
    </style>
</head>
<body>
<div class="card">
    <!-- Logo -->
    <div class="logo" aria-hidden="true">
        <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
            <path d="M12 12c2.7 0 4.8-2.1 4.8-4.8S14.7 2.4 12 2.4 7.2 4.5 7.2 7.2 9.3 12 12 12zm0 2.4c-3.2 0-9.6 1.6-9.6 4.8v2.4h19.2v-2.4c0-3.2-6.4-4.8-9.6-4.8z"/>
        </svg>
    </div>

    <h1>เข้าสู่ระบบด้วย Provider ID</h1>
    <p class="subtitle">สำหรับบุคลากรสาธารณสุข</p>

    <?php
    $provider_host = parse_url(PROVIDER_ID_AUTHORIZATION_URL, PHP_URL_HOST) ?: 'provider.id.th';
    $env_label = defined('PROVIDER_ENV') ? strtoupper(PROVIDER_ENV) : strtoupper(getenv('PROVIDER_ENV') ?: 'UAT');
    ?>
    <span class="provider-badge">🔐 <?= htmlspecialchars($provider_host) ?> (<?= htmlspecialchars($env_label) ?>)</span>

    <?php if ($expired): ?>
    <div class="alert alert-warning">
        ⚠️ session หมดอายุแล้ว กรุณาเข้าสู่ระบบใหม่อีกครั้ง
    </div>
    <?php endif; ?>

    <?php if ($error_msg): ?>
    <div class="alert alert-error">
        ❌ <?= $error_msg ?>
    </div>
    <?php endif; ?>

    <a href="<?= $auth_url ?>" class="btn-login">
        เข้าสู่ระบบสำหรับบุคลากรสาธารณสุข
    </a>

    <p class="footer-note">
        ระบบนี้ใช้การยืนยันตัวตนผ่าน Provider ID (provider.id.th)<br>
        สำหรับบุคลากรในสังกัดกระทรวงสาธารณสุข
    </p>
</div>
</body>
</html>
