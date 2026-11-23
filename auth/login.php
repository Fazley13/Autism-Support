<?php
require_once __DIR__ . '/../includes/bootstrap.php';

if (isLoggedIn() && current_user()) {
    redirect('modules/social/newsfeed.php');
}

$error = '';
$email = '';
$next = $_GET['next'] ?? ($_POST['next'] ?? '');

if (is_post()) {
    csrf_check();
    $email = post_str('email', 100);
    $password = (string) ($_POST['password'] ?? '');

    // tiny brute-force throttle (per session)
    $_SESSION['login_fail'] = $_SESSION['login_fail'] ?? ['n' => 0, 't' => 0];
    if ($_SESSION['login_fail']['n'] >= 5 && time() - $_SESSION['login_fail']['t'] < 30) {
        $error = 'Too many attempts. Please wait 30 seconds and try again.';
    } else {
        $user = db_one('SELECT user_id, password FROM user WHERE email = ? LIMIT 1', 's', $email);
        if ($user && password_verify($password, $user['password'])) {
            session_regenerate_id(true);
            $_SESSION['login_fail'] = ['n' => 0, 't' => 0];
            $_SESSION['user_id'] = (int) $user['user_id'];
            redirect(safe_next($next));
        }
        $_SESSION['login_fail']['n']++;
        $_SESSION['login_fail']['t'] = time();
        $error = 'Invalid email or password.';
    }
}

$page_title = 'Log in';
$body_class = 'auth-body';
include ROOT_PATH . '/includes/header.php';
?>
<div class="auth-wrap">
    <div class="auth-shell">
        <aside class="auth-art" aria-hidden="true">
            <h2>Welcome back</h2>
            <p>Pick up right where you left off – your routines, notes and community are waiting.</p>
            <ul><li><i class="fa-solid fa-heart"></i> Friendly community of parents</li><li><i class="fa-solid fa-chart-line"></i> Track behaviour &amp; progress</li><li><i class="fa-solid fa-list-check"></i> Predictable daily routines</li></ul>
        </aside>
    <div class="auth-card">
        <img class="auth-logo" src="<?= e(asset('images/logo-mark.svg')) ?>" alt="">
        <h1>Welcome back</h1>
        <p class="sub">Log in to continue to <?= e(SITE_NAME) ?></p>

        <?php if ($error): ?><?= alert_html($error, 'danger') ?><?php endif; ?>

        <form method="post" action="" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="next" value="<?= e($next) ?>">
            <div class="form-group">
                <label for="email">Email</label>
                <div class="input-icon"><i class="fa-solid fa-envelope"></i>
                    <input type="email" id="email" name="email" value="<?= e($email) ?>" autocomplete="email" required autofocus></div>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <div class="input-icon"><i class="fa-solid fa-lock"></i>
                    <input type="password" id="password" name="password" autocomplete="current-password" required></div>
            </div>
            <button class="btn btn-primary btn-block btn-lg" type="submit">Log in</button>
        </form>
        <p class="text-center text-muted mt-3 mb-0">New here? <a href="<?= e(url('auth/signup.php')) ?>">Create an account</a></p>
    </div>
    </div>
</div>
<?php include ROOT_PATH . '/includes/footer.php'; ?>
