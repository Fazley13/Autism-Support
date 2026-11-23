<?php
/**
 * ParentConnect – Autism Support System
 * Central configuration + database connection.
 *
 * Every value can be overridden with an ENVIRONMENT VARIABLE (this is how you
 * configure the app on Vercel / any cloud host).  When no variable is set, the
 * defaults below match a fresh local XAMPP install.
 *
 *   DB_HOST   DB_PORT   DB_USER   DB_PASS   DB_NAME   DB_SSL   DB_SSL_CA
 *   BASE_URL  APP_ENV   APP_DEBUG
 *
 * (A single DATABASE_URL / MYSQL_URL = mysql://user:pass@host:port/dbname is
 *  understood as well.)
 */

if (defined('APP_CONFIG_LOADED')) {
    return;
}
define('APP_CONFIG_LOADED', true);

function env_value(string $key, $default = null)
{
    $v = getenv($key);
    if ($v === false || $v === '') {
        $v = $_ENV[$key] ?? $_SERVER[$key] ?? null;
    }
    return ($v === null || $v === '') ? $default : $v;
}

/* ------------------------------------------------------------------ */
/* Optional local override file (not committed): config/config.local.php
   return ['DB_HOST'=>'...', ...];                                      */
/* ------------------------------------------------------------------ */
$GLOBALS['_local_cfg'] = [];
if (is_file(__DIR__ . '/config.local.php')) {
    $GLOBALS['_local_cfg'] = (array) include __DIR__ . '/config.local.php';
}
function cfg(string $key, $default = null)
{
    return env_value($key, $GLOBALS['_local_cfg'][$key] ?? $default);
}

/* --------------------------- Database ------------------------------ */
$dbUrl = cfg('DATABASE_URL', cfg('MYSQL_URL'));
$parsed = $dbUrl ? parse_url($dbUrl) : [];

define('DB_SERVER',   $parsed['host'] ?? cfg('DB_HOST', 'localhost'));
define('DB_PORT',     (int) ($parsed['port'] ?? cfg('DB_PORT', 3306)));
define('DB_USERNAME', isset($parsed['user']) ? urldecode($parsed['user']) : cfg('DB_USER', 'root'));
define('DB_PASSWORD', isset($parsed['pass']) ? urldecode($parsed['pass']) : cfg('DB_PASS', ''));
define('DB_NAME',     isset($parsed['path']) ? ltrim($parsed['path'], '/') : cfg('DB_NAME', 'autism_support_system'));
define('DB_SSL',      filter_var(cfg('DB_SSL', false), FILTER_VALIDATE_BOOLEAN));
define('DB_SSL_CA',   cfg('DB_SSL_CA', ''));

/* --------------------------- Site info ----------------------------- */
define('SITE_NAME',    'ParentConnect');
define('SITE_TAGLINE', 'Autism Support System');
define('ROOT_PATH',    dirname(__DIR__));
define('IS_VERCEL',    (bool) (getenv('VERCEL') ?: getenv('VERCEL_ENV')));
define('APP_DEBUG',    filter_var(cfg('APP_DEBUG', false), FILTER_VALIDATE_BOOLEAN));

/**
 * BASE_URL = URL path under which the project lives, without trailing slash.
 *   Vercel / domain root  -> ''
 *   XAMPP /htdocs/Autism-Support -> '/Autism-Support'
 */
(function () {
    $explicit = cfg('BASE_URL');
    if ($explicit !== null) {
        define('BASE_URL', rtrim($explicit, '/'));
        return;
    }
    if (IS_VERCEL) {
        define('BASE_URL', '');
        return;
    }
    // 1) derive from the URL of the running script (works on Windows/XAMPP, any folder name or case)
    $sf = !empty($_SERVER['SCRIPT_FILENAME']) ? realpath($_SERVER['SCRIPT_FILENAME']) : false;
    $sn = $_SERVER['SCRIPT_NAME'] ?? '';
    $root = realpath(ROOT_PATH);
    if ($sf && $root && $sn !== '') {
        $sf = str_replace('\\', '/', $sf); $root = str_replace('\\', '/', $root);
        if (stripos($sf, $root . '/') === 0) {
            $rel = substr($sf, strlen($root));                 // e.g. /auth/login.php
            if (strlen($sn) >= strlen($rel) && strcasecmp(substr($sn, -strlen($rel)), $rel) === 0) {
                define('BASE_URL', rtrim(substr($sn, 0, strlen($sn) - strlen($rel)), '/'));
                return;
            }
        }
    }
    // 2) Fallback: document root comparison
    $docRoot = isset($_SERVER['DOCUMENT_ROOT']) ? realpath($_SERVER['DOCUMENT_ROOT']) : false;
    if ($docRoot && $root && stripos($root, $docRoot) === 0) {
        define('BASE_URL', str_replace('\\', '/', substr($root, strlen($docRoot))));
    } else {
        define('BASE_URL', '');
    }
})();

/* --------------------------- Uploads ------------------------------- */
define('MAX_UPLOAD_BYTES', 3 * 1024 * 1024); // Vercel request limit is 4.5 MB

/* --------------------------- Errors -------------------------------- */
error_reporting(E_ALL);
ini_set('display_errors', APP_DEBUG ? '1' : '0');
ini_set('log_errors', '1');

date_default_timezone_set(cfg('APP_TIMEZONE', 'Asia/Dhaka'));

/* --------------------------- Connect ------------------------------- */
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

function db_connect(): mysqli
{
    $c = mysqli_init();
    $c->options(MYSQLI_OPT_CONNECT_TIMEOUT, 10);
    $flags = 0;
    if (DB_SSL) {
        $flags = MYSQLI_CLIENT_SSL;
        $ca = DB_SSL_CA ?: '';
        if (!$ca) {
            foreach (['/etc/ssl/certs/ca-certificates.crt', '/etc/pki/tls/certs/ca-bundle.crt', '/etc/ssl/cert.pem'] as $p) {
                if (is_file($p)) { $ca = $p; break; }
            }
        }
        if ($ca) {
            $c->ssl_set(null, null, $ca, null, null);
        } else {
            $flags |= MYSQLI_CLIENT_SSL_DONT_VERIFY_SERVER_CERT;
        }
    }
    $c->real_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME, DB_PORT, null, $flags);
    $c->set_charset('utf8mb4');
    $c->query("SET time_zone = '+00:00'");
    return $c;
}

try {
    $conn = db_connect();
} catch (Throwable $e) {
    error_log('DB connection failed: ' . $e->getMessage());
    http_response_code(503);
    $msg = APP_DEBUG ? htmlspecialchars($e->getMessage()) : 'The database is not reachable right now.';
    if (PHP_SAPI !== 'cli') {
        echo '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
           . '<title>Service unavailable</title><body style="font-family:system-ui,sans-serif;max-width:560px;margin:15vh auto;padding:0 20px;color:#1f2937">'
           . '<h1>We will be right back</h1><p>' . $msg . '</p>'
           . '<p style="color:#6b7280;font-size:14px">Site owner: check your DB_HOST / DB_USER / DB_PASS / DB_NAME settings and that the SQL file was imported.</p></body>';
    }
    exit;
}

