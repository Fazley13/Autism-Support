<?php
/**
 * Shared page header + navbar.
 *
 * Usage in a page (after bootstrap + require_login):
 *     $page_title = 'Newsfeed';          // required
 *     $active     = 'newsfeed';          // nav key to highlight (optional)
 *     $extra_css  = ['assets/css/x.css'];// optional extra stylesheets
 *     $extra_js   = ['assets/js/x.js'];  // optional extra scripts (loaded in footer, `defer`)
 *     $body_class = 'auth-body';         // optional
 *     include ROOT_PATH . '/includes/header.php';
 *     ... page content ...
 *     include ROOT_PATH . '/includes/footer.php';
 *
 * Nav keys: newsfeed planner events behavior resources qna messages notifications profile
 */
require_once __DIR__ . '/bootstrap.php';

$page_title    = $page_title ?? SITE_NAME;
$active        = $active ?? '';
$body_class    = $body_class ?? '';
$meta_desc     = $meta_desc ?? 'ParentConnect is a calm, supportive space for parents and caregivers of autistic children: community, behaviour tracking, daily planning, events, resources and expert Q&A.';
$me            = isLoggedIn() ? current_user() : null;
$notifCount    = $me ? unread_notifications() : 0;
$msgCount      = $me ? unread_messages() : 0;

$nav = [
    'newsfeed'  => ['modules/social/newsfeed.php',          'fa-house',            'Newsfeed'],
    'planner'   => ['modules/planner/daily_planner.php',    'fa-list-check',       'Planner'],
    'events'    => ['modules/planner/events.php',           'fa-calendar-days',    'Events'],
    'behavior'  => ['modules/reports/behavioral_feature.php','fa-chart-line',      'Behavior'],
    'resources' => ['modules/reports/resource.php',         'fa-book-open',        'Resources'],
    'qna'       => ['modules/reports/qna.php',              'fa-circle-question',  'Q&A'],
    'messages'  => ['modules/social/messages.php',          'fa-envelope',         'Messages'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($page_title) ?> · <?= e(SITE_NAME) ?></title>
    <meta name="description" content="<?= e($meta_desc) ?>">
    <meta name="theme-color" content="#2a747c">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <meta name="base-url" content="<?= e(BASE_URL) ?>">
    <link rel="icon" type="image/svg+xml" href="<?= e(asset('images/favicon.svg')) ?>">
    <link rel="icon" type="image/png" sizes="32x32" href="<?= e(asset('images/favicon-32.png')) ?>">
    <link rel="apple-touch-icon" href="<?= e(asset('images/apple-touch-icon.png')) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&family=Fraunces:opsz,wght@9..144,600;9..144,700;9..144,800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">
    <script>try{var t=localStorage.getItem('pc-theme');if(t)document.documentElement.setAttribute('data-theme',t);}catch(e){}</script>
    <link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>?v=<?= (int) @filemtime(ROOT_PATH . '/assets/css/style.css') ?>">
<?php foreach (($extra_css ?? []) as $css): ?>
    <link rel="stylesheet" href="<?= e(url($css)) ?>">
<?php endforeach; ?>
</head>
<body class="<?= e($body_class) ?>" data-module="<?= e($active) ?>">
<a class="skip-link" href="#main">Skip to content</a>

<header class="navbar" id="navbar">
    <div class="navbar-inner">
        <a class="brand" href="<?= e(url($me ? 'modules/social/newsfeed.php' : 'index.php')) ?>" aria-label="<?= e(SITE_NAME) ?> home">
            <img src="<?= e(asset('images/logo-mark.svg')) ?>" alt="" width="38" height="38">
            <span class="brand-text"><span class="brand-name"><?= e(SITE_NAME) ?></span><span class="brand-tag">Autism Support</span></span>
        </a>

<?php if ($me): ?>
        <nav class="nav-links" aria-label="Main">
            <div class="mobile-search">
                <form action="<?= e(url('modules/social/search_results.php')) ?>" method="get" role="search">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="search" name="q" placeholder="Search people, posts, resources…" aria-label="Search" required>
                </form>
            </div>
<?php foreach ($nav as $key => [$href, $icon, $label]): ?>
            <a class="nav-link<?= $active === $key ? ' active' : '' ?>" href="<?= e(url($href)) ?>" title="<?= e($label) ?>"<?= $active === $key ? ' aria-current="page"' : '' ?>>
                <i class="fa-solid <?= $icon ?>"></i><span class="nav-label"><?= e($label) ?></span>
<?php if ($key === 'messages' && $msgCount > 0): ?><span class="badge-dot"><?= $msgCount > 99 ? '99+' : $msgCount ?></span><?php endif; ?>
            </a>
<?php endforeach; ?>
        </nav>

        <div class="nav-right">
            <form class="nav-search" action="<?= e(url('modules/social/search_results.php')) ?>" method="get" role="search">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="search" name="q" placeholder="Search…" aria-label="Search" value="<?= e($_GET['q'] ?? '') ?>" required>
            </form>

            <a class="icon-btn search-icon-btn" href="<?= e(url('modules/social/search_results.php')) ?>" title="Search" aria-label="Search"><i class="fa-solid fa-magnifying-glass"></i></a>
            <button class="icon-btn" type="button" id="themeToggle" title="Change look: light / dark / calm" aria-label="Change theme"><i class="fa-solid fa-circle-half-stroke"></i></button>
            <a class="icon-btn" href="<?= e(url('modules/reports/notification.php')) ?>" title="Notifications" aria-label="Notifications<?= $notifCount ? ', ' . $notifCount . ' unread' : '' ?>">
                <i class="fa-solid fa-bell"></i>
<?php if ($notifCount > 0): ?><span class="badge-dot"><?= $notifCount > 99 ? '99+' : $notifCount ?></span><?php endif; ?>
            </a>

            <div class="dropdown">
                <button class="user-chip" type="button" data-dropdown aria-haspopup="true" aria-expanded="false">
                    <img class="avatar avatar-sm" src="<?= e(avatar_url($me)) ?>" alt="">
                    <span><?= e($me['username']) ?></span>
                    <i class="fa-solid fa-chevron-down text-xs text-muted"></i>
                </button>
                <div class="dropdown-menu" role="menu">
                    <div class="menu-head">
                        <strong><?= e(display_name($me)) ?></strong><br>
                        <span class="text-muted text-sm"><?= e($me['email']) ?></span>
                    </div>
                    <a href="<?= e(url('modules/social/view_profile.php?user_id=' . $me['user_id'])) ?>"><i class="fa-solid fa-user"></i> My profile</a>
                    <a href="<?= e(url('modules/social/profile.php')) ?>"><i class="fa-solid fa-user-pen"></i> Edit profile &amp; password</a>
                    <a href="<?= e(url('modules/social/logtherapy.php')) ?>"><i class="fa-solid fa-notes-medical"></i> Therapy log</a>
                    <a href="<?= e(url('modules/behavior/analysis.php')) ?>"><i class="fa-solid fa-chart-pie"></i> Behavior analytics</a>
                    <a href="<?= e(url('modules/social/followers.php')) ?>"><i class="fa-solid fa-user-group"></i> Followers</a>
                    <a href="<?= e(url('modules/social/following.php')) ?>"><i class="fa-solid fa-user-check"></i> Following</a>
                    <div class="menu-sep"></div>
                    <form action="<?= e(url('auth/logout.php')) ?>" method="post">
                        <?= csrf_field() ?>
                        <button type="submit"><i class="fa-solid fa-right-from-bracket"></i> Log out</button>
                    </form>
                </div>
            </div>
            <button class="icon-btn menu-toggle" type="button" aria-label="Open menu" aria-expanded="false"><i class="fa-solid fa-bars"></i></button>
        </div>
<?php else: ?>
        <div class="nav-right">
            <a class="btn btn-ghost btn-sm" href="<?= e(url('auth/login.php')) ?>">Log in</a>
            <a class="btn btn-primary btn-sm" href="<?= e(url('auth/signup.php')) ?>">Create account</a>
        </div>
<?php endif; ?>
    </div>
</header>

<main id="main" class="page">
<?php $___flash = flash_html(); if ($___flash !== ''): ?>
    <div class="container"><?= str_replace('class="alert ', 'data-autohide class="alert ', $___flash) ?></div>
<?php endif; ?>
