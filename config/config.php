<?php
/**
 * Application-wide configuration
 */

// --- Base paths / URL ---
define('APP_NAME', 'AI-Powered Client Information Encoding System');
define('BASE_PATH', dirname(__DIR__));
define('UPLOAD_DIR', BASE_PATH . '/uploads/documents/');

// Adjust this if your project folder is not at the web root
define('BASE_URL', '/ScannerEncoding/');

// --- Upload rules ---
define('MAX_UPLOAD_SIZE', 8 * 1024 * 1024); // 8 MB
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/webp']);

// --- Tesseract OCR ---
// Windows/XAMPP: after installing Tesseract-OCR (UB Mannheim build),
// this is the typical install path. Adjust if yours differs.
// macOS (brew install tesseract): '/usr/local/bin/tesseract' or '/opt/homebrew/bin/tesseract'
// Linux (apt install tesseract-ocr): '/usr/bin/tesseract'
if (stripos(PHP_OS, 'WIN') === 0) {
    define('TESSERACT_PATH', 'C:\\Program Files\\Tesseract-OCR\\tesseract.exe');
} else {
    define('TESSERACT_PATH', '/usr/bin/tesseract');
}

// --- Session security ---
ini_set('session.cookie_httponly', 1);
ini_set('session.use_strict_mode', 1);
// Uncomment the line below once you're serving the app over HTTPS
// ini_set('session.cookie_secure', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- Error display ---
// Set to 0 / suppress before deploying anywhere public
error_reporting(E_ALL);
ini_set('display_errors', '1');

// --- Timezone ---
date_default_timezone_set('Asia/Manila');

// --- Autoload core includes ---
require_once BASE_PATH . '/config/database.php';
require_once BASE_PATH . '/includes/functions.php';
require_once BASE_PATH . '/includes/auth.php';