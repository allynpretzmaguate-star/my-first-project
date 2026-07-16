<?php
/**
 * Shared helper functions used across the app.
 */

/** Escape output for safe HTML rendering */
function h(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

/** Trim + strip tags on user input (defense in depth alongside prepared statements) */
function clean(?string $value): string
{
    return trim(strip_tags($value ?? ''));
}

/** Generate (and cache in session) a CSRF token */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Render a hidden CSRF input for forms */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '">';
}

/** Validate a submitted CSRF token, die with 403 on mismatch */
function verify_csrf(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (empty($token) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(403);
        die('Invalid or expired form submission (CSRF check failed). Please go back and try again.');
    }
}

/** One-time flash messages (success/error banners after redirect) */
function set_flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function get_flashes(): array
{
    $flashes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $flashes;
}

/** Redirect helper */
function redirect(string $path): void
{
    header('Location: ' . BASE_URL . ltrim($path, '/'));
    exit;
}

/** Log an activity row for the audit trail */
function log_activity(?int $userId, string $action, string $description = ''): void
{
    try {
        $db = Database::getConnection();
        $stmt = $db->prepare(
            'INSERT INTO activity_logs (user_id, action, description, ip_address) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$userId, $action, $description, $_SERVER['REMOTE_ADDR'] ?? null]);
    } catch (Throwable $e) {
        error_log('Failed to write activity log: ' . $e->getMessage());
    }
}

/** Format a DB datetime nicely for display */
function format_date(?string $datetime, string $format = 'M d, Y h:i A'): string
{
    if (empty($datetime)) return '—';
    return date($format, strtotime($datetime));
}

/** Simple pagination helper: returns [offset, limit, currentPage] */
function paginate(int $totalRows, int $perPage = 15): array
{
    $page = max(1, (int)($_GET['page'] ?? 1));
    $offset = ($page - 1) * $perPage;
    $totalPages = max(1, (int)ceil($totalRows / $perPage));
    return [$offset, $perPage, $page, $totalPages];
}