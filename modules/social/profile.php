<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_login();

$uid = current_user_id();
$u = db_one('SELECT * FROM user WHERE user_id = ?', 'i', $uid);
$errors = [];
$tab = ($_GET['tab'] ?? 'profile') === 'password' ? 'password' : 'profile';

if (is_post()) {
    csrf_check();
    $action = post_str('action');

    if ($action === 'profile') {
        $f = [
            'first_name' => post_str('first_name', 50), 'last_name' => post_str('last_name', 50),
            'bio' => post_str('bio', 1000), 'location' => post_str('location', 100),
            'children_details' => post_str('children_details', 1000), 'phone' => post_str('phone', 20),
        ];
        $dob = post_str('date_of_birth');
        $gender = pick(post_str('gender'), ['male', 'female', 'other'], '');
        $username = post_str('username', 50);
        $email = post_str('email', 100);

        if (!preg_match('/^[A-Za-z0-9_.-]{3,30}$/', $username)) $errors[] = 'Username must be 3–30 characters (letters, numbers, . _ -).';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Please enter a valid email.';
        if ($dob !== '' && !(DateTime::createFromFormat('Y-m-d', $dob) && $dob < date('Y-m-d'))) $errors[] = 'Please enter a valid date of birth.';
        if (!$errors && db_one('SELECT 1 AS x FROM user WHERE username = ? AND user_id <> ?', 'si', $username, $uid)) $errors[] = 'That username is taken.';
        if (!$errors && db_one('SELECT 1 AS x FROM user WHERE email = ? AND user_id <> ?', 'si', $email, $uid)) $errors[] = 'That email is already used by another account.';

        $profilePhoto = $u['profile_photo']; $coverPhoto = $u['cover_photo'];
        if (!$errors) {
            try {
                if ($new = save_image_upload('profile_photo', 800)) { delete_media($profilePhoto); $profilePhoto = $new; }
                if ($new = save_image_upload('cover_photo', 1600)) { delete_media($coverPhoto); $coverPhoto = $new; }
            } catch (RuntimeException $ex) { $errors[] = $ex->getMessage(); }
            catch (Throwable $ex) { error_log('profile upload: ' . $ex->getMessage()); $errors[] = 'Could not save the image' . ': ' . $ex->getMessage(); }
        }
        if (!$errors) {
            db_exec('UPDATE user SET username=?, email=?, first_name=?, last_name=?, bio=?, location=?, children_details=?, phone=?, date_of_birth=?, gender=?, profile_photo=?, cover_photo=? WHERE user_id=?',
                'ssssssssssssi', $username, $email, $f['first_name'], $f['last_name'], $f['bio'], $f['location'], $f['children_details'], $f['phone'],
                $dob === '' ? null : $dob, $gender === '' ? null : $gender, $profilePhoto, $coverPhoto, $uid);
            flash('Profile updated.');
            redirect('modules/social/view_profile.php?user_id=' . $uid);
        }
        $u = array_merge($u, $f, ['username' => $username, 'email' => $email, 'date_of_birth' => $dob, 'gender' => $gender]);
    }

    if ($action === 'password') {
        $tab = 'password';
        $cur = (string) ($_POST['current_password'] ?? ''); $new = (string) ($_POST['new_password'] ?? ''); $conf = (string) ($_POST['confirm_password'] ?? '');
        if (!password_verify($cur, $u['password'])) $errors[] = 'Your current password is incorrect.';
        elseif (strlen($new) < 8) $errors[] = 'New password must be at least 8 characters.';
        elseif ($new !== $conf) $errors[] = 'New passwords do not match.';
        else {
            db_exec('UPDATE user SET password = ? WHERE user_id = ?', 'si', password_hash($new, PASSWORD_DEFAULT), $uid);
            session_regenerate_id(true);
            flash('Password changed successfully.');
            redirect('modules/social/profile.php?tab=password');
        }
    }
}

$page_title = 'Edit profile'; $active = 'profile';
include ROOT_PATH . '/includes/header.php';
?>
<div class="container-sm">
    <div class="page-header"><h1><i class="fa-solid fa-user-pen"></i> Account settings</h1>
        <a class="btn btn-outline" href="<?= e(url('modules/social/view_profile.php?user_id=' . $uid)) ?>"><i class="fa-solid fa-eye"></i> View my profile</a></div>
    <div class="tabs">
        <a class="tab<?= $tab === 'profile' ? ' active' : '' ?>" href="?tab=profile">Profile</a>
        <a class="tab<?= $tab === 'password' ? ' active' : '' ?>" href="?tab=password">Password</a>
    </div>
    <?php foreach ($errors as $er): ?><?= alert_html($er, 'danger') ?><?php endforeach; ?>

    <?php if ($tab === 'profile'): ?>
    <form class="card" method="post" enctype="multipart/form-data">
        <?= csrf_field() ?><input type="hidden" name="action" value="profile">
        <div class="flex gap-3 flex-wrap items-center mb-2">
            <img id="avPrev" class="avatar avatar-xl" src="<?= e(avatar_url($u)) ?>" alt="Current profile photo">
            <div class="flex-1">
                <label class="form-label" for="profile_photo">Profile photo</label>
                <input type="file" id="profile_photo" name="profile_photo" accept="image/*" data-preview="#avPrev" data-max-dim="800">
                <label class="form-label mt-2" for="cover_photo">Cover photo</label>
                <input type="file" id="cover_photo" name="cover_photo" accept="image/*">
                <div class="form-hint">JPG, PNG, GIF or WebP · up to 3 MB.</div>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group"><label for="first_name">First name</label><input type="text" id="first_name" name="first_name" value="<?= e($u['first_name']) ?>"></div>
            <div class="form-group"><label for="last_name">Last name</label><input type="text" id="last_name" name="last_name" value="<?= e($u['last_name']) ?>"></div>
        </div>
        <div class="form-row">
            <div class="form-group"><label for="username">Username</label><input type="text" id="username" name="username" value="<?= e($u['username']) ?>" required></div>
            <div class="form-group"><label for="email">Email</label><input type="email" id="email" name="email" value="<?= e($u['email']) ?>" required></div>
        </div>
        <div class="form-group"><label for="bio">About you</label><textarea id="bio" name="bio" maxlength="1000" placeholder="Tell other parents a little about yourself…"><?= e($u['bio']) ?></textarea></div>
        <div class="form-group"><label for="children_details">About your child / children</label>
            <textarea id="children_details" name="children_details" maxlength="1000" placeholder="e.g. One son, age 6, on the autism spectrum with ADHD"><?= e($u['children_details']) ?></textarea>
            <div class="form-hint">Used to suggest parents with similar experiences.</div></div>
        <div class="form-row">
            <div class="form-group"><label for="location">Location</label><input type="text" id="location" name="location" value="<?= e($u['location']) ?>" placeholder="City"></div>
            <div class="form-group"><label for="phone">Phone (private)</label><input type="tel" id="phone" name="phone" value="<?= e($u['phone']) ?>"></div>
        </div>
        <div class="form-row">
            <div class="form-group"><label for="date_of_birth">Date of birth</label><input type="date" id="date_of_birth" name="date_of_birth" max="<?= date('Y-m-d') ?>" value="<?= e($u['date_of_birth'] && $u['date_of_birth'] !== '0000-00-00' ? $u['date_of_birth'] : '') ?>"></div>
            <div class="form-group"><label for="gender">Gender</label>
                <select id="gender" name="gender"><option value="">Prefer not to say</option>
                    <?php foreach (['male' => 'Male', 'female' => 'Female', 'other' => 'Other'] as $k => $l): ?><option value="<?= $k ?>"<?= $u['gender'] === $k ? ' selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></div>
        </div>
        <button class="btn btn-primary" type="submit"><i class="fa-solid fa-floppy-disk"></i> Save profile</button>
    </form>
    <?php else: ?>
    <form class="card" method="post" autocomplete="off">
        <?= csrf_field() ?><input type="hidden" name="action" value="password">
        <div class="form-group"><label for="current_password">Current password</label><input type="password" id="current_password" name="current_password" required autocomplete="current-password"></div>
        <div class="form-group"><label for="new_password">New password</label><input type="password" id="new_password" name="new_password" minlength="8" required autocomplete="new-password"><div class="form-hint">At least 8 characters.</div></div>
        <div class="form-group"><label for="confirm_password">Confirm new password</label><input type="password" id="confirm_password" name="confirm_password" minlength="8" required autocomplete="new-password"></div>
        <button class="btn btn-primary" type="submit"><i class="fa-solid fa-key"></i> Change password</button>
    </form>
    <?php endif; ?>
</div>
<?php include ROOT_PATH . '/includes/footer.php'; ?>
