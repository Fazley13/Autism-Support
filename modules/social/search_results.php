<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/_lib.php';
require_login();

$uid = current_user_id();
$q = trim((string) ($_GET['q'] ?? ''));
$type = pick((string) ($_GET['type'] ?? 'all'), ['all', 'people', 'posts', 'resources', 'events', 'questions'], 'all');
$like = '%' . addcslashes($q, '%_\\') . '%';
$R = ['people' => [], 'posts' => [], 'resources' => [], 'events' => [], 'questions' => []];

if (mb_strlen($q) >= 2) {
    $R['people'] = db_all("SELECT user_id, username, first_name, last_name, profile_photo, location, children_details FROM user
        WHERE username LIKE ? OR first_name LIKE ? OR last_name LIKE ? OR location LIKE ? OR children_details LIKE ? ORDER BY username LIMIT 20", 'sssss', $like, $like, $like, $like, $like);
    $R['posts'] = db_all("SELECT p.post_id, p.content, p.created_at, u.username FROM post p JOIN user u ON u.user_id = p.user_id
        WHERE p.privacy = 'public' AND (p.content LIKE ? OR p.category LIKE ?) ORDER BY p.created_at DESC LIMIT 20", 'ss', $like, $like);
    $R['resources'] = db_all("SELECT r.id, r.title, r.description, t.type_name FROM resources r LEFT JOIN resource_types t ON t.id = r.resource_type_id
        WHERE r.title LIKE ? OR r.description LIKE ? ORDER BY r.created_at DESC LIMIT 20", 'ss', $like, $like);
    $R['events'] = db_all("SELECT event_id, title, location, date FROM events WHERE title LIKE ? OR description LIKE ? OR location LIKE ? ORDER BY date DESC LIMIT 20", 'sss', $like, $like, $like);
    $R['questions'] = db_all("SELECT question_id, question_text, created_at FROM questions WHERE question_text LIKE ? ORDER BY created_at DESC LIMIT 20", 's', $like);
}
$total = array_sum(array_map('count', $R));
$labels = ['people' => ['People', 'user'], 'posts' => ['Posts', 'newspaper'], 'resources' => ['Resources', 'book-open'], 'events' => ['Events', 'calendar-days'], 'questions' => ['Questions', 'circle-question']];

$page_title = 'Search'; $active = '';
include ROOT_PATH . '/includes/header.php';
?>
<div class="container-sm">
    <div class="page-header"><div><h1><i class="fa-solid fa-magnifying-glass"></i> Search</h1>
        <?php if ($q !== ''): ?><p>Results for “<?= e($q) ?>”<?= mb_strlen($q) >= 2 ? ' · ' . $total . ' found' : '' ?></p><?php endif; ?></div></div>
    <form class="card mb-2" method="get"><div class="flex gap-1">
        <input class="flex-1" type="search" name="q" value="<?= e($q) ?>" placeholder="Search people, posts, resources, events, questions…" aria-label="Search" required autofocus>
        <button class="btn btn-primary" type="submit">Search</button></div></form>

    <?php if ($q !== '' && mb_strlen($q) < 2): ?><?= alert_html('Please type at least 2 characters.', 'warning') ?><?php endif; ?>
    <?php if ($q === ''): ?><div class="card empty-state"><i class="fa-solid fa-magnifying-glass empty-icon"></i><p class="mb-0">Search the whole community for parents, stories, resources and events.</p></div>
    <?php elseif (mb_strlen($q) >= 2 && $total === 0): ?><div class="card empty-state"><i class="fa-regular fa-face-frown empty-icon"></i><h3>Nothing found</h3><p>Try a different or shorter keyword.</p></div>
    <?php elseif ($total > 0): ?>
        <div class="pills mb-2">
            <a class="pill<?= $type === 'all' ? ' active' : '' ?>" href="?q=<?= urlencode($q) ?>">All (<?= $total ?>)</a>
            <?php foreach ($labels as $k => [$l]): if (!$R[$k]) continue; ?><a class="pill<?= $type === $k ? ' active' : '' ?>" href="?q=<?= urlencode($q) ?>&type=<?= $k ?>"><?= $l ?> (<?= count($R[$k]) ?>)</a><?php endforeach; ?>
        </div>
        <?php foreach ($labels as $k => [$label, $icon]): if (!$R[$k] || ($type !== 'all' && $type !== $k)) continue; ?>
            <div class="card mb-2">
                <div class="card-header"><h3><i class="fa-solid fa-<?= $icon ?>"></i> <?= $label ?></h3></div>
                <?php foreach ($R[$k] as $r): ?>
                    <?php if ($k === 'people'): ?>
                        <div class="person-row"><a href="<?= e(url('modules/social/view_profile.php?user_id=' . $r['user_id'])) ?>"><img class="avatar" src="<?= e(avatar_url($r)) ?>" alt=""></a>
                            <div class="who"><a href="<?= e(url('modules/social/view_profile.php?user_id=' . $r['user_id'])) ?>"><?= e(display_name($r)) ?></a>
                                <div class="text-muted text-sm">@<?= e($r['username']) ?><?= $r['location'] ? ' · ' . e($r['location']) : '' ?></div></div>
                            <a class="btn btn-outline btn-sm" href="<?= e(url('modules/social/messages.php?with=' . $r['user_id'])) ?>">Message</a></div>
                    <?php elseif ($k === 'posts'): ?>
                        <div class="person-row"><div class="who">                            <div><?= e(truncate((string) $r['content'], 180)) ?></div><div class="text-muted text-xs">@<?= e($r['username']) ?> · <?= e(time_ago($r['created_at'])) ?></div></div></div>
                    <?php elseif ($k === 'resources'): ?>
                        <div class="person-row"><div class="who"><a href="<?= e(url('modules/reports/resource.php?q=' . urlencode($q))) ?>"><?= e($r['title']) ?></a>
                            <div class="text-muted text-sm"><?= e(truncate((string) $r['description'], 120)) ?></div></div><span class="badge"><?= e($r['type_name']) ?></span></div>
                    <?php elseif ($k === 'events'): ?>
                        <div class="person-row"><div class="who"><a href="<?= e(url('modules/planner/events.php?q=' . urlencode($r['title']))) ?>"><?= e($r['title']) ?></a>
                            <div class="text-muted text-sm"><?= e($r['location']) ?> · <?= e($r['date']) ?></div></div></div>
                    <?php else: ?>
                        <div class="person-row"><div class="who"><a href="<?= e(url('modules/reports/qna.php?q=' . urlencode($q))) ?>"><?= e(truncate((string) $r['question_text'], 160)) ?></a>
                            <div class="text-muted text-xs"><?= e(time_ago($r['created_at'])) ?></div></div></div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
<?php include ROOT_PATH . '/includes/footer.php'; ?>
