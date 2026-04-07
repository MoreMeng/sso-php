# SSO — Provider ID Gateway (โรงพยาบาลอ่างทอง)

> Full documentation: [README.md](../README.md) · OAuth API docs: [docs/](../docs/)

PHP OAuth2 SSO gateway for internal apps (`/athweb/*`). Identity provider: **provider.id.th** (กระทรวงสาธารณสุข). Session shared across same domain.

## Architecture

```
index.php  →  pages/{login,callback,logout,profile}.php
conf/sso-config.php   # OAuth2 credentials + constants
pages/session-check.php  # Auth guard — require in every protected page
logs/access.log          # Append-only login log (auto-created)
```

**Routing:** `?page={login|callback|logout|profile}` — sanitized in `index.php` (lowercase, alphanumeric only, max 50 chars). Unknown pages fall through to `login.php`.

**Session starts** at the top of `index.php` (before routing), with `httponly`, `samesite=Lax`, `secure` (when HTTPS), `lifetime=28800`.

## Environments & Base Path

| `PROVIDER_ENV` | Base path | Endpoint |
|---|---|---|
| `uat` (default) | `/athweb/sso` | `https://uat-provider.id.th` |
| `prd` | `/sso` | `https://provider.id.th` |

`BASE_PATH` constant is auto-set in `sso-config.php`. All internal redirects and `session-check.php` use it. **Do not hardcode `/athweb/sso` strings** — always use `BASE_PATH`.

Override credentials: `PROVIDER_UAT_CLIENT_ID`, `PROVIDER_PRD_CLIENT_ID`, etc. via env vars.

## Key Implementation Details

**CSRF State** — generated in `login.php` as 32-byte hex, stored in `$_SESSION['oauth_state']` + `$_SESSION['oauth_state_time']`. Validated in `callback.php` with `hash_equals()`, unset immediately (single-use, 10-minute TTL).

**Token Exchange** — HTTP Basic Auth (`Authorization: Basic base64(id:secret)`). Response may be wrapped: check for `$data['data']` key before unwrapping.

**User Profile Fetch** — requires three headers: `Authorization: Bearer {token}`, `client-id: ...`, `secret-key: ...`.

**safeRedirect()** in `callback.php` — allows only relative paths or same-host URLs. Silently falls back to `BASE_PATH . '/?page=profile'` for untrusted hosts (no error logged).

**Continue URL** — stored in `$_SESSION['sso_continue_url']` (not in query string across hops). Special value `'profile'` → redirects to profile page. Unset after use.

**Session fixation** — `session_regenerate_id(true)` is called in `callback.php` after successful login only.

## Auth Guard Usage

```php
<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/athweb/sso/pages/session-check.php';
// $sso_user is now available — redirect already happened if not logged in

echo htmlspecialchars($sso_user['name_th']);
$hcode = $sso_user['organizations'][0]['hcode'] ?? '';  // may be empty array!
```

`session-check.php` steps: start session → legacy fallback → auth check → expiry check → expose `$sso_user`.

**Legacy fallback:** if `$_SESSION['provider']` exists (from older `/member` system), converts to SSO session without re-verifying OAuth. This is intentional trust delegation.

## Gotchas

- **`organizations` may be `[]`** — always use `??` when accessing `$sso_user['organizations'][0]`.
- **HTTPS detection** uses `isset($_SERVER['HTTPS'])` — fails behind reverse proxy without `X-Forwarded-Proto`. Relevant for `secure` cookie flag.
- **Logout is local only** — OAuth access token at Provider ID side remains valid. Only local session is destroyed.
- **Login page has no UI** — `login.php` auto-redirects immediately; error codes in `?error=` param are never shown to users (they just see a redirect loop or re-login).
- **Access log** uses `FILE_APPEND | LOCK_EX`. Format: `YYYY-MM-DD HH:MM:SS | hash_cid | name_th | ip`.

## Security Non-Negotiables

Every user-facing redirect must go through `safeRedirect()`. Never use `header('Location: ' . $_GET[...])` directly. Output user data with `htmlspecialchars()`. The CSRF `state` must be validated with `hash_equals()` before any token exchange.
ใช้ **Provider ID** (provider.id.th) ของกระทรวงสาธารณสุขเป็น OAuth2 Identity Provider
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
| `GET /athweb/sso/?page=login` | เริ่มต้น login — redirect ไปยัง Provider ID OAuth |
| `GET /athweb/sso/?page=login&continue={url}` | Login พร้อมระบุ URL ต้นทาง (ref path) |
| `GET /athweb/sso/?page=callback` | รับ OAuth code จาก Provider ID (callback URL) |
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
  Provider ID OAuth2 Authorization URL
           │
           ▼  (ผู้ใช้ login ที่ Provider ID)
  redirect → /athweb/sso/?page=callback?code=...&state=...
           │
           ├─ Validate CSRF state
           ├─ Exchange code → access_token
           ├─ Fetch user profile จาก Provider ID API
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
    'provider_id'   => '...',   // Provider ID unique ID
    'account_id'    => '...',
    'hash_cid'      => '...',   // Hashed citizen ID

    // Security level
    'ial_level'     => 2,       // Identity Assurance Level

    // Personal info
    'name_th'       => 'ชื่อ นามสกุล',
    'name_eng'      => 'Name Surname',
    'email'         => '...',

    // Organizations (อาจมีมากกว่า 1 หน่วยงาน)
    'organizations' => [
        [
            'hcode'       => '10669',
            'hname_th'    => 'โรงพยาบาลอ่างทอง',
            'hname_eng'   => 'Angthong Hospital',
            'position'    => 'แพทย์',
            'position_id' => '...',
            'business_id' => '...',
        ],
        // ...
    ],

    // Metadata
    'login_at'      => '2026-03-24 08:00:00',
    'login_ip'      => '192.168.1.1',
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
echo 'หน่วยงาน: ' . ($sso_user['organizations'][0]['hname_th'] ?? '-');
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
│   ├── login.php          # สร้าง CSRF state + auto-redirect ไป Provider ID
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

| ค่า | Provider ID Endpoint |
|---|---|
| `uat` (default) | `https://uat-provider.id.th` |
| `prd` | `https://provider.id.th` |

สามารถ override credentials ผ่าน env vars:

```env
PROVIDER_ENV=prd
PROVIDER_PRD_CLIENT_ID=...
PROVIDER_PRD_CLIENT_SECRET=...
PROVIDER_PRD_REDIRECT_URI=https://ath7.link/sso/callback
```

---

## Error Handling

เมื่อเกิดข้อผิดพลาดใน callback ระบบจะ redirect กลับไปยัง login พร้อม error code:

| `?error=` | สาเหตุ |
|---|---|
| `csrf` | CSRF state ไม่ตรงหรือหมดอายุ |
| `state` | ไม่มี `code` หรือ `state` ใน request |
| `token` | Token exchange ล้มเหลว |
| `profile` | ดึงข้อมูลผู้ใช้จาก Provider ID ไม่สำเร็จ |
| `no_cid` | ไม่พบ `provider_id` ในข้อมูลผู้ใช้ |

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
$hcode   = $sso_user['organizations'][0]['hcode'] ?? '';
$expires = date('H:i', $_SESSION['sso_expires_at']);
?>
<p>ยินดีต้อนรับ <?= htmlspecialchars($name) ?> (หมดอายุ <?= $expires ?> น.)</p>

<!-- ปุ่ม logout ให้ชี้ไปที่ SSO logout -->
<a href="/athweb/sso/?page=logout">ออกจากระบบ</a>
```
