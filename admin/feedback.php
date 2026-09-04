<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once __DIR__ . '/../includes/maintenance-check.php';

require_role('admin');

$pdo = db();

$pageTitle = 'Feedback Management';


/*
|--------------------------------------------------------------------------
| OPTIONS
|--------------------------------------------------------------------------
*/

$allowedTypes = [
    'suggestion' => 'Suggestion',
    'bug'        => 'Bug / Issue',
    'feature'    => 'Feature Request',
    'other'      => 'Other'
];

$allowedStatuses = [
    'open'         => 'Open',
    'under_review' => 'Under Review',
    'in_progress'  => 'In Progress',
    'resolved'     => 'Resolved',
    'closed'       => 'Closed'
];

$allowedPriorities = [
    'low'      => 'Low',
    'medium'   => 'Medium',
    'high'     => 'High',
    'critical' => 'Critical'
];

$statusClasses = [
    'open'         => 'primary',
    'under_review' => 'info',
    'in_progress'  => 'warning',
    'resolved'     => 'success',
    'closed'       => 'secondary'
];

$priorityClasses = [
    'low'      => 'secondary',
    'medium'   => 'info',
    'high'     => 'warning',
    'critical' => 'danger'
];

$typeClasses = [
    'suggestion' => 'primary',
    'bug'        => 'danger',
    'feature'    => 'success',
    'other'      => 'secondary'
];


/*
|--------------------------------------------------------------------------
| HELPER
|--------------------------------------------------------------------------
*/

function feedback_redirect(): never
{
    header('Location: feedback.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| POST ACTIONS
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    try {

        verify_csrf();

        $action = trim(
            (string)($_POST['action'] ?? '')
        );


        /*
        |--------------------------------------------------------------------------
        | UPDATE FEEDBACK
        |--------------------------------------------------------------------------
        */

        if ($action === 'update_feedback') {

            $feedbackId = (int)(
                $_POST['feedback_id'] ?? 0
            );

            $status = strtolower(
                trim(
                    (string)(
                        $_POST['status'] ?? ''
                    )
                )
            );

            $adminResponse = trim(
                (string)(
                    $_POST['admin_response'] ?? ''
                )
            );

            if ($feedbackId <= 0) {

                throw new RuntimeException(
                    'Invalid feedback report.'
                );
            }

            if (!isset($allowedStatuses[$status])) {

                throw new RuntimeException(
                    'Please select a valid status.'
                );
            }

            if (mb_strlen($adminResponse) > 10000) {

                throw new RuntimeException(
                    'The admin response is too long.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | GET EXISTING FEEDBACK
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare(
                'SELECT
                    fr.*,
                    u.email,
                    p.full_name
                 FROM feedback_reports fr
                 INNER JOIN users u
                    ON u.id = fr.user_id
                 LEFT JOIN profiles p
                    ON p.user_id = u.id
                 WHERE fr.id = ?
                 LIMIT 1'
            );

            $stmt->execute([
                $feedbackId
            ]);

            $existing = $stmt->fetch(
                PDO::FETCH_ASSOC
            );

            if (!$existing) {

                throw new RuntimeException(
                    'Feedback report not found.'
                );
            }

            $oldStatus =
                (string)$existing['status'];

            $oldResponse =
                (string)(
                    $existing['admin_response']
                    ?? ''
                );


            /*
            |--------------------------------------------------------------------------
            | UPDATE
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare(
                'UPDATE feedback_reports
                 SET
                    status = ?,
                    admin_response = ?
                 WHERE id = ?
                 LIMIT 1'
            );

            $stmt->execute([
                $status,
                $adminResponse !== ''
                    ? $adminResponse
                    : null,
                $feedbackId
            ]);


            /*
            |--------------------------------------------------------------------------
            | ADMIN NOTIFICATION
            |--------------------------------------------------------------------------
            */

            try {

                $notificationTitle =
                    'Feedback Updated';

                $notificationMessage =
                    'Feedback "' .
                    $existing['subject'] .
                    '" was updated.';

                if ($oldStatus !== $status) {

                    $notificationMessage .=
                        ' Status changed from "' .
                        (
                            $allowedStatuses[$oldStatus]
                            ?? ucfirst($oldStatus)
                        ) .
                        '" to "' .
                        (
                            $allowedStatuses[$status]
                            ?? ucfirst($status)
                        ) .
                        '".';
                }

                if ($oldResponse !== $adminResponse) {

                    $notificationMessage .=
                        ' The admin response was updated.';
                }

                $stmtNotification =
                    $pdo->prepare(
                        'INSERT INTO admin_notifications
                        (
                            feedback_id,
                            type,
                            title,
                            message
                        )
                        VALUES
                        (?, ?, ?, ?)'
                    );

                $stmtNotification->execute([
                    $feedbackId,
                    'feedback_update',
                    $notificationTitle,
                    $notificationMessage
                ]);
            } catch (Throwable $notificationError) {

                // Notification failure must not prevent update.

            }


            flash(
                'success',
                'Feedback updated successfully.'
            );

            feedback_redirect();
        }


        /*
        |--------------------------------------------------------------------------
        | DELETE FEEDBACK
        |--------------------------------------------------------------------------
        */

        if ($action === 'delete_feedback') {

            $feedbackId = (int)(
                $_POST['feedback_id'] ?? 0
            );

            if ($feedbackId <= 0) {

                throw new RuntimeException(
                    'Invalid feedback report.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | GET ATTACHMENT
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare(
                'SELECT attachment
                 FROM feedback_reports
                 WHERE id = ?
                 LIMIT 1'
            );

            $stmt->execute([
                $feedbackId
            ]);

            $feedback = $stmt->fetch(
                PDO::FETCH_ASSOC
            );

            if (!$feedback) {

                throw new RuntimeException(
                    'Feedback report not found.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | DELETE FEEDBACK
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare(
                'DELETE FROM feedback_reports
                 WHERE id = ?
                 LIMIT 1'
            );

            $stmt->execute([
                $feedbackId
            ]);


            /*
            |--------------------------------------------------------------------------
            | DELETE ATTACHMENT
            |--------------------------------------------------------------------------
            */

            if (!empty($feedback['attachment'])) {

                try {

                    delete_upload(
                        $feedback['attachment']
                    );
                } catch (Throwable $attachmentError) {

                    // Ignore missing attachment.

                }
            }


            /*
            |--------------------------------------------------------------------------
            | DELETE NOTIFICATIONS
            |--------------------------------------------------------------------------
            */

            try {

                $stmtNotification =
                    $pdo->prepare(
                        'DELETE FROM admin_notifications
                         WHERE feedback_id = ?'
                    );

                $stmtNotification->execute([
                    $feedbackId
                ]);
            } catch (Throwable $notificationError) {

                // Ignore unavailable notification table.

            }


            flash(
                'success',
                'Feedback report deleted successfully.'
            );

            feedback_redirect();
        }
    } catch (Throwable $e) {

        flash(
            'danger',
            $e->getMessage()
        );

        feedback_redirect();
    }
}


/*
|--------------------------------------------------------------------------
| FILTERS
|--------------------------------------------------------------------------
*/

$search = trim(
    (string)($_GET['search'] ?? '')
);

$statusFilter = strtolower(
    trim(
        (string)(
            $_GET['status'] ?? ''
        )
    )
);

$typeFilter = strtolower(
    trim(
        (string)(
            $_GET['type'] ?? ''
        )
    )
);

$priorityFilter = strtolower(
    trim(
        (string)(
            $_GET['priority'] ?? ''
        )
    )
);


/*
|--------------------------------------------------------------------------
| BUILD QUERY
|--------------------------------------------------------------------------
*/

$sql = '
    SELECT
        fr.*,
        u.email,
        u.username,
        p.full_name,
        p.profile_image

    FROM feedback_reports fr

    INNER JOIN users u
        ON u.id = fr.user_id

    LEFT JOIN profiles p
        ON p.user_id = u.id

    WHERE 1 = 1
';

$params = [];


/*
|--------------------------------------------------------------------------
| SEARCH
|--------------------------------------------------------------------------
*/

if ($search !== '') {

    $sql .= '
        AND (
            p.full_name LIKE ?
            OR u.email LIKE ?
            OR u.username LIKE ?
            OR fr.subject LIKE ?
            OR fr.description LIKE ?
        )
    ';

    $searchValue =
        '%' . $search . '%';

    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
}


/*
|--------------------------------------------------------------------------
| STATUS
|--------------------------------------------------------------------------
*/

if (
    $statusFilter !== '' &&
    isset($allowedStatuses[$statusFilter])
) {

    $sql .= '
        AND fr.status = ?
    ';

    $params[] = $statusFilter;
}


/*
|--------------------------------------------------------------------------
| TYPE
|--------------------------------------------------------------------------
*/

if (
    $typeFilter !== '' &&
    isset($allowedTypes[$typeFilter])
) {

    $sql .= '
        AND fr.type = ?
    ';

    $params[] = $typeFilter;
}


/*
|--------------------------------------------------------------------------
| PRIORITY
|--------------------------------------------------------------------------
*/

if (
    $priorityFilter !== '' &&
    isset($allowedPriorities[$priorityFilter])
) {

    $sql .= '
        AND fr.priority = ?
    ';

    $params[] = $priorityFilter;
}


$sql .= '
    ORDER BY fr.created_at DESC
';


$stmt = $pdo->prepare($sql);

$stmt->execute($params);

$feedbackReports =
    $stmt->fetchAll(
        PDO::FETCH_ASSOC
    );


/*
|--------------------------------------------------------------------------
| STATISTICS
|--------------------------------------------------------------------------
*/

$totalFeedback = 0;
$openFeedback = 0;
$underReviewFeedback = 0;
$inProgressFeedback = 0;
$resolvedFeedback = 0;
$closedFeedback = 0;

try {

    $totalFeedback =
        (int)$pdo->query(
            'SELECT COUNT(*)
             FROM feedback_reports'
        )->fetchColumn();

    $openFeedback =
        (int)$pdo->query(
            "SELECT COUNT(*)
             FROM feedback_reports
             WHERE status = 'open'"
        )->fetchColumn();

    $underReviewFeedback =
        (int)$pdo->query(
            "SELECT COUNT(*)
             FROM feedback_reports
             WHERE status = 'under_review'"
        )->fetchColumn();

    $inProgressFeedback =
        (int)$pdo->query(
            "SELECT COUNT(*)
             FROM feedback_reports
             WHERE status = 'in_progress'"
        )->fetchColumn();

    $resolvedFeedback =
        (int)$pdo->query(
            "SELECT COUNT(*)
             FROM feedback_reports
             WHERE status = 'resolved'"
        )->fetchColumn();

    $closedFeedback =
        (int)$pdo->query(
            "SELECT COUNT(*)
             FROM feedback_reports
             WHERE status = 'closed'"
        )->fetchColumn();
} catch (Throwable $e) {

    $totalFeedback =
        count($feedbackReports);
}


/*
|--------------------------------------------------------------------------
| VIEW FEEDBACK
|--------------------------------------------------------------------------
*/

$viewId = (int)(
    $_GET['view'] ?? 0
);

$viewFeedback = null;

if ($viewId > 0) {

    $stmt = $pdo->prepare(
        'SELECT
            fr.*,
            u.email,
            u.username,
            p.full_name,
            p.profile_image

         FROM feedback_reports fr

         INNER JOIN users u
            ON u.id = fr.user_id

         LEFT JOIN profiles p
            ON p.user_id = u.id

         WHERE fr.id = ?

         LIMIT 1'
    );

    $stmt->execute([
        $viewId
    ]);

    $viewFeedback =
        $stmt->fetch(
            PDO::FETCH_ASSOC
        ) ?: null;
}


/*
|--------------------------------------------------------------------------
| HEADER
|--------------------------------------------------------------------------
*/

require dirname(__DIR__) . '/includes/header.php';

?>

<div class="container-fluid">


    <!-- =========================================================
         PAGE HEADER
    ========================================================== -->

    <div class="d-sm-flex align-items-center justify-content-between mb-4">

        <div>

            <h1 class="h3 mb-1 text-gray-800">

                <i class="fas fa-comments mr-2"></i>

                Feedback

            </h1>

            <p class="mb-0 text-muted">

                Review and manage user suggestions, issues, and requests.

            </p>

        </div>

    </div>

    <!-- =========================================================
         STATISTICS
    ========================================================== -->

    <div class="row">


        <!-- TOTAL -->

        <div class="col-xl-2 col-md-4 col-sm-6 mb-4">

            <div class="card border-left-primary shadow h-100 py-2">

                <div class="card-body">

                    <div class="row no-gutters align-items-center">

                        <div class="col mr-2">

                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">

                                Total

                            </div>

                            <div class="h5 mb-0 font-weight-bold text-gray-800">

                                <?= number_format($totalFeedback) ?>

                            </div>

                        </div>

                        <div class="col-auto">

                            <i class="fas fa-comments fa-2x text-gray-300"></i>

                        </div>

                    </div>

                </div>

            </div>

        </div>


        <!-- OPEN -->

        <div class="col-xl-2 col-md-4 col-sm-6 mb-4">

            <div class="card border-left-primary shadow h-100 py-2">

                <div class="card-body">

                    <div class="row no-gutters align-items-center">

                        <div class="col mr-2">

                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">

                                Open

                            </div>

                            <div class="h5 mb-0 font-weight-bold text-gray-800">

                                <?= number_format($openFeedback) ?>

                            </div>

                        </div>

                        <div class="col-auto">

                            <i class="fas fa-inbox fa-2x text-gray-300"></i>

                        </div>

                    </div>

                </div>

            </div>

        </div>


        <!-- UNDER REVIEW -->

        <div class="col-xl-2 col-md-4 col-sm-6 mb-4">

            <div class="card border-left-info shadow h-100 py-2">

                <div class="card-body">

                    <div class="row no-gutters align-items-center">

                        <div class="col mr-2">

                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">

                                Under Review

                            </div>

                            <div class="h5 mb-0 font-weight-bold text-gray-800">

                                <?= number_format($underReviewFeedback) ?>

                            </div>

                        </div>

                        <div class="col-auto">

                            <i class="fas fa-search fa-2x text-gray-300"></i>

                        </div>

                    </div>

                </div>

            </div>

        </div>


        <!-- IN PROGRESS -->

        <div class="col-xl-2 col-md-4 col-sm-6 mb-4">

            <div class="card border-left-warning shadow h-100 py-2">

                <div class="card-body">

                    <div class="row no-gutters align-items-center">

                        <div class="col mr-2">

                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">

                                In Progress

                            </div>

                            <div class="h5 mb-0 font-weight-bold text-gray-800">

                                <?= number_format($inProgressFeedback) ?>

                            </div>

                        </div>

                        <div class="col-auto">

                            <i class="fas fa-spinner fa-2x text-gray-300"></i>

                        </div>

                    </div>

                </div>

            </div>

        </div>


        <!-- RESOLVED -->

        <div class="col-xl-2 col-md-4 col-sm-6 mb-4">

            <div class="card border-left-success shadow h-100 py-2">

                <div class="card-body">

                    <div class="row no-gutters align-items-center">

                        <div class="col mr-2">

                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">

                                Resolved

                            </div>

                            <div class="h5 mb-0 font-weight-bold text-gray-800">

                                <?= number_format($resolvedFeedback) ?>

                            </div>

                        </div>

                        <div class="col-auto">

                            <i class="fas fa-check-circle fa-2x text-gray-300"></i>

                        </div>

                    </div>

                </div>

            </div>

        </div>


        <!-- CLOSED -->

        <div class="col-xl-2 col-md-4 col-sm-6 mb-4">

            <div class="card border-left-secondary shadow h-100 py-2">

                <div class="card-body">

                    <div class="row no-gutters align-items-center">

                        <div class="col mr-2">

                            <div class="text-xs font-weight-bold text-secondary text-uppercase mb-1">

                                Closed

                            </div>

                            <div class="h5 mb-0 font-weight-bold text-gray-800">

                                <?= number_format($closedFeedback) ?>

                            </div>

                        </div>

                        <div class="col-auto">

                            <i class="fas fa-times-circle fa-2x text-gray-300"></i>

                        </div>

                    </div>

                </div>

            </div>

        </div>

    </div>


    <!-- =========================================================
         FEEDBACK TABLE CARD
    ========================================================== -->

    <div class="card shadow mb-4">


        <!-- CARD HEADER -->

        <div class="card-header py-3">

            <div class="d-flex align-items-center justify-content-between">

                <h6 class="m-0 font-weight-bold text-primary">

                    <i class="fas fa-inbox mr-2"></i>

                    Feedback Reports

                </h6>

                <span class="badge badge-primary px-3 py-2">

                    <?= number_format(count($feedbackReports)) ?>

                    report<?= count($feedbackReports) === 1 ? '' : 's' ?>

                </span>

            </div>

        </div>


        <!-- =====================================================
             FILTERS
        ====================================================== -->

        <div class="card-body border-bottom bg-light">


            <form
                method="get"
                action="feedback.php">

                <div class="form-row align-items-end">


                    <!-- SEARCH -->

                    <div class="col-lg-4 col-md-6 mb-3">

                        <label
                            for="search"
                            class="small font-weight-bold text-gray-700">

                            Search

                        </label>

                        <div class="input-group">

                            <div class="input-group-prepend">

                                <span class="input-group-text bg-white">

                                    <i class="fas fa-search text-gray-500"></i>

                                </span>

                            </div>

                            <input
                                type="text"
                                class="form-control"
                                id="search"
                                name="search"
                                value="<?= e($search) ?>"
                                placeholder="Name, email, subject...">

                        </div>

                    </div>


                    <!-- STATUS -->

                    <div class="col-md-6 col-lg-2 mb-3">

                        <label
                            for="status"
                            class="small font-weight-bold text-gray-700">

                            Status

                        </label>

                        <select
                            class="form-control"
                            id="status"
                            name="status">

                            <option value="">
                                All Statuses
                            </option>

                            <?php foreach (
                                $allowedStatuses
                                as $value => $label
                            ): ?>

                                <option
                                    value="<?= e($value) ?>"
                                    <?= $statusFilter === $value
                                        ? 'selected'
                                        : '' ?>>

                                    <?= e($label) ?>

                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>


                    <!-- TYPE -->

                    <div class="col-md-6 col-lg-2 mb-3">

                        <label
                            for="type"
                            class="small font-weight-bold text-gray-700">

                            Type

                        </label>

                        <select
                            class="form-control"
                            id="type"
                            name="type">

                            <option value="">
                                All Types
                            </option>

                            <?php foreach (
                                $allowedTypes
                                as $value => $label
                            ): ?>

                                <option
                                    value="<?= e($value) ?>"
                                    <?= $typeFilter === $value
                                        ? 'selected'
                                        : '' ?>>

                                    <?= e($label) ?>

                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>


                    <!-- PRIORITY -->

                    <div class="col-md-6 col-lg-2 mb-3">

                        <label
                            for="priority"
                            class="small font-weight-bold text-gray-700">

                            Priority

                        </label>

                        <select
                            class="form-control"
                            id="priority"
                            name="priority">

                            <option value="">
                                All Priorities
                            </option>

                            <?php foreach (
                                $allowedPriorities
                                as $value => $label
                            ): ?>

                                <option
                                    value="<?= e($value) ?>"
                                    <?= $priorityFilter === $value
                                        ? 'selected'
                                        : '' ?>>

                                    <?= e($label) ?>

                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>


                    <!-- BUTTONS -->

                    <div class="col-md-6 col-lg-2 mb-3">

                        <div class="filter-buttons">

                            <button
                                type="submit"
                                class="btn btn-primary btn-block">

                                <i class="fas fa-filter mr-1"></i>

                                Apply

                            </button>

                            <a
                                href="feedback.php"
                                class="btn btn-light btn-block mt-2">

                                <i class="fas fa-redo mr-1"></i>

                                Reset

                            </a>

                        </div>

                    </div>

                </div>

            </form>

        </div>


        <!-- =====================================================
             TABLE
        ====================================================== -->

        <div class="card-body">


            <?php if (empty($feedbackReports)): ?>

                <div class="text-center py-5">

                    <div class="empty-state-icon mb-3">

                        <i class="fas fa-comment-slash fa-3x text-gray-300"></i>

                    </div>

                    <h5 class="font-weight-bold text-gray-600">

                        No feedback reports found

                    </h5>

                    <p class="text-muted mb-0">

                        Try changing your search or filter options.

                    </p>

                </div>

            <?php else: ?>


                <div class="table-responsive">

                    <table
                        class="table table-bordered table-hover"
                        id="feedbackTable"
                        width="100%"
                        cellspacing="0">

                        <thead class="thead-light">

                            <tr>

                                <th>
                                    User
                                </th>

                                <th>
                                    Type
                                </th>

                                <th>
                                    Subject
                                </th>

                                <th>
                                    Priority
                                </th>

                                <th>
                                    Status
                                </th>

                                <th>
                                    Submitted
                                </th>

                                <th
                                    class="text-center"
                                    style="width:155px;">

                                    Actions

                                </th>

                            </tr>

                        </thead>


                        <tbody>


                            <?php foreach (
                                $feedbackReports
                                as $report
                            ): ?>


                                <?php

                                $displayName =
                                    trim(
                                        (string)(
                                            $report['full_name']
                                            ?? ''
                                        )
                                    );

                                if ($displayName === '') {

                                    $displayName =
                                        trim(
                                            (string)(
                                                $report['username']
                                                ?? ''
                                            )
                                        );
                                }

                                if ($displayName === '') {

                                    $displayName =
                                        'User';
                                }


                                $email =
                                    (string)(
                                        $report['email']
                                        ?? ''
                                    );


                                $username =
                                    (string)(
                                        $report['username']
                                        ?? ''
                                    );


                                $profileImage =
                                    trim(
                                        (string)(
                                            $report['profile_image']
                                            ?? ''
                                        )
                                    );


                                $avatarUrl = '';

                                if ($profileImage !== '') {

                                    if (
                                        str_starts_with(
                                            $profileImage,
                                            'http://'
                                        )
                                        ||
                                        str_starts_with(
                                            $profileImage,
                                            'https://'
                                        )
                                    ) {

                                        $avatarUrl =
                                            $profileImage;
                                    } else {

                                        $avatarUrl =
                                            asset(
                                                ltrim(
                                                    $profileImage,
                                                    '/'
                                                )
                                            );
                                    }
                                }


                                $initial =
                                    strtoupper(
                                        substr(
                                            $displayName,
                                            0,
                                            1
                                        )
                                    );


                                $status =
                                    (string)(
                                        $report['status']
                                        ?? 'open'
                                    );


                                $priority =
                                    (string)(
                                        $report['priority']
                                        ?? 'medium'
                                    );


                                $type =
                                    (string)(
                                        $report['type']
                                        ?? 'other'
                                    );


                                $statusClass =
                                    $statusClasses[$status]
                                    ?? 'primary';


                                $priorityClass =
                                    $priorityClasses[$priority]
                                    ?? 'info';


                                $typeClass =
                                    $typeClasses[$type]
                                    ?? 'secondary';

                                ?>


                                <tr>


                                    <!-- USER -->

                                    <td class="align-middle">

                                        <div class="d-flex align-items-center">


                                            <?php if ($avatarUrl !== ''): ?>

                                                <img
                                                    src="<?= e($avatarUrl) ?>"
                                                    alt="<?= e($displayName) ?>"
                                                    width="42"
                                                    height="42"
                                                    class="rounded-circle mr-3"
                                                    style="object-fit:cover;"
                                                    onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">

                                                <div
                                                    class="rounded-circle bg-primary text-white align-items-center justify-content-center mr-3"
                                                    style="width:42px;height:42px;display:none;">

                                                    <strong>

                                                        <?= e($initial) ?>

                                                    </strong>

                                                </div>

                                            <?php else: ?>

                                                <div
                                                    class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center mr-3"
                                                    style="width:42px;height:42px;">

                                                    <strong>

                                                        <?= e($initial) ?>

                                                    </strong>

                                                </div>

                                            <?php endif; ?>


                                            <div>

                                                <div class="font-weight-bold text-gray-800">

                                                    <?= e($displayName) ?>

                                                </div>

                                                <div class="small text-muted">

                                                    <?= e($email) ?>

                                                </div>

                                            </div>

                                        </div>

                                    </td>


                                    <!-- TYPE -->

                                    <td class="align-middle">

                                        <span
                                            class="badge badge-<?= e($typeClass) ?> px-2 py-1">

                                            <?php if ($type === 'suggestion'): ?>

                                                <i class="fas fa-lightbulb mr-1"></i>

                                            <?php elseif ($type === 'bug'): ?>

                                                <i class="fas fa-bug mr-1"></i>

                                            <?php elseif ($type === 'feature'): ?>

                                                <i class="fas fa-star mr-1"></i>

                                            <?php else: ?>

                                                <i class="fas fa-comment mr-1"></i>

                                            <?php endif; ?>


                                            <?= e(
                                                $allowedTypes[$type]
                                                    ?? ucfirst($type)
                                            ) ?>

                                        </span>

                                    </td>


                                    <!-- SUBJECT -->

                                    <td class="align-middle">

                                        <div class="feedback-subject">

                                            <?= e(
                                                $report['subject']
                                            ) ?>

                                        </div>


                                        <?php if (
                                            !empty($report['affected_page'])
                                        ): ?>

                                            <div class="small text-muted mt-1">

                                                <i class="fas fa-link mr-1"></i>

                                                <?= e(
                                                    $report['affected_page']
                                                ) ?>

                                            </div>

                                        <?php endif; ?>

                                    </td>


                                    <!-- PRIORITY -->

                                    <td class="align-middle">

                                        <span
                                            class="badge badge-<?= e($priorityClass) ?> px-2 py-1">

                                            <?= e(
                                                $allowedPriorities[$priority]
                                                    ?? ucfirst($priority)
                                            ) ?>

                                        </span>

                                    </td>


                                    <!-- STATUS -->

                                    <td class="align-middle">

                                        <span
                                            class="badge badge-<?= e($statusClass) ?> px-2 py-1">

                                            <?= e(
                                                $allowedStatuses[$status]
                                                    ?? ucfirst(
                                                        str_replace(
                                                            '_',
                                                            ' ',
                                                            $status
                                                        )
                                                    )
                                            ) ?>

                                        </span>

                                    </td>


                                    <!-- DATE -->

                                    <td class="align-middle">

                                        <div class="small text-gray-700">

                                            <?= e(
                                                format_date(
                                                    $report['created_at']
                                                )
                                            ) ?>

                                        </div>

                                    </td>


                                    <!-- ACTIONS -->

                                    <td class="align-middle text-center">

                                        <div class="feedback-actions">


                                            <!-- VIEW -->

                                            <a
                                                href="feedback.php?view=<?= (int)$report['id'] ?>"
                                                class="btn btn-sm btn-outline-info"
                                                title="View feedback">

                                                <i class="fas fa-eye"></i>

                                            </a>


                                            <!-- UPDATE -->

                                            <button
                                                type="button"
                                                class="btn btn-sm btn-outline-primary"
                                                data-toggle="modal"
                                                data-target="#updateFeedbackModal<?= (int)$report['id'] ?>"
                                                title="Update feedback">

                                                <i class="fas fa-edit"></i>

                                            </button>


                                            <!-- DELETE -->

                                            <button
                                                type="button"
                                                class="btn btn-sm btn-outline-danger"
                                                data-toggle="modal"
                                                data-target="#deleteFeedbackModal<?= (int)$report['id'] ?>"
                                                title="Delete feedback">

                                                <i class="fas fa-trash"></i>

                                            </button>

                                        </div>

                                    </td>

                                </tr>


                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            <?php endif; ?>

        </div>

    </div>

</div>


<!-- =========================================================
     VIEW FEEDBACK MODAL
========================================================== -->

<?php if ($viewFeedback): ?>


    <?php

    $viewType =
        (string)(
            $viewFeedback['type']
            ?? 'other'
        );

    $viewStatus =
        (string)(
            $viewFeedback['status']
            ?? 'open'
        );

    $viewPriority =
        (string)(
            $viewFeedback['priority']
            ?? 'medium'
        );


    $viewName =
        trim(
            (string)(
                $viewFeedback['full_name']
                ?? ''
            )
        );


    if ($viewName === '') {

        $viewName =
            trim(
                (string)(
                    $viewFeedback['username']
                    ?? ''
                )
            );
    }


    if ($viewName === '') {

        $viewName = 'User';
    }


    $viewProfileImage =
        trim(
            (string)(
                $viewFeedback['profile_image']
                ?? ''
            )
        );


    $viewAvatarUrl = '';

    if ($viewProfileImage !== '') {

        if (
            str_starts_with(
                $viewProfileImage,
                'http://'
            )
            ||
            str_starts_with(
                $viewProfileImage,
                'https://'
            )
        ) {

            $viewAvatarUrl =
                $viewProfileImage;
        } else {

            $viewAvatarUrl =
                asset(
                    ltrim(
                        $viewProfileImage,
                        '/'
                    )
                );
        }
    }


    $viewInitial =
        strtoupper(
            substr(
                $viewName,
                0,
                1
            )
        );

    ?>


    <div
        class="modal fade"
        id="viewFeedbackModal"
        tabindex="-1"
        role="dialog"
        aria-labelledby="viewFeedbackModalLabel"
        aria-hidden="true">

        <div
            class="modal-dialog modal-lg modal-dialog-centered"
            role="document">

            <div class="modal-content border-0 shadow">


                <!-- HEADER -->

                <div class="modal-header">

                    <h5
                        class="modal-title font-weight-bold text-gray-800"
                        id="viewFeedbackModalLabel">

                        <i class="fas fa-comment-alt text-primary mr-2"></i>

                        Feedback Details

                    </h5>

                    <button
                        type="button"
                        class="close"
                        data-dismiss="modal">

                        <span>&times;</span>

                    </button>

                </div>


                <!-- BODY -->

                <div class="modal-body">


                    <!-- USER HEADER -->

                    <div class="feedback-user-header mb-4">

                        <div class="d-flex align-items-center">


                            <?php if ($viewAvatarUrl !== ''): ?>

                                <img
                                    src="<?= e($viewAvatarUrl) ?>"
                                    alt="<?= e($viewName) ?>"
                                    width="64"
                                    height="64"
                                    class="rounded-circle mr-3"
                                    style="object-fit:cover;"
                                    onerror="this.style.display='none';document.getElementById('viewFeedbackInitial').style.display='flex';">

                                <div
                                    id="viewFeedbackInitial"
                                    class="rounded-circle bg-primary text-white align-items-center justify-content-center mr-3"
                                    style="width:64px;height:64px;display:none;">

                                    <span class="h4 mb-0">

                                        <?= e($viewInitial) ?>

                                    </span>

                                </div>

                            <?php else: ?>

                                <div
                                    class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center mr-3"
                                    style="width:64px;height:64px;">

                                    <span class="h4 mb-0">

                                        <?= e($viewInitial) ?>

                                    </span>

                                </div>

                            <?php endif; ?>


                            <div>

                                <h5 class="font-weight-bold text-gray-800 mb-1">

                                    <?= e($viewName) ?>

                                </h5>

                                <div class="text-muted small">

                                    <?= e(
                                        $viewFeedback['email']
                                            ?? ''
                                    ) ?>

                                </div>

                            </div>

                        </div>

                    </div>


                    <!-- SUBJECT -->

                    <div class="feedback-detail-card mb-3">

                        <div class="feedback-detail-label">

                            <i class="fas fa-heading mr-2"></i>

                            Subject

                        </div>

                        <div class="feedback-detail-value">

                            <?= e(
                                $viewFeedback['subject']
                            ) ?>

                        </div>

                    </div>


                    <!-- META -->

                    <div class="row">


                        <!-- TYPE -->

                        <div class="col-md-4 mb-3">

                            <div class="feedback-detail-card h-100">

                                <div class="feedback-detail-label">

                                    <i class="fas fa-tag mr-2"></i>

                                    Type

                                </div>

                                <div class="mt-2">

                                    <span
                                        class="badge badge-<?= e(
                                                                $typeClasses[$viewType]
                                                                    ?? 'secondary'
                                                            ) ?> px-2 py-1">

                                        <?= e(
                                            $allowedTypes[$viewType]
                                                ?? ucfirst($viewType)
                                        ) ?>

                                    </span>

                                </div>

                            </div>

                        </div>


                        <!-- PRIORITY -->

                        <div class="col-md-4 mb-3">

                            <div class="feedback-detail-card h-100">

                                <div class="feedback-detail-label">

                                    <i class="fas fa-flag mr-2"></i>

                                    Priority

                                </div>

                                <div class="mt-2">

                                    <span
                                        class="badge badge-<?= e(
                                                                $priorityClasses[$viewPriority]
                                                                    ?? 'info'
                                                            ) ?> px-2 py-1">

                                        <?= e(
                                            $allowedPriorities[$viewPriority]
                                                ?? ucfirst($viewPriority)
                                        ) ?>

                                    </span>

                                </div>

                            </div>

                        </div>


                        <!-- STATUS -->

                        <div class="col-md-4 mb-3">

                            <div class="feedback-detail-card h-100">

                                <div class="feedback-detail-label">

                                    <i class="fas fa-tasks mr-2"></i>

                                    Status

                                </div>

                                <div class="mt-2">

                                    <span
                                        class="badge badge-<?= e(
                                                                $statusClasses[$viewStatus]
                                                                    ?? 'primary'
                                                            ) ?> px-2 py-1">

                                        <?= e(
                                            $allowedStatuses[$viewStatus]
                                                ?? ucfirst(
                                                    str_replace(
                                                        '_',
                                                        ' ',
                                                        $viewStatus
                                                    )
                                                )
                                        ) ?>

                                    </span>

                                </div>

                            </div>

                        </div>

                    </div>


                    <!-- AFFECTED PAGE / DATE -->

                    <div class="row">


                        <div class="col-md-6 mb-3">

                            <div class="feedback-detail-card h-100">

                                <div class="feedback-detail-label">

                                    <i class="fas fa-link mr-2"></i>

                                    Affected Page

                                </div>

                                <div class="feedback-detail-value">

                                    <?= !empty($viewFeedback['affected_page'])
                                        ? e(
                                            $viewFeedback['affected_page']
                                        )
                                        : 'Not specified'
                                    ?>

                                </div>

                            </div>

                        </div>


                        <div class="col-md-6 mb-3">

                            <div class="feedback-detail-card h-100">

                                <div class="feedback-detail-label">

                                    <i class="fas fa-calendar-alt mr-2"></i>

                                    Submitted

                                </div>

                                <div class="feedback-detail-value">

                                    <?= e(
                                        format_date(
                                            $viewFeedback['created_at']
                                        )
                                    ) ?>

                                </div>

                            </div>

                        </div>

                    </div>


                    <!-- DESCRIPTION -->

                    <div class="feedback-detail-card mb-3">

                        <div class="feedback-detail-label">

                            <i class="fas fa-align-left mr-2"></i>

                            User Description

                        </div>

                        <div
                            class="feedback-description">

                            <?= e(
                                $viewFeedback['description']
                            ) ?>

                        </div>

                    </div>


                    <!-- ATTACHMENT -->

                    <?php if (
                        !empty($viewFeedback['attachment'])
                    ): ?>

                        <div class="feedback-detail-card mb-3">

                            <div class="feedback-detail-label">

                                <i class="fas fa-paperclip mr-2"></i>

                                Attachment

                            </div>

                            <div class="mt-2">

                                <a
                                    href="<?= e(
                                                asset(
                                                    $viewFeedback['attachment']
                                                )
                                            ) ?>"
                                    target="_blank"
                                    rel="noopener"
                                    class="btn btn-sm btn-outline-primary">

                                    <i class="fas fa-external-link-alt mr-1"></i>

                                    View Attachment

                                </a>

                            </div>

                        </div>

                    <?php endif; ?>


                    <!-- ADMIN RESPONSE -->

                    <div class="feedback-detail-card">

                        <div class="feedback-detail-label">

                            <i class="fas fa-reply mr-2"></i>

                            Admin Response

                        </div>

                        <div class="feedback-description">

                            <?php if (
                                !empty($viewFeedback['admin_response'])
                            ): ?>

                                <?= e(
                                    $viewFeedback['admin_response']
                                ) ?>

                            <?php else: ?>

                                <span class="text-muted">

                                    No response yet.

                                </span>

                            <?php endif; ?>

                        </div>

                    </div>

                </div>


                <!-- FOOTER -->

                <div class="modal-footer">

                    <button
                        type="button"
                        class="btn btn-light"
                        data-dismiss="modal">

                        Close

                    </button>

                    <button
                        type="button"
                        class="btn btn-primary"
                        data-dismiss="modal"
                        data-toggle="modal"
                        data-target="#updateFeedbackModal<?= (int)$viewFeedback['id'] ?>">

                        <i class="fas fa-edit mr-1"></i>

                        Update Feedback

                    </button>

                </div>

            </div>

        </div>

    </div>


<?php endif; ?>


<!-- =========================================================
     UPDATE + DELETE MODALS
========================================================== -->

<?php foreach (
    $feedbackReports
    as $report
): ?>


    <!-- =====================================================
         UPDATE
    ====================================================== -->

    <div
        class="modal fade"
        id="updateFeedbackModal<?= (int)$report['id'] ?>"
        tabindex="-1"
        role="dialog"
        aria-hidden="true">

        <div
            class="modal-dialog modal-lg"
            role="document">

            <form method="post">

                <div class="modal-content border-0 shadow">


                    <div class="modal-header">

                        <div>

                            <h5 class="modal-title font-weight-bold text-gray-800 mb-1">

                                <i class="fas fa-edit text-primary mr-2"></i>

                                Update Feedback

                            </h5>

                            <small class="text-muted">

                                <?= e(
                                    $report['subject']
                                ) ?>

                            </small>

                        </div>

                        <button
                            type="button"
                            class="close"
                            data-dismiss="modal">

                            <span>&times;</span>

                        </button>

                    </div>


                    <div
                        class="modal-body"
                        style="max-height:70vh;overflow-y:auto;">

                        <input
                            type="hidden"
                            name="action"
                            value="update_feedback">

                        <input
                            type="hidden"
                            name="feedback_id"
                            value="<?= (int)$report['id'] ?>">

                        <?= csrf_field() ?>


                        <!-- STATUS -->

                        <div class="form-group">

                            <label
                                for="status<?= (int)$report['id'] ?>"
                                class="font-weight-bold">

                                Status

                            </label>

                            <select
                                class="form-control"
                                id="status<?= (int)$report['id'] ?>"
                                name="status"
                                required>

                                <?php foreach (
                                    $allowedStatuses
                                    as $value => $label
                                ): ?>

                                    <option
                                        value="<?= e($value) ?>"
                                        <?= (
                                            $report['status']
                                            === $value
                                        )
                                            ? 'selected'
                                            : '' ?>>

                                        <?= e($label) ?>

                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>


                        <!-- USER DESCRIPTION -->

                        <div class="form-group">

                            <label class="font-weight-bold">

                                User's Description

                            </label>

                            <div
                                class="form-control bg-light"
                                style="height:auto;min-height:120px;white-space:pre-wrap;">

                                <?= e(
                                    $report['description']
                                ) ?>

                            </div>

                        </div>


                        <!-- ADMIN RESPONSE -->

                        <div class="form-group mb-0">

                            <label
                                for="adminResponse<?= (int)$report['id'] ?>"
                                class="font-weight-bold">

                                Admin Response

                            </label>

                            <textarea
                                class="form-control"
                                id="adminResponse<?= (int)$report['id'] ?>"
                                name="admin_response"
                                rows="7"
                                maxlength="10000"
                                placeholder="Write a response to the user..."><?= e(
                                                                                    $report['admin_response']
                                                                                        ?? ''
                                                                                ) ?></textarea>

                            <small class="form-text text-muted">

                                Explain the resolution, provide instructions,
                                or give the user an update.

                            </small>

                        </div>

                    </div>


                    <div class="modal-footer">

                        <button
                            type="button"
                            class="btn btn-light"
                            data-dismiss="modal">

                            Cancel

                        </button>

                        <button
                            type="submit"
                            class="btn btn-primary">

                            <i class="fas fa-save mr-1"></i>

                            Save Changes

                        </button>

                    </div>

                </div>

            </form>

        </div>

    </div>


    <!-- =====================================================
         DELETE
    ====================================================== -->

    <div
        class="modal fade"
        id="deleteFeedbackModal<?= (int)$report['id'] ?>"
        tabindex="-1"
        role="dialog"
        aria-hidden="true">

        <div
            class="modal-dialog modal-lg"
            role="document">

            <form method="post">

                <div class="modal-content border-0 shadow">


                    <div class="modal-header">

                        <h5 class="modal-title font-weight-bold text-danger">

                            <i class="fas fa-trash-alt mr-2"></i>

                            Delete Feedback

                        </h5>

                        <button
                            type="button"
                            class="close"
                            data-dismiss="modal">

                            <span>&times;</span>

                        </button>

                    </div>


                    <div class="modal-body">

                        <div class="text-center py-2">

                            <div class="delete-icon mb-3">

                                <i class="fas fa-exclamation-triangle fa-3x text-warning"></i>

                            </div>

                            <h6 class="font-weight-bold text-gray-800">

                                Are you sure?

                            </h6>

                            <p class="text-muted">

                                You are about to permanently delete this
                                feedback report.

                            </p>

                            <div class="alert alert-light border text-left">

                                <strong>

                                    <?= e(
                                        $report['subject']
                                    ) ?>

                                </strong>

                            </div>

                            <p class="small text-muted mb-0">

                                This action cannot be undone.

                            </p>

                        </div>

                        <input
                            type="hidden"
                            name="action"
                            value="delete_feedback">

                        <input
                            type="hidden"
                            name="feedback_id"
                            value="<?= (int)$report['id'] ?>">

                        <?= csrf_field() ?>

                    </div>


                    <div class="modal-footer">

                        <button
                            type="button"
                            class="btn btn-light"
                            data-dismiss="modal">

                            Cancel

                        </button>

                        <button
                            type="submit"
                            class="btn btn-danger">

                            <i class="fas fa-trash mr-1"></i>

                            Delete Feedback

                        </button>

                    </div>

                </div>

            </form>

        </div>

    </div>


<?php endforeach; ?>


<!-- =========================================================
     PAGE CSS
========================================================== -->

<style>
    /*
    |--------------------------------------------------------------------------
    | TABLE
    |--------------------------------------------------------------------------
    */

    #feedbackTable {
        font-size: 0.9rem;
    }

    #feedbackTable thead th {
        font-weight: 700;
        vertical-align: middle;
        white-space: nowrap;
    }

    #feedbackTable tbody td {
        vertical-align: middle;
    }

    #feedbackTable tbody tr:hover {
        background-color: #f8f9fc;
    }


    /*
    |--------------------------------------------------------------------------
    | USER
    |--------------------------------------------------------------------------
    */

    .feedback-subject {
        font-weight: 600;
        color: #3a3b45;
        max-width: 280px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }


    /*
    |--------------------------------------------------------------------------
    | ACTIONS
    |--------------------------------------------------------------------------
    */

    .feedback-actions {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 7px;
    }

    .feedback-actions .btn {
        min-width: 34px;
    }


    /*
    |--------------------------------------------------------------------------
    | FILTERS
    |--------------------------------------------------------------------------
    */

    .filter-buttons .btn {
        white-space: nowrap;
    }


    /*
    |--------------------------------------------------------------------------
    | VIEW MODAL
    |--------------------------------------------------------------------------
    */

    .feedback-user-header {
        background: #f8f9fc;
        border: 1px solid #e3e6f0;
        border-radius: 0.5rem;
        padding: 1.25rem;
    }

    .feedback-detail-card {
        background: #fff;
        border: 1px solid #e3e6f0;
        border-radius: 0.4rem;
        padding: 1rem;
    }

    .feedback-detail-label {
        color: #858796;
        font-size: 0.72rem;
        font-weight: 700;
        text-transform: uppercase;
        margin-bottom: 0.35rem;
    }

    .feedback-detail-value {
        color: #3a3b45;
        font-weight: 600;
        word-break: break-word;
    }

    .feedback-description {
        color: #3a3b45;
        line-height: 1.65;
        white-space: pre-wrap;
        word-break: break-word;
    }


    /*
    |--------------------------------------------------------------------------
    | MODALS
    |--------------------------------------------------------------------------
    */

    .modal-content {
        border-radius: 0.5rem;
    }

    .modal-header {
        border-bottom: 1px solid #e3e6f0;
    }

    .modal-footer {
        border-top: 1px solid #e3e6f0;
    }


    /*
    |--------------------------------------------------------------------------
    | DATATABLES
    |--------------------------------------------------------------------------
    */

    .dataTables_wrapper .dataTables_length select {
        min-width: 70px;
        margin: 0 5px;
    }

    .dataTables_wrapper .dataTables_info {
        padding-top: 0.75rem;
        color: #858796;
        font-size: 0.85rem;
    }

    .dataTables_wrapper .dataTables_paginate {
        padding-top: 0.5rem;
    }

    .dataTables_wrapper .pagination {
        margin-bottom: 0;
    }

    .dataTables_wrapper .page-item .page-link {
        border-radius: 0.25rem;
        margin: 0 2px;
    }


    /*
    |--------------------------------------------------------------------------
    | MOBILE
    |--------------------------------------------------------------------------
    */

    @media (max-width: 767.98px) {

        #feedbackTable {
            min-width: 1050px;
        }

        .feedback-actions {
            gap: 5px;
        }

        .feedback-user-header {
            padding: 1rem;
        }

    }
</style>


<!-- =========================================================
     PAGE JS
========================================================== -->

<script>
    document.addEventListener('DOMContentLoaded', function() {


        /*
        |--------------------------------------------------------------------------
        | DATATABLES
        |--------------------------------------------------------------------------
        */

        if ($.fn.DataTable) {

            $('#feedbackTable').DataTable({

                paging: true,

                searching: false,

                ordering: false,

                info: true,

                lengthChange: true,

                pageLength: 10,

                lengthMenu: [
                    [10, 25, 50, 100],
                    [10, 25, 50, 100]
                ],

                responsive: false,

                language: {

                    lengthMenu: 'Show _MENU_ entries',

                    info: 'Showing _START_ to _END_ of _TOTAL_ feedback reports',

                    infoEmpty: 'Showing 0 to 0 of 0 feedback reports',

                    zeroRecords: 'No feedback reports found',

                    paginate: {

                        previous: '<i class="fas fa-chevron-left"></i>',

                        next: '<i class="fas fa-chevron-right"></i>'

                    }

                },

                dom:

                    '<"row"<"col-sm-12 col-md-6"l>>' +

                    '<"row mt-2"<"col-sm-12"tr>>' +

                    '<"row align-items-center mt-3"' +

                    '<"col-sm-12 col-md-5"i>' +

                    '<"col-sm-12 col-md-7"p>' +

                    '>'

            });

        }

    });
</script>


<!-- =========================================================
     AUTO OPEN VIEW MODAL
========================================================== -->

<?php if ($viewFeedback): ?>

    <script>
        document.addEventListener(
            'DOMContentLoaded',
            function() {

                $('#viewFeedbackModal').modal('show');

            }
        );
    </script>

<?php endif; ?>


<?php

require dirname(__DIR__) . '/includes/footer.php';

?>