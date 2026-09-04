<?php

$sidebarRole = $_SESSION['role'] ?? 'user';

/*
|--------------------------------------------------------------------------
| Navigation Items
|--------------------------------------------------------------------------
*/

if ($sidebarRole === 'admin') {
    $nav = [
        ['admin/index.php', 'Dashboard', 'fas fa-tachometer-alt'],
        ['admin/users.php', 'Users', 'fas fa-users'],
        ['admin/feedback.php', 'Feedback & Issues', 'fas fa-comment-dots']
    ];
} else {
    $nav = [
        ['user/index.php', 'Dashboard', 'fas fa-tachometer-alt'],
        ['user/profile.php', 'Profile', 'fas fa-user'],
        ['user/projects.php', 'Projects', 'fas fa-folder-open'],
        ['user/experience.php', 'Experience', 'fas fa-briefcase'],
        ['user/education.php', 'Education', 'fas fa-graduation-cap'],
        ['user/skills.php', 'Skills', 'fas fa-bolt'],
        ['user/certifications.php', 'Certifications', 'fas fa-certificate'],
        ['user/references.php', 'References', 'fas fa-users'],
        ['user/visibility.php', 'Portfolio Settings', 'fas fa-eye'],
        ['user/resume.php', 'Resume Builder', 'fas fa-file-alt'],
        ['user/feedback.php', 'Suggestions / Report Issue', 'fas fa-comment-dots'],
    ];
}

/*
|--------------------------------------------------------------------------
| Current Page
|--------------------------------------------------------------------------
*/

$currentPath = basename($_SERVER['PHP_SELF'] ?? '');

/*
|--------------------------------------------------------------------------
| Portfolio Information
|--------------------------------------------------------------------------
*/

$portfolioPublic = false;
$portfolioUrl = '';

if ($sidebarRole !== 'admin' && function_exists('current_user_id')) {
    $sidebarUserId = current_user_id();

    if ($sidebarUserId) {
        $sidebarProfile = get_profile($sidebarUserId);
        $sidebarUser = get_user($sidebarUserId);

        $portfolioPublic = !empty($sidebarProfile['portfolio_public']);

        if ($portfolioPublic && !empty($sidebarUser['username'])) {
            $portfolioUrl = asset($sidebarUser['username']);
        }
    }
}

?>

<ul class="navbar-nav bg-gradient-primary sidebar sidebar-dark accordion" id="accordionSidebar">

    <!-- Sidebar - Brand -->
    <a class="sidebar-brand d-flex align-items-center justify-content-center" href="<?= asset($sidebarRole === 'admin' ? 'admin/index.php' : 'user/index.php') ?>">
        <div class="sidebar-brand-icon rotate-n-15">
            <i class="fas fa-briefcase"></i>
        </div>
        <div class="sidebar-brand-text mx-3">MyPortfolio</div>
    </a>

    <!-- Divider -->
    <hr class="sidebar-divider my-0">
    <!-- Divider -->
    <hr class="sidebar-divider">

    <!-- Heading -->
    <div class="sidebar-heading">
        Main Menu
    </div>

    <!-- Main Navigation -->
    <?php
    foreach ($nav as $item):
        [$url, $label, $icon] = $item;

        $navPage = basename($url);
        $isActive = ($currentPath === $navPage);
    ?>
        <li class="nav-item <?= $isActive ? 'active' : '' ?>">
            <a class="nav-link" href="<?= asset($url) ?>">
                <i class="<?= $icon ?>"></i>
                <span><?= $label ?></span></a>
        </li>

    <?php endforeach; ?>

    <!-- Divider -->
    <hr class="sidebar-divider d-none d-md-block">

    <!-- Sidebar Toggler (Sidebar) -->
    <div class="text-center d-none d-md-inline">
        <button class="rounded-circle border-0" id="sidebarToggle"></button>
    </div>

</ul>