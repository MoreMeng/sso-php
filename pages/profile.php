<?php
/**
 * profile.php — หน้าแสดงข้อมูลผู้ใช้ (สำหรับทดสอบ)
 * Profile page — displays logged-in user information for testing purposes
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
            align-items: center;
            justify-content: center;
            padding: 1rem;
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
            <th>Provider ID</th>
            <td><?= htmlspecialchars($sso_user['provider_id'] ?? '-') ?></td>
        </tr>
        <tr>
            <th>Account ID</th>
            <td><code><?= htmlspecialchars(substr($sso_user['account_id'] ?? '-', 0, 20)) ?>...</code></td>
        </tr>
        <tr>
            <th>ชื่อ (ไทย)</th>
            <td><?= htmlspecialchars($sso_user['name_th'] ?? '-') ?></td>
        </tr>
        <tr>
            <th>Name (English)</th>
            <td><?= htmlspecialchars($sso_user['name_eng'] ?? '-') ?></td>
        </tr>
        <tr>
            <th>อีเมล</th>
            <td><?= htmlspecialchars($sso_user['email'] ?? '-') ?></td>
        </tr>
        <tr>
            <th>ระดับความน่าเชื่อถือ</th>
            <td><?= htmlspecialchars($sso_user['ial_level'] ?? '-') ?></td>
        </tr>
        <tr>
            <th>Hash CID</th>
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

    <h2 style="font-size: 1.1rem; color: #1a237e; margin-top: 2rem; margin-bottom: 1rem;">📋 สังกัดหน่วยงาน</h2>

    <?php
    $organizations = $sso_user['organizations'] ?? [];
    if (empty($organizations)):
    ?>
        <p style="color: #78909c;">ไม่พบข้อมูลสังกัด</p>
    <?php else: ?>
        <?php foreach ($organizations as $i => $org): ?>
            <div style="background: #f5f5f5; border-radius: 8px; padding: 1rem; margin-bottom: 1rem;">
                <h3 style="color: #1565c0; font-size: 1rem; margin-bottom: 0.5rem;">สังกัดที่ <?= $i + 1 ?></h3>
                <table style="margin: 0;">
                    <tr>
                        <th>หน่วยงาน (ไทย)</th>
                        <td><?= htmlspecialchars($org['hname_th'] ?? '-') ?></td>
                    </tr>
                    <tr>
                        <th>Organization (Eng)</th>
                        <td><?= htmlspecialchars($org['hname_eng'] ?? '-') ?></td>
                    </tr>
                    <tr>
                        <th>รหัสหน่วยงาน</th>
                        <td><?= htmlspecialchars($org['hcode'] ?? '-') ?></td>
                    </tr>
                    <tr>
                        <th>ตำแหน่ง</th>
                        <td><?= htmlspecialchars($org['position'] ?? '-') ?></td>
                    </tr>
                    <tr>
                        <th>ID ตำแหน่ง</th>
                        <td><?= htmlspecialchars($org['position_id'] ?? '-') ?></td>
                    </tr>
                    <tr>
                        <th>Business ID</th>
                        <td><code><?= htmlspecialchars(substr($org['business_id'] ?? '-', 0, 20)) ?>...</code></td>
                    </tr>
                </table>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <p class="expires">
        session หมดอายุเวลา:
        <?= isset($_SESSION['sso_expires_at']) ? date('Y-m-d H:i:s', $_SESSION['sso_expires_at']) : '-' ?>
    </p>

    <a href="?page=logout" class="btn-logout">ออกจากระบบ</a>
</div>
</body>
</html>
