# SSO — ระบบ Single Sign-On ด้วย Health ID (โรงพยาบาลอ่างทอง)

## Overview

ระบบนี้ทำหน้าที่เป็น **SSO Gateway** สำหรับแอปพลิเคชันภายใน (`/athweb/*`)
ใช้ **Health ID** (หมอพร้อมดิจิทัลไอดี — moph.id.th) ของกระทรวงสาธารณสุขเป็น OAuth2 Identity Provider
เมื่อผู้ใช้ยืนยันตัวตนสำเร็จแล้ว ข้อมูลจะถูกเก็บใน **PHP Session** และแชร์ร่วมกันระหว่างแอปในโดเมนเดียวกัน

> **หมายเหตุ — Base Path ตามสภาพแวดล้อม:**
> | Environment | SSO Base Path | แอปใต้ SSO |
> |---|---|---|
> | **DEV** (localhost) | `/athweb/sso/` | `/athweb/*` |
> | **PRD** (production) | `/sso/` | `/*` |
>
> Repository นี้พัฒนาและทดสอบบน DEV (`/athweb/sso/`)
> เมื่อ deploy จริงบน PRD ให้เปลี่ยน base path ทุกที่จาก `/athweb/sso/` เป็น `/sso/`
> และ `session-check.php` redirect จาก `/athweb/sso/?page=login&continue=...` เป็น `/sso/?page=login&continue=...`

---

## URLs

| Path | หน้าที่ |
|---|---|
| `GET /athweb/sso/?page=login` | เริ่มต้น login — redirect ไปยัง Health ID OAuth |
| `GET /athweb/sso/?page=login&continue={url}` | Login พร้อมระบุ URL ต้นทาง (ref path) |
| `GET /athweb/sso/?page=callback` | รับ OAuth code จาก Health ID (callback URL) |
| `GET /athweb/sso/?page=profile` | หน้าแสดงข้อมูลผู้ใช้ (protected) |
| `GET /athweb/sso/?page=logout` | ออกจากระบบ — ลบ session ทั้งหมด |

> _PRD: เปลี่ยนทุก `/athweb/sso/` → `/sso/` และ `/athweb/` → `/`_

---

## Authentication Flow

```
[User] → /athweb/some-app/page
    │
    ├─ session ยังใช้งานได้ ──────────────────────────→ [แสดงหน้าปลายทาง]
    │
    └─ ไม่มี session / หมดอายุ
           │
           ▼
  redirect → /athweb/sso/?page=login&continue=http://localhost/athweb/some-app/page
           │
           ▼  (auto-redirect ทันที ไม่มี UI)
  Health ID OAuth2 Authorization URL
           │
           ▼  (ผู้ใช้ login ที่ Health ID)
  redirect → /athweb/sso/?page=callback?code=...&state=...
           │
           ├─ Validate CSRF state
           ├─ Exchange code → access_token
           ├─ Fetch user profile จาก Health ID API
           ├─ สร้าง PHP Session
           ├─ บันทึก access log
           │
           ├─ มี continue URL ──────────────────────────→ redirect กลับ /athweb/some-app/page
           │
           └─ ไม่มี continue URL ───────────────────────→ redirect → /athweb/sso/?page=profile
```

---

## Session Structure

หลัง login สำเร็จ ข้อมูลต่อไปนี้จะอยู่ใน `$_SESSION`:

```php
$_SESSION['sso_logged_in']  = true;
$_SESSION['sso_expires_at'] = time() + (60 * 60 * 8); // 8 ชั่วโมง

$_SESSION['sso_user'] = [
    // Primary identifiers
    'account_id'     => '...',   // Health ID unique account ID
    'hash_cid'       => '...',   // hash_id_card_num (SHA256)

    // Security level
    'ial_level'      => 1.3,     // Identity Assurance Level

    // Personal info
    'name_th'        => 'คำนำหน้าชื่อ นามสกุล',
    'name_eng'       => 'Title Firstname Lastname',
    'title_th'       => 'นาย',
    'title_eng'      => 'Mr.',
    'first_name_th'  => 'ชื่อ',
    'last_name_th'   => 'นามสกุล',
    'first_name_eng' => 'Firstname',
    'last_name_eng'  => 'Lastname',
    'mobile_number'  => '08xxxxxxxx',
    'gender_th'      => 'ชาย',
    'birth_date'     => '01/01/1990',

    // Metadata
    'login_at'       => '2026-03-24 08:00:00',
    'login_ip'       => '192.168.1.1',
];
```

---

## การป้องกันหน้าในโปรเจคอื่น (Auth Guard)

สำหรับแอปใด ๆ ใน `/athweb/` ที่ต้องการป้องกันด้วย SSO ให้ `require` ไฟล์ `session-check.php` บรรทัดแรกของทุกหน้า:

```php
<?php
require_once '/var/www/html/athweb/sso/pages/session-check.php';
// หลังจากนี้ $sso_user พร้อมใช้งานทันที

echo 'สวัสดี, ' . $sso_user['name_th'];
echo 'เบอร์โทร: ' . ($sso_user['mobile_number'] ?? '-');
```

**`session-check.php` ทำสิ่งต่อไปนี้โดยอัตโนมัติ:**
1. เริ่ม session (ถ้ายังไม่ได้เริ่ม)
2. ตรวจสอบว่า `sso_logged_in === true`
3. ตรวจสอบว่า session ยังไม่หมดอายุ
4. ถ้าไม่ผ่าน → redirect ไปยัง `/athweb/sso/?page=login&continue={URL ปัจจุบัน}`
5. ถ้าผ่าน → set `$sso_user` จาก `$_SESSION['sso_user']`

---

## Security

| มาตรการ | รายละเอียด |
|---|---|
| **CSRF Protection** | ใช้ `state` token แบบ single-use (หมดอายุใน 10 นาที) |
| **Session Fixation** | `session_regenerate_id(true)` หลัง login สำเร็จ |
| **Open Redirect Prevention** | `safeRedirect()` อนุญาตเฉพาะ same-host หรือ relative path |
| **Session Cookie** | `httponly=true`, `samesite=Lax`, `secure` เมื่อใช้ HTTPS |
| **Session Lifetime** | 8 ชั่วโมง (ปรับได้ผ่าน `SSO_SESSION_LIFETIME`) |
| **Access Log** | บันทึก login ทุกครั้งที่ `logs/access.log` |

---

## Project Structure

```
/athweb/sso/
├── index.php              # Router หลัก — dispatch ไปยัง pages/
├── conf/
│   └── sso-config.php     # OAuth2 credentials, session config (UAT/PRD)
├── pages/
│   ├── login.php          # สร้าง CSRF state + auto-redirect ไป Health ID
│   ├── callback.php       # รับ OAuth code, แลก token, สร้าง session, redirect กลับ
│   ├── logout.php         # Destroy session + redirect ไป login
│   ├── profile.php        # หน้าแสดง sso_user (protected)
│   └── session-check.php  # Auth guard — ใช้ require ในทุกหน้าที่ต้องการป้องกัน
└── logs/
    └── access.log         # Login access log (auto-created)
```

---

## Environment Configuration

ควบคุมผ่าน environment variable `PROVIDER_ENV`:

| ค่า | Health ID Endpoint |
|---|---|
| `uat` (default) | `https://uat-moph.id.th` |
| `prd` | `https://moph.id.th` |

สามารถ override credentials ผ่าน env vars:

```env
PROVIDER_ENV=prd
HEALTHID_PRD_CLIENT_ID=...
HEALTHID_PRD_CLIENT_SECRET=...
HEALTHID_PRD_REDIRECT_URI=https://ath7.link/sso/callback
```

---

## Error Handling

เมื่อเกิดข้อผิดพลาดใน callback ระบบจะ redirect กลับไปยัง login พร้อม error code:

| `?error=` | สาเหตุ |
|---|---|
| `csrf` | CSRF state ไม่ตรงหรือหมดอายุ |
| `state` | ไม่มี `code` หรือ `state` ใน request |
| `token` | Token exchange ล้มเหลว |
| `profile` | ดึงข้อมูลผู้ใช้จาก Health ID ไม่สำเร็จ |
| `no_cid` | ไม่พบ `account_id` ในข้อมูลผู้ใช้ |

> **หมายเหตุ:** หน้า login ปัจจุบัน auto-redirect ทันที ไม่แสดง UI จึงไม่แสดง error message แก่ผู้ใช้

---

## ตัวอย่างการใช้งานในโปรเจคอื่น

```php
<?php
// /athweb/my-app/dashboard.php

require_once $_SERVER['DOCUMENT_ROOT'] . '/athweb/sso/pages/session-check.php';
// ถ้าไม่ได้ login → redirect ไป SSO login อัตโนมัติ
// ถ้า login แล้ว → $sso_user พร้อมใช้

$name    = $sso_user['name_th'];
$mobile  = $sso_user['mobile_number'] ?? '';
$expires = date('H:i', $_SESSION['sso_expires_at']);
?>
<p>ยินดีต้อนรับ <?= htmlspecialchars($name) ?> (หมดอายุ <?= $expires ?> น.)</p>

<!-- ปุ่ม logout ให้ชี้ไปที่ SSO logout -->
<a href="/athweb/sso/?page=logout">ออกจากระบบ</a>
```
