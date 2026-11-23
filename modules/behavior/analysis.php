<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/_lib.php';
require_login();

$uid = current_user_id();
[$from, $to, $days] = behavior_range();
$logs = behavior_logs($uid, $from, $to);

$total = count($logs);
$avg = $total ? round(array_sum(array_column($logs, 'intensity')) / $total, 1) : 0;
$byType = []; $perDay = []; $intByType = [];
foreach ($logs as $l) {
    $byType[$l['type_name']] = ($byType[$l['type_name']] ?? 0) + 1;
    $intByType[$l['type_name']][] = (int) $l['intensity'];
    $d = substr($l['occurrence_time'], 0, 10); $perDay[$d] = ($perDay[$d] ?? 0) + 1;
}
arsort($byType);
$avgByType = [];
foreach ($intByType as $k => $v) $avgByType[$k] = round(array_sum($v) / count($v), 2);
// fill every day of the range for a continuous line
$series = [];
for ($t = strtotime($from); $t <= strtotime($to) && count($series) < 400; $t = strtotime('+1 day', $t)) { $k = date('Y-m-d', $t); $series[$k] = $perDay[$k] ?? 0; }

// previous period for trend
$span = (int) ((strtotime($to) - strtotime($from)) / 86400) + 1;
$prevTo = date('Y-m-d', strtotime($from . ' -1 day')); $prevFrom = date('Y-m-d', strtotime($prevTo . " -" . ($span - 1) . " days"));
$prevCount = count(behavior_logs($uid, $prevFrom, $prevTo));
$trend = $prevCount ? round(($total - $prevCount) / $prevCount * 100) : null;
$top = $byType ? array_key_first($byType) : null;

$page_title = 'Behaviour analysis'; $active = 'behavior';
$extra_js = ['https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js', 'assets/js/behavior.js'];
include ROOT_PATH . '/includes/header.php';
?>
<script>window.PAGE = <?= json_encode(['kind' => 'analysis', 'series' => $series, 'byType' => $byType, 'avgByType' => $avgByType], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;</script>
<div class="container">
    <div class="page-header"><div><h1><i class="fa-solid fa-chart-pie"></i> Behaviour analysis</h1><p><?= e(date('M j', strtotime($from))) ?> – <?= e(date('M j, Y', strtotime($to))) ?></p></div></div>
    <?php behavior_nav('analysis'); behavior_range_form($from, $to, $days); ?>

    <?php if (!$total): ?>
        <div class="card empty-state"><i class="fa-solid fa-chart-column empty-icon"></i><h3>No data in this period</h3><p>Log behaviours to unlock charts and trends.</p><a class="btn btn-primary" href="<?= e(url('modules/reports/behavioral_feature.php')) ?>">Log a behaviour</a></div>
    <?php else: ?>
    <div class="grid grid-4 mb-3">
        <div class="card stat-card"><div class="stat-icon"><i class="fa-solid fa-clipboard-list"></i></div><div><div class="stat-value"><?= $total ?></div><div class="stat-label">Entries logged</div></div></div>
        <div class="card stat-card"><div class="stat-icon accent"><i class="fa-solid fa-gauge-high"></i></div><div><div class="stat-value"><?= $avg ?></div><div class="stat-label">Average intensity (0–5)</div></div></div>
        <div class="card stat-card"><div class="stat-icon info"><i class="fa-solid fa-tag"></i></div><div><div class="stat-value" style="font-size:1.15rem"><?= e($top) ?></div><div class="stat-label">Most common</div></div></div>
        <div class="card stat-card"><div class="stat-icon <?= $trend !== null && $trend > 0 ? 'warning' : 'success' ?>"><i class="fa-solid fa-arrow-<?= $trend !== null && $trend > 0 ? 'trend-up' : 'trend-down' ?>"></i></div>
            <div><div class="stat-value"><?= $trend === null ? '–' : ($trend > 0 ? '+' : '') . $trend . '%' ?></div><div class="stat-label">vs previous <?= $span ?> days</div></div></div>
    </div>
    <div class="card mb-2"><p class="card-title-sm">Entries per day</p><div class="chart-box"><canvas id="chSeries"></canvas></div></div>
    <div class="grid grid-2">
        <div class="card"><p class="card-title-sm">By behaviour</p><div class="chart-box"><canvas id="chType"></canvas></div></div>
        <div class="card"><p class="card-title-sm">Average intensity by behaviour</p><div class="chart-box"><canvas id="chInt"></canvas></div></div>
    </div>
    <?php endif; ?>
</div>
<?php include ROOT_PATH . '/includes/footer.php'; ?>
