<?php
// Provider ID OAuth2 Settings (UAT / PRD)
// Usage: set PROVIDER_ENV to 'uat' or 'prd' (via env var) to select environment.
// Defaults to 'uat' to avoid accidental production calls.

define( 'PROVIDER_ENV', getenv( 'PROVIDER_ENV' ) ?: 'uat' ); // 'uat' or 'prd'

// Per-environment configuration. Values can be overridden with environment variables.
$providers = [
    'uat' => [
        'auth_url'      => 'https://uat-provider.id.th/v1/oauth2/authorize',
        'token_url'     => 'https://uat-provider.id.th/oauth/token',
        'user_info_url' => 'https://uat-provider.id.th/api/v1/services/profile?moph_center_token=1&moph_idp_permission=1&position_type=1',
        'client_id'     => getenv( 'PROVIDER_UAT_CLIENT_ID' ) ?: '8248c4a6-955c-424d-9ed4-f6edcb417d71',
        'client_secret' => getenv( 'PROVIDER_UAT_CLIENT_SECRET' ) ?: 'RfaLO1UdOCYJMQ5QMG6asvHbGTiyeeAk',
        'redirect_uri'  => getenv( 'PROVIDER_UAT_REDIRECT_URI' ) ?: 'http://localhost/athweb/sso/callback'
    ],
    'prd' => [
        'auth_url'            => 'https://provider.id.th/v1/oauth2/authorize',
        'token_url'           => 'https://provider.id.th/oauth/token',
        'user_info_url'       => 'https://provider.id.th/api/v1/services/profile?moph_center_token=1&moph_idp_permission=1&position_type=1',
        'client_id'           => getenv( 'PROVIDER_PRD_CLIENT_ID' ) ?: 'aa6c696a-1d76-4a4c-91b6-5f8d6802b0f5',
        'client_secret'       => getenv( 'PROVIDER_PRD_CLIENT_SECRET' ) ?: 'A68s5HYbDBCPj981BFU7asvVslkjqxhn',
        // Primary redirect for PRD; additional valid redirect URIs can be listed below.
        'redirect_uri'        => getenv( 'PROVIDER_PRD_REDIRECT_URI' ) ?: 'https://ath7.link/sso/callback',
        'redirect_alternates' => [
            'http://athweb.athospit.net/sso/callback'
        ]
    ]
];

if ( !isset( $providers[PROVIDER_ENV] ) ) {
    throw new RuntimeException( 'Invalid PROVIDER_ENV: ' . PROVIDER_ENV );
}

$cfg = $providers[PROVIDER_ENV];

// Export constants used by the rest of the app (keeps legacy names)
define( 'PROVIDER_ID_AUTHORIZATION_URL', $cfg['auth_url'] );
define( 'PROVIDER_ID_TOKEN_URL', $cfg['token_url'] );
define( 'PROVIDER_ID_USER_INFO_URL', $cfg['user_info_url'] );
define( 'PROVIDER_ID_CLIENT_ID', $cfg['client_id'] );
define( 'PROVIDER_ID_CLIENT_SECRET', $cfg['client_secret'] );
define( 'OAUTH_REDIRECT_URI', $cfg['redirect_uri'] );

// List of PRD alternate redirect URIs for reference/validation (may be empty)
define( 'PROVIDER_PRD_REDIRECT_ALTERNATES', isset( $providers['prd']['redirect_alternates'] ) ? $providers['prd']['redirect_alternates'] : [] );

// After successful login, if no ?continue= is provided, redirect here
define( 'DEFAULT_REDIRECT_URL', '/' );

// Session lifetime (seconds) — 8 hours
define( 'SSO_SESSION_LIFETIME', 60 * 60 * 8 );

/*
Guide / Notes:
- To switch environment, set PROVIDER_ENV=uat or PROVIDER_ENV=prd in your environment.
- You can override individual values with env vars:
- PROVIDER_UAT_CLIENT_ID, PROVIDER_UAT_CLIENT_SECRET, PROVIDER_UAT_REDIRECT_URI
- PROVIDER_PRD_CLIENT_ID, PROVIDER_PRD_CLIENT_SECRET, PROVIDER_PRD_REDIRECT_URI
- Default redirect URIs:
- UAT:  http://localhost/athweb/sso/callback
- PRD:  https://ath7.link/sso/callback  (alternate: http://athweb.athospit.net/sso/callback)
 */
