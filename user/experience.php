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
| Delete Experience
|--------------------------------------------------------------------------
*/
if (isset($_GET['delete'])) {
    $deleteId = (int) $_GET['delete'];

    try {
        $stmt = $pdo->prepare(
            'DELETE FROM experience
             WHERE id = ? AND user_id = ?'
        );

        $stmt->execute([
            $deleteId,
            $uid
        ]);

        flash('success', 'Work experience deleted successfully.');
    } catch (Throwable $e) {
        flash('danger', 'Unable to delete work experience.');
    }

    redirect('user/experience.php');
}

/*
|--------------------------------------------------------------------------
| Add / Update Experience
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verify_csrf();

        $id = (int) ($_POST['id'] ?? 0);

        $company = trim((string) ($_POST['company'] ?? ''));
        $jobTitle = trim((string) ($_POST['job_title'] ?? ''));
        $location = trim((string) ($_POST['location'] ?? ''));
        $companyUrl = trim((string) ($_POST['company_url'] ?? ''));

        /*
        |--------------------------------------------------------------------------
        | Responsibilities
        |--------------------------------------------------------------------------
        | Responsibilities are submitted as multiple repeatable inputs and
        | stored in the existing description column, one responsibility per
        | line. This keeps the current database structure unchanged.
        */
        $responsibilities = $_POST['responsibilities'] ?? [];

        if (!is_array($responsibilities)) {
            $responsibilities = [$responsibilities];
        }

        $cleanResponsibilities = [];

        foreach ($responsibilities as $responsibility) {
            $responsibility = trim((string) $responsibility);

            if ($responsibility === '') {
                continue;
            }

            $responsibility = mb_substr(
                $responsibility,
                0,
                255
            );

            $cleanResponsibilities[] = $responsibility;
        }

        $description = implode(
            PHP_EOL,
            $cleanResponsibilities
        );

        $startDate = !empty($_POST['start_date'])
            ? $_POST['start_date']
            : null;

        $isCurrent = isset($_POST['is_current']) ? 1 : 0;

        $endDate = $isCurrent
            ? null
            : (!empty($_POST['end_date']) ? $_POST['end_date'] : null);

        /*
        |--------------------------------------------------------------------------
        | Validation
        |--------------------------------------------------------------------------
        */
        if ($company === '') {
            throw new RuntimeException('Company name is required.');
        }

        if ($jobTitle === '') {
            throw new RuntimeException('Job title is required.');
        }

        if ($startDate === null) {
            throw new RuntimeException('Start date is required.');
        }

        if (!$isCurrent && $endDate === null) {
            throw new RuntimeException(
                'End date is required unless this is your current position.'
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
        | Update Existing Experience
        |--------------------------------------------------------------------------
        */
        if ($id > 0) {
            $stmt = $pdo->prepare(
                'UPDATE experience
                 SET
                    company = ?,
                    job_title = ?,
                    description = ?,
                    location = ?,
                    company_url = ?,
                    start_date = ?,
                    end_date = ?,
                    is_current = ?
                 WHERE id = ?
                 AND user_id = ?'
            );

            $stmt->execute([
                $company,
                $jobTitle,
                $description,
                $location,
                $companyUrl,
                $startDate,
                $endDate,
                $isCurrent,
                $id,
                $uid
            ]);

            flash('success', 'Work experience updated successfully.');
        }

        /*
        |--------------------------------------------------------------------------
        | Add New Experience
        |--------------------------------------------------------------------------
        */ else {
            $stmt = $pdo->prepare(
                'INSERT INTO experience (
                    user_id,
                    company,
                    job_title,
                    description,
                    location,
                    company_url,
                    start_date,
                    end_date,
                    is_current
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );

            $stmt->execute([
                $uid,
                $company,
                $jobTitle,
                $description,
                $location,
                $companyUrl,
                $startDate,
                $endDate,
                $isCurrent
            ]);

            flash('success', 'Work experience added successfully.');
        }
    } catch (Throwable $e) {
        flash('danger', $e->getMessage());
    }

    redirect('user/experience.php');
}

/*
|--------------------------------------------------------------------------
| Get Experiences
|--------------------------------------------------------------------------
*/
$stmt = $pdo->prepare(
    'SELECT *
     FROM experience
     WHERE user_id = ?
     ORDER BY
        is_current DESC,
        start_date DESC,
        created_at DESC'
);

$stmt->execute([$uid]);

$experiences = $stmt->fetchAll();

/*
|--------------------------------------------------------------------------
| Statistics
|--------------------------------------------------------------------------
*/
$totalExperiences = count($experiences);

$currentExperiences = count(
    array_filter(
        $experiences,
        static fn(array $experience): bool =>
        !empty($experience['is_current'])
    )
);

/*
|--------------------------------------------------------------------------
| Page
|--------------------------------------------------------------------------
*/
$pageTitle = 'Experience';

require dirname(__DIR__) . '/includes/header.php';
?>

<div class="container-fluid">

    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">

        <div>
            <h1 class="h3 mb-1 text-gray-800">
                Work Experience
            </h1>

            <p class="mb-0 text-muted">
                Keep your employment history organized and ready for your
                portfolio and resume.
            </p>
        </div>

    </div>

    <!-- Career History -->
    <div class="card shadow mb-4">

        <div class="card-header py-3">

            <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between">

                <div>
                    <h6 class="m-0 font-weight-bold text-primary">
                        Career History
                    </h6>

                    <small class="text-muted">
                        Your professional work experience
                    </small>
                </div>

                <button
                    type="button"
                    class="btn btn-primary btn-sm mt-3 mt-md-0"
                    data-toggle="modal"
                    data-target="#experienceModal">
                    <i class="fas fa-plus mr-1"></i>
                    Add Experience
                </button>

            </div>

        </div>


        <div class="card-body">

            <?php if (!$experiences): ?>

                <!-- Empty State -->
                <div class="text-center py-5">

                    <div class="mb-3">
                        <i class="fas fa-briefcase fa-3x text-gray-300"></i>
                    </div>

                    <h5 class="text-gray-800">
                        No experience added
                    </h5>

                    <p class="text-muted mb-4">
                        Add your first work experience to start building
                        your professional profile.
                    </p>

                    <button
                        type="button"
                        class="btn btn-primary"
                        data-toggle="modal"
                        data-target="#experienceModal">
                        <i class="fas fa-plus mr-1"></i>
                        Add Experience
                    </button>

                </div>

            <?php else: ?>

                <div class="list-group">

                    <?php foreach ($experiences as $experience): ?>

                        <div class="list-group-item py-4">

                            <div class="row align-items-center">

                                <!-- Icon -->
                                <div class="col-auto">

                                    <div
                                        class="icon-circle bg-primary">
                                        <i class="fas fa-briefcase text-white"></i>
                                    </div>

                                </div>


                                <!-- Experience Details -->
                                <div class="col">

                                    <div class="font-weight-bold text-gray-800">
                                        <?= e($experience['job_title']) ?>
                                    </div>

                                    <div class="text-primary font-weight-bold">
                                        <?= e($experience['company']) ?>
                                    </div>

                                    <?php if (!empty($experience['location'])): ?>

                                        <div class="small text-muted mt-1">
                                            <i class="fas fa-map-marker-alt mr-1"></i>
                                            <?= e($experience['location']) ?>
                                        </div>

                                    <?php endif; ?>


                                    <!-- Dates -->
                                    <div class="small text-muted mt-2">

                                        <i class="far fa-calendar-alt mr-1"></i>

                                        <?= e(format_date($experience['start_date'])) ?>

                                        &ndash;

                                        <?php if (!empty($experience['is_current'])): ?>

                                            Present

                                        <?php else: ?>

                                            <?= e(format_date($experience['end_date'])) ?>

                                        <?php endif; ?>

                                    </div>


                                    <!-- Current Badge -->
                                    <?php if (!empty($experience['is_current'])): ?>

                                        <span class="badge badge-success mt-2">
                                            <i class="fas fa-circle fa-xs mr-1"></i>
                                            Current
                                        </span>

                                    <?php endif; ?>


                                    <!-- Responsibilities -->
                                    <?php if (!empty($experience['description'])): ?>

                                        <?php
                                        $responsibilities = preg_split(
                                            '/\\r\\n|\\r|\\n/',
                                            (string) $experience['description']
                                        ) ?: [];

                                        $responsibilities = array_values(
                                            array_filter(
                                                array_map(
                                                    static fn($item) => trim((string) $item),
                                                    $responsibilities
                                                ),
                                                static fn($item) => $item !== ''
                                            )
                                        );
                                        ?>

                                        <?php if ($responsibilities): ?>
                                            <div class="small text-gray-600 mt-3">
                                                <div class="font-weight-bold text-gray-700 mb-2">
                                                    Responsibilities
                                                </div>

                                                <ul class="mb-0 pl-3">
                                                    <?php foreach ($responsibilities as $responsibility): ?>
                                                        <li class="mb-1">
                                                            <?= e($responsibility) ?>
                                                        </li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            </div>
                                        <?php endif; ?>

                                    <?php endif; ?>


                                    <!-- Company Website -->
                                    <?php if (!empty($experience['company_url'])): ?>

                                        <div class="mt-3">

                                            <a
                                                href="<?= e($experience['company_url']) ?>"
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                class="btn btn-outline-primary btn-sm">
                                                <i class="fas fa-external-link-alt mr-1"></i>
                                                Company Website
                                            </a>

                                        </div>

                                    <?php endif; ?>

                                </div>


                                <!-- Actions -->
                                <div class="col-auto">

                                    <div class="btn-group">

                                        <button
                                            type="button"
                                            class="btn btn-outline-primary mr-2 btn-sm"
                                            data-toggle="modal"
                                            data-target="#editExperience<?= (int) $experience['id'] ?>"
                                            title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </button>

                                        <button
                                            type="button"
                                            class="btn btn-outline-danger btn-sm"
                                            data-toggle="modal"
                                            data-target="#deleteModal"
                                            data-delete-url="<?= e(
                                                                    asset(
                                                                        'user/experience.php?delete=' .
                                                                            (int) $experience['id']
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
     ADD EXPERIENCE MODAL
========================================================= -->

<div
    class="modal fade"
    id="experienceModal"
    tabindex="-1"
    role="dialog"
    aria-labelledby="experienceModalLabel"
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
                        id="experienceModalLabel">
                        <!-- <i class="fas fa-briefcase mr-2 text-primary"></i> -->
                        Add Experience
                    </h5>

                    <button
                        type="button"
                        class="close"
                        data-dismiss="modal"
                        aria-label="Close">
                        <span aria-hidden="true">&times;</span>
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


                    <!-- Company -->
                    <div class="form-group">

                        <label for="add_company">
                            Company
                            <span class="text-danger">*</span>
                        </label>

                        <input
                            type="text"
                            class="form-control"
                            id="add_company"
                            name="company"
                            placeholder="e.g. ABC Company"
                            required>

                    </div>


                    <!-- Job Title -->
                    <div class="form-group">

                        <label for="add_job_title">
                            Job Title
                            <span class="text-danger">*</span>
                        </label>

                        <input
                            type="text"
                            class="form-control"
                            id="add_job_title"
                            name="job_title"
                            placeholder="e.g. Web Developer"
                            required>

                    </div>


                    <!-- Location -->
                    <div class="form-group">

                        <label for="add_location">
                            Location
                        </label>

                        <input
                            type="text"
                            class="form-control"
                            id="add_location"
                            name="location"
                            placeholder="e.g. Naga City, Philippines">

                    </div>


                    <!-- Company URL -->
                    <!-- <div class="form-group">

                        <label for="add_company_url">
                            Company Website
                        </label>

                        <input
                            type="url"
                            class="form-control"
                            id="add_company_url"
                            name="company_url"
                            placeholder="https://example.com">

                    </div> -->


                    <!-- Responsibilities -->
                    <div class="form-group">

                        <label class="font-weight-bold">
                            Job Responsibilities
                        </label>

                        <div class="responsibilities-container">

                            <div class="responsibility-row mb-2">

                                <div class="input-group">

                                    <input
                                        type="text"
                                        class="form-control responsibility-input"
                                        name="responsibilities[]"
                                        maxlength="255"
                                        placeholder="e.g. Developed and maintained web applications.">

                                    <div class="input-group-append">

                                        <button
                                            type="button"
                                            class="btn btn-outline-danger remove-responsibility"
                                            title="Remove responsibility">
                                            <i class="fas fa-times"></i>
                                        </button>

                                    </div>

                                </div>

                            </div>

                        </div>

                        <button
                            type="button"
                            class="btn btn-outline-primary btn-sm mt-1 add-responsibility">
                            <i class="fas fa-plus mr-1"></i>
                            Add Responsibility
                        </button>

                        <small class="form-text text-muted">
                            Add each job responsibility separately.
                        </small>

                    </div>


                    <!-- Dates -->
                    <div class="row">

                        <div class="col-md-6">

                            <div class="form-group">

                                <label for="add_start_date">
                                    Start Date
                                    <span class="text-danger">*</span>
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


                    <!-- Current Position -->
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
                                I currently work here
                            </label>

                        </div>

                        <!-- <small class="form-text text-muted">
                            End date will be ignored when this option is selected.
                        </small> -->

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
                        Save Experience
                    </button>

                </div>

                <?= csrf_field() ?>

            </div>

        </form>

    </div>

</div>


<!-- =========================================================
     EDIT EXPERIENCE MODALS
========================================================= -->

<?php foreach ($experiences as $experience): ?>

    <div
        class="modal fade"
        id="editExperience<?= (int) $experience['id'] ?>"
        tabindex="-1"
        role="dialog"
        aria-labelledby="editExperienceLabel<?= (int) $experience['id'] ?>"
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
                            id="editExperienceLabel<?= (int) $experience['id'] ?>">
                            <!-- <i class="fas fa-edit mr-2 text-primary"></i> -->
                            Edit Experience
                        </h5>

                        <button
                            type="button"
                            class="close"
                            data-dismiss="modal"
                            aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>

                    </div>


                    <!-- Modal Body -->
                    <div
                        class="modal-body"
                        style="max-height: 70vh; overflow-y: auto;">

                        <input
                            type="hidden"
                            name="id"
                            value="<?= (int) $experience['id'] ?>">


                        <!-- Company -->
                        <div class="form-group">

                            <label for="company_<?= (int) $experience['id'] ?>">
                                Company
                                <span class="text-danger">*</span>
                            </label>

                            <input
                                type="text"
                                class="form-control"
                                id="company_<?= (int) $experience['id'] ?>"
                                name="company"
                                value="<?= e($experience['company']) ?>"
                                required>

                        </div>


                        <!-- Job Title -->
                        <div class="form-group">

                            <label for="job_title_<?= (int) $experience['id'] ?>">
                                Job Title
                                <span class="text-danger">*</span>
                            </label>

                            <input
                                type="text"
                                class="form-control"
                                id="job_title_<?= (int) $experience['id'] ?>"
                                name="job_title"
                                value="<?= e($experience['job_title']) ?>"
                                required>

                        </div>


                        <!-- Location -->
                        <div class="form-group">

                            <label for="location_<?= (int) $experience['id'] ?>">
                                Location
                            </label>

                            <input
                                type="text"
                                class="form-control"
                                id="location_<?= (int) $experience['id'] ?>"
                                name="location"
                                value="<?= e($experience['location'] ?? '') ?>"
                                placeholder="e.g. Naga City, Philippines">

                        </div>


                        <!-- Company URL -->
                        <!-- <div class="form-group">

                            <label for="company_url_<?= (int) $experience['id'] ?>">
                                Company Website
                            </label>

                            <input
                                type="url"
                                class="form-control"
                                id="company_url_<?= (int) $experience['id'] ?>"
                                name="company_url"
                                value="<?= e($experience['company_url'] ?? '') ?>"
                                placeholder="https://example.com">

                        </div> -->


                        <!-- Responsibilities -->
                        <?php
                        $experienceResponsibilities = [];

                        if (!empty($experience['description'])) {
                            $experienceResponsibilities = preg_split(
                                '/\\r\\n|\\r|\\n/',
                                (string) $experience['description']
                            ) ?: [];
                        }

                        $experienceResponsibilities = array_values(
                            array_filter(
                                array_map(
                                    static fn($item) => trim((string) $item),
                                    $experienceResponsibilities
                                ),
                                static fn($item) => $item !== ''
                            )
                        );

                        if (!$experienceResponsibilities) {
                            $experienceResponsibilities = [''];
                        }
                        ?>

                        <div class="form-group">

                            <label class="font-weight-bold">
                                Job Responsibilities
                            </label>

                            <div class="responsibilities-container">

                                <?php foreach ($experienceResponsibilities as $responsibility): ?>

                                    <div class="responsibility-row mb-2">

                                        <div class="input-group">

                                            <input
                                                type="text"
                                                class="form-control responsibility-input"
                                                name="responsibilities[]"
                                                maxlength="255"
                                                value="<?= e($responsibility) ?>"
                                                placeholder="e.g. Developed and maintained web applications.">

                                            <div class="input-group-append">

                                                <button
                                                    type="button"
                                                    class="btn btn-outline-danger remove-responsibility"
                                                    title="Remove responsibility">
                                                    <i class="fas fa-times"></i>
                                                </button>

                                            </div>

                                        </div>

                                    </div>

                                <?php endforeach; ?>

                            </div>

                            <button
                                type="button"
                                class="btn btn-outline-primary btn-sm mt-1 add-responsibility">
                                <i class="fas fa-plus mr-1"></i>
                                Add Responsibility
                            </button>

                            <small class="form-text text-muted">
                                Add each job responsibility separately.
                            </small>

                        </div>


                        <!-- Dates -->
                        <div class="row">

                            <div class="col-md-6">

                                <div class="form-group">

                                    <label for="start_date_<?= (int) $experience['id'] ?>">
                                        Start Date
                                        <span class="text-danger">*</span>
                                    </label>

                                    <input
                                        type="date"
                                        class="form-control"
                                        id="start_date_<?= (int) $experience['id'] ?>"
                                        name="start_date"
                                        value="<?= e($experience['start_date'] ?? '') ?>"
                                        required>

                                </div>

                            </div>


                            <div class="col-md-6">

                                <div class="form-group">

                                    <label for="end_date_<?= (int) $experience['id'] ?>">
                                        End Date
                                    </label>

                                    <input
                                        type="date"
                                        class="form-control"
                                        id="end_date_<?= (int) $experience['id'] ?>"
                                        name="end_date"
                                        value="<?= e($experience['end_date'] ?? '') ?>">

                                </div>

                            </div>

                        </div>


                        <!-- Current Position -->
                        <div class="form-group">

                            <div class="custom-control custom-checkbox">

                                <input
                                    type="checkbox"
                                    class="custom-control-input"
                                    id="is_current_<?= (int) $experience['id'] ?>"
                                    name="is_current"
                                    <?= !empty($experience['is_current']) ? 'checked' : '' ?>>

                                <label
                                    class="custom-control-label"
                                    for="is_current_<?= (int) $experience['id'] ?>">
                                    I currently work here
                                </label>

                            </div>

                            <!-- <small class="form-text text-muted">
                                End date will be ignored when this option is selected.
                            </small> -->

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
                    <i class="fas fa-exclamation-triangle text-danger mr-2"></i>
                    Delete Experience
                </h5>

                <button
                    type="button"
                    class="close"
                    data-dismiss="modal"
                    aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>

            </div>


            <!-- Modal Body -->
            <div class="modal-body">

                <p class="mb-0">
                    Are you sure you want to delete this work experience?
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
        | Current Position Checkbox
        |--------------------------------------------------------------------------
        */

        document.querySelectorAll(
            'input[name="is_current"]'
        ).forEach(function(checkbox) {

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
            | Initialize state when editing
            */
            checkbox.dispatchEvent(new Event('change'));

        });


        /*
        |--------------------------------------------------------------------------
        | Job Responsibilities Repeater
        |--------------------------------------------------------------------------
        */

        document
            .querySelectorAll('form')
            .forEach(function(form) {

                const container = form.querySelector(
                    '.responsibilities-container'
                );

                const addButton = form.querySelector(
                    '.add-responsibility'
                );

                if (!container || !addButton) {
                    return;
                }

                /*
                |--------------------------------------------------------------------------
                | Add Responsibility
                |--------------------------------------------------------------------------
                */

                addButton.addEventListener(
                    'click',
                    function(event) {

                        event.preventDefault();

                        const row = document.createElement('div');

                        row.className =
                            'responsibility-row mb-2';

                        row.innerHTML = `
                            <div class="input-group">

                                <input
                                    type="text"
                                    class="form-control responsibility-input"
                                    name="responsibilities[]"
                                    maxlength="255"
                                    placeholder="e.g. Developed and maintained web applications.">

                                <div class="input-group-append">

                                    <button
                                        type="button"
                                        class="btn btn-outline-danger remove-responsibility"
                                        title="Remove responsibility">
                                        <i class="fas fa-times"></i>
                                    </button>

                                </div>

                            </div>
                        `;

                        container.appendChild(row);

                        const input = row.querySelector(
                            '.responsibility-input'
                        );

                        if (input) {
                            input.focus();
                        }

                    }
                );

                /*
                |--------------------------------------------------------------------------
                | Remove Responsibility
                |--------------------------------------------------------------------------
                */

                container.addEventListener(
                    'click',
                    function(event) {

                        const button = event.target.closest(
                            '.remove-responsibility'
                        );

                        if (!button) {
                            return;
                        }

                        event.preventDefault();

                        const row = button.closest(
                            '.responsibility-row'
                        );

                        if (row) {
                            row.remove();
                        }

                    }
                );

                /*
                |--------------------------------------------------------------------------
                | Prevent Enter From Submitting
                |--------------------------------------------------------------------------
                */

                container.addEventListener(
                    'keydown',
                    function(event) {

                        const input = event.target.closest(
                            '.responsibility-input'
                        );

                        if (
                            input &&
                            event.key === 'Enter'
                        ) {
                            event.preventDefault();
                        }

                    }
                );

            });


        /*
        |--------------------------------------------------------------------------
        | Delete Confirmation
        |--------------------------------------------------------------------------
        */

        const deleteModal = document.getElementById('deleteModal');
        const confirmDelete = document.getElementById('confirmDelete');

        if (deleteModal && confirmDelete) {

            $('[data-delete-url]').on('click', function() {

                const deleteUrl = this.getAttribute('data-delete-url');

                confirmDelete.setAttribute(
                    'href',
                    deleteUrl
                );

            });

            $('#deleteModal').on('hidden.bs.modal', function() {

                confirmDelete.setAttribute(
                    'href',
                    '#'
                );

            });

        }

    });
</script>


<?php
require dirname(__DIR__) . '/includes/footer.php';
?>