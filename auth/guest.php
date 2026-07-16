<?php
require_once dirname(__DIR__) . '/config/config.php';

if (is_logged_in()) {
    redirect('dashboard.php');
}

session_regenerate_id(true);
$_SESSION['user_id']   = 0;
$_SESSION['full_name'] = 'Guest Viewer';
$_SESSION['username']  = 'guest';
$_SESSION['role']      = 'guest';
$_SESSION['ua_hash']   = hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? '');

set_flash('success', "You're browsing as a guest. Viewing only — editing, saving, deleting, and scanning are disabled.");
redirect('dashboard.php');