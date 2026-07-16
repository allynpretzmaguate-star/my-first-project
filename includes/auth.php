<?php
/**
 * Authentication & authorization helpers.
 * Include config.php (which includes this file) at the top of any
 * protected page, then call require_login() or require_role('admin').
 */

function is_logged_in(): bool
{
    return !empty($_SESSION['user_id']) || (($_SESSION['role'] ?? '') === 'guest');
}

function current_user(): ?array
{
    if (!is_logged_in()) return null;

    return [
        'id'        => $_SESSION['user_id'] ?? 0,
        'full_name' => $_SESSION['full_name'],
        'username'  => $_SESSION['username'],
        'role'      => $_SESSION['role'],
    ];
}

/** Force login; call at the top of every protected page */
function require_login(): void
{
    if (!is_logged_in()) {
        set_flash('error', 'Please log in to continue.');
        redirect('auth/login.php');
    }

    // Basic session hijack mitigation: bind session to user agent
    if (empty($_SESSION['ua_hash'])) {
        $_SESSION['ua_hash'] = hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? '');
    } elseif (!hash_equals($_SESSION['ua_hash'], hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? ''))) {
        session_unset();
        session_destroy();
        redirect('auth/login.php');
    }
}

/** Restrict a page to one or more roles, e.g. require_role('admin') */
function require_role(string ...$roles): void
{
    require_login();
    if (!in_array($_SESSION['role'], $roles, true)) {
        http_response_code(403);
        die('Access denied. You do not have permission to view this page.');
    }
}

function is_admin(): bool
{
    return is_logged_in() && $_SESSION['role'] === 'admin';
}

function is_guest(): bool
{
    return is_logged_in() && ($_SESSION['role'] ?? '') === 'guest';
}

/** Call this at the top of any page/action that modifies data */
function block_guest(): void
{
    if (is_guest()) {
        set_flash('error', "You're viewing this in guest mode. Please log in with a real account to make changes.");
        redirect('dashboard.php');
    }
}

/**
 * Basic login rate limiting (per session) to slow down brute force.
 * Not a replacement for a proper WAF/fail2ban, but a reasonable
 * app-level safeguard for a portfolio project.
 */
function too_many_attempts(): bool
{
    $attempts = $_SESSION['login_attempts'] ?? 0;
    $lastAttempt = $_SESSION['last_attempt_time'] ?? 0;

    if ($attempts >= 5 && (time() - $lastAttempt) < 300) {
        return true; // locked out for 5 minutes after 5 failed attempts
    }
    if ((time() - $lastAttempt) >= 300) {
        $_SESSION['login_attempts'] = 0;
    }
    return false;
}

function register_failed_attempt(): void
{
    $_SESSION['login_attempts'] = ($_SESSION['login_attempts'] ?? 0) + 1;
    $_SESSION['last_attempt_time'] = time();
}

function reset_login_attempts(): void
{
    unset($_SESSION['login_attempts'], $_SESSION['last_attempt_time']);
}