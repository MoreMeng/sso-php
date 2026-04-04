<?php
/**
 * session-check.php — Auth Guard (Include File)
 * ไฟล์นี้ใช้ require ที่ด้านบนของ protected pages
 *
 * Usage:
 *   require '/path/to/sso-simple/session-check.php';
 *   // After this, $sso_user is available if the user is authenticated.
 *
 * Example:
 *   <?php
 *   require_once '/path/to/sso-simple/session-check.php';
 *   echo 'สวัสดี, ' . $sso_user['name_th'];
 */

require_once __DIR__ . '/../conf/sso-config.php';

if ( session_status() === PHP_SESSION_NONE ) {
    session_set_cookie_params( [
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => isset( $_SERVER['HTTPS'] ),
        'httponly' => true,
        'samesite' => 'Lax'
    ] );
    session_start();
}

// ตรวจสอบว่า login หรือยัง — Check if user is logged in
if ( !isset( $_SESSION['sso_logged_in'] ) || $_SESSION['sso_logged_in'] !== true ) {
    $current_url = ( isset( $_SERVER['HTTPS'] ) ? 'https' : 'http' )
        . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    header( 'Location: ' . BASE_PATH . '/?page=login&continue=' . urlencode( $current_url ) );
    exit;
}

// ตรวจสอบว่า session หมดอายุหรือยัง — Check session expiry
if ( time() > $_SESSION['sso_expires_at'] ) {
    session_unset();
    session_destroy();
    header( 'Location: ' . BASE_PATH . '/?page=login&expired=1' );
    exit;
}

// ผู้ใช้ผ่านการตรวจสอบแล้ว — User is authenticated
$sso_user = $_SESSION['sso_user'];
