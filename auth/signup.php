<?php
require_once __DIR__ . '/../includes/bootstrap.php';

if (isLoggedIn() && current_user()) {
    redirect('modules/social/newsfeed.php');
}

$errors = [];
$v = ['username' => '', 'email' => '', 'first_name' => '', 'last_name' => ''];

if (is_post()) {
    csrf_check();
    $v['username']   = post_str('username', 50);
    $v['email']      = post_str('email', 100);
    $v['first_name'] = post_str('first_name', 50);
    $v['last_name']  = post_str('last_name', 50);
    $password = (string) ($_POST['password'] ?? '');
    $confirm  = (string) ($_POST['confirm'] ?? '');

    if (!preg_match('/^[A-Za-z0-9_.-]{3,30}$/', $v['username'])) $errors[] = 'Username must be 3–30 characters: letters, numbers, dot, dash or underscore.';
    if (!filter_var($v['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Please enter a valid email address.';
    if (strlen($password) < 8) $errors[] = 'Password must be at least 8 characters.';
    if ($password !== $confirm) $errors[] = 'Passwords do not match.';

    if (!$errors) {
        if (db_one('SELECT 1 AS x FROM user WHERE email = ?', 's', $v['email'])) $errors[] = 'An account with this email already exists.';
        if (db_one('SELECT 1 AS x FROM user WHERE username = ?', 's', $v['username'])) $errors[] = 'That username is taken.';
    }
    if (!$errors) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        db_exec('INSERT INTO user (username, password, email, first_name, last_name) VALUES (?, ?, ?, ?, ?)',
            'sssss', $v['username'], $hash, $v['email'], $v['first_name'], $v['last_name']);
        session_regenerate_id(true);
        $_SESSION['user_id'] = db_insert_id();
        flash('Welcome to ' . SITE_NAME . '! Tell the community a little about you and your child.', 'success');
        redirect('modules/social/profile.php');
    }
}

$page_title = 'Create account';
$body_class = 'auth-body';
include ROOT_PATH . '/includes/header.php';
?>
<div class="auth-wrap">
    <div class="auth-shell">
        <aside class="auth-art" aria-hidden="true">
            <h2>You are not alone</h2>
            <p>Join a calm, supportive space built for parents and caregivers of autistic children.</p>
            <ul><li><i class="fa-solid fa-heart"></i> Friendly community of parents</li><li><i class="fa-solid fa-chart-line"></i> Track behaviour &amp; progress</li><li><i class="fa-solid fa-list-check"></i> Predictable daily routines</li></ul>
        </aside>
    <div class="auth-card">
        <img class="auth-logo" src="<?= e(asset('images/logo-mark.svg')) ?>" alt="">
        <h1>Join <?= e(SITE_NAME) ?></h1>
        <p class="sub">A calm space to connect, plan and track progress</p>

        <?php foreach ($errors as $er): ?><?= alert_html($er, 'danger') ?><?php endforeach; ?>

        <form method="post" action="" novalidate>
            <?= csrf_field() ?>
            <div class="form-row">
                <div class="form-group"><label for="first_name">First name</label>
                    <input type="text" id="first_name" name="first_name" value="<?= e($v['first_name']) ?>" autocomplete="given-name"></div>
                <div class="form-group"><label for="last_name">Last name</label>
                    <input type="text" id="last_name" name="last_name" value="<?= e($v['last_name']) ?>" autocomplete="family-name"></div>
            </div>
            <div class="form-group"><label for="username">Username</label>
                <div class="input-icon"><i class="fa-solid fa-at"></i>
                    <input type="text" id="username" name="username" value="<?= e($v['username']) ?>" autocomplete="username" required></div></div>
            <div class="form-group"><label for="email">Email</label>
                <div class="input-icon"><i class="fa-solid fa-envelope"></i>
                    <input type="email" id="email" name="email" value="<?= e($v['email']) ?>" autocomplete="email" required></div></div>
            <div class="form-group"><label for="password">Password</label>
                <div class="input-icon"><i class="fa-solid fa-lock"></i>
                    <input type="password" id="password" name="password" minlength="8" autocomplete="new-password" required></div>
                <div class="pw-meter"><span id="pwBar"></span></div>
                <div class="form-hint">At least 8 characters.</div></div>
            <div class="form-group"><label for="confirm">Confirm password</label>
                <div class="input-icon"><i class="fa-solid fa-lock"></i>
                    <input type="password" id="confirm" name="confirm" minlength="8" autocomplete="new-password" required></div></div>
            <button class="btn btn-primary btn-block btn-lg" type="submit">Create account</button>
        </form>
        <p class="text-center text-muted mt-3 mb-0">Already a member? <a href="<?= e(url('auth/login.php')) ?>">Log in</a></p>
    </div>
    </div>
</div>
<script>
(function () {
    var pw = document.getElementById('password'), bar = document.getElementById('pwBar');
    pw.addEventListener('input', function () {
        var v = pw.value, s = 0;
        if (v.length >= 8) s++; if (/[A-Z]/.test(v) && /[a-z]/.test(v)) s++; if (/\d/.test(v)) s++; if (/[^A-Za-z0-9]/.test(v) || v.length >= 14) s++;
        bar.style.width = (s * 25) + '%';
        bar.style.background = ['', 'var(--danger)', 'var(--warning)', 'var(--info)', 'var(--success)'][s] || '';
    });
})();
</script>
<?php include ROOT_PATH . '/includes/footer.php'; ?>
