<?php
/**
 * Include this at the very top of EVERY page / endpoint:
 *
 *     require_once __DIR__ . '/../../includes/bootstrap.php';   // adjust ../ depth
 *
 * It loads config + DB, helper functions, starts the (DB-backed) session,
 * sends security headers and installs a friendly error handler.
 */

if (defined('APP_BOOTSTRAPPED')) {
    return;
}
define('APP_BOOTSTRAPPED', true);

require_once __DIR__ . '/../config/config.php';     // defines constants + $conn
require_once __DIR__ . '/../config/functions.php';  // helpers
require_once __DIR__ . '/session.php';

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');

app_session_start($conn);
ensure_schema();

set_exception_handler(function (Throwable $e) {
    error_log('Unhandled: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    if (function_exists('is_ajax_request') && is_ajax_request()) {
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode(['success' => false, 'message' => APP_DEBUG ? $e->getMessage() : 'Something went wrong. Please try again.']);
        return;
    }
    http_response_code(500);
    if (headers_sent()) {
        echo '<p>Something went wrong.</p>';
        return;
    }
    $detail = APP_DEBUG ? $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')' : 'Something went wrong on our side. Please try again in a moment.';
    ob_start();
    try {
        render_error_page('Oops – something went wrong', $detail);
    } catch (Throwable $e2) {
        ob_end_clean();
        echo '<!doctype html><meta charset="utf-8"><title>Error</title><p style="font-family:sans-serif;margin:3rem">Something went wrong. Please try again.</p>';
    }
});
