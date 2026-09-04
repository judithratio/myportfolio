<?php

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/database.php';

$pageTitle = $pageTitle ?? APP_NAME;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$flash = get_flash();
$isLoggedIn = is_logged_in();
$role = $_SESSION['role'] ?? null;

$homeUrl = $role === 'admin'
    ? asset('admin/index.php')
    : asset('user/index.php');

/*
|--------------------------------------------------------------------------
| Admin Notifications Only
|--------------------------------------------------------------------------
| Notifications are only loaded for administrators.
| Regular users do not receive or see the notification bell.
*/

$adminUnreadCount = 0;
$adminNotifications = [];

if ($isLoggedIn && $role === 'admin') {
    try {
        $pdo = db();

        $stmt = $pdo->query("
            SELECT
                id,
                title,
                message,
                type,
                is_read,
                created_at
            FROM admin_notifications
            ORDER BY created_at DESC
            LIMIT 5
        ");

        $adminNotifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $pdo->query("
            SELECT COUNT(*)
            FROM admin_notifications
            WHERE is_read = 0
        ");

        $adminUnreadCount = (int) $stmt->fetchColumn();
    } catch (Throwable $e) {
        $adminUnreadCount = 0;
        $adminNotifications = [];
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="utf-8">

    <meta http-equiv="X-UA-Compatible" content="IE=edge">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1, shrink-to-fit=no">

    <meta
        name="description"
        content="MyPortfolio">

    <meta
        name="author"
        content="">

    <title>
        <?= e($pageTitle) ?> | <?= e(APP_NAME) ?>
    </title>

    <!-- Font Awesome -->
    <link
        href="<?= asset('vendor/fontawesome-free/css/all.min.css') ?>"
        rel="stylesheet"
        type="text/css">

    <!-- Google Fonts -->
    <link
        href="https://fonts.googleapis.com/css?family=Nunito:200,300,400,600,700,800,900"
        rel="stylesheet">

    <!-- SB Admin 2 -->
    <link
        href="<?= asset('css/myportfolio.min.css') ?>"
        rel="stylesheet">


    <!-- Uniform SB Admin 2 Modal Styling -->
    <style>
        .modal .modal-dialog {
            width: auto;
            max-width: 720px;
            margin: 1rem auto;
        }

        .modal .modal-content {
            border: 0 !important;
            border-radius: .5rem !important;
            overflow: hidden;
            box-shadow: 0 .5rem 1.5rem rgba(58, 59, 69, .20) !important;
        }

        .modal .modal-header {
            display: flex;
            align-items: center;
            padding: 1rem 1.25rem !important;
            background-color: #f8f9fc !important;
            color: #5a5c69 !important;
            border-bottom: 1px solid #e3e6f0 !important;
        }

        .modal .modal-title {
            margin: 0;
            font-size: 1rem;
            font-weight: 700;
            color: #5a5c69 !important;
            line-height: 1.5;
        }

        .modal .modal-header .close {
            margin: -.5rem -.5rem -.5rem auto;
            padding: .5rem;
            color: #858796;
            opacity: .8;
        }

        .modal .modal-header .close:hover,
        .modal .modal-header .close:focus {
            color: #5a5c69;
            opacity: 1;
        }

        .modal .modal-body {
            padding: 1.25rem !important;
            background-color: #fff !important;
        }

        .modal .modal-footer {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: .5rem;
            padding: .85rem 1.25rem !important;
            background-color: #f8f9fc !important;
            border-top: 1px solid #e3e6f0 !important;
        }

        .modal .modal-footer .btn { margin: 0 !important; }
        .modal .form-group { margin-bottom: 1rem; }
        .modal label { margin-bottom: .4rem; font-weight: 600; color: #5a5c69; }
        .modal .form-control,
        .modal .custom-select { border-color: #d1d3e2; border-radius: .35rem; }
        .modal .form-control:focus,
        .modal .custom-select:focus {
            border-color: #4e73df;
            box-shadow: 0 0 0 .2rem rgba(78, 115, 223, .12);
        }
        .modal .custom-control-label { font-weight: 400; }
        .modal .text-muted { color: #858796 !important; }

        .modal .modal-dialog.modal-sm { max-width: 400px; }
        .modal .modal-dialog.modal-lg { max-width: 800px; }
        .modal .modal-dialog.modal-xl { max-width: 1140px; }

        @media (max-width: 767.98px) {
            .modal .modal-dialog,
            .modal .modal-dialog.modal-sm,
            .modal .modal-dialog.modal-lg,
            .modal .modal-dialog.modal-xl {
                max-width: calc(100% - 1rem);
                margin: .5rem auto;
            }
            .modal .modal-content { border-radius: .35rem !important; }
            .modal .modal-header { padding: .85rem 1rem !important; }
            .modal .modal-body {
                padding: 1rem !important;
                max-height: 75vh;
                overflow-y: auto;
                -webkit-overflow-scrolling: touch;
            }
            .modal .modal-footer { padding: .75rem 1rem !important; flex-wrap: wrap; }
            .modal .modal-footer .btn { flex: 1 1 auto; min-width: 100px; }
        }

        @media (max-width: 575.98px) {
            .modal .modal-dialog,
            .modal .modal-dialog.modal-sm,
            .modal .modal-dialog.modal-lg,
            .modal .modal-dialog.modal-xl {
                max-width: calc(100% - .75rem);
                margin: .375rem auto;
            }
            .modal .modal-header { padding: .75rem 1rem !important; }
            .modal .modal-title { font-size: .95rem; }
            .modal .modal-body { padding: .9rem !important; max-height: 78vh; }
            .modal .modal-footer { flex-direction: column-reverse; align-items: stretch; }
            .modal .modal-footer .btn { width: 100%; min-width: 0; }
        }
    </style>

</head>

<body id="page-top">

    <?php if ($isLoggedIn): ?>

        <!-- Page Wrapper -->
        <div id="wrapper">

            <?php require dirname(__DIR__) . '/includes/sidebar.php'; ?>

            <!-- Content Wrapper -->
            <div
                id="content-wrapper"
                class="d-flex flex-column">

                <!-- Main Content -->
                <div id="content">

                    <!-- Topbar -->
                    <nav
                        class="navbar navbar-expand navbar-light bg-white topbar mb-4 static-top shadow">

                        <!-- Sidebar Toggle (Topbar) -->
                        <button
                            id="sidebarToggleTop"
                            class="btn btn-link d-md-none rounded-circle mr-3"
                            type="button">
                            <i class="fa fa-bars"></i>
                        </button>

                        <!-- Topbar Navbar -->
                        <ul class="navbar-nav ml-auto">

                            <?php if ($role === 'admin'): ?>

                                <!-- Admin Notifications -->
                                <li class="nav-item dropdown no-arrow mx-1">

                                    <a
                                        class="nav-link dropdown-toggle"
                                        href="#"
                                        id="alertsDropdown"
                                        role="button"
                                        data-toggle="dropdown"
                                        aria-haspopup="true"
                                        aria-expanded="false">

                                        <i class="fas fa-bell fa-fw"></i>

                                        <?php if ($adminUnreadCount > 0): ?>

                                            <span class="badge badge-danger badge-counter">
                                                <?= $adminUnreadCount > 9 ? '9+' : $adminUnreadCount ?>
                                            </span>

                                        <?php endif; ?>

                                    </a>

                                    <!-- Notification Dropdown -->
                                    <div
                                        class="dropdown-list dropdown-menu dropdown-menu-right shadow animated--grow-in"
                                        aria-labelledby="alertsDropdown">

                                        <h6 class="dropdown-header">
                                            Notifications
                                        </h6>

                                        <?php if (!empty($adminNotifications)): ?>

                                            <?php foreach ($adminNotifications as $notification): ?>

                                                <?php
                                                $notificationType = $notification['type'] ?? 'default';

                                                switch ($notificationType) {
                                                    case 'feedback':
                                                    case 'feedback_update':
                                                        $icon = 'fa-comment-alt';
                                                        break;

                                                    case 'user':
                                                        $icon = 'fa-user';
                                                        break;

                                                    case 'system':
                                                        $icon = 'fa-cog';
                                                        break;

                                                    default:
                                                        $icon = 'fa-bell';
                                                        break;
                                                }

                                                $isUnread = (int) ($notification['is_read'] ?? 0) === 0;
                                                ?>

                                                <a
                                                    class="dropdown-item d-flex align-items-center <?= $isUnread ? 'font-weight-bold' : '' ?>"
                                                    href="<?= asset('admin/feedback.php') ?>">

                                                    <div class="mr-3">
                                                        <div class="icon-circle bg-primary">
                                                            <i class="fas <?= e($icon) ?> text-white"></i>
                                                        </div>
                                                    </div>

                                                    <div>
                                                        <div class="small text-gray-500">
                                                            <?= e(format_date($notification['created_at'])) ?>
                                                        </div>

                                                        <span>
                                                            <?= e($notification['title'] ?? 'Notification') ?>
                                                        </span>

                                                        <?php if (!empty($notification['message'])): ?>

                                                            <div class="small text-gray-600">
                                                                <?= e($notification['message']) ?>
                                                            </div>

                                                        <?php endif; ?>

                                                    </div>

                                                </a>

                                            <?php endforeach; ?>

                                        <?php else: ?>

                                            <div class="dropdown-item text-center small text-gray-500 py-3">
                                                No notifications
                                            </div>

                                        <?php endif; ?>

                                        <?php if (!empty($adminNotifications)): ?>

                                            <a
                                                class="dropdown-item text-center small text-gray-500"
                                                href="<?= asset('admin/feedback.php') ?>">
                                                View All Notifications
                                            </a>

                                        <?php endif; ?>

                                    </div>

                                </li>

                                <div class="topbar-divider d-none d-sm-block"></div>

                            <?php endif; ?>

                            <!-- User Information -->
                            <li class="nav-item dropdown no-arrow">

                                <a
                                    class="nav-link dropdown-toggle"
                                    href="#"
                                    id="userDropdown"
                                    role="button"
                                    data-toggle="dropdown"
                                    aria-haspopup="true"
                                    aria-expanded="false">

                                    <span class="mr-2 d-none d-lg-inline text-gray-600 small">
                                        <?= e($_SESSION['name'] ?? $_SESSION['email'] ?? 'Account') ?>
                                    </span>

                                    <?php
                                    $profilePic = get_profile_picture(
                                        current_user_id()
                                    );
                                    ?>

                                    <img
                                        class="img-profile rounded-circle"
                                        src="<?= e($profilePic) ?>"
                                        alt="Profile">

                                </a>

                                <!-- User Dropdown -->
                                <div
                                    class="dropdown-menu dropdown-menu-right shadow animated--grow-in"
                                    aria-labelledby="userDropdown">

                                    <?php if ($role === 'user'): ?>

                                        <a
                                            class="dropdown-item"
                                            href="<?= asset('user/profile.php') ?>">
                                            <i class="fas fa-user fa-sm fa-fw mr-2 text-gray-400"></i>
                                            Profile
                                        </a>

                                        <div class="dropdown-divider"></div>

                                    <?php endif; ?>

                                    <a
                                        class="dropdown-item"
                                        href="<?= asset('logout.php') ?>"
                                        data-toggle="modal"
                                        data-target="#logoutModal">
                                        <i class="fas fa-sign-out-alt fa-sm fa-fw mr-2 text-gray-400"></i>
                                        Logout
                                    </a>

                                </div>

                            </li>

                        </ul>

                    </nav>
                    <!-- End of Topbar -->

                <?php endif; ?>

                <!-- Flash Messages -->

                <?php if ($flash): ?>

                    <div class="container-fluid mt-3">

                        <div
                            class="alert alert-<?= e($flash['type'] ?? 'info') ?> alert-dismissible fade show"
                            role="alert">

                            <?= e($flash['message'] ?? '') ?>

                            <button
                                type="button"
                                class="close"
                                data-dismiss="alert"
                                aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>

                        </div>

                    </div>

                <?php endif; ?>