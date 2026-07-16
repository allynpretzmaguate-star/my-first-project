<?php
require_once dirname(__DIR__) . '/config/config.php';

if (is_logged_in()) {
    log_activity($_SESSION['user_id'], 'LOGOUT', 'User logged out');
}

$_SESSION = [];
session_unset();
session_destroy();

session_start();
set_flash('success', 'You have been logged out successfully.');
redirect('auth/login.php');