<?php
/** Tabs shared by the behaviour pages.  Usage: behavior_nav('analysis'); */
function behavior_nav(string $current): void
{
    $items = [
        'log'      => ['modules/reports/behavioral_feature.php', 'fa-pen-to-square', 'Log'],
        'analysis' => ['modules/behavior/analysis.php',          'fa-chart-pie',      'Analysis'],
        'patterns' => ['modules/behavior/patterns.php',          'fa-clock',          'Patterns'],
        'reports'  => ['modules/behavior/reports.php',           'fa-file-lines',     'Reports'],
        'types'    => ['modules/behavior/behavior.php',          'fa-tags',           'Behaviour types'],
    ];
    echo '<div class="tabs no-print" role="tablist">';
    foreach ($items as $k => [$href, $icon, $label]) {
        echo '<a class="tab' . ($k === $current ? ' active' : '') . '" href="' . e(url($href)) . '"><i class="fa-solid ' . $icon . '"></i> ' . e($label) . '</a>';
    }
    echo '</div>';
}
