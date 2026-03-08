<?php
/**
 * index.php – SSO Main Router
 *
 * ระบบ Single Sign-On (SSO) - โรงพยาบาลอ่างทอง
 * All requests are routed here by .htaccess (mod_rewrite).
 */

session_start();

// Get page parameter (sanitized)
$GET_PAGE = $_GET['page'] ?? '';
$GET_PAGE = preg_replace('/[^a-z0-9\-_]/', '', strtolower($GET_PAGE));
$GET_PAGE = substr($GET_PAGE, 0, 50);

// -------- Page Routing -------- //
switch ($GET_PAGE) {

    case '':
    case 'login':
        require 'pages/login.php';
        break;

    case 'callback':
        require 'pages/callback.php';
        break;

    case 'logout':
        require 'pages/logout.php';
        break;

    case 'profile':
        require 'pages/profile.php';
        break;

    case 'session-check':
        require 'pages/session-check.php';
        break;

    default:
        require 'pages/login.php';
        break;
}
?>