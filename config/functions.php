<?php
/**
 * Shared helper functions.  Loaded by includes/bootstrap.php.
 * Conventions used across the whole project:
 *   - always escape output with e()
 *   - always use prepared statements via db_all / db_one / db_exec
 *   - every state-changing request (POST) carries a CSRF token
 */

if (defined('APP_FUNCTIONS_LOADED')) {
    return;
}
define('APP_FUNCTIONS_LOADED', true);

/* ============================ Output ============================== */

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Absolute URL path inside the app, e.g. url('modules/social/newsfeed.php') */
function url(string $path = ''): string
{
    return BASE_URL . '/' . ltrim($path, '/');
}

function asset(string $path): string
{
    return url('assets/' . ltrim($path, '/'));
}

/* ============================ Redirect ============================ */

function redirect(string $path): void
{
    // "/abs/path" and full URLs are used as-is; "modules/x.php" is relative to the app root
    $target = (preg_match('#^(https?:)?//#', $path) || ($path !== '' && $path[0] === '/')) ? $path : url($path);
    header('Location: ' . $target);
    exit;
}

/** Only allow redirects that stay inside this site. */
function safe_next(?string $next, string $fallback = 'modules/social/newsfeed.php'): string
{
    if ($next && $next[0] === '/' && substr($next, 0, 2) !== '//' && strpos($next, '\\') === false) {
        // only follow the link if it points to a real page of this app
        $p = rawurldecode((string) parse_url($next, PHP_URL_PATH));
        if (BASE_URL !== '' && strncasecmp($p, BASE_URL . '/', strlen(BASE_URL) + 1) === 0) {
            $p = substr($p, strlen(BASE_URL));
        }
        $file = realpath(ROOT_PATH . $p);
        if ($file && is_file($file) && substr($file, -4) === '.php' && strncmp($file, realpath(ROOT_PATH), strlen(realpath(ROOT_PATH))) === 0) {
            return $next;
        }
    }
    return url($fallback);
}

/* ============================ Auth ================================ */

function isLoggedIn(): bool
{
    return !empty($_SESSION['user_id']);
}

function current_user_id(): int
{
    return (int) ($_SESSION['user_id'] ?? 0);
}

/** Logged-in user row (cached per request). */
function current_user(): ?array
{
    static $cache = false;
    if ($cache === false) {
        $cache = isLoggedIn()
            ? db_one('SELECT user_id, username, email, first_name, last_name, profile_photo FROM user WHERE user_id = ?', 'i', current_user_id())
            : null;
        if (isLoggedIn() && !$cache) {          // account was deleted
            session_destroy();
        }
    }
    return $cache;
}

function require_login(): void
{
    if (!isLoggedIn() || !current_user()) {
        if (is_ajax_request()) {
            json_out(['success' => false, 'message' => 'Please log in again.', 'login' => url('auth/login.php')], 401);
        }
        $next = $_SERVER['REQUEST_URI'] ?? '';
        redirect('auth/login.php' . ($next ? '?next=' . urlencode($next) : ''));
    }
}

function is_ajax_request(): bool
{
    return (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest')
        || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);
}

function display_name(array $u): string
{
    $n = trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? ''));
    return $n !== '' ? $n : ($u['username'] ?? 'User');
}

/* ============================ CSRF ================================ */

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(24));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

/** Validates POST token (field "csrf") or header X-CSRF-Token. Dies with 419 on failure. */
function csrf_check(): void
{
    $sent = $_POST['csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!is_string($sent) || !hash_equals(csrf_token(), $sent)) {
        if (is_ajax_request()) {
            json_out(['success' => false, 'message' => 'Your session expired. Please refresh the page.'], 419);
        }
        http_response_code(419);
        render_error_page('Session expired', 'Your form session expired. Please go back, refresh the page and try again.');
    }
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';
}

/** Read a JSON request body (for fetch() calls). */
function json_input(): array
{
    $raw = file_get_contents('php://input');
    $d = json_decode($raw ?: '', true);
    return is_array($d) ? $d : [];
}

function json_out(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/** Input helpers */
function post_str(string $key, int $max = 0): string
{
    $v = trim((string) ($_POST[$key] ?? ''));
    return $max > 0 ? mb_substr($v, 0, $max) : $v;
}
function get_int(string $key, int $default = 0): int
{
    return isset($_GET[$key]) ? (int) $_GET[$key] : $default;
}
function post_int(string $key, int $default = 0): int
{
    return isset($_POST[$key]) ? (int) $_POST[$key] : $default;
}

/* ============================ Flash =============================== */

function flash(string $message, string $type = 'success'): void
{
    $_SESSION['flash'][] = ['m' => $message, 't' => $type];
}

/** Returns & clears queued flash messages as ready-to-print HTML. */
function flash_html(): string
{
    $out = '';
    foreach ($_SESSION['flash'] ?? [] as $f) {
        $out .= alert_html($f['m'], $f['t']);
    }
    unset($_SESSION['flash']);
    return $out;
}

function alert_html(string $message, string $type = 'success'): string
{
    $icons = ['success' => 'circle-check', 'error' => 'circle-exclamation', 'danger' => 'circle-exclamation', 'warning' => 'triangle-exclamation', 'info' => 'circle-info'];
    $type = $type === 'error' ? 'danger' : $type;
    return '<div class="alert alert-' . e($type) . '" role="alert"><i class="fa-solid fa-' . ($icons[$type] ?? 'circle-info') . '"></i><span>' . e($message) . '</span></div>';
}

/* legacy name kept for old snippets */
function showAlert($message, $type = 'success')
{
    return alert_html((string) $message, (string) $type);
}

/* ============================ Database ============================ */

/** Run a prepared statement. $types like 'iss'. Returns mysqli_stmt (already executed). */
function db_run(string $sql, string $types = '', ...$params): mysqli_stmt
{
    global $conn;
    $stmt = $conn->prepare($sql);
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    return $stmt;
}

/** All rows as assoc arrays. */
function db_all(string $sql, string $types = '', ...$params): array
{
    $stmt = db_run($sql, $types, ...$params);
    $res = $stmt->get_result();
    $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();
    return $rows;
}

/** First row or null. */
function db_one(string $sql, string $types = '', ...$params): ?array
{
    $rows = db_all($sql, $types, ...$params);
    return $rows[0] ?? null;
}

/** First column of first row. */
function db_value(string $sql, string $types = '', ...$params)
{
    $row = db_one($sql, $types, ...$params);
    return $row ? reset($row) : null;
}

/** INSERT/UPDATE/DELETE. Returns affected rows. Last insert id: db_insert_id() */
function db_exec(string $sql, string $types = '', ...$params): int
{
    $stmt = db_run($sql, $types, ...$params);
    $n = $stmt->affected_rows;
    $stmt->close();
    return $n;
}

function db_insert_id(): int
{
    global $conn;
    return (int) $conn->insert_id;
}

/* ============================ Time ================================ */

/** Convert a UTC timestamp string from the DB to a DateTime in the app timezone. */
function to_local($ts): ?DateTimeImmutable
{
    if (!$ts) return null;
    try {
        return (new DateTimeImmutable((string) $ts, new DateTimeZone('UTC')))->setTimezone(new DateTimeZone(date_default_timezone_get()));
    } catch (Throwable $e) {
        return null;
    }
}

function fmt_dt($ts, string $format = 'M j, Y · g:i A'): string
{
    $d = to_local($ts);
    return $d ? $d->format($format) : '';
}

function time_ago($ts): string
{
    $d = to_local($ts);
    if (!$d) return '';
    $diff = time() - $d->getTimestamp();
    if ($diff < 45) return 'just now';
    if ($diff < 3600) return floor($diff / 60) . ' min ago';
    if ($diff < 86400) return floor($diff / 3600) . ' h ago';
    if ($diff < 7 * 86400) return floor($diff / 86400) . ' d ago';
    return $d->format('M j, Y');
}

/* ============================ Media =============================== */
/* Uploaded images are stored in the `media` table (works on Vercel, which
   has a read-only filesystem).  Columns that used to hold a file name now
   hold "media:<id>".  Old values (plain file names) keep working.        */

/**
 * Validate + store an uploaded image. Returns "media:ID" or null if no file.
 * Throws RuntimeException with a user-friendly message on invalid files.
 */
function save_image_upload(string $field, int $maxDim = 1600): ?string
{
    if (empty($_FILES[$field]) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    $f = $_FILES[$field];
    if ($f['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException($f['error'] === UPLOAD_ERR_INI_SIZE || $f['error'] === UPLOAD_ERR_FORM_SIZE
            ? 'That image is too large (max 3 MB).' : 'The upload failed. Please try again.');
    }
    if ($f['size'] > MAX_UPLOAD_BYTES) {
        throw new RuntimeException('That image is too large (max 3 MB).');
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
    if (!isset($allowed[$mime])) {
        throw new RuntimeException('Only JPG, PNG, GIF or WebP images are allowed.');
    }
    $data = file_get_contents($f['tmp_name']);

    // Always keep stored images small: XAMPP's MySQL often has max_allowed_packet = 1 MB, and a bigger
    // INSERT fails with "MySQL server has gone away". Down-scale / re-compress until it fits.
    $limit = 700 * 1024;
    try {
        $mp = (int) db_value('SELECT @@max_allowed_packet');
        if ($mp > 0) $limit = min($limit, (int) ($mp * 0.7));
    } catch (Throwable $e) {}
    $needsWork = strlen($data) > $limit;
    if (function_exists('imagecreatefromstring') && $mime !== 'image/gif') {
        $img = @imagecreatefromstring($data);
        if ($img) {
            $w = imagesx($img); $h = imagesy($img);
            if ($w > $maxDim || $h > $maxDim || $needsWork) {
                $dim = min($maxDim, max($w, $h)); $q = 84; $out = $data;
                for ($i = 0; $i < 8; $i++) {
                    $scale = min(1, $dim / max($w, $h));
                    $nw = max(1, (int) round($w * $scale)); $nh = max(1, (int) round($h * $scale));
                    $dst = imagecreatetruecolor($nw, $nh);
                    imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
                    imagecopyresampled($dst, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
                    ob_start(); imagejpeg($dst, null, $q); $out = ob_get_clean();
                    imagedestroy($dst);
                    if (strlen($out) <= $limit) break;
                    $q = max(55, $q - 8); $dim = (int) ($dim * 0.8);
                }
                $data = $out; $mime = 'image/jpeg';
            }
            imagedestroy($img);
        }
    }
    if (strlen($data) > $limit && strlen($data) > 900 * 1024) {
        throw new RuntimeException('That image is too large for the database. Please pick a smaller one.');
    }

    db_exec('INSERT INTO media (mime, size, data) VALUES (?, ?, ?)', 'sis', $mime, strlen($data), $data);
    return 'media:' . db_insert_id();
}

/** Remove a stored "media:ID" value (ignored for legacy file names). */
function delete_media(?string $stored): void
{
    if ($stored && strpos($stored, 'media:') === 0) {
        db_exec('DELETE FROM media WHERE id = ?', 'i', (int) substr($stored, 6));
    }
}

/**
 * URL for a stored image value.
 *   $kind: 'profile' | 'cover' | 'post' | 'file'  (decides legacy folder / default image)
 */
function media_url(?string $stored, string $kind = 'post'): string
{
    $stored = trim((string) $stored);
    if (strpos($stored, 'media:') === 0) {
        return url('media.php?id=' . (int) substr($stored, 6));
    }
    $isDefault = $stored === '' || in_array($stored, ['default-profile.png', 'default-cover.png', 'default-profile.jpg', 'default-cover.jpg'], true);
    if ($kind === 'profile') {
        return $isDefault ? asset('images/default-profile.svg') : url('uploads/profile_photos/' . rawurlencode($stored));
    }
    if ($kind === 'cover') {
        return $isDefault ? asset('images/default-cover.svg') : url('uploads/cover_photos/' . rawurlencode($stored));
    }
    return $stored === '' ? '' : url('uploads/' . implode('/', array_map('rawurlencode', explode('/', ltrim($stored, '/')))));
}

function avatar_url(?array $user): string
{
    return media_url($user['profile_photo'] ?? '', 'profile');
}

/* ============================ Notifications ======================= */

function notify(int $userId, string $message, ?string $link = null): void
{
    if ($userId <= 0 || $userId === current_user_id()) return;   // don't notify yourself
    try {
        db_exec('INSERT INTO notifications (user_id, message, link) VALUES (?, ?, ?)', 'iss', $userId, mb_substr($message, 0, 500), $link);
    } catch (Throwable $e) {
        error_log('notify failed: ' . $e->getMessage());
    }
}

function unread_notifications(): int
{
    return isLoggedIn() ? (int) db_value('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND seen = 0', 'i', current_user_id()) : 0;
}

function unread_messages(): int
{
    if (!isLoggedIn()) return 0;
    try {
        return (int) db_value('SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND is_read = 0', 'i', current_user_id());
    } catch (Throwable $e) {
        return 0;
    }
}

/* ============================ Misc ================================ */

function truncate(string $s, int $len = 140): string
{
    return mb_strlen($s) > $len ? mb_substr($s, 0, $len - 1) . '…' : $s;
}

/** Whitelist helper: returns $value if it is one of $allowed, else $default. */
function pick(string $value, array $allowed, string $default): string
{
    return in_array($value, $allowed, true) ? $value : $default;
}

/** Friendly full-page error (uses the site layout when possible). */
function render_error_page(string $title, string $message): void
{
    $page_title = $title;
    $active = '';
    if (!headers_sent()) {
        header('Content-Type: text/html; charset=utf-8');
    }
    include ROOT_PATH . '/includes/header.php';
    echo '<div class="card empty-state" style="max-width:560px;margin:3rem auto"><i class="fa-solid fa-triangle-exclamation empty-icon"></i><h2>' . e($title) . '</h2><p>' . e($message) . '</p>'
       . '<p><a class="btn btn-primary" href="' . e(url('index.php')) . '">Back to home</a></p></div>';
    include ROOT_PATH . '/includes/footer.php';
    exit;
}

/* ---- legacy aliases used by older snippets ---- */
function sanitizeInput($data)
{
    return trim((string) $data);        // NOTE: use prepared statements, not escaping
}


/* ============================ Self-healing schema ================= */
/** Creates the v2 tables/columns if the SQL upgrade was not imported. Runs once per session. */
function ensure_schema(): void
{
    global $conn;
    if (!empty($_SESSION['schema_ok'])) return;
    try {
        $conn->query("CREATE TABLE IF NOT EXISTS media (id bigint(20) NOT NULL AUTO_INCREMENT, mime varchar(60) NOT NULL, size int(11) NOT NULL DEFAULT 0, data longblob NOT NULL, created_at timestamp NOT NULL DEFAULT current_timestamp(), PRIMARY KEY (id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $conn->query("CREATE TABLE IF NOT EXISTS messages (id int(11) NOT NULL AUTO_INCREMENT, sender_id int(11) NOT NULL, receiver_id int(11) NOT NULL, body text NOT NULL, is_read tinyint(1) NOT NULL DEFAULT 0, created_at timestamp NOT NULL DEFAULT current_timestamp(), PRIMARY KEY (id), KEY pair (sender_id, receiver_id, id), KEY inbox (receiver_id, is_read)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        if ($conn->query("SHOW COLUMNS FROM notifications LIKE 'link'")->num_rows === 0) {
            $conn->query("ALTER TABLE notifications ADD COLUMN link varchar(255) DEFAULT NULL AFTER message");
        }
        if ($conn->query("SHOW INDEX FROM event_participants WHERE Key_name = 'uniq_event_user'")->num_rows === 0) {
            $conn->query("DELETE ep1 FROM event_participants ep1 JOIN event_participants ep2 ON ep1.event_id = ep2.event_id AND ep1.participant_id = ep2.participant_id AND ep1.event_participant_id > ep2.event_participant_id");
            $conn->query("ALTER TABLE event_participants ADD UNIQUE KEY uniq_event_user (event_id, participant_id)");
        }
        $conn->query("UPDATE events SET name = title WHERE (name = '' OR name IS NULL) AND title IS NOT NULL");
        $conn->query("UPDATE post SET privacy = 'public' WHERE privacy = ''");
        $_SESSION['schema_ok'] = 1;
    } catch (Throwable $e) {
        error_log('ensure_schema: ' . $e->getMessage());
    }
}
