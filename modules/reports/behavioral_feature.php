<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../behavior/_lib.php';
require_login();

$uid = current_user_id();
$errors = [];
$edit = null;

if (is_post()) {
    csrf_check();
    $action = post_str('action');
    if ($action === 'save') {
        $id = post_int('log_id');
        $type = post_int('behavior_type_id');
        $when = str_replace('T', ' ', post_str('occurrence_time'));
        $int = post_int('intensity', 0);
        $notes = post_str('notes', 2000);
        $dt = DateTime::createFromFormat('Y-m-d H:i', substr($when, 0, 16));
        if (!db_one('SELECT 1 AS x FROM behavior_types WHERE id = ?', 'i', $type)) $errors[] = 'Please choose a behaviour type.';
        if (!$dt) $errors[] = 'Please choose a valid date and time.';
        if ($int < 0 || $int > 5) $errors[] = 'Intensity must be between 0 and 5.';
        if (!$errors) {
            if ($id) {
                db_exec('UPDATE behavioral_logs SET behavior_type_id=?, occurrence_time=?, intensity=?, notes=? WHERE id=? AND user_id=?', 'isisii', $type, $dt->format('Y-m-d H:i:s'), $int, $notes, $id, $uid);
                flash('Entry updated.');
            } else {
                db_exec('INSERT INTO behavioral_logs (user_id, behavior_type_id, occurrence_time, intensity, notes) VALUES (?,?,?,?,?)', 'iisis', $uid, $type, $dt->format('Y-m-d H:i:s'), $int, $notes);
                flash('Behaviour logged. Thank you for tracking!');
            }
            redirect('modules/reports/behavioral_feature.php');
        }
        $edit = ['id' => $id, 'behavior_type_id' => $type, 'occurrence_time' => $when, 'intensity' => $int, 'notes' => $notes];
    } elseif ($action === 'delete') {
        db_exec('DELETE FROM behavioral_logs WHERE id = ? AND user_id = ?', 'ii', post_int('log_id'), $uid);
        flash('Entry deleted.');
        redirect('modules/reports/behavioral_feature.php');
    }
}
if (!$edit && ($eid = get_int('edit'))) {
    $edit = db_one('SELECT * FROM behavioral_logs WHERE id = ? AND user_id = ?', 'ii', $eid, $uid);
    if ($edit) $edit['occurrence_time'] = str_replace(' ', 'T', substr($edit['occurrence_time'], 0, 16));
}

$types = db_all('SELECT id, type_name FROM behavior_types ORDER BY type_name');
$fType = get_int('type');
$fFrom = DateTime::createFromFormat('Y-m-d', $_GET['from'] ?? '') ? $_GET['from'] : '';
$fTo = DateTime::createFromFormat('Y-m-d', $_GET['to'] ?? '') ? $_GET['to'] : '';
$sql = 'SELECT l.id, l.behavior_type_id, l.occurrence_time, l.notes, l.intensity, t.type_name FROM behavioral_logs l JOIN behavior_types t ON t.id = l.behavior_type_id WHERE l.user_id = ?';
$ty = 'i'; $pa = [$uid];
if ($fType) { $sql .= ' AND l.behavior_type_id = ?'; $ty .= 'i'; $pa[] = $fType; }
if ($fFrom) { $sql .= ' AND l.occurrence_time >= ?'; $ty .= 's'; $pa[] = $fFrom . ' 00:00:00'; }
if ($fTo)   { $sql .= ' AND l.occurrence_time <= ?'; $ty .= 's'; $pa[] = $fTo . ' 23:59:59'; }
$logs = db_all($sql . ' ORDER BY l.occurrence_time DESC LIMIT 100', $ty, ...$pa);

$page_title = 'Behaviour tracking'; $active = 'behavior';
$extra_js = ['assets/js/behavior.js'];
include ROOT_PATH . '/includes/header.php';
$cur = $edit['intensity'] ?? 2;
?>
<div class="container">
    <div class="page-header"><div><h1><i class="fa-solid fa-chart-line"></i> Behaviour tracking</h1><p>Record what happened, how strong it was, and what was going on around it.</p></div>
        <?php if ($types): ?><button class="btn btn-primary" data-modal-open="#logModal" data-new data-modal-title="Log a behaviour"><i class="fa-solid fa-plus"></i> Log behaviour</button><?php endif; ?></div>
    <?php behavior_nav('log'); ?>

    <?php if (!$types): ?><?= alert_html('No behaviour types exist yet. Add some first so you can start logging.', 'warning') ?>
        <a class="btn btn-primary" href="<?= e(url('modules/behavior/behavior.php')) ?>">Add behaviour types</a><?php endif; ?>

    <form class="card mb-2" method="get"><div class="form-row" style="align-items:end">
        <div class="form-group mb-0"><label for="ftype">Behaviour</label><select id="ftype" name="type"><option value="">All</option><?php foreach ($types as $t): ?><option value="<?= (int) $t['id'] ?>"<?= $fType === (int) $t['id'] ? ' selected' : '' ?>><?= e($t['type_name']) ?></option><?php endforeach; ?></select></div>
        <div class="form-group mb-0"><label for="ffrom">From</label><input type="date" id="ffrom" name="from" value="<?= e($fFrom) ?>"></div>
        <div class="form-group mb-0"><label for="fto">To</label><input type="date" id="fto" name="to" value="<?= e($fTo) ?>"></div>
        <div class="form-group mb-0 btn-group"><button class="btn btn-outline" type="submit"><i class="fa-solid fa-filter"></i> Filter</button><a class="btn btn-ghost" href="?">Reset</a></div>
    </div></form>

    <div class="card">
        <?php if (!$logs): ?>
            <div class="empty-state"><i class="fa-regular fa-clipboard empty-icon"></i><h3>No entries yet</h3><p>Logging a few incidents a week is enough to start seeing helpful patterns.</p>
                <?php if ($types): ?><button class="btn btn-primary" data-modal-open="#logModal" data-new data-modal-title="Log a behaviour">Log the first one</button><?php endif; ?></div>
        <?php else: ?>
        <div class="table-wrap"><table class="table"><thead><tr><th>When</th><th>Behaviour</th><th>Intensity</th><th>Notes</th><th></th></tr></thead><tbody>
            <?php foreach ($logs as $l): ?>
                <tr><td class="truncate"><?= e(date('M j, Y · g:i A', strtotime($l['occurrence_time']))) ?></td>
                    <td><strong><?= e($l['type_name']) ?></strong></td><td><?= intensity_badge((int) $l['intensity']) ?></td>
                    <td class="text-muted text-sm"><?= e(truncate((string) $l['notes'], 90)) ?></td>
                    <td class="text-right" style="white-space:nowrap"><a class="btn btn-ghost btn-sm" href="?edit=<?= (int) $l['id'] ?>" aria-label="Edit" data-modal-open="#logModal" data-modal-title="Edit entry" data-fill='<?= e(json_encode(['log_id' => (int) $l['id'], 'behavior_type_id' => (int) $l['behavior_type_id'], 'occurrence_time' => str_replace(' ', 'T', substr($l['occurrence_time'], 0, 16)), 'intensity' => (int) $l['intensity'], 'notes' => $l['notes']], JSON_HEX_APOS)) ?>'><i class="fa-solid fa-pen"></i></a>
                        <form method="post" style="display:inline" data-confirm="Delete this entry?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="log_id" value="<?= (int) $l['id'] ?>"><button class="btn btn-ghost btn-sm" aria-label="Delete"><i class="fa-solid fa-trash"></i></button></form></td></tr>
            <?php endforeach; ?></tbody></table></div>
        <?php endif; ?>
    </div>
</div>

<div class="modal-backdrop<?= ($edit || $errors) ? ' open' : '' ?>" id="logModal">
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="logTitle">
        <div class="modal-header"><h3 id="logTitle"><?= !empty($edit['id']) ? 'Edit entry' : 'Log a behaviour' ?></h3><button class="btn btn-ghost btn-sm" data-modal-close aria-label="Close"><i class="fa-solid fa-xmark"></i></button></div>
        <?php foreach ($errors as $er): ?><?= alert_html($er, 'danger') ?><?php endforeach; ?>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="log_id" value="<?= (int) ($edit['id'] ?? 0) ?>">
            <div class="form-row">
                <div class="form-group"><label for="behavior_type_id">Behaviour</label><select id="behavior_type_id" name="behavior_type_id" required><option value="">Choose…</option>
                    <?php foreach ($types as $t): ?><option value="<?= (int) $t['id'] ?>"<?= (int) ($edit['behavior_type_id'] ?? 0) === (int) $t['id'] ? ' selected' : '' ?>><?= e($t['type_name']) ?></option><?php endforeach; ?></select></div>
                <div class="form-group"><label for="occurrence_time">When</label><input type="datetime-local" id="occurrence_time" name="occurrence_time" max="<?= date('Y-m-d\TH:i') ?>" value="<?= e($edit['occurrence_time'] ?? date('Y-m-d\TH:i')) ?>" required></div>
            </div>
            <div class="form-group"><label for="intensity">Intensity: <strong id="intLabel"></strong></label>
                <input type="range" id="intensity" name="intensity" min="0" max="5" step="1" value="<?= (int) $cur ?>" data-labels='["None","Very mild","Mild","Moderate","Strong","Severe"]' data-label-target="#intLabel">
                <div class="flex justify-between text-xs text-muted"><span>0 none</span><span>5 severe</span></div></div>
            <div class="form-group"><label for="notes">What was happening? (triggers, place, what helped)</label><textarea id="notes" name="notes" maxlength="2000"><?= e($edit['notes'] ?? '') ?></textarea></div>
            <div class="btn-group"><button class="btn btn-primary" type="submit">Save entry</button><button class="btn btn-outline" type="button" data-modal-close>Cancel</button></div>
        </form>
    </div>
</div>
<?php include ROOT_PATH . '/includes/footer.php'; ?>
