<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/_lib.php';
require_login();

$uid = current_user_id();
$id = get_int('user_id', $uid);
$u = db_one('SELECT user_id, username, first_name, last_name, bio, profile_photo, cover_photo, location, children_details, created_at FROM user WHERE user_id = ?', 'i', $id);
if (!$u) { http_response_code(404); render_error_page('Profile not found', 'This member does not exist (anymore).'); }

$own = $id === $uid;
$followers = (int) db_value('SELECT COUNT(*) FROM followers WHERE user_id = ?', 'i', $id);
$following = (int) db_value('SELECT COUNT(*) FROM followers WHERE follower_user_id = ?', 'i', $id);
$postCount = (int) db_value('SELECT COUNT(*) FROM post p WHERE p.user_id = ? AND ' . post_visible_sql(), 'iii', $id, $uid, $uid);
$isFollowing = !$own && db_one('SELECT 1 AS x FROM followers WHERE user_id = ? AND follower_user_id = ?', 'ii', $id, $uid);

$page = max(1, get_int('page', 1)); $perPage = 10;
$posts = db_all(post_select_sql() . ' WHERE p.user_id = ? AND ' . post_visible_sql() . ' ORDER BY p.created_at DESC, p.post_id DESC LIMIT ' . ($perPage + 1) . ' OFFSET ' . (($page - 1) * $perPage),
    'iiiii', $uid, $uid, $id, $uid, $uid);
$hasMore = count($posts) > $perPage; $posts = array_slice($posts, 0, $perPage);

$page_title = display_name($u); $active = $own ? 'profile' : '';
$extra_js = ['assets/js/newsfeed.js'];
include ROOT_PATH . '/includes/header.php';
$cover = media_url($u['cover_photo'] ?? '', 'cover');
?>
<script>window.PAGE = { api: <?= json_encode(url('modules/social/social_api.php')) ?>, me: <?= $uid ?> };</script>
<div class="container">
    <div class="card" style="padding:0;overflow:hidden">
        <div class="profile-cover"><img src="<?= e($cover) ?>" alt=""></div>
        <div class="profile-head">
            <img class="avatar avatar-xl" src="<?= e(avatar_url($u)) ?>" alt="<?= e($u['username']) ?>'s photo">
            <div class="flex-1">
                <h1 class="mb-0"><?= e(display_name($u)) ?></h1>
                <div class="text-muted">@<?= e($u['username']) ?><?php if ($u['location']): ?> · <i class="fa-solid fa-location-dot"></i> <?= e($u['location']) ?><?php endif; ?></div>
            </div>
            <div class="btn-group">
                <?php if ($own): ?>
                    <a class="btn btn-primary" href="<?= e(url('modules/social/profile.php')) ?>"><i class="fa-solid fa-user-pen"></i> Edit profile</a>
                <?php else: ?>
                    <button class="btn <?= $isFollowing ? 'btn-outline' : 'btn-primary' ?>" type="button" data-follow="<?= $id ?>"><?= $isFollowing ? 'Following' : 'Follow' ?></button>
                    <a class="btn btn-outline" href="<?= e(url('modules/social/messages.php?with=' . $id)) ?>"><i class="fa-solid fa-envelope"></i> Message</a>
                <?php endif; ?>
            </div>
        </div>
        <div class="profile-head" style="margin-top:0;padding-top:.25rem">
            <div class="profile-stats">
                <div><strong><?= $postCount ?></strong><span>Posts</span></div>
                <a href="<?= e(url('modules/social/followers.php?user_id=' . $id)) ?>"><strong><?= $followers ?></strong><span>Followers</span></a>
                <a href="<?= e(url('modules/social/following.php?user_id=' . $id)) ?>"><strong><?= $following ?></strong><span>Following</span></a>
            </div>
        </div>
    </div>

    <div class="layout-2col mt-2">
        <div>
            <?php if (!$posts): ?>
                <div class="card empty-state"><i class="fa-regular fa-newspaper empty-icon"></i><h3>No posts to show</h3><p><?= $own ? 'Share your first story from the newsfeed.' : 'This member has not shared anything you can see yet.' ?></p></div>
            <?php endif; ?>
            <?php foreach ($posts as $p) render_post($p, $uid); ?>
            <?php if ($page > 1 || $hasMore): ?>
                <div class="flex justify-between mt-3">
                    <?php if ($page > 1): ?><a class="btn btn-outline" href="?user_id=<?= $id ?>&page=<?= $page - 1 ?>">← Newer</a><?php else: ?><span></span><?php endif; ?>
                    <?php if ($hasMore): ?><a class="btn btn-outline" href="?user_id=<?= $id ?>&page=<?= $page + 1 ?>">Older →</a><?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        <aside>
            <div class="card">
                <p class="card-title-sm">About</p>
                <?php if ($u['bio']): ?><p><?= nl2br(e($u['bio'])) ?></p><?php endif; ?>
                <ul class="meta-list">
                    <?php if ($u['children_details']): ?><li><i class="fa-solid fa-child-reaching"></i><span><?= e($u['children_details']) ?></span></li><?php endif; ?>
                    <?php if ($u['location']): ?><li><i class="fa-solid fa-location-dot"></i><span><?= e($u['location']) ?></span></li><?php endif; ?>
                    <li><i class="fa-solid fa-calendar"></i><span>Joined <?= e(fmt_dt($u['created_at'], 'F Y')) ?></span></li>
                </ul>
                <?php if (!$u['bio'] && !$u['children_details']): ?><p class="text-muted text-sm mt-2 mb-0">No details shared yet.</p><?php endif; ?>
            </div>
        </aside>
    </div>
</div>
<?php include ROOT_PATH . '/includes/footer.php'; ?>
