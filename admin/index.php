<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once __DIR__ . '/../includes/maintenance-check.php';

require_role('admin');

$db = db();

$pageTitle = 'Admin Dashboard';

/*
|--------------------------------------------------------------------------
| STATISTICS
|--------------------------------------------------------------------------
*/

$totalUsers = 0;
$activeUsers = 0;
$inactiveUsers = 0;
$publishedPortfolios = 0;

$totalFeedback = 0;
$openFeedback = 0;
$underReviewFeedback = 0;
$inProgressFeedback = 0;
$resolvedFeedback = 0;

try {

    /*
     * USERS
     */

    $stmt = $db->query("
        SELECT COUNT(*)
        FROM users
        WHERE role != 'admin'
    ");

    $totalUsers = (int) $stmt->fetchColumn();


    $stmt = $db->query("
        SELECT COUNT(*)
        FROM users
        WHERE role != 'admin'
        AND account_status = 'active'
    ");

    $activeUsers = (int) $stmt->fetchColumn();


    $stmt = $db->query("
        SELECT COUNT(*)
        FROM users
        WHERE role != 'admin'
        AND account_status != 'active'
    ");

    $inactiveUsers = (int) $stmt->fetchColumn();


    /*
     * PUBLISHED PORTFOLIOS
     */

    $stmt = $db->query("
        SELECT COUNT(*)
        FROM profiles p
        INNER JOIN users u
            ON u.id = p.user_id
        WHERE u.role != 'admin'
        AND p.portfolio_public = 1
    ");

    $publishedPortfolios = (int) $stmt->fetchColumn();


    /*
     * FEEDBACK
     */

    $stmt = $db->query("
        SELECT COUNT(*)
        FROM feedback_reports
    ");

    $totalFeedback = (int) $stmt->fetchColumn();


    $stmt = $db->query("
        SELECT COUNT(*)
        FROM feedback_reports
        WHERE status = 'open'
    ");

    $openFeedback = (int) $stmt->fetchColumn();


    $stmt = $db->query("
        SELECT COUNT(*)
        FROM feedback_reports
        WHERE status = 'under_review'
    ");

    $underReviewFeedback = (int) $stmt->fetchColumn();


    $stmt = $db->query("
        SELECT COUNT(*)
        FROM feedback_reports
        WHERE status = 'in_progress'
    ");

    $inProgressFeedback = (int) $stmt->fetchColumn();


    $stmt = $db->query("
        SELECT COUNT(*)
        FROM feedback_reports
        WHERE status IN ('resolved', 'closed')
    ");

    $resolvedFeedback = (int) $stmt->fetchColumn();
} catch (Throwable $e) {

    /*
     * Keep dashboard usable even if an optional table
     * such as feedback_reports is not available yet.
     */
}


/*
|--------------------------------------------------------------------------
| RECENT USERS
|--------------------------------------------------------------------------
*/

$recentUsers = [];

try {

    $stmt = $db->query("
        SELECT
            u.id,
            u.username,
            u.email,
            u.account_status,
            u.created_at,
            p.full_name,
            p.portfolio_public

        FROM users u

        LEFT JOIN profiles p
            ON p.user_id = u.id

        WHERE u.role != 'admin'

        ORDER BY u.created_at DESC

        LIMIT 5
    ");

    $recentUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {

    $recentUsers = [];
}


/*
|--------------------------------------------------------------------------
| RECENT FEEDBACK
|--------------------------------------------------------------------------
*/

$recentFeedback = [];

try {

    $stmt = $db->query("
        SELECT
            f.id,
            f.type,
            f.subject,
            f.priority,
            f.status,
            f.created_at,
            u.username,
            u.email,
            p.full_name

        FROM feedback_reports f

        INNER JOIN users u
            ON u.id = f.user_id

        LEFT JOIN profiles p
            ON p.user_id = u.id

        ORDER BY f.created_at DESC

        LIMIT 5
    ");

    $recentFeedback = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {

    $recentFeedback = [];
}


/*
|--------------------------------------------------------------------------
| LABELS
|--------------------------------------------------------------------------
*/

$feedbackTypes = [
    'suggestion' => 'Suggestion',
    'bug'        => 'Bug / Issue',
    'feature'    => 'Feature Request',
    'other'      => 'Other'
];

$feedbackStatuses = [
    'open'         => 'Open',
    'under_review' => 'Under Review',
    'in_progress'  => 'In Progress',
    'resolved'     => 'Resolved',
    'closed'       => 'Closed'
];

$feedbackStatusClasses = [
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


/*
|--------------------------------------------------------------------------
| HEADER
|--------------------------------------------------------------------------
*/

require dirname(__DIR__) . '/includes/header.php';

?>

<div class="container-fluid">


    <!-- =====================================================
         PAGE HEADING
         ===================================================== -->

    <div class="d-sm-flex align-items-center justify-content-between mb-4">

        <div>

            <h2 class="h3 mb-1 text-gray-800 font-weight-bold">
                Admin Dashboard
            </h2>

            <p class="text-muted mb-0">
                Overview of users, portfolios, and feedback.
            </p>

        </div>


        <div class="mt-3 mt-md-0">

            <a
                href="<?= e(asset('admin/users.php')) ?>"
                class="btn btn-primary">
                <i class="fas fa-users mr-1"></i>
                Manage Users
            </a>

        </div>

    </div>


    <!-- =========================================================
                 STAT CARDS
            ========================================================== -->

    <div class="row">

        <!-- TOTAL USERS -->

        <div class="col-xl-3 col-md-6 mb-4">

            <div class="card border-left-primary shadow h-100 py-2">

                <div class="card-body">

                    <div class="row no-gutters align-items-center">

                        <div class="col mr-2">

                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                Total Users
                            </div>

                            <div class="h4 mb-0 font-weight-bold text-gray-800">
                                <?= number_format($totalUsers) ?>
                            </div>

                        </div>

                        <div class="col-auto">

                            <i class="fas fa-users fa-2x text-gray-300"></i>

                        </div>

                    </div>

                </div>

            </div>

        </div>


        <!-- ACTIVE USERS -->

        <div class="col-xl-3 col-md-6 mb-4">

            <div class="card border-left-success shadow h-100 py-2">

                <div class="card-body">

                    <div class="row no-gutters align-items-center">

                        <div class="col mr-2">

                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                Active Users
                            </div>

                            <div class="h4 mb-0 font-weight-bold text-gray-800">
                                <?= number_format($activeUsers) ?>
                            </div>

                        </div>

                        <div class="col-auto">

                            <i class="fas fa-user-check fa-2x text-gray-300"></i>

                        </div>

                    </div>

                </div>

            </div>

        </div>


        <!-- PUBLISHED PORTFOLIOS -->

        <div class="col-xl-3 col-md-6 mb-4">

            <div class="card border-left-info shadow h-100 py-2">

                <div class="card-body">

                    <div class="row no-gutters align-items-center">

                        <div class="col mr-2">

                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                Published Portfolios
                            </div>

                            <div class="h4 mb-0 font-weight-bold text-gray-800">
                                <?= number_format($publishedPortfolios) ?>
                            </div>

                        </div>

                        <div class="col-auto">

                            <i class="fas fa-globe fa-2x text-gray-300"></i>

                        </div>

                    </div>

                </div>

            </div>

        </div>


        <!-- OPEN FEEDBACK -->

        <div class="col-xl-3 col-md-6 mb-4">

            <div class="card border-left-warning shadow h-100 py-2">

                <div class="card-body">

                    <div class="row no-gutters align-items-center">

                        <div class="col mr-2">

                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                Open Feedback
                            </div>

                            <div class="h4 mb-0 font-weight-bold text-gray-800">
                                <?= number_format($openFeedback) ?>
                            </div>

                        </div>

                        <div class="col-auto">

                            <i class="fas fa-comment-dots fa-2x text-gray-300"></i>

                        </div>

                    </div>

                </div>

            </div>

        </div>

    </div>

    <!-- =========================================================
                 RECENT USERS + RECENT FEEDBACK
            ========================================================== -->

    <div class="row">


        <!-- =====================================================
                     RECENT USERS
                ====================================================== -->

        <div class="col-xl-7 col-lg-12 mb-4">

            <div class="card shadow h-100">

                <div class="card-header py-3 d-flex justify-content-between align-items-center">

                    <div>

                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-user-plus mr-1"></i>
                            Recently Added Users
                        </h6>

                        <small class="text-muted">
                            The latest users who joined MyPortfolio.
                        </small>

                    </div>


                    <a
                        href="<?= e(asset('admin/users.php')) ?>"
                        class="btn btn-sm btn-outline-primary">
                        View All
                    </a>

                </div>


                <div class="card-body p-0">

                    <?php if (!empty($recentUsers)): ?>

                        <div class="table-responsive">

                            <table class="table table-hover mb-0">

                                <thead class="bg-light">

                                    <tr>

                                        <th>
                                            User
                                        </th>

                                        <th>
                                            Status
                                        </th>

                                        <th>
                                            Portfolio
                                        </th>

                                        <th>
                                            Joined
                                        </th>

                                    </tr>

                                </thead>


                                <tbody>

                                    <?php foreach ($recentUsers as $user): ?>

                                        <?php

                                        $userId = (int) (
                                            $user['id'] ?? 0
                                        );

                                        $name = trim(
                                            (string) (
                                                $user['full_name']
                                                ?? ''
                                            )
                                        );

                                        $username = trim(
                                            (string) (
                                                $user['username']
                                                ?? ''
                                            )
                                        );

                                        $email = trim(
                                            (string) (
                                                $user['email']
                                                ?? ''
                                            )
                                        );

                                        if ($name === '') {
                                            $name = $username !== ''
                                                ? $username
                                                : 'User #' . $userId;
                                        }

                                        $initial = strtoupper(
                                            substr(
                                                $name,
                                                0,
                                                1
                                            )
                                        );

                                        $status = strtolower(
                                            (string) (
                                                $user['account_status']
                                                ?? 'inactive'
                                            )
                                        );

                                        $portfolioPublic =
                                            (int) (
                                                $user['portfolio_public']
                                                ?? 0
                                            ) === 1;

                                        ?>

                                        <tr>

                                            <!-- USER -->

                                            <td>

                                                <div class="d-flex align-items-center">

                                                    <div
                                                        class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center mr-3"
                                                        style="width:40px;height:40px;">

                                                        <span class="font-weight-bold">
                                                            <?= e($initial) ?>
                                                        </span>

                                                    </div>


                                                    <div>

                                                        <div class="font-weight-bold text-gray-800">

                                                            <?= e($name) ?>

                                                        </div>


                                                        <div class="small text-muted">

                                                            <?= e($email) ?>

                                                        </div>

                                                    </div>

                                                </div>

                                            </td>


                                            <!-- STATUS -->

                                            <td>

                                                <?php if ($status === 'active'): ?>

                                                    <span class="badge badge-success">
                                                        <i class="fas fa-check-circle mr-1"></i>
                                                        Active
                                                    </span>

                                                <?php else: ?>

                                                    <span class="badge badge-secondary">
                                                        Inactive
                                                    </span>

                                                <?php endif; ?>

                                            </td>


                                            <!-- PORTFOLIO -->

                                            <td>

                                                <?php if ($portfolioPublic): ?>

                                                    <span class="badge badge-success">
                                                        Published
                                                    </span>

                                                <?php else: ?>

                                                    <span class="badge badge-light border">
                                                        Private
                                                    </span>

                                                <?php endif; ?>

                                            </td>


                                            <!-- JOINED -->

                                            <td class="small text-muted">

                                                <?php if (!empty($user['created_at'])): ?>

                                                    <?= e(
                                                        date(
                                                            'M d, Y',
                                                            strtotime(
                                                                (string) $user['created_at']
                                                            )
                                                        )
                                                    ) ?>

                                                <?php else: ?>

                                                    —

                                                <?php endif; ?>

                                            </td>

                                        </tr>

                                    <?php endforeach; ?>

                                </tbody>

                            </table>

                        </div>

                    <?php else: ?>

                        <div class="text-center py-5">

                            <i class="fas fa-users fa-3x text-gray-300 mb-3"></i>

                            <h6 class="font-weight-bold text-gray-700">
                                No users found
                            </h6>

                            <p class="text-muted small mb-0">
                                Recently registered users will appear here.
                            </p>

                        </div>

                    <?php endif; ?>

                </div>

            </div>

        </div>


        <!-- =====================================================
                     RECENT FEEDBACK
                ====================================================== -->

        <div class="col-xl-5 col-lg-12 mb-4">

            <div class="card shadow h-100">

                <div class="card-header py-3 d-flex justify-content-between align-items-center">

                    <div>

                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-comment-alt mr-1"></i>
                            Recent Feedback
                        </h6>

                        <small class="text-muted">
                            Latest reports from users.
                        </small>

                    </div>


                    <a
                        href="<?= e(asset('admin/feedback.php')) ?>"
                        class="btn btn-sm btn-outline-primary">
                        View All
                    </a>

                </div>


                <div class="card-body">

                    <?php if (!empty($recentFeedback)): ?>

                        <?php foreach ($recentFeedback as $feedback): ?>

                            <?php

                            $type = strtolower(
                                (string) (
                                    $feedback['type']
                                    ?? 'other'
                                )
                            );

                            $status = strtolower(
                                (string) (
                                    $feedback['status']
                                    ?? 'open'
                                )
                            );

                            $priority = strtolower(
                                (string) (
                                    $feedback['priority']
                                    ?? 'medium'
                                )
                            );

                            $subject = trim(
                                (string) (
                                    $feedback['subject']
                                    ?? ''
                                )
                            );

                            if ($subject === '') {
                                $subject = 'Untitled Feedback';
                            }

                            $userName = trim(
                                (string) (
                                    $feedback['full_name']
                                    ?? ''
                                )
                            );

                            if ($userName === '') {

                                $userName = trim(
                                    (string) (
                                        $feedback['username']
                                        ?? ''
                                    )
                                );
                            }

                            if ($userName === '') {
                                $userName = 'User';
                            }

                            $statusClass =
                                $feedbackStatusClasses[$status]
                                ?? 'secondary';

                            $priorityClass =
                                $priorityClasses[$priority]
                                ?? 'secondary';

                            ?>

                            <div class="border-bottom pb-3 mb-3">

                                <div class="d-flex justify-content-between align-items-start">

                                    <div class="pr-2">

                                        <div class="font-weight-bold text-gray-800">

                                            <?= e($subject) ?>

                                        </div>


                                        <!-- <div class="small text-muted mt-1">

                                            <i class="fas fa-user mr-1"></i>

                                            <?= e($userName) ?>

                                        </div> -->

                                    </div>


                                    <span class="badge badge-<?= e($statusClass) ?>">

                                        <?= e(
                                            $feedbackStatuses[$status]
                                                ?? ucfirst(
                                                    str_replace(
                                                        '_',
                                                        ' ',
                                                        $status
                                                    )
                                                )
                                        ) ?>

                                    </span>

                                </div>


                                <div class="mt-2">

                                    <span class="badge badge-light border mr-1">

                                        <?= e(
                                            $feedbackTypes[$type]
                                                ?? 'Other'
                                        ) ?>

                                    </span>


                                    <span class="badge badge-<?= e($priorityClass) ?>">

                                        <?= e(
                                            ucfirst($priority)
                                        ) ?>

                                    </span>

                                </div>


                                <!-- <?php if (!empty($feedback['created_at'])): ?>

                                    <div class="small text-muted mt-2">

                                        <i class="far fa-clock mr-1"></i>

                                        <?= e(
                                            date(
                                                'M d, Y · g:i A',
                                                strtotime(
                                                    (string) $feedback['created_at']
                                                )
                                            )
                                        ) ?>

                                    </div>

                                <?php endif; ?> -->

                            </div>

                        <?php endforeach; ?>


                        <div class="text-center">

                            <a
                                href="<?= e(asset('admin/feedback.php')) ?>"
                                class="btn btn-sm btn-primary">
                                <i class="fas fa-comments mr-1"></i>
                                Manage Feedback
                            </a>

                        </div>

                    <?php else: ?>

                        <div class="text-center py-5">

                            <i class="fas fa-comments fa-3x text-gray-300 mb-3"></i>

                            <h6 class="font-weight-bold text-gray-700">
                                No feedback yet
                            </h6>

                            <p class="text-muted small mb-0">
                                User suggestions and reports will appear here.
                            </p>

                        </div>

                    <?php endif; ?>

                </div>

            </div>

        </div>

    </div>

</div>

</main>

</div>


<?php require dirname(__DIR__) . '/includes/footer.php'; ?>