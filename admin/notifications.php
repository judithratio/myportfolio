<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once __DIR__ . '/../includes/maintenance-check.php';

require_role('admin');

$pdo = db();

$pageTitle = 'Notifications';


/*
|--------------------------------------------------------------------------
| Notification Helpers
|--------------------------------------------------------------------------
*/

function admin_notification_icon(string $type): string
{
    switch ($type) {

        case 'feedback':
            return 'fas fa-comment';

        case 'bug':
            return 'fas fa-bug';

        case 'feature':
            return 'fas fa-lightbulb';

        case 'suggestion':
            return 'fas fa-lightbulb';

        case 'other':
            return 'fas fa-info-circle';

        default:
            return 'fas fa-bell';
    }
}


function admin_notification_color(string $type): string
{
    switch ($type) {

        case 'feedback':
            return 'text-primary';

        case 'bug':
            return 'text-danger';

        case 'feature':
        case 'suggestion':
            return 'text-warning';

        case 'other':
            return 'text-info';

        default:
            return 'text-secondary';
    }
}


/*
|--------------------------------------------------------------------------
| Handle POST Actions
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
        | Mark One As Read
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
                'UPDATE admin_notifications
                 SET is_read = 1
                 WHERE id = ?'
            );


            $stmt->execute([
                $notificationId
            ]);


            flash(
                'success',
                'Notification marked as read.'
            );


            redirect(
                'admin/notifications.php'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Mark All As Read
        |--------------------------------------------------------------------------
        */

        if ($action === 'mark_all_read') {

            $stmt = $pdo->prepare(
                'UPDATE admin_notifications
                 SET is_read = 1
                 WHERE is_read = 0'
            );


            $stmt->execute();


            flash(
                'success',
                'All notifications have been marked as read.'
            );


            redirect(
                'admin/notifications.php'
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
                'DELETE FROM admin_notifications
                 WHERE id = ?'
            );


            $stmt->execute([
                $notificationId
            ]);


            flash(
                'success',
                'Notification deleted.'
            );


            redirect(
                'admin/notifications.php'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Delete All Read
        |--------------------------------------------------------------------------
        */

        if ($action === 'delete_read') {

            $stmt = $pdo->prepare(
                'DELETE FROM admin_notifications
                 WHERE is_read = 1'
            );


            $stmt->execute();


            flash(
                'success',
                'Read notifications have been deleted.'
            );


            redirect(
                'admin/notifications.php'
            );
        }
    } catch (Throwable $e) {

        flash(
            'danger',
            $e->getMessage()
        );


        redirect(
            'admin/notifications.php'
        );
    }
}


/*
|--------------------------------------------------------------------------
| Open Notification
|--------------------------------------------------------------------------
|
| Clicking a notification can open the feedback page.
|
*/

$openNotification =
    (int) (
        $_GET['notification'] ?? 0
    );


if ($openNotification > 0) {

    $stmt = $pdo->prepare(
        'SELECT
            id,
            feedback_id
         FROM admin_notifications
         WHERE id = ?
         LIMIT 1'
    );


    $stmt->execute([
        $openNotification
    ]);


    $notification =
        $stmt->fetch();


    if ($notification) {

        $stmt = $pdo->prepare(
            'UPDATE admin_notifications
             SET is_read = 1
             WHERE id = ?'
        );


        $stmt->execute([
            $openNotification
        ]);


        if (
            !empty($notification['feedback_id'])
        ) {

            /*
            |--------------------------------------------------------------------------
            | Open Admin Feedback Page
            |--------------------------------------------------------------------------
            |
            | Change the query parameter if your admin feedback page
            | uses a different parameter.
            |
            */

            redirect(
                'admin/feedback.php?id=' .
                    (int) $notification['feedback_id']
            );
        }
    }
}


/*
|--------------------------------------------------------------------------
| Get Notifications
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query(
    'SELECT
        id,
        feedback_id,
        type,
        title,
        message,
        is_read,
        created_at
     FROM admin_notifications
     ORDER BY created_at DESC'
);


$notifications =
    $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| Unread Count
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query(
    'SELECT COUNT(*)
     FROM admin_notifications
     WHERE is_read = 0'
);


$unreadCount =
    (int) $stmt->fetchColumn();


/*
|--------------------------------------------------------------------------
| Page
|--------------------------------------------------------------------------
*/

require dirname(__DIR__) . '/includes/header.php';

?>


<div class="container-fluid">


    <!-- ========================================================= -->
    <!-- PAGE HEADER -->
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

                Updates about feedback, issues, and suggestions
                submitted by users.

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
    <!-- NOTIFICATION CARD -->
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

                        New user feedback and issue reports
                        will appear here.

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
                                                        admin_notification_icon(
                                                            $notification['type']
                                                        )
                                                    ) ?>
                                            <?= e(
                                                admin_notification_color(
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



                                        <!-- ================================= -->
                                        <!-- ACTIONS -->
                                        <!-- ================================= -->

                                        <div
                                            class="mt-3 mt-md-0 ml-md-3 text-md-right">


                                            <?php if ($feedbackId > 0): ?>


                                                <a
                                                    href="<?= asset(
                                                                'admin/feedback.php?id=' .
                                                                    $feedbackId
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