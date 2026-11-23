<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/_lib.php';
require_login();

$uid = current_user_id();
[$from, $to, $days] = behavior_range();
$logs = behavior_logs($uid, $from, $to);

if (($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="behaviour-report-' . $from . '-to-' . $to . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Date & time', 'Behaviour', 'Intensity (0-5)', 'Notes']);
    foreach (array_reverse($logs) as $l) {
        $notes = (string) $l['notes'];
        if ($notes !== '' && strpos('=+-@', $notes[0]) !== false) $notes = "'" . $notes;   // CSV-injection guard
        fputcsv($out, [$l['occurrence_time'], $l['type_name'], $l['intensity'], $notes]);
    }
    exit;
}

$me = db_one('SELECT first_name, last_name, username, children_details FROM user WHERE user_id = ?', 'i', $uid);
$total = count($logs);
$avg = $total ? round(array_sum(array_column($logs, 'intensity')) / $total, 1) : 0;
$byType = [];
foreach ($logs as $l) { $byType[$l['type_name']]['n'] = ($byType[$l['type_name']]['n'] ?? 0) + 1; $byType[$l['type_name']]['s'] = ($byType[$l['type_name']]['s'] ?? 0) + $l['intensity']; }
uasort($byType, fn($a, $b) => $b['n'] <=> $a['n']);

$page_title = 'Behaviour report'; $active = 'behavior';
include ROOT_PATH . '/includes/header.php';
?>
<div class="container">
    <div class="page-header no-print"><div><h1><i class="fa-solid fa-file-lines"></i> Behaviour report</h1><p>A printable summary you can share with teachers or therapists.</p></div>
        <div class="btn-group"><button class="btn btn-primary" onclick="window.print()"><i class="fa-solid fa-print"></i> Print / save PDF</button>
            <a class="btn btn-outline" href="?<?= e(http_build_query(['from' => $from, 'to' => $to, 'export' => 'csv'])) ?>"><i class="fa-solid fa-file-csv"></i> Download CSV</a></div></div>
    <?php behavior_nav('reports'); behavior_range_form($from, $to, $days); ?>

    <div class="card">
        <div class="flex items-center gap-2 mb-2"><img src="<?= e(asset('images/logo-mark.svg')) ?>" alt="" width="40" height="40" style="border-radius:11px">
            <div><h2 class="mb-0">Behaviour report</h2><div class="text-muted"><?= e(date('M j, Y', strtotime($from))) ?> – <?= e(date('M j, Y', strtotime($to))) ?> · prepared by <?= e(display_name($me)) ?></div></div></div>
        <?php if ($me['children_details']): ?><p><strong>About the child:</strong> <?= e($me['children_details']) ?></p><?php endif; ?>
        <div class="grid grid-3 mb-2">
            <div><div class="stat-value"><?= $total ?></div><div class="stat-label">Entries</div></div>
            <div><div class="stat-value"><?= $avg ?></div><div class="stat-label">Average intensity</div></div>
            <div><div class="stat-value"><?= count($byType) ?></div><div class="stat-label">Behaviour types</div></div>
        </div>
        <?php if (!$total): ?><div class="empty-state"><p class="mb-0">No entries in this period.</p></div><?php else: ?>
        <h3>Summary by behaviour</h3>
        <div class="table-wrap mb-2"><table class="table"><thead><tr><th>Behaviour</th><th>Entries</th><th>Avg intensity</th></tr></thead><tbody>
            <?php foreach ($byType as $n => $v): ?><tr><td><?= e($n) ?></td><td><?= $v['n'] ?></td><td><?= round($v['s'] / $v['n'], 1) ?></td></tr><?php endforeach; ?></tbody></table></div>
        <h3>All entries</h3>
        <div class="table-wrap"><table class="table"><thead><tr><th>Date &amp; time</th><th>Behaviour</th><th>Intensity</th><th>Notes</th></tr></thead><tbody>
            <?php foreach ($logs as $l): ?><tr><td class="truncate"><?= e(date('M j, Y g:i A', strtotime($l['occurrence_time']))) ?></td><td><?= e($l['type_name']) ?></td><td><?= (int) $l['intensity'] ?>/5</td><td><?= e($l['notes']) ?></td></tr><?php endforeach; ?></tbody></table></div>
        <?php endif; ?>
        <p class="text-muted text-xs mt-2 mb-0">Generated <?= e(date('M j, Y')) ?> by <?= e(SITE_NAME) ?>. This report is a parent-kept log and not a medical record.</p>
    </div>
</div>
<?php include ROOT_PATH . '/includes/footer.php'; ?>
