<?php
/**
 * profile.php — หน้าแสดงข้อมูลผู้ใช้ (สำหรับทดสอบ)
 * Profile page — displays logged-in Health ID user information for testing purposes
 */

require_once __DIR__ . '/session-check.php';
// $sso_user is now available (set by session-check.php)
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ข้อมูลผู้ใช้ — SSO</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Sarabun', 'Prompt', sans-serif;
            background: #f0f4f8;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: flex-start;
            padding: 2rem 1rem;
        }

        .card {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 4px 24px rgba(0,0,0,0.10);
            padding: 2.5rem 2rem;
            width: 100%;
            max-width: 500px;
        }

        h1 {
            font-size: 1.3rem;
            color: #1a237e;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 1.5rem;
            font-size: 0.95rem;
        }

        th, td {
            padding: 0.55rem 0.75rem;
            text-align: left;
            border-bottom: 1px solid #e8ecf0;
        }

        th {
            color: #546e7a;
            font-weight: 600;
            width: 40%;
            white-space: nowrap;
        }

        td { color: #263238; word-break: break-all; }

        .badge {
            display: inline-block;
            background: #e8f5e9;
            color: #2e7d32;
            border-radius: 20px;
            padding: 0.2rem 0.7rem;
            font-size: 0.8rem;
            font-weight: 600;
            margin-bottom: 1.25rem;
        }

        .expires {
            font-size: 0.82rem;
            color: #78909c;
            margin-bottom: 1.5rem;
        }

        .btn-logout {
            display: inline-block;
            padding: 0.65rem 1.5rem;
            background: #e53935;
            color: #fff;
            border-radius: 8px;
            text-decoration: none;
            font-size: 0.95rem;
            font-weight: 600;
            transition: background 0.2s;
        }

        .btn-logout:hover { background: #b71c1c; }

        @media (max-width: 480px) {
            .card { padding: 1.5rem 1rem; }
            th    { width: 35%; }
        }
    </style>
</head>
<body>
<div class="card">
    <h1>👤 ข้อมูลผู้ใช้</h1>

    <span class="badge">✅ เข้าสู่ระบบแล้ว</span>

    <table>
        <tr>
            <th>Account ID</th>
            <td><code><?= htmlspecialchars(substr($sso_user['account_id'] ?? '-', 0, 20)) ?>...</code></td>
        </tr>
        <tr>
            <th>ชื่อ-สกุล (ไทย)</th>
            <td><?= htmlspecialchars($sso_user['name_th'] ?? '-') ?></td>
        </tr>
        <tr>
            <th>Name (English)</th>
            <td><?= htmlspecialchars($sso_user['name_eng'] ?? '-') ?></td>
        </tr>
        <tr>
            <th>เพศ</th>
            <td><?= htmlspecialchars($sso_user['gender_th'] ?? '-') ?></td>
        </tr>
        <tr>
            <th>วันเดือนปีเกิด</th>
            <td><?= htmlspecialchars($sso_user['birth_date'] ?? '-') ?></td>
        </tr>
        <tr>
            <th>เบอร์โทรศัพท์</th>
            <td><?= htmlspecialchars($sso_user['mobile_number'] ?? '-') ?></td>
        </tr>
        <tr>
            <th>ระดับความน่าเชื่อถือ (IAL)</th>
            <td><?= htmlspecialchars($sso_user['ial_level'] ?? '-') ?></td>
        </tr>
        <tr>
            <th>Hash เลขบัตรประชาชน</th>
            <td><code><?= htmlspecialchars(substr($sso_user['hash_cid'] ?? '-', 0, 32)) ?>...</code></td>
        </tr>
        <tr>
            <th>เข้าสู่ระบบเมื่อ</th>
            <td><?= htmlspecialchars($sso_user['login_at'] ?? '-') ?></td>
        </tr>
        <tr>
            <th>IP Address</th>
            <td><?= htmlspecialchars($sso_user['login_ip'] ?? '-') ?></td>
        </tr>
    </table>

    <p class="expires">
        session หมดอายุเวลา:
        <?= isset($_SESSION['sso_expires_at']) ? date('Y-m-d H:i:s', $_SESSION['sso_expires_at']) : '-' ?>
    </p>

    <a href="?page=logout" class="btn-logout">ออกจากระบบ</a>
</div>

<?php if (isset($_SESSION['debug_profile_raw'])): ?>
<div style="margin:2rem auto;width:80%;font-family:monospace;font-size:0.85rem;">
    <h2 style="background:#1a237e;color:#fff;padding:0.6rem 1rem;border-radius:8px 8px 0 0;margin:0;">
        🐛 DEBUG SESSION
    </h2>

    <details open style="background:#fff;border:1px solid #c5cae9;border-top:none;padding:1rem;">
        <summary style="font-weight:700;cursor:pointer;color:#1a237e;">Token Response (raw)</summary>
        <pre style="background:#f5f5f5;padding:0.75rem;overflow:auto;margin-top:0.5rem;"><?= htmlspecialchars($token_response ?? $_SESSION['debug_token_raw'] ?? '') ?></pre>
    </details>

    <details style="background:#fff;border:1px solid #c5cae9;border-top:none;padding:1rem;">
        <summary style="font-weight:700;cursor:pointer;color:#1a237e;">Token Decoded (array)</summary>
        <pre style="background:#f5f5f5;padding:0.75rem;overflow:auto;margin-top:0.5rem;"><?= htmlspecialchars(print_r($_SESSION['debug_token_decoded'] ?? [], true)) ?></pre>
    </details>

    <details open style="background:#fff;border:1px solid #c5cae9;border-top:none;padding:1rem;">
        <summary style="font-weight:700;cursor:pointer;color:#1a237e;">Profile Response (raw)</summary>
        <pre style="background:#f5f5f5;padding:0.75rem;overflow:auto;margin-top:0.5rem;"><?= htmlspecialchars($_SESSION['debug_profile_raw'] ?? '') ?></pre>
    </details>

    <details open style="background:#fff;border:1px solid #c5cae9;border-top:none;padding:1rem;">
        <summary style="font-weight:700;cursor:pointer;color:#1a237e;">Profile Unwrapped (array)</summary>
        <pre style="background:#f5f5f5;padding:0.75rem;overflow:auto;margin-top:0.5rem;"><?= htmlspecialchars(print_r($_SESSION['debug_profile_unwrapped'] ?? [], true)) ?></pre>
    </details>

    <details style="background:#fff;border:1px solid #c5cae9;border-top:none;border-radius:0 0 8px 8px;padding:1rem;">
        <summary style="font-weight:700;cursor:pointer;color:#1a237e;">Full $_SESSION</summary>
        <pre style="background:#f5f5f5;padding:0.75rem;overflow:auto;margin-top:0.5rem;"><?= htmlspecialchars(print_r($_SESSION, true)) ?></pre>
    </details>
</div>
<?php endif; ?>
</body>
</html>
