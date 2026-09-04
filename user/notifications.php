<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once __DIR__ . '/../includes/maintenance-check.php';

require_login();

if (($_SESSION['role'] ?? null) !== 'user') {
    redirect('admin/index.php');
}

$userId = current_user_id();

$pdo = db();

$pageTitle = 'Notifications';


/*
|--------------------------------------------------------------------------
| Notification Helper
|--------------------------------------------------------------------------
*/

function notification_icon(string $type): string
{
    switch ($type) {

        case 'feedback_status':
            return 'fas fa-sync-alt';

        case 'feedback_response':
            return 'fas fa-comment-dots';

        case 'feedback':
            return 'fas fa-comment';

        default:
            return 'fas fa-info-circle';
    }
}


function notification_icon_color(string $type): string
{
    switch ($type) {

        case 'feedback_status':
            return 'text-primary';

        case 'feedback_response':
            return 'text-success';

        case 'feedback':
            return 'text-info';

        default:
            return 'text-secondary';
    }
}


/*
|--------------------------------------------------------------------------
| Handle POST
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    try {

        verify_csrf();

        $action =
            trim(
                (string) (
                    $_POST['action'] ?? ''
                )
            );


        /*
        |--------------------------------------------------------------------------
        | Mark One Notification As Read
        |--------------------------------------------------------------------------
        */

        if ($action === 'mark_read') {

            $notificationId =
                (int) (
                    $_POST['notification_id'] ?? 0
                );


            if ($notificationId <= 0) {

                throw new RuntimeException(
                    'Invalid notification.'
                );
            }


            $stmt = $pdo->prepare(
                'UPDATE notifications
                 SET is_read = 1
                 WHERE id = ?
                 AND user_id = ?'
            );


            $stmt->execute([
                $notificationId,
                $userId
            ]);


            flash(
                'success',
                'Notification marked as read.'
            );


            redirect(
                'user/notifications.php'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Mark All As Read
        |--------------------------------------------------------------------------
        */

        if ($action === 'mark_all_read') {

            $stmt = $pdo->prepare(
                'UPDATE notifications
                 SET is_read = 1
                 WHERE user_id = ?
                 AND is_read = 0'
            );


            $stmt->execute([
                $userId
            ]);


            flash(
                'success',
                'All notifications have been marked as read.'
            );


            redirect(
                'user/notifications.php'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Delete Notification
        |--------------------------------------------------------------------------
        */

        if ($action === 'delete_notification') {

            $notificationId =
                (int) (
                    $_POST['notification_id'] ?? 0
                );


            if ($notificationId <= 0) {

                throw new RuntimeException(
                    'Invalid notification.'
                );
            }


            $stmt = $pdo->prepare(
                'DELETE FROM notifications
                 WHERE id = ?
                 AND user_id = ?'
            );


            $stmt->execute([
                $notificationId,
                $userId
            ]);


            flash(
                'success',
                'Notification deleted.'
            );


            redirect(
                'user/notifications.php'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Delete All Read Notifications
        |--------------------------------------------------------------------------
        */

        if ($action === 'delete_read') {

            $stmt = $pdo->prepare(
                'DELETE FROM notifications
                 WHERE user_id = ?
                 AND is_read = 1'
            );


            $stmt->execute([
                $userId
            ]);


            flash(
                'success',
                'Read notifications have been deleted.'
            );


            redirect(
                'user/notifications.php'
            );
        }
    } catch (Throwable $e) {

        flash(
            'danger',
            $e->getMessage()
        );


        redirect(
            'user/notifications.php'
        );
    }
}


/*
|--------------------------------------------------------------------------
| Open Notification
|--------------------------------------------------------------------------
|
| If a notification is clicked from the dropdown,
| mark it as read and redirect to the related page.
|
*/

$openNotificationId =
    (int) (
        $_GET['notification'] ?? 0
    );


if ($openNotificationId > 0) {

    $stmt = $pdo->prepare(
        'SELECT
            id,
            feedback_id
         FROM notifications
         WHERE id = ?
         AND user_id = ?
         LIMIT 1'
    );


    $stmt->execute([
        $openNotificationId,
        $userId
    ]);


    $notification =
        $stmt->fetch();


    if ($notification) {

        $stmt = $pdo->prepare(
            'UPDATE notifications
             SET is_read = 1
             WHERE id = ?
             AND user_id = ?'
        );


        $stmt->execute([
            $openNotificationId,
            $userId
        ]);


        if (
            !empty($notification['feedback_id'])
        ) {

            redirect(
                'user/feedback.php'
            );
        }
    }
}


/*
|--------------------------------------------------------------------------
| Get Notifications
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare(
    'SELECT
        id,
        feedback_id,
        type,
        title,
        message,
        is_read,
        created_at
     FROM notifications
     WHERE user_id = ?
     ORDER BY created_at DESC'
);


$stmt->execute([
    $userId
]);


$notifications =
    $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| Count Unread
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare(
    'SELECT COUNT(*)
     FROM notifications
     WHERE user_id = ?
     AND is_read = 0'
);


$stmt->execute([
    $userId
]);


$unreadCount =
    (int) $stmt->fetchColumn();


require dirname(__DIR__) . '/includes/header.php';

?>


<div class="container-fluid">


    <!-- ========================================================= -->
    <!-- PAGE HEADING -->
    <!-- ========================================================= -->

    <div
        class="d-sm-flex align-items-center justify-content-between mb-4">


        <div>

            <h1
                class="h3 mb-1 text-gray-800">

                Notifications

            </h1>


            <p
                class="mb-0 text-gray-600">

                Updates about your feedback and reported issues.

            </p>

        </div>


        <?php if ($notifications): ?>

            <div
                class="mt-3 mt-sm-0">


                <?php if ($unreadCount > 0): ?>

                    <form
                        method="POST"
                        class="d-inline">

                        <?= csrf_field() ?>

                        <input
                            type="hidden"
                            name="action"
                            value="mark_all_read">


                        <button
                            type="submit"
                            class="btn btn-sm btn-outline-primary">

                            <i
                                class="fas fa-check-double mr-1">
                            </i>

                            Mark All as Read

                        </button>

                    </form>

                <?php endif; ?>


                <form
                    method="POST"
                    class="d-inline"
                    onsubmit="return confirm('Delete all read notifications?');">

                    <?= csrf_field() ?>

                    <input
                        type="hidden"
                        name="action"
                        value="delete_read">


                    <button
                        type="submit"
                        class="btn btn-sm btn-outline-secondary">

                        <i
                            class="fas fa-trash-alt mr-1">
                        </i>

                        Clear Read

                    </button>

                </form>


            </div>

        <?php endif; ?>


    </div>



    <!-- ========================================================= -->
    <!-- NOTIFICATIONS CARD -->
    <!-- ========================================================= -->

    <div
        class="card shadow mb-4">


        <div
            class="card-header py-3 d-flex justify-content-between align-items-center">


            <h6
                class="m-0 font-weight-bold text-primary">

                <i
                    class="fas fa-bell mr-2">
                </i>

                Notification History

            </h6>


            <?php if ($unreadCount > 0): ?>

                <span
                    class="badge badge-primary">

                    <?= $unreadCount ?>
                    unread

                </span>

            <?php endif; ?>


        </div>



        <div
            class="card-body p-0">


            <?php if (!$notifications): ?>


                <div
                    class="text-center py-5 px-3">


                    <i
                        class="fas fa-bell-slash fa-3x text-gray-300 mb-3">
                    </i>


                    <h5
                        class="font-weight-bold text-gray-800">

                        No notifications yet

                    </h5>


                    <p
                        class="text-gray-500 mb-0">

                        Notifications about your feedback and
                        reported issues will appear here.

                    </p>


                </div>


            <?php else: ?>


                <div
                    class="list-group list-group-flush">


                    <?php foreach (
                        $notifications
                        as $notification
                    ): ?>


                        <?php

                        $notificationId =
                            (int) $notification['id'];

                        $feedbackId =
                            (int) (
                                $notification['feedback_id']
                                ?? 0
                            );

                        $isUnread =
                            (int) $notification['is_read'] === 0;

                        ?>


                        <div
                            class="list-group-item
                                <?= $isUnread
                                    ? 'bg-light'
                                    : '' ?>">


                            <div
                                class="row align-items-start">


                                <!-- ================================= -->
                                <!-- ICON -->
                                <!-- ================================= -->

                                <div
                                    class="col-auto">


                                    <div
                                        class="icon-circle bg-white shadow-sm">

                                        <i
                                            class="<?= e(
                                                        notification_icon(
                                                            $notification['type']
                                                        )
                                                    ) ?>
                                            <?= e(
                                                notification_icon_color(
                                                    $notification['type']
                                                )
                                            ) ?>">
                                        </i>

                                    </div>


                                </div>



                                <!-- ================================= -->
                                <!-- CONTENT -->
                                <!-- ================================= -->

                                <div
                                    class="col">


                                    <div
                                        class="d-flex flex-column flex-md-row justify-content-between">


                                        <div>


                                            <h6
                                                class="<?= $isUnread
                                                            ? 'font-weight-bold text-gray-800'
                                                            : 'text-gray-800' ?> mb-1">

                                                <?= e(
                                                    $notification['title']
                                                ) ?>


                                                <?php if ($isUnread): ?>

                                                    <span
                                                        class="badge badge-primary ml-2">

                                                        New

                                                    </span>

                                                <?php endif; ?>


                                            </h6>


                                            <p
                                                class="text-gray-600 mb-2">

                                                <?= nl2br(
                                                    e(
                                                        $notification['message']
                                                    )
                                                ) ?>

                                            </p>


                                            <div
                                                class="small text-muted">

                                                <i
                                                    class="far fa-clock mr-1">
                                                </i>

                                                <?= e(
                                                    date(
                                                        'M d, Y h:i A',
                                                        strtotime(
                                                            $notification['created_at']
                                                        )
                                                    )
                                                ) ?>

                                            </div>


                                        </div>


                                        <!-- ============================= -->
                                        <!-- ACTIONS -->
                                        <!-- ============================= -->

                                        <div
                                            class="mt-3 mt-md-0 ml-md-3 text-md-right">


                                            <?php if (
                                                $feedbackId > 0
                                            ): ?>


                                                <a
                                                    href="<?= asset(
                                                                'user/feedback.php'
                                                            ) ?>"
                                                    class="btn btn-sm btn-outline-primary mb-1">

                                                    <i
                                                        class="fas fa-comment-alt mr-1">
                                                    </i>

                                                    View Feedback

                                                </a>


                                            <?php endif; ?>


                                            <?php if ($isUnread): ?>


                                                <form
                                                    method="POST"
                                                    class="d-inline">


                                                    <?= csrf_field() ?>


                                                    <input
                                                        type="hidden"
                                                        name="action"
                                                        value="mark_read">


                                                    <input
                                                        type="hidden"
                                                        name="notification_id"
                                                        value="<?= $notificationId ?>">


                                                    <button
                                                        type="submit"
                                                        class="btn btn-sm btn-outline-secondary mb-1">

                                                        <i
                                                            class="fas fa-check mr-1">
                                                        </i>

                                                        Mark Read

                                                    </button>


                                                </form>


                                            <?php endif; ?>


                                            <form
                                                method="POST"
                                                class="d-inline"
                                                onsubmit="return confirm('Delete this notification?');">


                                                <?= csrf_field() ?>


                                                <input
                                                    type="hidden"
                                                    name="action"
                                                    value="delete_notification">


                                                <input
                                                    type="hidden"
                                                    name="notification_id"
                                                    value="<?= $notificationId ?>">


                                                <button
                                                    type="submit"
                                                    class="btn btn-sm btn-outline-danger mb-1">

                                                    <i
                                                        class="fas fa-trash mr-1">
                                                    </i>

                                                    Delete

                                                </button>


                                            </form>


                                        </div>


                                    </div>


                                </div>


                            </div>


                        </div>


                    <?php endforeach; ?>


                </div>


            <?php endif; ?>


        </div>


    </div>


</div>


<?php require dirname(__DIR__) . '/includes/footer.php'; ?>