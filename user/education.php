<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once __DIR__ . '/../includes/maintenance-check.php';

require_role('user');

$uid = current_user_id();
$pdo = db();

/*
|--------------------------------------------------------------------------
| Delete Education
|--------------------------------------------------------------------------
*/
if (isset($_GET['delete'])) {
    $deleteId = (int) $_GET['delete'];

    try {
        $stmt = $pdo->prepare(
            'DELETE FROM education
             WHERE id = ?
             AND user_id = ?'
        );

        $stmt->execute([
            $deleteId,
            $uid
        ]);

        flash('success', 'Education deleted successfully.');
    } catch (Throwable $e) {
        flash('danger', 'Unable to delete education.');
    }

    redirect('user/education.php');
}

/*
|--------------------------------------------------------------------------
| Add / Update Education
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    try {
        verify_csrf();

        $id = (int) ($_POST['id'] ?? 0);

        $institution = trim(
            (string) ($_POST['institution'] ?? '')
        );

        $degree = trim(
            (string) ($_POST['degree'] ?? '')
        );

        $description = trim(
            (string) ($_POST['description'] ?? '')
        );

        $startDate = !empty($_POST['start_date'])
            ? $_POST['start_date']
            : null;

        $isCurrent = isset($_POST['is_current'])
            ? 1
            : 0;

        $endDate = $isCurrent
            ? null
            : (
                !empty($_POST['end_date'])
                ? $_POST['end_date']
                : null
            );

        $institutionUrl = trim(
            (string) ($_POST['institution_url'] ?? '')
        );

        $isPublic = isset($_POST['is_public'])
            ? 1
            : 0;

        /*
        |--------------------------------------------------------------------------
        | Validation
        |--------------------------------------------------------------------------
        */

        if ($institution === '') {
            throw new RuntimeException(
                'Institution is required.'
            );
        }

        if ($degree === '') {
            throw new RuntimeException(
                'Degree or program is required.'
            );
        }

        if ($startDate === null) {
            throw new RuntimeException(
                'Start date is required.'
            );
        }

        if (!$isCurrent && $endDate === null) {
            throw new RuntimeException(
                'End date is required unless you are currently studying here.'
            );
        }

        if (
            !$isCurrent &&
            $endDate !== null &&
            strtotime($endDate) < strtotime($startDate)
        ) {
            throw new RuntimeException(
                'End date cannot be earlier than the start date.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Update Existing Education
        |--------------------------------------------------------------------------
        */
        if ($id > 0) {

            $stmt = $pdo->prepare(
                'UPDATE education
                 SET
                    institution = ?,
                    degree = ?,
                    description = ?,
                    start_date = ?,
                    end_date = ?,
                    is_current = ?,
                    institution_url = ?,
                    is_public = ?
                 WHERE id = ?
                 AND user_id = ?'
            );

            $stmt->execute([
                $institution,
                $degree,
                $description,
                $startDate,
                $endDate,
                $isCurrent,
                $institutionUrl,
                $isPublic,
                $id,
                $uid
            ]);

            flash(
                'success',
                'Education updated successfully.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Add New Education
        |--------------------------------------------------------------------------
        */ else {

            $stmt = $pdo->prepare(
                'INSERT INTO education (
                    user_id,
                    institution,
                    degree,
                    description,
                    start_date,
                    end_date,
                    is_current,
                    institution_url,
                    is_public
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );

            $stmt->execute([
                $uid,
                $institution,
                $degree,
                $description,
                $startDate,
                $endDate,
                $isCurrent,
                $institutionUrl,
                $isPublic
            ]);

            flash(
                'success',
                'Education added successfully.'
            );
        }
    } catch (Throwable $e) {

        flash(
            'danger',
            $e->getMessage()
        );
    }

    redirect('user/education.php');
}

/*
|--------------------------------------------------------------------------
| Get Education
|--------------------------------------------------------------------------
*/
$stmt = $pdo->prepare(
    'SELECT *
     FROM education
     WHERE user_id = ?
     ORDER BY
        is_current DESC,
        start_date DESC'
);

$stmt->execute([$uid]);

$education = $stmt->fetchAll();

/*
|--------------------------------------------------------------------------
| Statistics
|--------------------------------------------------------------------------
*/
$totalEducation = count($education);

$currentEducation = count(
    array_filter(
        $education,
        static fn(array $item): bool =>
        !empty($item['is_current'])
    )
);

$publicEducation = count(
    array_filter(
        $education,
        static fn(array $item): bool =>
        !empty($item['is_public'])
    )
);

/*
|--------------------------------------------------------------------------
| Page
|--------------------------------------------------------------------------
*/
$pageTitle = 'Education';

require dirname(__DIR__) . '/includes/header.php';
?>


<div class="container-fluid">

    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">

        <div>

            <h1 class="h3 mb-1 text-gray-800">
                Education
            </h1>

            <p class="mb-0 text-muted">
                Manage your schools, degrees, and academic achievements.
            </p>

        </div>

    </div>
    <!-- Education History -->
    <div class="card shadow mb-4">

        <div class="card-header py-3">

            <div
                class="d-flex flex-column flex-md-row align-items-md-center justify-content-between">

                <div>

                    <h6 class="m-0 font-weight-bold text-primary">
                        Education History
                    </h6>

                    <small class="text-muted">
                        Your academic background
                    </small>

                </div>


                <button
                    type="button"
                    class="btn btn-primary btn-sm mt-3 mt-md-0"
                    data-toggle="modal"
                    data-target="#educationModal">
                    <i class="fas fa-plus mr-1"></i>
                    Add Education
                </button>

            </div>

        </div>


        <div class="card-body">

            <?php if (!$education): ?>

                <!-- Empty State -->
                <div class="text-center py-5">

                    <div class="mb-3">

                        <i
                            class="fas fa-graduation-cap fa-3x text-gray-300"></i>

                    </div>


                    <h5 class="text-gray-800">
                        No education added
                    </h5>


                    <p class="text-muted mb-4">
                        Add your academic background to your profile.
                    </p>


                    <button
                        type="button"
                        class="btn btn-primary"
                        data-toggle="modal"
                        data-target="#educationModal">
                        <i class="fas fa-plus mr-1"></i>
                        Add Education
                    </button>

                </div>

            <?php else: ?>

                <div class="list-group">

                    <?php foreach ($education as $item): ?>

                        <div class="list-group-item py-4">

                            <div class="row align-items-center">

                                <!-- Icon -->
                                <div class="col-auto">

                                    <div class="icon-circle bg-primary">

                                        <i
                                            class="fas fa-graduation-cap text-white"></i>

                                    </div>

                                </div>


                                <!-- Education Details -->
                                <div class="col">

                                    <!-- Degree -->
                                    <div class="font-weight-bold text-gray-800">

                                        <?= e($item['degree']) ?>

                                    </div>


                                    <!-- Institution -->
                                    <div class="text-primary font-weight-bold">

                                        <?= e($item['institution']) ?>

                                    </div>


                                    <!-- Dates -->
                                    <div class="small text-muted mt-2">

                                        <i class="far fa-calendar-alt mr-1"></i>

                                        <?= e(format_date($item['start_date'])) ?>

                                        &ndash;

                                        <?php if (!empty($item['is_current'])): ?>

                                            Present

                                        <?php else: ?>

                                            <?= e(format_date($item['end_date'])) ?>

                                        <?php endif; ?>

                                    </div>


                                    <!-- Status Badges -->
                                    <div class="mt-2">

                                        <?php if (!empty($item['is_current'])): ?>

                                            <span class="badge badge-success mr-1">

                                                <i
                                                    class="fas fa-circle fa-xs mr-1"></i>

                                                Currently Studying

                                            </span>

                                        <?php endif; ?>


                                        <?php if (!empty($item['is_public'])): ?>

                                            <span class="badge badge-info">

                                                <i
                                                    class="fas fa-eye mr-1"></i>

                                                Public

                                            </span>

                                        <?php else: ?>

                                            <span class="badge badge-secondary">

                                                <i
                                                    class="fas fa-eye-slash mr-1"></i>

                                                Private

                                            </span>

                                        <?php endif; ?>

                                    </div>


                                    <!-- Description -->
                                    <?php if (!empty($item['description'])): ?>

                                        <div class="small text-gray-600 mt-3">

                                            <?= nl2br(
                                                e($item['description'])
                                            ) ?>

                                        </div>

                                    <?php endif; ?>

                                </div>


                                <!-- Actions -->
                                <div class="col-auto">

                                    <div class="btn-group">

                                        <!-- Edit -->
                                        <button
                                            type="button"
                                            class="btn btn-outline-primary mr-2 btn-sm"
                                            data-toggle="modal"
                                            data-target="#editEducation<?= (int) $item['id'] ?>"
                                            title="Edit">

                                            <i class="fas fa-edit"></i>

                                        </button>


                                        <!-- Delete -->
                                        <button
                                            type="button"
                                            class="btn btn-outline-danger btn-sm"
                                            data-toggle="modal"
                                            data-target="#deleteModal"
                                            data-delete-url="<?= e(
                                                                    asset(
                                                                        'user/education.php?delete=' .
                                                                            (int) $item['id']
                                                                    )
                                                                ) ?>"
                                            title="Delete">

                                            <i class="fas fa-trash"></i>

                                        </button>

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


<!-- =========================================================
     ADD EDUCATION MODAL
========================================================= -->

<div
    class="modal fade"
    id="educationModal"
    tabindex="-1"
    role="dialog"
    aria-labelledby="educationModalLabel"
    aria-hidden="true">

    <div
        class="modal-dialog modal-lg"
        role="document">

        <form method="post">

            <div class="modal-content">

                <!-- Modal Header -->
                <div class="modal-header">

                    <h5
                        class="modal-title text-primary"
                        id="educationModalLabel">

                        <!-- <i
                            class="fas fa-graduation-cap mr-2 text-primary"></i> -->

                        Add Education

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


                <!-- Modal Body -->
                <div
                    class="modal-body"
                    style="max-height: 70vh; overflow-y: auto;">

                    <input
                        type="hidden"
                        name="id"
                        value="0">


                    <!-- Institution -->
                    <div class="form-group">

                        <label for="add_institution">

                            Institution

                            <span class="text-danger">
                                *
                            </span>

                        </label>


                        <input
                            type="text"
                            class="form-control"
                            id="add_institution"
                            name="institution"
                            placeholder="e.g. University of the Philippines"
                            required>

                    </div>


                    <!-- Degree -->
                    <div class="form-group">

                        <label for="add_degree">

                            Degree / Program

                            <span class="text-danger">
                                *
                            </span>

                        </label>


                        <input
                            type="text"
                            class="form-control"
                            id="add_degree"
                            name="degree"
                            placeholder="e.g. Bachelor of Science in Information Technology"
                            required>

                    </div>


                    <!-- Description -->
                    <div class="form-group">

                        <label for="add_description">
                            Description
                        </label>


                        <textarea
                            class="form-control"
                            id="add_description"
                            name="description"
                            rows="5"
                            placeholder="Describe your academic achievements, activities, honors, or other relevant information."></textarea>

                    </div>


                    <!-- Dates -->
                    <div class="row">

                        <div class="col-md-6">

                            <div class="form-group">

                                <label for="add_start_date">

                                    Start Date

                                    <span class="text-danger">
                                        *
                                    </span>

                                </label>


                                <input
                                    type="date"
                                    class="form-control"
                                    id="add_start_date"
                                    name="start_date"
                                    required>

                            </div>

                        </div>


                        <div class="col-md-6">

                            <div class="form-group">

                                <label for="add_end_date">
                                    End Date
                                </label>


                                <input
                                    type="date"
                                    class="form-control"
                                    id="add_end_date"
                                    name="end_date">

                            </div>

                        </div>

                    </div>

                    <!-- Currently Studying -->
                    <div class="form-group">

                        <div class="custom-control custom-checkbox">

                            <input
                                type="checkbox"
                                class="custom-control-input"
                                id="add_is_current"
                                name="is_current">


                            <label
                                class="custom-control-label"
                                for="add_is_current">
                                I am currently studying here
                            </label>

                        </div>


                        <small class="form-text text-muted">
                            End date will be ignored when this option is selected.
                        </small>

                    </div>


                    <!-- Public -->
                    <div class="form-group">

                        <div class="custom-control custom-checkbox">

                            <input
                                type="checkbox"
                                class="custom-control-input"
                                id="add_is_public"
                                name="is_public"
                                checked>


                            <label
                                class="custom-control-label"
                                for="add_is_public">
                                Make this education visible on my public portfolio
                            </label>

                        </div>


                        <small class="form-text text-muted">
                            Private education entries will only be visible to you.
                        </small>

                    </div>

                </div>


                <!-- Modal Footer -->
                <div class="modal-footer">

                    <button
                        type="button"
                        class="btn btn-secondary"
                        data-dismiss="modal">
                        Cancel
                    </button>


                    <button
                        type="submit"
                        class="btn btn-primary">

                        <i class="fas fa-save mr-1"></i>

                        Save Education

                    </button>

                </div>


                <?= csrf_field() ?>

            </div>

        </form>

    </div>

</div>


<!-- =========================================================
     EDIT EDUCATION MODALS
========================================================= -->

<?php foreach ($education as $item): ?>

    <div
        class="modal fade"
        id="editEducation<?= (int) $item['id'] ?>"
        tabindex="-1"
        role="dialog"
        aria-labelledby="editEducationLabel<?= (int) $item['id'] ?>"
        aria-hidden="true">

        <div
            class="modal-dialog modal-lg"
            role="document">

            <form method="post">

                <div class="modal-content">

                    <!-- Modal Header -->
                    <div class="modal-header">

                        <h5
                            class="modal-title text-primary"
                            id="editEducationLabel<?= (int) $item['id'] ?>">

                            <!-- <i
                                class="fas fa-edit mr-2 text-primary"></i> -->

                            Edit Education

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


                    <!-- Modal Body -->
                    <div
                        class="modal-body"
                        style="max-height: 70vh; overflow-y: auto;">

                        <input
                            type="hidden"
                            name="id"
                            value="<?= (int) $item['id'] ?>">


                        <!-- Institution -->
                        <div class="form-group">

                            <label
                                for="institution_<?= (int) $item['id'] ?>">

                                Institution

                                <span class="text-danger">
                                    *
                                </span>

                            </label>


                            <input
                                type="text"
                                class="form-control"
                                id="institution_<?= (int) $item['id'] ?>"
                                name="institution"
                                value="<?= e($item['institution']) ?>"
                                required>

                        </div>


                        <!-- Degree -->
                        <div class="form-group">

                            <label
                                for="degree_<?= (int) $item['id'] ?>">

                                Degree / Program

                                <span class="text-danger">
                                    *
                                </span>

                            </label>


                            <input
                                type="text"
                                class="form-control"
                                id="degree_<?= (int) $item['id'] ?>"
                                name="degree"
                                value="<?= e($item['degree']) ?>"
                                required>

                        </div>


                        <!-- Description -->
                        <div class="form-group">

                            <label
                                for="description_<?= (int) $item['id'] ?>">
                                Description
                            </label>


                            <textarea
                                class="form-control"
                                id="description_<?= (int) $item['id'] ?>"
                                name="description"
                                rows="5"
                                placeholder="Describe your academic achievements, activities, honors, or other relevant information."><?= e($item['description'] ?? '') ?></textarea>

                        </div>


                        <!-- Dates -->
                        <div class="row">

                            <div class="col-md-6">

                                <div class="form-group">

                                    <label
                                        for="start_date_<?= (int) $item['id'] ?>">

                                        Start Date

                                        <span class="text-danger">
                                            *
                                        </span>

                                    </label>


                                    <input
                                        type="date"
                                        class="form-control"
                                        id="start_date_<?= (int) $item['id'] ?>"
                                        name="start_date"
                                        value="<?= e($item['start_date'] ?? '') ?>"
                                        required>

                                </div>

                            </div>


                            <div class="col-md-6">

                                <div class="form-group">

                                    <label
                                        for="end_date_<?= (int) $item['id'] ?>">
                                        End Date
                                    </label>


                                    <input
                                        type="date"
                                        class="form-control"
                                        id="end_date_<?= (int) $item['id'] ?>"
                                        name="end_date"
                                        value="<?= e($item['end_date'] ?? '') ?>">

                                </div>

                            </div>

                        </div>

                        <!-- Currently Studying -->
                        <div class="form-group">

                            <div class="custom-control custom-checkbox">

                                <input
                                    type="checkbox"
                                    class="custom-control-input"
                                    id="is_current_<?= (int) $item['id'] ?>"
                                    name="is_current"
                                    <?= !empty($item['is_current']) ? 'checked' : '' ?>>


                                <label
                                    class="custom-control-label"
                                    for="is_current_<?= (int) $item['id'] ?>">
                                    I am currently studying here
                                </label>

                            </div>


                            <small class="form-text text-muted">
                                End date will be ignored when this option is selected.
                            </small>

                        </div>


                        <!-- Public -->
                        <div class="form-group">

                            <div class="custom-control custom-checkbox">

                                <input
                                    type="checkbox"
                                    class="custom-control-input"
                                    id="is_public_<?= (int) $item['id'] ?>"
                                    name="is_public"
                                    <?= !empty($item['is_public']) ? 'checked' : '' ?>>


                                <label
                                    class="custom-control-label"
                                    for="is_public_<?= (int) $item['id'] ?>">
                                    Make this education visible on my public portfolio
                                </label>

                            </div>


                            <small class="form-text text-muted">
                                Private education entries will only be visible to you.
                            </small>

                        </div>

                    </div>


                    <!-- Modal Footer -->
                    <div class="modal-footer">

                        <button
                            type="button"
                            class="btn btn-secondary"
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


                    <?= csrf_field() ?>

                </div>

            </form>

        </div>

    </div>

<?php endforeach; ?>


<!-- =========================================================
     DELETE MODAL
========================================================= -->

<div
    class="modal fade"
    id="deleteModal"
    tabindex="-1"
    role="dialog"
    aria-labelledby="deleteModalLabel"
    aria-hidden="true">

    <div
        class="modal-dialog"
        role="document">

        <div class="modal-content">

            <!-- Modal Header -->
            <div class="modal-header">

                <h5
                    class="modal-title"
                    id="deleteModalLabel">

                    <i
                        class="fas fa-exclamation-triangle text-danger mr-2"></i>

                    Delete Education

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


            <!-- Modal Body -->
            <div class="modal-body">

                <p class="mb-0">
                    Are you sure you want to delete this education entry?
                </p>

                <small class="text-muted">
                    This action cannot be undone.
                </small>

            </div>


            <!-- Modal Footer -->
            <div class="modal-footer">

                <button
                    type="button"
                    class="btn btn-secondary"
                    data-dismiss="modal">
                    Cancel
                </button>


                <a
                    href="#"
                    id="confirmDelete"
                    class="btn btn-danger">

                    <i class="fas fa-trash mr-1"></i>

                    Delete

                </a>

            </div>

        </div>

    </div>

</div>


<!-- =========================================================
     PAGE JAVASCRIPT
========================================================= -->

<script>
    document.addEventListener('DOMContentLoaded', function() {

        /*
        |--------------------------------------------------------------------------
        | Current Education Checkbox
        |--------------------------------------------------------------------------
        */

        document
            .querySelectorAll('input[name="is_current"]')
            .forEach(function(checkbox) {

                checkbox.addEventListener('change', function() {

                    const form = checkbox.closest('form');

                    if (!form) {
                        return;
                    }

                    const endDate = form.querySelector(
                        'input[name="end_date"]'
                    );

                    if (!endDate) {
                        return;
                    }

                    if (checkbox.checked) {

                        endDate.value = '';
                        endDate.disabled = true;

                    } else {

                        endDate.disabled = false;

                    }

                });


                /*
                |--------------------------------------------------------------------------
                | Initialize Existing State
                |--------------------------------------------------------------------------
                */

                checkbox.dispatchEvent(
                    new Event('change')
                );

            });


        /*
        |--------------------------------------------------------------------------
        | Delete Confirmation
        |--------------------------------------------------------------------------
        */

        const confirmDelete = document.getElementById(
            'confirmDelete'
        );

        if (confirmDelete) {

            $('[data-delete-url]').on(
                'click',
                function() {

                    const deleteUrl =
                        this.getAttribute('data-delete-url');

                    confirmDelete.setAttribute(
                        'href',
                        deleteUrl
                    );

                }
            );


            $('#deleteModal').on(
                'hidden.bs.modal',
                function() {

                    confirmDelete.setAttribute(
                        'href',
                        '#'
                    );

                }
            );

        }

    });
</script>


<?php
require dirname(__DIR__) . '/includes/footer.php';
?>