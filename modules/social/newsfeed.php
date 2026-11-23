<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/_lib.php';
require_login();

$uid = current_user_id();
$me = current_user();
$msgCount = unread_messages();
$cat = pick((string) ($_GET['cat'] ?? ''), array_keys(POST_CATEGORIES), '');
$page = max(1, get_int('page', 1));
$perPage = 10;

$sql = post_select_sql() . ' WHERE ' . post_visible_sql();
$types = 'iiii'; $params = [$uid, $uid, $uid, $uid];
if ($cat !== '') { $sql .= ' AND p.category = ?'; $types .= 's'; $params[] = $cat; }
$sql .= ' ORDER BY p.created_at DESC, p.post_id DESC LIMIT ' . ($perPage + 1) . ' OFFSET ' . (($page - 1) * $perPage);
$posts = db_all($sql, $types, ...$params);
$hasMore = count($posts) > $perPage;
$posts = array_slice($posts, 0, $perPage);

$suggested = db_all(
    "SELECT user_id, username, first_name, last_name, profile_photo, location, children_details FROM user
     WHERE user_id <> ? AND user_id NOT IN (SELECT user_id FROM followers WHERE follower_user_id = ?)
     ORDER BY (location = ? AND ? <> '') DESC, RAND() LIMIT 4",
    'iiss', $uid, $uid, (string) db_value('SELECT location FROM user WHERE user_id = ?', 'i', $uid), (string) db_value('SELECT location FROM user WHERE user_id = ?', 'i', $uid)
);
$quote = db_one('SELECT motivationcontent, inspirationcontent FROM motivationalquotes ORDER BY RAND() LIMIT 1');
$todayD = date('Y-m-d');
$tTotal = (int) db_value('SELECT COUNT(*) FROM daily_planner WHERE user_id = ? AND deadline = ?', 'is', $uid, $todayD);
$tDone  = (int) db_value("SELECT COUNT(*) FROM daily_planner WHERE user_id = ? AND deadline = ? AND status = 'Completed'", 'is', $uid, $todayD);
$nextEv = db_one('SELECT event_id, title, date FROM events WHERE date >= ? ORDER BY date ASC, start_time ASC LIMIT 1', 's', $todayD);
$hr = (int) date('G');
$greet = $hr < 5 ? 'Still up' : ($hr < 12 ? 'Good morning' : ($hr < 17 ? 'Good afternoon' : 'Good evening'));

$page_title = 'Newsfeed'; $active = 'newsfeed';
$extra_js = ['assets/js/newsfeed.js'];
include ROOT_PATH . '/includes/header.php';
$qs = fn(array $o) => http_build_query(array_filter($o, fn($v) => $v !== '' && $v !== null));
?>
<script>window.PAGE = { api: <?= json_encode(url('modules/social/social_api.php')) ?>, me: <?= $uid ?> };</script>
<div class="container">
    <div class="layout-2col">
        <div>
            <section class="today" aria-label="Today">
                <h2><?= e($greet) ?>, <?= e($me['first_name'] ?: $me['username']) ?> 👋</h2>
                <p>Small steps count. Here is your day at a glance.</p>
                <div class="today-stats">
                    <a class="today-chip" href="<?= e(url('modules/planner/daily_planner.php')) ?>"><i class="fa-solid fa-list-check"></i> <?= $tDone ?>/<?= $tTotal ?> tasks today</a>
<?php if ($nextEv): ?>
                    <a class="today-chip" href="<?= e(url('modules/planner/events.php')) ?>"><i class="fa-solid fa-calendar-day"></i> <?= e(mb_strimwidth((string) $nextEv['title'], 0, 28, '…')) ?> · <?= e(date('M j', strtotime($nextEv['date']))) ?></a>
<?php endif; ?>
<?php if ($msgCount > 0): ?>
                    <a class="today-chip" href="<?= e(url('modules/social/messages.php')) ?>"><i class="fa-solid fa-envelope"></i> <?= $msgCount ?> unread</a>
<?php endif; ?>
                </div>
                <div class="quick-grid">
                    <a class="quick-tile" href="<?= e(url('modules/planner/daily_planner.php')) ?>"><i style="background:#5468c9" class="fa-solid fa-plus"></i> Add task</a>
                    <a class="quick-tile" href="<?= e(url('modules/reports/behavioral_feature.php')) ?>"><i style="background:#8456c4" class="fa-solid fa-pen"></i> Log behaviour</a>
                    <a class="quick-tile" href="<?= e(url('modules/reports/qna.php')) ?>"><i style="background:#278a60" class="fa-solid fa-question"></i> Ask expert</a>
                    <a class="quick-tile" href="<?= e(url('modules/reports/resource.php')) ?>"><i style="background:#c98308" class="fa-solid fa-book-open"></i> Resources</a>
                </div>
            </section>
            <div class="card composer">
                <form method="post" action="<?= e(url('modules/social/post_create.php')) ?>" enctype="multipart/form-data">
                    <?= csrf_field() ?>
                    <div class="flex gap-2">
                        <img class="avatar" src="<?= e(avatar_url($me)) ?>" alt="">
                        <textarea name="content" class="flex-1" placeholder="What's on your mind, <?= e($me['first_name'] ?: $me['username']) ?>?" maxlength="5000" aria-label="New post"></textarea>
                    </div>
                    <img id="composerPreview" class="img-preview" alt="Photo preview">
                    <div class="composer-bar">
                        <label class="btn btn-outline btn-sm" for="photo-upload"><i class="fa-solid fa-image"></i> Photo</label>
                        <input type="file" id="photo-upload" name="photo" accept="image/*" class="sr-only" data-preview="#composerPreview">
                        <select name="category" aria-label="Topic"><?= category_options('general') ?></select>
                        <select name="privacy" aria-label="Audience"><?= privacy_options('public') ?></select>
                        <button class="btn btn-primary btn-sm" style="margin-left:auto" type="submit"><i class="fa-solid fa-paper-plane"></i> Post</button>
                    </div>
                </form>
            </div>

            <div class="pills mt-2 mb-2" aria-label="Filter by topic">
                <a class="pill<?= $cat === '' ? ' active' : '' ?>" href="?">All</a>
                <?php foreach (POST_CATEGORIES as $v => $l): ?>
                    <a class="pill<?= $cat === $v ? ' active' : '' ?>" href="?<?= e($qs(['cat' => $v])) ?>"><?= e($l) ?></a>
                <?php endforeach; ?>
            </div>

            <?php if (!$posts): ?>
                <div class="card empty-state"><i class="fa-regular fa-comments empty-icon"></i>
                    <h3>Nothing here yet</h3><p>Be the first to share something<?= $cat ? ' in this topic' : '' ?> – every story helps another parent.</p></div>
            <?php endif; ?>
            <?php foreach ($posts as $p) render_post($p, $uid); ?>

            <?php if ($page > 1 || $hasMore): ?>
                <div class="flex justify-between mt-3">
                    <?php if ($page > 1): ?><a class="btn btn-outline" href="?<?= e($qs(['cat' => $cat, 'page' => $page - 1])) ?>"><i class="fa-solid fa-arrow-left"></i> Newer</a><?php else: ?><span></span><?php endif; ?>
                    <?php if ($hasMore): ?><a class="btn btn-outline" href="?<?= e($qs(['cat' => $cat, 'page' => $page + 1])) ?>">Older posts <i class="fa-solid fa-arrow-right"></i></a><?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <aside>
            <?php if ($quote): ?>
                <div class="card card-soft mb-2">
                    <p class="card-title-sm"><i class="fa-solid fa-sun"></i> Today's encouragement</p>
                    <?php if (!empty($quote['motivationcontent'])): ?><p class="fw-bold mb-1">“<?= e($quote['motivationcontent']) ?>”</p><?php endif; ?>
                    <?php if (!empty($quote['inspirationcontent'])): ?><p class="text-muted text-sm mb-0"><?= e($quote['inspirationcontent']) ?></p><?php endif; ?>
                </div>
            <?php endif; ?>
            <div class="card">
                <p class="card-title-sm"><i class="fa-solid fa-user-group"></i> Parents you may know</p>
                <?php if (!$suggested): ?><p class="text-muted text-sm mb-0">You're connected with everyone for now.</p><?php endif; ?>
                <?php foreach ($suggested as $s): ?>
                    <div class="person-row">
                        <a href="<?= e(url('modules/social/view_profile.php?user_id=' . $s['user_id'])) ?>"><img class="avatar avatar-sm" src="<?= e(avatar_url($s)) ?>" alt=""></a>
                        <div class="who">
                            <a href="<?= e(url('modules/social/view_profile.php?user_id=' . $s['user_id'])) ?>"><?= e(display_name($s)) ?></a>
                            <div class="text-muted text-xs truncate"><?= e($s['location'] ?: truncate((string) $s['children_details'], 40)) ?></div>
                        </div>
                        <button class="btn btn-outline btn-sm" type="button" data-follow="<?= (int) $s['user_id'] ?>">Follow</button>
                    </div>
                <?php endforeach; ?>
            </div>
        </aside>
    </div>
</div>
<?php include ROOT_PATH . '/includes/footer.php'; ?>
