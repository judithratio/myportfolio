<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once __DIR__ . '/../includes/maintenance-check.php';

require_role('user');

$userId = current_user_id();
$pdo = db();

$pageTitle = 'Suggestions / Report Issue';

$allowedTypes = [
    'suggestion' => 'Suggestion',
    'bug'        => 'Bug / Issue',
    'feature'    => 'Feature Request',
    'other'      => 'Other'
];

$allowedPriorities = [
    'low'      => 'Low',
    'medium'   => 'Medium',
    'high'     => 'High',
    'critical' => 'Critical'
];

$allowedPages = [
    'Dashboard',
    'Profile',
    'Projects',
    'Experience',
    'Education',
    'Skills',
    'Certifications',
    'References',
    'Portfolio Settings',
    'Resume / CV',
    'Personal Website',
    'Login / Account',
    'Other'
];

$allowedStatuses = [
    'open'         => 'Open',
    'under_review' => 'Under Review',
    'in_progress'  => 'In Progress',
    'resolved'     => 'Resolved',
    'closed'       => 'Closed'
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


/*
|--------------------------------------------------------------------------
| Handle POST
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    try {

        verify_csrf();

        $action = trim(
            (string) ($_POST['action'] ?? '')
        );


        /*
        |--------------------------------------------------------------------------
        | Submit Feedback
        |--------------------------------------------------------------------------
        */

        if ($action === 'submit_feedback') {

            $type = strtolower(
                trim((string) ($_POST['type'] ?? ''))
            );

            $subject = trim(
                (string) ($_POST['subject'] ?? '')
            );

            $description = trim(
                (string) ($_POST['description'] ?? '')
            );

            $priority = strtolower(
                trim((string) ($_POST['priority'] ?? 'medium'))
            );

            $affectedPage = trim(
                (string) ($_POST['affected_page'] ?? '')
            );


            if (!isset($allowedTypes[$type])) {

                throw new RuntimeException(
                    'Please select a valid feedback type.'
                );
            }


            if ($subject === '') {

                throw new RuntimeException(
                    'Please enter a subject.'
                );
            }


            if (mb_strlen($subject) > 255) {

                throw new RuntimeException(
                    'The subject must be 255 characters or less.'
                );
            }


            if ($description === '') {

                throw new RuntimeException(
                    'Please describe your suggestion or issue.'
                );
            }


            if (mb_strlen($description) > 10000) {

                throw new RuntimeException(
                    'The description is too long.'
                );
            }


            if (!isset($allowedPriorities[$priority])) {

                $priority = 'medium';
            }


            if (
                $affectedPage !== '' &&
                !in_array(
                    $affectedPage,
                    $allowedPages,
                    true
                )
            ) {

                $affectedPage = 'Other';
            }


            /*
            |--------------------------------------------------------------------------
            | Upload Attachment
            |--------------------------------------------------------------------------
            */

            $attachment = upload_file(
                'attachment',
                [
                    'jpg',
                    'jpeg',
                    'png',
                    'webp',
                    'pdf'
                ],
                'feedback'
            );


            /*
            |--------------------------------------------------------------------------
            | Insert Feedback
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare(
                'INSERT INTO feedback_reports
                (
                    user_id,
                    type,
                    subject,
                    description,
                    priority,
                    affected_page,
                    attachment
                )
                VALUES
                (?, ?, ?, ?, ?, ?, ?)'
            );

            $stmt->execute([
                $userId,
                $type,
                $subject,
                $description,
                $priority,
                $affectedPage !== ''
                    ? $affectedPage
                    : null,
                $attachment
            ]);


            flash(
                'success',
                'Your feedback has been submitted successfully.'
            );

            redirect('user/feedback.php');
        }


        /*
        |--------------------------------------------------------------------------
        | Edit Feedback
        |--------------------------------------------------------------------------
        */

        if ($action === 'edit_feedback') {

            $feedbackId = (int) (
                $_POST['feedback_id'] ?? 0
            );

            $type = strtolower(
                trim((string) ($_POST['type'] ?? ''))
            );

            $subject = trim(
                (string) ($_POST['subject'] ?? '')
            );

            $description = trim(
                (string) ($_POST['description'] ?? '')
            );

            $priority = strtolower(
                trim((string) ($_POST['priority'] ?? 'medium'))
            );

            $affectedPage = trim(
                (string) ($_POST['affected_page'] ?? '')
            );


            if ($feedbackId <= 0) {

                throw new RuntimeException(
                    'Invalid feedback report.'
                );
            }


            if (!isset($allowedTypes[$type])) {

                throw new RuntimeException(
                    'Please select a valid feedback type.'
                );
            }


            if ($subject === '') {

                throw new RuntimeException(
                    'Please enter a subject.'
                );
            }


            if (mb_strlen($subject) > 255) {

                throw new RuntimeException(
                    'The subject must be 255 characters or less.'
                );
            }


            if ($description === '') {

                throw new RuntimeException(
                    'Please describe your suggestion or issue.'
                );
            }


            if (mb_strlen($description) > 10000) {

                throw new RuntimeException(
                    'The description is too long.'
                );
            }


            if (!isset($allowedPriorities[$priority])) {

                $priority = 'medium';
            }


            if (
                $affectedPage !== '' &&
                !in_array(
                    $affectedPage,
                    $allowedPages,
                    true
                )
            ) {

                $affectedPage = 'Other';
            }


            /*
            |--------------------------------------------------------------------------
            | Get Existing Feedback
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare(
                'SELECT *
                 FROM feedback_reports
                 WHERE id = ?
                 AND user_id = ?
                 LIMIT 1'
            );

            $stmt->execute([
                $feedbackId,
                $userId
            ]);

            $existing = $stmt->fetch();


            if (!$existing) {

                throw new RuntimeException(
                    'Feedback report not found.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | Only Open Feedback Can Be Edited
            |--------------------------------------------------------------------------
            */

            if ($existing['status'] !== 'open') {

                throw new RuntimeException(
                    'This feedback can no longer be edited because it is already being reviewed.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | Existing Attachment
            |--------------------------------------------------------------------------
            */

            $attachment = $existing['attachment'];


            /*
            |--------------------------------------------------------------------------
            | Replace Attachment If New File Was Uploaded
            |--------------------------------------------------------------------------
            */

            $newAttachment = upload_file(
                'attachment',
                [
                    'jpg',
                    'jpeg',
                    'png',
                    'webp',
                    'pdf'
                ],
                'feedback'
            );


            if (!empty($newAttachment)) {

                if (!empty($attachment)) {

                    delete_upload(
                        $attachment
                    );
                }

                $attachment = $newAttachment;
            }


            /*
            |--------------------------------------------------------------------------
            | Update Feedback
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare(
                'UPDATE feedback_reports
                 SET
                    type = ?,
                    subject = ?,
                    description = ?,
                    priority = ?,
                    affected_page = ?,
                    attachment = ?
                 WHERE id = ?
                 AND user_id = ?
                 AND status = "open"'
            );

            $stmt->execute([
                $type,
                $subject,
                $description,
                $priority,
                $affectedPage !== ''
                    ? $affectedPage
                    : null,
                $attachment,
                $feedbackId,
                $userId
            ]);


            flash(
                'success',
                'Your feedback has been updated successfully.'
            );

            redirect('user/feedback.php');
        }


        /*
        |--------------------------------------------------------------------------
        | Delete Feedback
        |--------------------------------------------------------------------------
        */

        if ($action === 'delete_feedback') {

            $feedbackId = (int) (
                $_POST['feedback_id'] ?? 0
            );


            if ($feedbackId <= 0) {

                throw new RuntimeException(
                    'Invalid feedback report.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | Make Sure Feedback Belongs To User
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare(
                'SELECT attachment, status
                 FROM feedback_reports
                 WHERE id = ?
                 AND user_id = ?
                 LIMIT 1'
            );

            $stmt->execute([
                $feedbackId,
                $userId
            ]);

            $feedback = $stmt->fetch();


            if (!$feedback) {

                throw new RuntimeException(
                    'Feedback report not found.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | Only Open Feedback Can Be Deleted
            |--------------------------------------------------------------------------
            */

            if ($feedback['status'] !== 'open') {

                throw new RuntimeException(
                    'This feedback can no longer be deleted because it is already being reviewed.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | Delete Feedback
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare(
                'DELETE FROM feedback_reports
                 WHERE id = ?
                 AND user_id = ?
                 AND status = "open"'
            );

            $stmt->execute([
                $feedbackId,
                $userId
            ]);


            /*
            |--------------------------------------------------------------------------
            | Delete Attachment
            |--------------------------------------------------------------------------
            */

            if (!empty($feedback['attachment'])) {

                delete_upload(
                    $feedback['attachment']
                );
            }


            flash(
                'success',
                'Feedback report deleted successfully.'
            );

            redirect('user/feedback.php');
        }
    } catch (Throwable $e) {

        flash(
            'danger',
            $e->getMessage()
        );

        redirect('user/feedback.php');
    }
}


/*
|--------------------------------------------------------------------------
| Get Feedback Reports
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare(
    'SELECT *
     FROM feedback_reports
     WHERE user_id = ?
     ORDER BY created_at DESC'
);

$stmt->execute([
    $userId
]);

$feedbackReports = $stmt->fetchAll();


require dirname(__DIR__) . '/includes/header.php';

?>

<div class="container-fluid">

    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">

        <div>

            <h1 class="h3 mb-1 text-gray-800">
                Suggestions / Report Issue
            </h1>

            <p class="mb-0 text-gray-600">
                Help us improve MyPortfolio by sharing your ideas
                or reporting problems.
            </p>

        </div>

        <button
            type="button"
            class="btn btn-primary shadow-sm"
            data-toggle="modal"
            data-target="#sendFeedbackModal">

            <i class="fas fa-comment-dots mr-1"></i>

            Send Feedback

        </button>

    </div>


    <!-- Feedback History -->
    <div class="card shadow mb-4">

        <div class="card-header py-3">

            <h6 class="m-0 font-weight-bold text-primary">

                <i class="fas fa-history mr-2"></i>

                My Feedback History

            </h6>

        </div>


        <div class="card-body">

            <?php if (!$feedbackReports): ?>

                <div class="text-center py-5">

                    <i class="fas fa-comments fa-3x text-gray-300 mb-3"></i>

                    <h5 class="font-weight-bold text-gray-800">

                        No feedback submitted yet

                    </h5>

                    <p class="text-gray-500 mb-4">

                        Have a suggestion or found an issue?

                    </p>

                    <button
                        type="button"
                        class="btn btn-primary"
                        data-toggle="modal"
                        data-target="#sendFeedbackModal">

                        <i class="fas fa-comment-dots mr-1"></i>

                        Send Feedback

                    </button>

                </div>

            <?php else: ?>

                <div class="table-responsive">

                    <table
                        class="table table-bordered table-hover"
                        width="100%"
                        cellspacing="0">

                        <thead>

                            <tr>

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

                                <th class="text-center">
                                    Action
                                </th>

                            </tr>

                        </thead>


                        <tbody>

                            <?php foreach (
                                $feedbackReports
                                as $report
                            ): ?>

                                <?php

                                $statusClass =
                                    $statusClasses[$report['status']]
                                    ?? 'primary';

                                $priorityClass =
                                    $priorityClasses[$report['priority']]
                                    ?? 'info';

                                ?>

                                <tr>

                                    <!-- Type -->
                                    <td class="align-middle">

                                        <?= e(
                                            $allowedTypes[$report['type']]
                                                ?? ucfirst($report['type'])
                                        ) ?>

                                    </td>


                                    <!-- Subject -->
                                    <td class="align-middle">

                                        <span class="font-weight-bold text-gray-800">

                                            <?= e(
                                                $report['subject']
                                            ) ?>

                                        </span>

                                        <?php if (
                                            !empty($report['attachment'])
                                        ): ?>

                                            <i
                                                class="fas fa-paperclip text-gray-400 ml-1"
                                                title="Attachment"
                                                aria-label="Attachment">
                                            </i>

                                        <?php endif; ?>

                                    </td>


                                    <!-- Priority -->
                                    <td class="align-middle">

                                        <span
                                            class="badge badge-<?= e(
                                                                    $priorityClass
                                                                ) ?>">

                                            <?= e(
                                                $allowedPriorities[$report['priority']]
                                                    ?? ucfirst($report['priority'])
                                            ) ?>

                                        </span>

                                    </td>


                                    <!-- Status -->
                                    <td class="align-middle">

                                        <span
                                            class="badge badge-<?= e(
                                                                    $statusClass
                                                                ) ?>">

                                            <?= e(
                                                $allowedStatuses[$report['status']]
                                                    ?? ucfirst($report['status'])
                                            ) ?>

                                        </span>

                                    </td>


                                    <!-- Submitted -->
                                    <td class="align-middle">

                                        <?= e(
                                            date(
                                                'M d, Y',
                                                strtotime(
                                                    $report['created_at']
                                                )
                                            )
                                        ) ?>

                                    </td>


                                    <!-- Actions -->
                                    <td class="text-center align-middle text-nowrap">

                                        <!-- View -->
                                        <button
                                            type="button"
                                            class="btn btn-sm btn-outline-primary"
                                            data-toggle="modal"
                                            data-target="#viewFeedbackModal<?= (int) $report['id'] ?>"
                                            title="View"
                                            aria-label="View feedback">

                                            <i class="fas fa-eye"></i>

                                        </button>


                                        <?php if (
                                            $report['status'] === 'open'
                                        ): ?>

                                            <!-- Edit -->
                                            <button
                                                type="button"
                                                class="btn btn-sm btn-outline-secondary"
                                                data-toggle="modal"
                                                data-target="#editFeedbackModal<?= (int) $report['id'] ?>"
                                                title="Edit"
                                                aria-label="Edit feedback">

                                                <i class="fas fa-edit"></i>

                                            </button>


                                            <!-- Delete -->
                                            <button
                                                type="button"
                                                class="btn btn-sm btn-outline-danger"
                                                data-toggle="modal"
                                                data-target="#deleteFeedbackModal<?= (int) $report['id'] ?>"
                                                title="Delete"
                                                aria-label="Delete feedback">

                                                <i class="fas fa-trash"></i>

                                            </button>

                                        <?php endif; ?>

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


<!-- ========================================================= -->
<!-- SEND FEEDBACK MODAL -->
<!-- ========================================================= -->

<div
    class="modal fade"
    id="sendFeedbackModal"
    tabindex="-1"
    role="dialog"
    aria-labelledby="sendFeedbackModalLabel"
    aria-hidden="true">

    <div
        class="modal-dialog modal-lg"
        role="document">

        <div class="modal-content">

            <div class="modal-header">

                <div>

                    <h5
                        class="modal-title font-weight-bold text-primary"
                        id="sendFeedbackModalLabel">

                        Send Feedback

                    </h5>

                    <small class="text-muted">

                        Tell us what you think or report a problem.

                    </small>

                </div>


                <button
                    type="button"
                    class="close"
                    data-dismiss="modal"
                    aria-label="Close">

                    <span aria-hidden="true">
                        &times;
                    </span>

                </button>

            </div>


            <form
                method="POST"
                enctype="multipart/form-data">

                <?= csrf_field() ?>

                <input
                    type="hidden"
                    name="action"
                    value="submit_feedback">


                <div class="modal-body">

                    <!-- Feedback Type -->
                    <div class="form-group">

                        <label
                            for="feedbackType"
                            class="font-weight-bold text-gray-700">

                            Feedback Type

                        </label>

                        <select
                            class="form-control"
                            id="feedbackType"
                            name="type"
                            required>

                            <option value="">
                                Select feedback type
                            </option>

                            <?php foreach (
                                $allowedTypes
                                as $value => $label
                            ): ?>

                                <option
                                    value="<?= e($value) ?>">

                                    <?= e($label) ?>

                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>


                    <!-- Subject -->
                    <div class="form-group">

                        <label
                            for="feedbackSubject"
                            class="font-weight-bold text-gray-700">

                            Subject

                        </label>

                        <input
                            type="text"
                            class="form-control"
                            id="feedbackSubject"
                            name="subject"
                            maxlength="255"
                            placeholder="e.g. Resume PDF preview is not loading"
                            required>

                    </div>


                    <!-- Priority / Page -->
                    <div class="row">

                        <div class="col-md-6">

                            <div class="form-group">

                                <label
                                    for="feedbackPriority"
                                    class="font-weight-bold text-gray-700">

                                    Priority

                                </label>

                                <select
                                    class="form-control"
                                    id="feedbackPriority"
                                    name="priority">

                                    <?php foreach (
                                        $allowedPriorities
                                        as $value => $label
                                    ): ?>

                                        <option
                                            value="<?= e($value) ?>"
                                            <?= $value === 'medium'
                                                ? 'selected'
                                                : '' ?>>

                                            <?= e($label) ?>

                                        </option>

                                    <?php endforeach; ?>

                                </select>

                            </div>

                        </div>


                        <div class="col-md-6">

                            <div class="form-group">

                                <label
                                    for="affectedPage"
                                    class="font-weight-bold text-gray-700">

                                    Page / Feature

                                </label>

                                <select
                                    class="form-control"
                                    id="affectedPage"
                                    name="affected_page">

                                    <option value="">
                                        Select page / feature
                                    </option>

                                    <?php foreach (
                                        $allowedPages
                                        as $page
                                    ): ?>

                                        <option
                                            value="<?= e($page) ?>">

                                            <?= e($page) ?>

                                        </option>

                                    <?php endforeach; ?>

                                </select>

                            </div>

                        </div>

                    </div>


                    <!-- Description -->
                    <div class="form-group">

                        <label
                            for="feedbackDescription"
                            class="font-weight-bold text-gray-700">

                            Description

                        </label>

                        <textarea
                            class="form-control"
                            id="feedbackDescription"
                            name="description"
                            rows="6"
                            maxlength="10000"
                            placeholder="Describe your suggestion or issue..."
                            required></textarea>

                        <small class="form-text text-muted">

                            Please provide as much detail as possible.

                        </small>

                    </div>


                    <!-- Attachment -->
                    <div class="form-group mb-0">

                        <label
                            for="feedbackAttachment"
                            class="font-weight-bold text-gray-700">

                            Screenshot / Attachment

                            <span class="font-weight-normal text-muted">
                                (optional)
                            </span>

                        </label>

                        <input
                            type="file"
                            class="form-control-file"
                            id="feedbackAttachment"
                            name="attachment"
                            accept=".jpg,.jpeg,.png,.webp,.pdf">

                        <small class="form-text text-muted">

                            JPG, PNG, WEBP, or PDF. Maximum 5MB.

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

                        <i class="fas fa-paper-plane mr-1"></i>

                        Submit Feedback

                    </button>

                </div>

            </form>

        </div>

    </div>

</div>


<!-- ========================================================= -->
<!-- VIEW / EDIT / DELETE MODALS -->
<!-- ========================================================= -->

<?php foreach ($feedbackReports as $report): ?>

    <?php

    $statusClass =
        $statusClasses[$report['status']]
        ?? 'primary';

    $priorityClass =
        $priorityClasses[$report['priority']]
        ?? 'info';

    ?>


    <!-- ===================================================== -->
    <!-- VIEW MODAL -->
    <!-- ===================================================== -->

    <div
        class="modal fade"
        id="viewFeedbackModal<?= (int) $report['id'] ?>"
        tabindex="-1"
        role="dialog"
        aria-labelledby="viewFeedbackLabel<?= (int) $report['id'] ?>"
        aria-hidden="true">

        <div
            class="modal-dialog modal-lg"
            role="document">

            <div class="modal-content">

                <div class="modal-header">

                    <h5
                        class="modal-title font-weight-bold text-primary"
                        id="viewFeedbackLabel<?= (int) $report['id'] ?>">

                        Feedback Details

                    </h5>


                    <button
                        type="button"
                        class="close"
                        data-dismiss="modal"
                        aria-label="Close">

                        <span aria-hidden="true">
                            &times;
                        </span>

                    </button>

                </div>


                <div class="modal-body">

                    <!-- Subject / Type -->
                    <div class="row">

                        <div class="col-md-8 mb-3">

                            <label class="font-weight-bold text-gray-700">

                                Subject

                            </label>

                            <div class="form-control bg-light">

                                <?= e(
                                    $report['subject']
                                ) ?>

                            </div>

                        </div>


                        <div class="col-md-4 mb-3">

                            <label class="font-weight-bold text-gray-700">

                                Type

                            </label>

                            <div class="form-control bg-light">

                                <?= e(
                                    $allowedTypes[$report['type']]
                                        ?? ucfirst($report['type'])
                                ) ?>

                            </div>

                        </div>

                    </div>


                    <!-- Priority / Status / Page -->
                    <div class="row">

                        <div class="col-md-4 mb-3">

                            <label class="font-weight-bold text-gray-700">

                                Priority

                            </label>

                            <div>

                                <span
                                    class="badge badge-<?= e(
                                                            $priorityClass
                                                        ) ?>">

                                    <?= e(
                                        $allowedPriorities[$report['priority']]
                                            ?? ucfirst($report['priority'])
                                    ) ?>

                                </span>

                            </div>

                        </div>


                        <div class="col-md-4 mb-3">

                            <label class="font-weight-bold text-gray-700">

                                Status

                            </label>

                            <div>

                                <span
                                    class="badge badge-<?= e(
                                                            $statusClass
                                                        ) ?>">

                                    <?= e(
                                        $allowedStatuses[$report['status']]
                                            ?? ucfirst($report['status'])
                                    ) ?>

                                </span>

                            </div>

                        </div>


                        <div class="col-md-4 mb-3">

                            <label class="font-weight-bold text-gray-700">

                                Page / Feature

                            </label>

                            <div class="form-control bg-light">

                                <?= e(
                                    $report['affected_page']
                                        ?: 'Not specified'
                                ) ?>

                            </div>

                        </div>

                    </div>


                    <hr>


                    <!-- Description -->
                    <div class="form-group">

                        <label class="font-weight-bold text-gray-700">

                            Description

                        </label>

                        <div
                            class="form-control bg-light"
                            style="min-height: 120px; height: auto; white-space: normal;">

                            <?= nl2br(
                                e(
                                    $report['description']
                                )
                            ) ?>

                        </div>

                    </div>


                    <!-- Attachment -->
                    <?php if (
                        !empty($report['attachment'])
                    ): ?>

                        <div class="form-group">

                            <label class="font-weight-bold text-gray-700">

                                Attachment

                            </label>

                            <div>

                                <a
                                    href="<?= e(
                                                asset(
                                                    $report['attachment']
                                                )
                                            ) ?>"
                                    target="_blank"
                                    rel="noopener"
                                    class="btn btn-sm btn-outline-primary">

                                    <i class="fas fa-paperclip mr-1"></i>

                                    View Attachment

                                </a>

                            </div>

                        </div>

                    <?php endif; ?>


                    <!-- Administrator Response -->
                    <?php if (
                        !empty($report['admin_response'])
                    ): ?>

                        <div class="form-group">

                            <label class="font-weight-bold text-gray-700">

                                Administrator Response

                            </label>

                            <div
                                class="form-control bg-light"
                                style="min-height: 100px; height: auto; white-space: normal;">

                                <?= nl2br(
                                    e(
                                        $report['admin_response']
                                    )
                                ) ?>

                            </div>

                        </div>

                    <?php endif; ?>


                    <!-- Submitted -->
                    <div class="small text-muted">

                        <i class="fas fa-clock mr-1"></i>

                        Submitted:

                        <?= e(
                            date(
                                'M d, Y h:i A',
                                strtotime(
                                    $report['created_at']
                                )
                            )
                        ) ?>

                    </div>

                </div>


                <div class="modal-footer">

                    <?php if (
                        $report['status'] === 'open'
                    ): ?>

                        <button
                            type="button"
                            class="btn btn-outline-secondary"
                            data-dismiss="modal"
                            data-toggle="modal"
                            data-target="#editFeedbackModal<?= (int) $report['id'] ?>">

                            <i class="fas fa-edit mr-1"></i>

                            Edit

                        </button>

                    <?php endif; ?>


                    <button
                        type="button"
                        class="btn btn-secondary"
                        data-dismiss="modal">

                        Close

                    </button>

                </div>

            </div>

        </div>

    </div>


    <!-- ===================================================== -->
    <!-- EDIT MODAL -->
    <!-- ===================================================== -->

    <?php if (
        $report['status'] === 'open'
    ): ?>

        <div
            class="modal fade"
            id="editFeedbackModal<?= (int) $report['id'] ?>"
            tabindex="-1"
            role="dialog"
            aria-labelledby="editFeedbackLabel<?= (int) $report['id'] ?>"
            aria-hidden="true">

            <div
                class="modal-dialog modal-lg"
                role="document">

                <div class="modal-content">

                    <div class="modal-header">

                        <div>

                            <h5
                                class="modal-title font-weight-bold text-primary"
                                id="editFeedbackLabel<?= (int) $report['id'] ?>">

                                Edit Feedback

                            </h5>

                            <small class="text-muted">

                                You can edit this report while it is still open.

                            </small>

                        </div>


                        <button
                            type="button"
                            class="close"
                            data-dismiss="modal"
                            aria-label="Close">

                            <span aria-hidden="true">
                                &times;
                            </span>

                        </button>

                    </div>


                    <form
                        method="POST"
                        enctype="multipart/form-data">

                        <?= csrf_field() ?>

                        <input
                            type="hidden"
                            name="action"
                            value="edit_feedback">

                        <input
                            type="hidden"
                            name="feedback_id"
                            value="<?= (int) $report['id'] ?>">


                        <div class="modal-body">

                            <!-- Feedback Type -->
                            <div class="form-group">

                                <label class="font-weight-bold text-gray-700">

                                    Feedback Type

                                </label>

                                <select
                                    class="form-control"
                                    name="type"
                                    required>

                                    <?php foreach (
                                        $allowedTypes
                                        as $value => $label
                                    ): ?>

                                        <option
                                            value="<?= e($value) ?>"
                                            <?= $report['type'] === $value
                                                ? 'selected'
                                                : '' ?>>

                                            <?= e($label) ?>

                                        </option>

                                    <?php endforeach; ?>

                                </select>

                            </div>


                            <!-- Subject -->
                            <div class="form-group">

                                <label class="font-weight-bold text-gray-700">

                                    Subject

                                </label>

                                <input
                                    type="text"
                                    class="form-control"
                                    name="subject"
                                    maxlength="255"
                                    value="<?= e(
                                                $report['subject']
                                            ) ?>"
                                    required>

                            </div>


                            <!-- Priority / Page -->
                            <div class="row">

                                <div class="col-md-6">

                                    <div class="form-group">

                                        <label class="font-weight-bold text-gray-700">

                                            Priority

                                        </label>

                                        <select
                                            class="form-control"
                                            name="priority">

                                            <?php foreach (
                                                $allowedPriorities
                                                as $value => $label
                                            ): ?>

                                                <option
                                                    value="<?= e($value) ?>"
                                                    <?= $report['priority'] === $value
                                                        ? 'selected'
                                                        : '' ?>>

                                                    <?= e($label) ?>

                                                </option>

                                            <?php endforeach; ?>

                                        </select>

                                    </div>

                                </div>


                                <div class="col-md-6">

                                    <div class="form-group">

                                        <label class="font-weight-bold text-gray-700">

                                            Page / Feature

                                        </label>

                                        <select
                                            class="form-control"
                                            name="affected_page">

                                            <option value="">
                                                Select page / feature
                                            </option>

                                            <?php foreach (
                                                $allowedPages
                                                as $page
                                            ): ?>

                                                <option
                                                    value="<?= e($page) ?>"
                                                    <?= $report['affected_page'] === $page
                                                        ? 'selected'
                                                        : '' ?>>

                                                    <?= e($page) ?>

                                                </option>

                                            <?php endforeach; ?>

                                        </select>

                                    </div>

                                </div>

                            </div>


                            <!-- Description -->
                            <div class="form-group">

                                <label class="font-weight-bold text-gray-700">

                                    Description

                                </label>

                                <textarea
                                    class="form-control"
                                    name="description"
                                    rows="6"
                                    maxlength="10000"
                                    required><?= e(
                                                    $report['description']
                                                ) ?></textarea>

                            </div>


                            <!-- Existing Attachment -->
                            <?php if (
                                !empty($report['attachment'])
                            ): ?>

                                <div class="form-group">

                                    <label class="font-weight-bold text-gray-700">

                                        Existing Attachment

                                    </label>

                                    <div
                                        class="form-control bg-light">

                                        <i class="fas fa-paperclip mr-2"></i>

                                        <a
                                            href="<?= e(
                                                        asset(
                                                            $report['attachment']
                                                        )
                                                    ) ?>"
                                            target="_blank"
                                            rel="noopener">

                                            View Attachment

                                        </a>

                                    </div>

                                </div>

                            <?php endif; ?>


                            <!-- Replace Attachment -->
                            <div class="form-group mb-0">

                                <label class="font-weight-bold text-gray-700">

                                    Replace Attachment

                                    <span class="font-weight-normal text-muted">
                                        (optional)
                                    </span>

                                </label>

                                <input
                                    type="file"
                                    class="form-control-file"
                                    name="attachment"
                                    accept=".jpg,.jpeg,.png,.webp,.pdf">

                                <small class="form-text text-muted">

                                    Upload a new file only if you want to
                                    replace the existing attachment.
                                    Maximum 5MB.

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

                    </form>

                </div>

            </div>

        </div>

    <?php endif; ?>


    <!-- ===================================================== -->
    <!-- DELETE MODAL -->
    <!-- ===================================================== -->

    <?php if (
        $report['status'] === 'open'
    ): ?>

        <div
            class="modal fade"
            id="deleteFeedbackModal<?= (int) $report['id'] ?>"
            tabindex="-1"
            role="dialog"
            aria-labelledby="deleteFeedbackLabel<?= (int) $report['id'] ?>"
            aria-hidden="true">

            <div
                class="modal-dialog"
                role="document">

                <div class="modal-content">

                    <div class="modal-header">

                        <h5
                            class="modal-title font-weight-bold text-gray-800"
                            id="deleteFeedbackLabel<?= (int) $report['id'] ?>">

                            Delete Feedback

                        </h5>


                        <button
                            type="button"
                            class="close"
                            data-dismiss="modal"
                            aria-label="Close">

                            <span aria-hidden="true">
                                &times;
                            </span>

                        </button>

                    </div>


                    <div class="modal-body">

                        <p class="mb-2">

                            Are you sure you want to delete this feedback?

                        </p>

                        <div class="font-weight-bold text-gray-800">

                            <?= e(
                                $report['subject']
                            ) ?>

                        </div>

                        <div class="small text-muted mt-2">

                            This action cannot be undone.

                        </div>

                    </div>


                    <div class="modal-footer">

                        <button
                            type="button"
                            class="btn btn-light"
                            data-dismiss="modal">

                            Cancel

                        </button>


                        <form method="POST" class="m-0">

                            <?= csrf_field() ?>

                            <input
                                type="hidden"
                                name="action"
                                value="delete_feedback">

                            <input
                                type="hidden"
                                name="feedback_id"
                                value="<?= (int) $report['id'] ?>">


                            <button
                                type="submit"
                                class="btn btn-danger">

                                <i class="fas fa-trash mr-1"></i>

                                Delete

                            </button>

                        </form>

                    </div>

                </div>

            </div>

        </div>

    <?php endif; ?>

<?php endforeach; ?>


<?php require dirname(__DIR__) . '/includes/footer.php'; ?>