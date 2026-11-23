<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_login();

$uid = current_user_id();
$id = get_int('user_id', $uid);
$owner = db_one('SELECT user_id, username, first_name, last_name FROM user WHERE user_id = ?', 'i', $id);
if (!$owner) { http_response_code(404); render_error_page('Profile not found', 'This member does not exist.'); }

$people = db_all("SELECT u.user_id, u.username, u.first_name, u.last_name, u.profile_photo, u.location FROM followers f JOIN user u ON u.user_id = f.follower_user_id WHERE f.user_id = ? ORDER BY f.follower_id DESC", 'i', $id);
$myFollowing = array_column(db_all('SELECT user_id FROM followers WHERE follower_user_id = ?', 'i', $uid), 'user_id');

$page_title = 'Followers'; $active = 'profile';
$extra_js = ['assets/js/newsfeed.js'];
include ROOT_PATH . '/includes/header.php';
?>
<script>window.PAGE = { api: <?= json_encode(url('modules/social/social_api.php')) ?>, me: <?= $uid ?> };</script>
<div class="container-sm">
    <div class="page-header">
        <div><h1><i class="fa-solid fa-user-group"></i> Followers</h1>
            <p><a href="<?= e(url('modules/social/view_profile.php?user_id=' . $id)) ?>">← <?= e(display_name($owner)) ?></a> · <?= count($people) ?> <?= count($people) === 1 ? 'person' : 'people' ?></p></div>
    </div>
    <div class="card">
        <?php if (!$people): ?><div class="empty-state"><i class="fa-solid fa-user-group empty-icon"></i><p class="mb-0">No followers yet.</p></div><?php endif; ?>
        <?php foreach ($people as $p): ?>
            <div class="person-row">
                <a href="<?= e(url('modules/social/view_profile.php?user_id=' . $p['user_id'])) ?>"><img class="avatar" src="<?= e(avatar_url($p)) ?>" alt=""></a>
                <div class="who"><a href="<?= e(url('modules/social/view_profile.php?user_id=' . $p['user_id'])) ?>"><?= e(display_name($p)) ?></a>
                    <div class="text-muted text-sm">@<?= e($p['username']) ?><?= $p['location'] ? ' · ' . e($p['location']) : '' ?></div></div>
                <?php if ((int) $p['user_id'] !== $uid): ?>
                    <button class="btn btn-outline btn-sm" type="button" data-follow="<?= (int) $p['user_id'] ?>"><?= in_array($p['user_id'], $myFollowing) ? 'Following' : 'Follow' ?></button>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php include ROOT_PATH . '/includes/footer.php'; ?>
