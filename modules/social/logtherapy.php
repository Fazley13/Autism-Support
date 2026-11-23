<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_login();

$uid = current_user_id();
$errors = [];
$edit = null;

if (is_post()) {
    csrf_check();
    $action = post_str('action');

    if ($action === 'save') {
        $sid = post_int('session_id');
        $therapist = post_str('therapist_name', 255);
        $date = str_replace('T', ' ', post_str('session_date'));
        $type = post_str('session_type', 255);
        $dur = post_int('duration_minutes');
        $notes = post_str('notes', 2000);
        $dt = DateTime::createFromFormat('Y-m-d H:i', substr($date, 0, 16));
        if ($therapist === '') $errors[] = 'Please enter the therapist\'s name.';
        if (!$dt) $errors[] = 'Please choose a valid date and time.';
        if ($type === '') $errors[] = 'Please enter the session type (e.g. Speech, OT, ABA).';
        if ($dur < 1 || $dur > 600) $errors[] = 'Duration must be between 1 and 600 minutes.';
        if (!$errors) {
            $tid = db_value('SELECT therapist_id FROM therapists WHERE therapist_name = ? LIMIT 1', 's', $therapist);
            if (!$tid) { db_exec('INSERT INTO therapists (therapist_name) VALUES (?)', 's', $therapist); $tid = db_insert_id(); }
            if ($sid) {
                db_exec('UPDATE therapy_sessions SET therapist_id=?, session_date=?, session_type=?, duration_minutes=?, notes=? WHERE session_id=? AND user_id=?',
                    'issisii', $tid, $dt->format('Y-m-d H:i:s'), $type, $dur, $notes, $sid, $uid);
                flash('Session updated.');
            } else {
                db_exec('INSERT INTO therapy_sessions (user_id, therapist_id, session_date, session_type, duration_minutes, notes) VALUES (?,?,?,?,?,?)',
                    'iissis', $uid, $tid, $dt->format('Y-m-d H:i:s'), $type, $dur, $notes);
                flash('Therapy session logged.');
            }
            redirect('modules/social/logtherapy.php');
        }
        $edit = ['session_id' => $sid, 'therapist_name' => $therapist, 'session_date' => $date, 'session_type' => $type, 'duration_minutes' => $dur, 'notes' => $notes];
    } elseif ($action === 'delete') {
        $sid = post_int('session_id');
        if (db_one('SELECT 1 AS x FROM therapy_sessions WHERE session_id=? AND user_id=?', 'ii', $sid, $uid)) {
            db_exec('DELETE FROM feedback WHERE session_id = ?', 'i', $sid);
            db_exec('DELETE FROM therapy_sessions WHERE session_id=? AND user_id=?', 'ii', $sid, $uid);
            flash('Session deleted.');
        }
        redirect('modules/social/logtherapy.php');
    } elseif ($action === 'feedback') {
        $sid = post_int('session_id'); $score = post_int('feedback_score'); $c = post_str('comments', 1000);
        if (!db_one('SELECT 1 AS x FROM therapy_sessions WHERE session_id=? AND user_id=?', 'ii', $sid, $uid)) { flash('Session not found.', 'danger'); redirect('modules/social/logtherapy.php'); }
        if ($score < 1 || $score > 5) { flash('Please pick a rating from 1 to 5.', 'warning'); redirect('modules/social/logtherapy.php'); }
        db_exec('DELETE FROM feedback WHERE session_id = ?', 'i', $sid);
        db_exec('INSERT INTO feedback (session_id, feedback_score, comments) VALUES (?,?,?)', 'iis', $sid, $score, $c);
        flash('Thanks – your feedback was saved.');
        redirect('modules/social/logtherapy.php');
    }
}

if (!$edit && ($eid = get_int('edit'))) {
    $edit = db_one('SELECT s.*, t.therapist_name FROM therapy_sessions s JOIN therapists t ON t.therapist_id = s.therapist_id WHERE s.session_id=? AND s.user_id=?', 'ii', $eid, $uid);
    if ($edit) $edit['session_date'] = str_replace(' ', 'T', substr($edit['session_date'], 0, 16));
}

$sessions = db_all('SELECT s.session_id, s.session_date, s.session_type, s.duration_minutes, s.notes, t.therapist_name,
        (SELECT f.feedback_score FROM feedback f WHERE f.session_id = s.session_id ORDER BY f.feedback_id DESC LIMIT 1) AS score,
        (SELECT f.comments FROM feedback f WHERE f.session_id = s.session_id ORDER BY f.feedback_id DESC LIMIT 1) AS fcomment
    FROM therapy_sessions s JOIN therapists t ON t.therapist_id = s.therapist_id WHERE s.user_id = ? ORDER BY s.session_date DESC', 'i', $uid);
$therapists = db_all('SELECT DISTINCT t.therapist_name FROM therapists t ORDER BY t.therapist_name');

/* analytics */
$totalMin = array_sum(array_column($sessions, 'duration_minutes'));
$scores = array_filter(array_column($sessions, 'score'), fn($x) => $x !== null);
$avgScore = $scores ? round(array_sum($scores) / count($scores), 1) : null;
$months = []; $byType = []; $byTher = [];
foreach ($sessions as $s) {
    $m = substr($s['session_date'], 0, 7); $months[$m] = ($months[$m] ?? 0) + 1;
    $byType[$s['session_type']] = ($byType[$s['session_type']] ?? 0) + (int) $s['duration_minutes'];
    $byTher[$s['therapist_name']] = ($byTher[$s['therapist_name']] ?? 0) + 1;
}
ksort($months); $months = array_slice($months, -12, null, true);
arsort($byType); arsort($byTher);

$page_title = 'Therapy log'; $active = 'profile';
$extra_js = ['https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js', 'assets/js/therapy.js'];
include ROOT_PATH . '/includes/header.php';
?>
<script>window.PAGE = <?= json_encode(['months' => $months, 'byType' => array_slice($byType, 0, 6, true), 'byTher' => array_slice($byTher, 0, 6, true)], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;</script>
<div class="container">
    <div class="page-header"><div><h1><i class="fa-solid fa-notes-medical"></i> Therapy log</h1><p>Keep track of every therapy session and how it went.</p></div>
        <button class="btn btn-primary" data-modal-open="#sessionModal" data-new data-modal-title="Log a therapy session"><i class="fa-solid fa-plus"></i> Log a session</button></div>

    <div class="grid grid-3 mb-3">
        <div class="card stat-card"><div class="stat-icon"><i class="fa-solid fa-calendar-check"></i></div><div><div class="stat-value"><?= count($sessions) ?></div><div class="stat-label">Sessions logged</div></div></div>
        <div class="card stat-card"><div class="stat-icon info"><i class="fa-solid fa-clock"></i></div><div><div class="stat-value"><?= round($totalMin / 60, 1) ?> h</div><div class="stat-label">Total therapy time</div></div></div>
        <div class="card stat-card"><div class="stat-icon accent"><i class="fa-solid fa-star"></i></div><div><div class="stat-value"><?= $avgScore ?? '–' ?></div><div class="stat-label">Average rating (of 5)</div></div></div>
    </div>

    <?php if ($sessions): ?>
    <div class="grid grid-3 mb-3" id="analytics">
        <div class="card"><p class="card-title-sm">Sessions per month</p><div class="chart-box"><canvas id="chMonths"></canvas></div></div>
        <div class="card"><p class="card-title-sm">Minutes by session type</p><div class="chart-box"><canvas id="chType"></canvas></div></div>
        <div class="card"><p class="card-title-sm">Sessions by therapist</p><div class="chart-box"><canvas id="chTher"></canvas></div></div>
    </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header"><h2><i class="fa-solid fa-list"></i> All sessions</h2></div>
        <?php if (!$sessions): ?>
            <div class="empty-state"><i class="fa-solid fa-notes-medical empty-icon"></i><h3>No sessions yet</h3><p>Log your child's first therapy session to start seeing progress.</p>
                <button class="btn btn-primary" data-modal-open="#sessionModal" data-new data-modal-title="Log a therapy session">Log a session</button></div>
        <?php else: ?>
        <div class="table-wrap"><table class="table">
            <thead><tr><th>Date</th><th>Therapist</th><th>Type</th><th>Duration</th><th>Rating</th><th>Notes</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($sessions as $s): ?>
                <tr>
                    <td class="truncate"><?= e(date('M j, Y · g:i A', strtotime($s['session_date']))) ?></td>
                    <td><?= e($s['therapist_name']) ?></td>
                    <td><span class="badge badge-info"><?= e($s['session_type']) ?></span></td>
                    <td><?= (int) $s['duration_minutes'] ?> min</td>
                    <td><?php if ($s['score']): ?><span class="rating-stars" title="<?= e($s['fcomment']) ?>"><?= str_repeat('★', (int) $s['score']) . str_repeat('☆', 5 - (int) $s['score']) ?></span>
                        <?php else: ?><button class="btn btn-outline btn-sm" data-fb="<?= (int) $s['session_id'] ?>">Rate</button><?php endif; ?></td>
                    <td class="text-muted text-sm"><?= e(truncate((string) $s['notes'], 70)) ?></td>
                    <td class="text-right" style="white-space:nowrap">
                        <?php if ($s['score']): ?><button class="btn btn-ghost btn-sm" data-fb="<?= (int) $s['session_id'] ?>" title="Change rating"><i class="fa-solid fa-star"></i></button><?php endif; ?>
                        <a class="btn btn-ghost btn-sm" href="?edit=<?= (int) $s['session_id'] ?>" title="Edit" data-modal-open="#sessionModal" data-modal-title="Edit session" data-fill='<?= e(json_encode(['session_id' => (int) $s['session_id'], 'therapist_name' => $s['therapist_name'], 'session_date' => str_replace(' ', 'T', substr($s['session_date'], 0, 16)), 'session_type' => $s['session_type'], 'duration_minutes' => (int) $s['duration_minutes'], 'notes' => $s['notes']], JSON_HEX_APOS)) ?>'><i class="fa-solid fa-pen"></i></a>
                        <form method="post" style="display:inline" data-confirm="Delete this session?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="session_id" value="<?= (int) $s['session_id'] ?>">
                            <button class="btn btn-ghost btn-sm" type="submit" title="Delete"><i class="fa-solid fa-trash"></i></button></form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody></table></div>
        <?php endif; ?>
    </div>
</div>

<div class="modal-backdrop<?= ($edit || $errors) ? ' open' : '' ?>" id="sessionModal">
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="sessionTitle">
        <div class="modal-header"><h3 id="sessionTitle"><?= !empty($edit['session_id']) ? 'Edit session' : 'Log a therapy session' ?></h3><button class="btn btn-ghost btn-sm" data-modal-close aria-label="Close"><i class="fa-solid fa-xmark"></i></button></div>
        <?php foreach ($errors as $er): ?><?= alert_html($er, 'danger') ?><?php endforeach; ?>
        <form method="post">
            <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="session_id" value="<?= (int) ($edit['session_id'] ?? 0) ?>">
            <div class="form-group"><label for="therapist_name">Therapist</label>
                <input type="text" id="therapist_name" name="therapist_name" list="therapistList" value="<?= e($edit['therapist_name'] ?? '') ?>" required>
                <datalist id="therapistList"><?php foreach ($therapists as $t): ?><option value="<?= e($t['therapist_name']) ?>"><?php endforeach; ?></datalist></div>
            <div class="form-row">
                <div class="form-group"><label for="session_date">Date &amp; time</label><input type="datetime-local" id="session_date" name="session_date" value="<?= e($edit['session_date'] ?? date('Y-m-d\TH:i')) ?>" required></div>
                <div class="form-group"><label for="duration_minutes">Duration (min)</label><input type="number" id="duration_minutes" name="duration_minutes" min="1" max="600" value="<?= e($edit['duration_minutes'] ?? 45) ?>" required></div>
            </div>
            <div class="form-group"><label for="session_type">Session type</label><input type="text" id="session_type" name="session_type" list="typeList" value="<?= e($edit['session_type'] ?? '') ?>" placeholder="e.g. Speech therapy" required>
                <datalist id="typeList"><option value="Speech therapy"><option value="Occupational therapy"><option value="Behavioural (ABA)"><option value="Physiotherapy"><option value="Social skills"><option value="Counselling"></datalist></div>
            <div class="form-group"><label for="notes">Notes</label><textarea id="notes" name="notes" maxlength="2000" placeholder="What went well? Anything to practise at home?"><?= e($edit['notes'] ?? '') ?></textarea></div>
            <div class="btn-group"><button class="btn btn-primary" type="submit">Save session</button><button class="btn btn-outline" type="button" data-modal-close>Cancel</button></div>
        </form>
    </div>
</div>

<div class="modal-backdrop" id="fbModal">
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="fbTitle">
        <div class="modal-header"><h3 id="fbTitle">How did the session go?</h3><button class="btn btn-ghost btn-sm" data-modal-close aria-label="Close"><i class="fa-solid fa-xmark"></i></button></div>
        <form method="post">
            <?= csrf_field() ?><input type="hidden" name="action" value="feedback"><input type="hidden" name="session_id" id="fbSession">
            <div class="form-group"><label for="feedback_score">Rating</label>
                <select id="feedback_score" name="feedback_score" required><option value="">Choose…</option><option value="5">★★★★★ Excellent</option><option value="4">★★★★ Good</option><option value="3">★★★ Okay</option><option value="2">★★ Difficult</option><option value="1">★ Very difficult</option></select></div>
            <div class="form-group"><label for="comments">Comments (optional)</label><textarea id="comments" name="comments" maxlength="1000"></textarea></div>
            <button class="btn btn-primary" type="submit">Save feedback</button>
        </form>
    </div>
</div>
<?php include ROOT_PATH . '/includes/footer.php'; ?>
