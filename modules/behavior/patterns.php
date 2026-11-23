<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/_lib.php';
require_login();

$uid = current_user_id();
[$from, $to, $days] = behavior_range();
$logs = behavior_logs($uid, $from, $to);

$hours = array_fill(0, 24, 0); $wd = array_fill(0, 7, 0); $grid = array_fill(0, 7, array_fill(0, 24, 0)); $words = [];
$stop = array_flip(explode(' ', 'the a an and or but if of to in on at for with was were is are be been it its he she they them his her their this that these those then than so as by from up down out about after before while when what which who we i you my our me not no yes had has have did do does very just also into over again more some any'));
foreach ($logs as $l) {
    $ts = strtotime($l['occurrence_time']); $h = (int) date('G', $ts); $w = (int) date('N', $ts) - 1;
    $hours[$h]++; $wd[$w]++; $grid[$w][$h]++;
    foreach (preg_split('/[^\p{L}]+/u', mb_strtolower((string) $l['notes']), -1, PREG_SPLIT_NO_EMPTY) as $word) {
        if (mb_strlen($word) > 3 && !isset($stop[$word])) $words[$word] = ($words[$word] ?? 0) + 1;
    }
}
arsort($words); $words = array_slice($words, 0, 12, true);
$max = max(1, ...array_map('max', $grid));
$dayNames = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

$insights = [];
if ($logs) {
    $ph = array_keys($hours, max($hours))[0]; $pd = array_keys($wd, max($wd))[0];
    $insights[] = 'Most entries happen around ' . date('g A', strtotime("$ph:00")) . '–' . date('g A', strtotime((($ph + 1) % 24) . ':00')) . '.';
    $insights[] = ['Mondays','Tuesdays','Wednesdays','Thursdays','Fridays','Saturdays','Sundays'][$pd] . ' are the busiest' . ($pd >= 5 ? ' (weekend).' : ' (weekday).');
    $hi = array_filter($logs, fn($l) => $l['intensity'] >= 4);
    if ($hi) $insights[] = count($hi) . ' of ' . count($logs) . ' entries were strong or severe (4–5) – worth discussing with your therapist.';
    if ($words) $insights[] = 'Words that appear most in your notes: ' . implode(', ', array_slice(array_keys($words), 0, 4)) . '.';
}

$page_title = 'Behaviour patterns'; $active = 'behavior';
$extra_js = ['https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js', 'assets/js/behavior.js'];
include ROOT_PATH . '/includes/header.php';
?>
<script>window.PAGE = <?= json_encode(['kind' => 'patterns', 'hours' => $hours, 'wd' => array_combine($dayNames, $wd)], JSON_HEX_TAG) ?>;</script>
<div class="container">
    <div class="page-header"><div><h1><i class="fa-solid fa-clock"></i> Behaviour patterns</h1><p>When do things happen – and what's around them?</p></div></div>
    <?php behavior_nav('patterns'); behavior_range_form($from, $to, $days); ?>

    <?php if (!$logs): ?>
        <div class="card empty-state"><i class="fa-regular fa-clock empty-icon"></i><h3>Not enough data yet</h3><p>Patterns appear after a few entries.</p><a class="btn btn-primary" href="<?= e(url('modules/reports/behavioral_feature.php')) ?>">Log a behaviour</a></div>
    <?php else: ?>
        <div class="card card-soft mb-2"><p class="card-title-sm"><i class="fa-solid fa-lightbulb"></i> Insights</p><ul class="mb-0"><?php foreach ($insights as $i): ?><li><?= e($i) ?></li><?php endforeach; ?></ul></div>
        <div class="grid grid-2 mb-2">
            <div class="card"><p class="card-title-sm">By hour of day</p><div class="chart-box"><canvas id="chHour"></canvas></div></div>
            <div class="card"><p class="card-title-sm">By weekday</p><div class="chart-box"><canvas id="chDay"></canvas></div></div>
        </div>
        <div class="card mb-2"><p class="card-title-sm">Heat map – weekday × hour</p>
            <div class="table-wrap" style="border:0"><div class="heat" style="display:grid;grid-template-columns:44px repeat(24,minmax(18px,1fr));gap:3px;min-width:640px">
                <span></span><?php for ($h = 0; $h < 24; $h++): ?><span class="text-xs text-muted text-center"><?= $h ?></span><?php endfor; ?>
                <?php foreach ($dayNames as $di => $dn): ?><span class="text-xs text-muted"><?= $dn ?></span>
                    <?php for ($h = 0; $h < 24; $h++): $v = $grid[$di][$h]; ?><span title="<?= $dn ?> <?= $h ?>:00 – <?= $v ?> entries" style="height:22px;border-radius:4px;background:color-mix(in srgb,var(--primary) <?= $v ? (15 + round($v / $max * 85)) : 0 ?>%,var(--surface-2))"></span><?php endfor; ?>
                <?php endforeach; ?>
            </div></div></div>
        <?php if ($words): ?><div class="card"><p class="card-title-sm">Common words in your notes (possible triggers)</p>
            <div class="pills"><?php foreach ($words as $w => $c): ?><span class="badge badge-info"><?= e($w) ?> · <?= $c ?></span><?php endforeach; ?></div></div><?php endif; ?>
    <?php endif; ?>
</div>
<?php include ROOT_PATH . '/includes/footer.php'; ?>
