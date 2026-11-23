<?php
/** Helpers for behaviour analysis pages. */
require_once ROOT_PATH . '/includes/behavior_nav.php';

/** Returns [from 'Y-m-d', to 'Y-m-d', days] from ?days=7|30|90 or ?from=&to= */
function behavior_range(): array
{
    $to = date('Y-m-d');
    $from = $_GET['from'] ?? ''; $toIn = $_GET['to'] ?? '';
    if (DateTime::createFromFormat('Y-m-d', $from) && DateTime::createFromFormat('Y-m-d', $toIn) && $from <= $toIn) {
        return [$from, $toIn, 0];
    }
    $days = (int) ($_GET['days'] ?? 30);
    if (!in_array($days, [7, 30, 90, 365], true)) $days = 30;
    return [date('Y-m-d', strtotime("-" . ($days - 1) . " days")), $to, $days];
}

function behavior_logs(int $uid, string $from, string $to): array
{
    return db_all('SELECT l.id, l.occurrence_time, l.notes, l.intensity, t.type_name, l.behavior_type_id
        FROM behavioral_logs l JOIN behavior_types t ON t.id = l.behavior_type_id
        WHERE l.user_id = ? AND l.occurrence_time >= ? AND l.occurrence_time <= ? ORDER BY l.occurrence_time DESC',
        'iss', $uid, $from . ' 00:00:00', $to . ' 23:59:59');
}

function behavior_range_form(string $from, string $to, int $days): void
{
    ?>
    <form class="card mb-2 no-print" method="get"><div class="flex gap-2 flex-wrap items-center">
        <div class="pills"><?php foreach ([7 => '7 days', 30 => '30 days', 90 => '90 days', 365 => '1 year'] as $d => $l): ?>
            <a class="pill<?= $days === $d ? ' active' : '' ?>" href="?days=<?= $d ?>"><?= $l ?></a><?php endforeach; ?></div>
        <span class="text-muted">or</span>
        <input type="date" name="from" value="<?= e($from) ?>" aria-label="From" style="width:auto"> <span>–</span>
        <input type="date" name="to" value="<?= e($to) ?>" aria-label="To" style="width:auto">
        <button class="btn btn-outline btn-sm" type="submit">Apply</button>
    </div></form>
    <?php
}

function intensity_label(int $i): string
{
    return ['None', 'Very mild', 'Mild', 'Moderate', 'Strong', 'Severe'][max(0, min(5, $i))];
}
function intensity_badge(int $i): string
{
    $cls = $i >= 4 ? 'badge-danger' : ($i >= 3 ? 'badge-warning' : ($i >= 1 ? 'badge-info' : 'badge-muted'));
    return '<span class="badge ' . $cls . '">' . $i . ' · ' . e(intensity_label($i)) . '</span>';
}
