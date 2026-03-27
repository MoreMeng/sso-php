<?php
// Health ID (หมอพร้อมดิจิทัลไอดี / moph.id.th) OAuth2 Settings (UAT / PRD)
// Usage: set PROVIDER_ENV to 'uat' or 'prd' (via env var) to select environment.
// Defaults to 'uat' to avoid accidental production calls.

define( 'PROVIDER_ENV', getenv( 'PROVIDER_ENV' ) ?: 'uat' ); // 'uat' or 'prd'

// Determine BASE_PATH based on environment
// Development (uat): /athweb/sso/
// Production (prd): /sso/
$base_path = ( PROVIDER_ENV === 'prd' ) ? '/sso' : '/athweb/sso';
define( 'BASE_PATH', $base_path );

// Per-environment configuration. Values can be overridden with environment variables.
$providers = [
    'uat' => [
        'auth_url'      => 'https://uat-moph.id.th/oauth/redirect',
        'token_url'     => 'https://uat-moph.id.th/api/v1/token',
        'user_info_url' => 'https://uat-moph.id.th/go-api/v1/profile',
        'client_id'     => getenv( 'HEALTHID_UAT_CLIENT_ID' ) ?: '01973f8e-3de2-7b2c-aba8-a7d7e7cca28b',
        'client_secret' => getenv( 'HEALTHID_UAT_CLIENT_SECRET' ) ?: 'b45752d46d5c76a4519e145c4662bd66792708c7',
        'redirect_uri'  => getenv( 'HEALTHID_UAT_REDIRECT_URI' ) ?: 'http://localhost/athweb/sso/callback'
    ],
    'prd' => [
        'auth_url'            => 'https://moph.id.th/oauth/redirect',
        'token_url'           => 'https://moph.id.th/api/v1/token',
        'user_info_url'       => 'https://moph.id.th/go-api/v1/profile',
        'client_id'           => getenv( 'HEALTHID_PRD_CLIENT_ID' ) ?: '01973f8e-6a28-7617-b426-6ff615d81497',
        'client_secret'       => getenv( 'HEALTHID_PRD_CLIENT_SECRET' ) ?: 'ef819c324e00c29178721646f6c4dd36ebfe1960',
        // Primary redirect for PRD; additional valid redirect URIs can be listed below.
        'redirect_uri'        => getenv( 'HEALTHID_PRD_REDIRECT_URI' ) ?: 'https://ath7.link/sso/callback',
        'redirect_alternates' => [
            'http://athweb.athospit.net/sso/callback'
        ]
    ]
];

if ( !isset( $providers[PROVIDER_ENV] ) ) {
    throw new RuntimeException( 'Invalid PROVIDER_ENV: ' . PROVIDER_ENV );
}

$cfg = $providers[PROVIDER_ENV];

// Export constants used by the rest of the app
define( 'PROVIDER_ID_AUTHORIZATION_URL', $cfg['auth_url'] );
define( 'PROVIDER_ID_TOKEN_URL', $cfg['token_url'] );
define( 'PROVIDER_ID_USER_INFO_URL', $cfg['user_info_url'] );
define( 'PROVIDER_ID_CLIENT_ID', $cfg['client_id'] );
define( 'PROVIDER_ID_CLIENT_SECRET', $cfg['client_secret'] );
define( 'OAUTH_REDIRECT_URI', $cfg['redirect_uri'] );

// List of PRD alternate redirect URIs for reference/validation (may be empty)
define( 'PROVIDER_PRD_REDIRECT_ALTERNATES', isset( $providers['prd']['redirect_alternates'] ) ? $providers['prd']['redirect_alternates'] : [] );

// After successful login, if no ?continue= is provided, redirect here
define( 'DEFAULT_REDIRECT_URL', BASE_PATH . '/?page=profile' );

// Session lifetime (seconds) — 8 hours
define( 'SSO_SESSION_LIFETIME', 60 * 60 * 8 );

/*
Guide / Notes:
- Identity Provider: Health ID (หมอพร้อมดิจิทัลไอดี) — https://moph.id.th
- To switch environment, set PROVIDER_ENV=uat or PROVIDER_ENV=prd in your environment.
- You can override individual values with env vars:
  - HEALTHID_UAT_CLIENT_ID, HEALTHID_UAT_CLIENT_SECRET, HEALTHID_UAT_REDIRECT_URI
  - HEALTHID_PRD_CLIENT_ID, HEALTHID_PRD_CLIENT_SECRET, HEALTHID_PRD_REDIRECT_URI
- Default redirect URIs:
  - UAT: http://localhost/athweb/sso/callback
  - PRD: https://ath7.link/sso/callback  (alternate: http://athweb.athospit.net/sso/callback)
- Service: ATH intranet — โรงพยาบาลอ่างทอง [10689]
 */
