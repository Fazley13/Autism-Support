<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_login();

$uid = current_user_id();
$errors = [];
$edit = null;
$today = date('Y-m-d');

if (is_post()) {
    csrf_check();
    $in = json_input();
    $action = $in['action'] ?? post_str('action');

    if ($action === 'toggle') {
        $tid = (int) ($in['task_id'] ?? 0);
        $row = db_one('SELECT status FROM daily_planner WHERE task_id = ? AND user_id = ?', 'ii', $tid, $uid);
        if (!$row) json_out(['success' => false, 'message' => 'Task not found.'], 404);
        $new = $row['status'] === 'Completed' ? 'Pending' : 'Completed';
        db_exec('UPDATE daily_planner SET status = ? WHERE task_id = ? AND user_id = ?', 'sii', $new, $tid, $uid);
        json_out(['success' => true, 'status' => $new]);
    }
    if ($action === 'delete') {
        db_exec('DELETE FROM daily_planner WHERE task_id = ? AND user_id = ?', 'ii', post_int('task_id'), $uid);
        flash('Task deleted.');
        redirect('modules/planner/daily_planner.php?' . http_build_query(array_filter(['f' => $_GET['f'] ?? ''])));
    }
    if ($action === 'save') {
        $tid = post_int('task_id');
        $title = post_str('title', 255); $desc = post_str('description', 2000);
        $prio = pick(post_str('priority'), ['High', 'Medium', 'Low'], 'Medium');
        $deadline = post_str('deadline');
        if ($title === '') $errors[] = 'Please give the task a title.';
        if (!DateTime::createFromFormat('Y-m-d', $deadline)) $errors[] = 'Please choose a valid date.';
        if (!$errors) {
            if ($tid) {
                db_exec('UPDATE daily_planner SET title=?, description=?, priority=?, deadline=? WHERE task_id=? AND user_id=?', 'ssssii', $title, $desc, $prio, $deadline, $tid, $uid);
                flash('Task updated.');
            } else {
                db_exec("INSERT INTO daily_planner (user_id, title, description, priority, deadline, status) VALUES (?,?,?,?,?, 'Pending')", 'issss', $uid, $title, $desc, $prio, $deadline);
                flash('Task added.');
            }
            redirect('modules/planner/daily_planner.php');
        }
        $edit = ['task_id' => $tid, 'title' => $title, 'description' => $desc, 'priority' => $prio, 'deadline' => $deadline];
    }
}
if (!$edit && ($eid = get_int('edit'))) {
    $edit = db_one('SELECT * FROM daily_planner WHERE task_id = ? AND user_id = ?', 'ii', $eid, $uid);
}

$filter = pick((string) ($_GET['f'] ?? 'all'), ['all', 'today', 'upcoming', 'overdue', 'done'], 'all');
$where = ''; $params = [$uid]; $types = 'i';
switch ($filter) {
    case 'today':    $where = " AND deadline = '$today'"; break;
    case 'upcoming': $where = " AND deadline > '$today' AND status = 'Pending'"; break;
    case 'overdue':  $where = " AND deadline < '$today' AND status = 'Pending'"; break;
    case 'done':     $where = " AND status = 'Completed'"; break;
}
$tasks = db_all("SELECT * FROM daily_planner WHERE user_id = ? $where ORDER BY status = 'Completed', deadline ASC, FIELD(priority,'High','Medium','Low')", $types, ...$params);

$todayTotal = (int) db_value('SELECT COUNT(*) FROM daily_planner WHERE user_id = ? AND deadline = ?', 'is', $uid, $today);
$todayDone = (int) db_value("SELECT COUNT(*) FROM daily_planner WHERE user_id = ? AND deadline = ? AND status = 'Completed'", 'is', $uid, $today);
$overdue = (int) db_value("SELECT COUNT(*) FROM daily_planner WHERE user_id = ? AND deadline < ? AND status = 'Pending'", 'is', $uid, $today);
$pct = $todayTotal ? round($todayDone / $todayTotal * 100) : 0;

$counts = [];
foreach (['all' => '', 'today' => " AND deadline = '$today'", 'upcoming' => " AND deadline > '$today' AND status='Pending'", 'overdue' => " AND deadline < '$today' AND status='Pending'", 'done' => " AND status='Completed'"] as $k => $w) {
    $counts[$k] = (int) db_value("SELECT COUNT(*) FROM daily_planner WHERE user_id = ? $w", 'i', $uid);
}
$labels = ['all' => 'All', 'today' => 'Today', 'upcoming' => 'Upcoming', 'overdue' => 'Overdue', 'done' => 'Completed'];

$page_title = 'Daily planner'; $active = 'planner';
$extra_js = ['assets/js/planner.js'];
include ROOT_PATH . '/includes/header.php';
?>
<div class="container-sm">
    <div class="page-header"><div><h1><i class="fa-solid fa-list-check"></i> Daily planner</h1><p><?= e(date('l, F j')) ?> · small steps, predictable routines.</p></div>
        <button class="btn btn-primary" data-modal-open="#taskModal" data-new data-modal-title="New task"><i class="fa-solid fa-plus"></i> Add task</button></div>

    <div class="card card-soft mb-2">
        <div class="flex justify-between items-center mb-1"><strong>Today's progress</strong><span class="text-muted"><?= $todayDone ?> of <?= $todayTotal ?> done</span></div>
        <div class="progress" role="progressbar" aria-valuenow="<?= $pct ?>" aria-valuemin="0" aria-valuemax="100"><span style="width:<?= $pct ?>%"></span></div>
        <?php if ($overdue): ?><p class="text-sm mt-2 mb-0"><span class="badge badge-danger"><i class="fa-solid fa-clock"></i> <?= $overdue ?> overdue</span> <a href="?f=overdue">Review them</a></p><?php endif; ?>
    </div>

    <div class="pills mb-2">
        <?php foreach ($labels as $k => $l): ?><a class="pill<?= $filter === $k ? ' active' : '' ?>" href="?f=<?= $k ?>"><?= $l ?> (<?= $counts[$k] ?>)</a><?php endforeach; ?>
    </div>

    <?php if (!$tasks): ?>
        <div class="card empty-state"><i class="fa-regular fa-calendar-check empty-icon"></i><h3>Nothing here</h3><p>Add a task to build today's routine – even "morning brush teeth" counts.</p>
            <button class="btn btn-primary" data-modal-open="#taskModal" data-new data-modal-title="New task">Add your first task</button></div>
    <?php endif; ?>

    <?php foreach ($tasks as $t):
        $done = $t['status'] === 'Completed'; $late = !$done && $t['deadline'] < $today; ?>
        <div class="task p-<?= e(strtolower($t['priority'])) ?><?= $done ? ' done' : '' ?>" data-task="<?= (int) $t['task_id'] ?>">
            <input type="checkbox" class="task-check" <?= $done ? 'checked' : '' ?> aria-label="Mark “<?= e($t['title']) ?>” complete" style="margin-top:.35rem;width:1.3rem;height:1.3rem">
            <div class="flex-1">
                <div class="task-title"><?= e($t['title']) ?></div>
                <?php if ($t['description']): ?><div class="text-muted text-sm"><?= e($t['description']) ?></div><?php endif; ?>
                <div class="mt-1 flex gap-1 flex-wrap">
                    <span class="badge <?= $t['priority'] === 'High' ? 'badge-danger' : ($t['priority'] === 'Medium' ? 'badge-warning' : 'badge-success') ?>"><?= e($t['priority']) ?></span>
                    <span class="badge badge-muted"><i class="fa-regular fa-calendar"></i> <?= $t['deadline'] === $today ? 'Today' : e(date('M j', strtotime($t['deadline']))) ?></span>
                    <?php if ($late): ?><span class="badge badge-danger">Overdue</span><?php endif; ?>
                </div>
            </div>
            <div class="task-actions">
                <a class="btn btn-ghost btn-sm" href="?edit=<?= (int) $t['task_id'] ?>" aria-label="Edit task" data-modal-open="#taskModal" data-modal-title="Edit task" data-fill='<?= e(json_encode(['task_id' => (int) $t['task_id'], 'title' => $t['title'], 'description' => $t['description'], 'priority' => $t['priority'], 'deadline' => $t['deadline']], JSON_HEX_APOS)) ?>'><i class="fa-solid fa-pen"></i></a>
                <form method="post" data-confirm="Delete this task?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="task_id" value="<?= (int) $t['task_id'] ?>">
                    <button class="btn btn-ghost btn-sm" aria-label="Delete"><i class="fa-solid fa-trash"></i></button></form>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="modal-backdrop<?= ($edit || $errors) ? ' open' : '' ?>" id="taskModal">
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="taskTitle">
        <div class="modal-header"><h3 id="taskTitle"><?= !empty($edit['task_id']) ? 'Edit task' : 'New task' ?></h3><button class="btn btn-ghost btn-sm" data-modal-close aria-label="Close"><i class="fa-solid fa-xmark"></i></button></div>
        <?php foreach ($errors as $er): ?><?= alert_html($er, 'danger') ?><?php endforeach; ?>
        <form method="post">
            <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="task_id" value="<?= (int) ($edit['task_id'] ?? 0) ?>">
            <div class="form-group"><label for="title">Title</label><input type="text" id="title" name="title" maxlength="255" value="<?= e($edit['title'] ?? '') ?>" required></div>
            <div class="form-group"><label for="description">Details (optional)</label><textarea id="description" name="description" maxlength="2000"><?= e($edit['description'] ?? '') ?></textarea></div>
            <div class="form-row">
                <div class="form-group"><label for="priority">Priority</label><select id="priority" name="priority"><?php foreach (['High', 'Medium', 'Low'] as $p): ?><option<?= ($edit['priority'] ?? 'Medium') === $p ? ' selected' : '' ?>><?= $p ?></option><?php endforeach; ?></select></div>
                <div class="form-group"><label for="deadline">Date</label><input type="date" id="deadline" name="deadline" value="<?= e($edit['deadline'] ?? $today) ?>" required></div>
            </div>
            <div class="btn-group"><button class="btn btn-primary" type="submit">Save task</button><button class="btn btn-outline" type="button" data-modal-close>Cancel</button></div>
        </form>
    </div>
</div>
<?php include ROOT_PATH . '/includes/footer.php'; ?>
