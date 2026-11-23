<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/_lib.php';
require_login();

$uid = current_user_id();
if (is_post()) {
    csrf_check();
    $id = post_int('id');
    $own = db_one('SELECT id FROM resources WHERE id = ? AND user_id = ?', 'ii', $id, $uid);
    if ($own) {
        db_exec('DELETE FROM resource_details WHERE resource_id = ?', 'i', $id);
        db_exec('DELETE FROM resources WHERE id = ?', 'i', $id);
        flash('Resource deleted.');
    } else flash('You can only delete resources you added.', 'danger');
    redirect('modules/reports/resource.php');
}

$q = trim((string) ($_GET['q'] ?? ''));
$typeId = get_int('type');
$types = db_all('SELECT id, type_name FROM resource_types ORDER BY type_name');
$sql = 'SELECT r.id, r.user_id, r.title, r.description, r.created_at, t.type_name, d.file_path, d.external_link, u.username
        FROM resources r LEFT JOIN resource_types t ON t.id = r.resource_type_id LEFT JOIN resource_details d ON d.resource_id = r.id LEFT JOIN user u ON u.user_id = r.user_id WHERE 1=1';
$ty = ''; $pa = [];
if ($q !== '') { $l = '%' . addcslashes($q, '%_\\') . '%'; $sql .= ' AND (r.title LIKE ? OR r.description LIKE ?)'; $ty .= 'ss'; array_push($pa, $l, $l); }
if ($typeId)   { $sql .= ' AND r.resource_type_id = ?'; $ty .= 'i'; $pa[] = $typeId; }
$resources = db_all($sql . ' ORDER BY r.created_at DESC, r.id DESC LIMIT 60', $ty, ...$pa);

$page_title = 'Educational resources'; $active = 'resources';
include ROOT_PATH . '/includes/header.php';
?>
<div class="container">
    <div class="page-header"><div><h1><i class="fa-solid fa-book-open"></i> Educational resources</h1><p>Videos, guides and documents shared by the community.</p></div>
        <a class="btn btn-primary" href="<?= e(url('modules/reports/add_resource.php')) ?>"><i class="fa-solid fa-plus"></i> Share a resource</a></div>
    <form class="card mb-2" method="get"><div class="flex gap-1 flex-wrap items-center">
        <input class="flex-1" type="search" name="q" value="<?= e($q) ?>" placeholder="Search resources…" aria-label="Search resources">
        <?php if ($typeId): ?><input type="hidden" name="type" value="<?= $typeId ?>"><?php endif; ?>
        <button class="btn btn-primary" type="submit">Search</button></div>
        <div class="pills mt-2"><a class="pill<?= !$typeId ? ' active' : '' ?>" href="?q=<?= urlencode($q) ?>">All</a>
            <?php foreach ($types as $t): ?><a class="pill<?= $typeId === (int) $t['id'] ? ' active' : '' ?>" href="?type=<?= (int) $t['id'] ?>&q=<?= urlencode($q) ?>"><?= e(ucfirst($t['type_name'])) ?></a><?php endforeach; ?></div></form>

    <?php if (!$resources): ?><div class="card empty-state"><i class="fa-solid fa-book-open empty-icon"></i><h3>No resources found</h3><p>Know something helpful? Share it with other parents.</p><a class="btn btn-primary" href="<?= e(url('modules/reports/add_resource.php')) ?>">Share a resource</a></div><?php endif; ?>
    <div class="grid grid-3">
    <?php foreach ($resources as $r):
        $link = $r['external_link'] ?: ($r['file_path'] ? url($r['file_path']) : ''); $yt = youtube_id((string) $r['external_link']);
        $icon = $yt || $r['type_name'] === 'video' ? 'fa-circle-play' : ($r['file_path'] ? 'fa-file-lines' : 'fa-link'); ?>
        <article class="card resource-card card-hover">
            <?php if ($yt): ?><img src="https://i.ytimg.com/vi/<?= e($yt) ?>/hqdefault.jpg" alt="" loading="lazy" style="border-radius:var(--radius-sm);aspect-ratio:16/9;object-fit:cover;width:100%">
            <?php else: ?><div class="resource-icon"><i class="fa-solid <?= $icon ?>"></i></div><?php endif; ?>
            <div class="flex justify-between gap-1"><span class="badge badge-info"><?= e(ucfirst((string) $r['type_name'])) ?></span><span class="text-xs text-muted"><?= e(time_ago($r['created_at'])) ?></span></div>
            <h3 class="mb-0"><?= e($r['title']) ?></h3>
            <p class="text-muted text-sm"><?= e(truncate((string) $r['description'], 150)) ?></p>
            <div class="flex gap-1 flex-wrap" style="margin-top:auto">
                <?php if ($yt): ?><button class="btn btn-primary btn-sm" type="button" data-yt="<?= e($yt) ?>"><i class="fa-solid fa-play"></i> Watch</button><?php endif; ?>
                <?php if ($link): ?><a class="btn <?= $yt ? 'btn-outline' : 'btn-primary' ?> btn-sm" href="<?= e($link) ?>" target="_blank" rel="noopener noreferrer"><i class="fa-solid fa-arrow-up-right-from-square"></i> Open</a><?php endif; ?>
                <?php if ((int) $r['user_id'] === $uid): ?>
                    <a class="btn btn-ghost btn-sm" href="<?= e(url('modules/reports/add_resource.php?id=' . $r['id'])) ?>" aria-label="Edit"><i class="fa-solid fa-pen"></i></a>
                    <form method="post" data-confirm="Delete this resource?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><button class="btn btn-ghost btn-sm" aria-label="Delete"><i class="fa-solid fa-trash"></i></button></form>
                <?php endif; ?>
            </div>
            <?php if ($r['username']): ?><div class="text-xs text-muted">Shared by @<?= e($r['username']) ?></div><?php endif; ?>
        </article>
    <?php endforeach; ?>
    </div>
</div>
<div class="modal-backdrop" id="ytModal"><div class="modal" style="width:min(820px,100%)"><div class="modal-header"><h3>Video</h3><button class="btn btn-ghost btn-sm" data-modal-close aria-label="Close"><i class="fa-solid fa-xmark"></i></button></div><div id="ytBox" style="aspect-ratio:16/9"></div></div></div>
<script>
document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-yt]'); var m = document.getElementById('ytModal');
    if (b) { document.getElementById('ytBox').innerHTML = '<iframe style="width:100%;height:100%;border:0;border-radius:12px" src="https://www.youtube-nocookie.com/embed/' + encodeURIComponent(b.getAttribute('data-yt')) + '?autoplay=1" allow="autoplay; encrypted-media; picture-in-picture" allowfullscreen title="Video"></iframe>'; m.classList.add('open'); }
    else if (m.classList.contains('open') === false) { document.getElementById('ytBox').innerHTML = ''; }
});
document.getElementById('ytModal').addEventListener('click', function (e) { if (e.target.closest('[data-modal-close]') || e.target === this) setTimeout(function () { document.getElementById('ytBox').innerHTML = ''; }, 50); });
</script>
<?php include ROOT_PATH . '/includes/footer.php'; ?>
