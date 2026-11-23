<?php
/**
 * Vercel entry point (vercel-php runtime).
 * vercel.json rewrites every request here; we then execute the matching PHP
 * file of the project (so URLs such as /modules/social/newsfeed.php keep working).
 */
$root = dirname(__DIR__);
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$path = rawurldecode($path);

if ($path === '/' || $path === '') {
    $path = '/index.php';
}
if (substr($path, -1) === '/') {
    $path .= 'index.php';
}

$blocked = ['api', 'config', 'includes', 'database', 'vendor', 'node_modules', '.git'];
$first = explode('/', ltrim($path, '/'))[0] ?? '';
$target = realpath($root . $path);

$ok = $target
    && strncmp($target, $root . DIRECTORY_SEPARATOR, strlen($root) + 1) === 0
    && substr($target, -4) === '.php'
    && !in_array($first, $blocked, true)
    && is_file($target);

if (!$ok) {
    http_response_code(404);
    $page_title = 'Page not found';
    require $root . '/includes/header.php';
    echo '<div class="container-sm"><div class="card empty-state"><i class="fa-solid fa-compass empty-icon"></i><h2>Page not found</h2>'
       . '<p>The page you are looking for does not exist or has moved.</p>'
       . '<a class="btn btn-primary" href="' . e(url('index.php')) . '">Back to home</a></div></div>';
    require $root . '/includes/footer.php';
    exit;
}

$rel = substr($target, strlen($root));
$_SERVER['SCRIPT_NAME'] = $_SERVER['PHP_SELF'] = $rel;
$_SERVER['SCRIPT_FILENAME'] = $target;
chdir(dirname($target));
require $target;
