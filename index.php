<?php
//Git practice change
//Git practice change 2
// This is the master version
require_once __DIR__ . '/config/config.php';

if (is_logged_in()) {
    redirect('dashboard.php');
} else {
    redirect('auth/login.php');
}