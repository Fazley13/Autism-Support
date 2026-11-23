<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/_lib.php';
require_login();

if (is_post()) {
    csrf_check();
    $action = post_str('action');
    $name = post_str('type_name', 100);
    if ($action === 'add') {
        if ($name === '') flash('Please enter a name.', 'warning');
        elseif (db_one('SELECT 1 AS x FROM behavior_types WHERE type_name = ?', 's', $name)) flash('That behaviour type already exists.', 'warning');
        else { db_exec('INSERT INTO behavior_types (type_name) VALUES (?)', 's', $name); flash('Behaviour type added.'); }
    } elseif ($action === 'rename') {
        $id = post_int('id');
        if ($name === '') flash('Name cannot be empty.', 'warning');
        elseif (db_one('SELECT 1 AS x FROM behavior_types WHERE type_name = ? AND id <> ?', 'si', $name, $id)) flash('That name is already used.', 'warning');
        else { db_exec('UPDATE behavior_types SET type_name = ? WHERE id = ?', 'si', $name, $id); flash('Renamed.'); }
    } elseif ($action === 'delete') {
        $id = post_int('id');
        if (db_value('SELECT COUNT(*) FROM behavioral_logs WHERE behavior_type_id = ?', 'i', $id) > 0) flash('This type is used by existing log entries, so it cannot be deleted.', 'warning');
        else { db_exec('DELETE FROM behavior_types WHERE id = ?', 'i', $id); flash('Behaviour type deleted.'); }
    }
    redirect('modules/behavior/behavior.php');
}

$types = db_all('SELECT t.id, t.type_name, (SELECT COUNT(*) FROM behavioral_logs l WHERE l.behavior_type_id = t.id) AS uses FROM behavior_types t ORDER BY t.type_name');
$page_title = 'Behaviour types'; $active = 'behavior';
include ROOT_PATH . '/includes/header.php';
?>
<div class="container-sm">
    <div class="page-header"><div><h1><i class="fa-solid fa-tags"></i> Behaviour types</h1><p>The categories you use when logging behaviours. Shared by all parents.</p></div></div>
    <?php behavior_nav('types'); ?>
    <form class="card mb-2" method="post"><?= csrf_field() ?><input type="hidden" name="action" value="add">
        <label class="form-label" for="new_type">Add a new type</label>
        <div class="flex gap-1"><input class="flex-1" type="text" id="new_type" name="type_name" maxlength="100" placeholder="e.g. Meltdown, Stimming, Elopement" required><button class="btn btn-primary" type="submit"><i class="fa-solid fa-plus"></i> Add</button></div></form>
    <div class="card">
        <?php foreach ($types as $t): ?>
            <form method="post" class="person-row"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
                <input class="flex-1" type="text" name="type_name" value="<?= e($t['type_name']) ?>" maxlength="100" aria-label="Name" required>
                <span class="badge badge-muted"><?= (int) $t['uses'] ?> logs</span>
                <button class="btn btn-outline btn-sm" name="action" value="rename">Save</button>
                <button class="btn btn-danger btn-sm" name="action" value="delete" formnovalidate onclick="return confirm('Delete this behaviour type?')" aria-label="Delete"><i class="fa-solid fa-trash"></i></button>
            </form>
        <?php endforeach; ?>
        <?php if (!$types): ?><p class="text-muted mb-0">No types yet – add your first above.</p><?php endif; ?>
    </div>
</div>
<?php include ROOT_PATH . '/includes/footer.php'; ?>
