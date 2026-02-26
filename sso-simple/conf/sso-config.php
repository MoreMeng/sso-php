<?php
// Provider ID OAuth2 Settings
// ตั้งค่า OAuth2 สำหรับ Provider ID (provider.id.th)

define('PROVIDER_ID_CLIENT_ID',         getenv('PROVIDER_ID_CLIENT_ID')     ?: 'your_client_id');
define('PROVIDER_ID_CLIENT_SECRET',     getenv('PROVIDER_ID_CLIENT_SECRET') ?: 'your_secret');
define('PROVIDER_ID_AUTHORIZATION_URL', 'https://provider.id.th/oauth/authorize');
define('PROVIDER_ID_TOKEN_URL',         'https://provider.id.th/oauth/token');
define('PROVIDER_ID_USER_INFO_URL',     'https://provider.id.th/api/v1/services/profile?moph_center_token=1&moph_idp_permission=1&position_type=1');

// Redirect URI — ต้องตรงกับที่ลงทะเบียนไว้กับ Provider ID
define('OAUTH_REDIRECT_URI', getenv('OAUTH_REDIRECT_URI') ?: 'https://yourdomain.com/sso-simple/callback.php');

// หลังจาก login สำเร็จ ถ้าไม่มี ?continue= จะ redirect ไปที่นี่
define('DEFAULT_REDIRECT_URL', '/');

// อายุ session (วินาที) — 8 ชั่วโมง
define('SSO_SESSION_LIFETIME', 60 * 60 * 8);
