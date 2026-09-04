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
| Helper Functions
|--------------------------------------------------------------------------
*/

function redirectReferences(): never
{
    redirect('user/references.php');
}

function getReferenceSettings(PDO $pdo, int $uid): array
{
    $stmt = $pdo->prepare(
        'SELECT show_references, references_on_request
         FROM resume_settings
         WHERE user_id = ?
         LIMIT 1'
    );

    $stmt->execute([$uid]);

    $settings = $stmt->fetch() ?: [];

    return [
        'show_references' => (int) ($settings['show_references'] ?? 0),
        'references_on_request' => (int) ($settings['references_on_request'] ?? 1),
    ];
}


/*
|--------------------------------------------------------------------------
| POST Actions
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    try {

        verify_csrf();

        $action = $_POST['action'] ?? '';


        /*
        |--------------------------------------------------------------------------
        | Save Resume Reference Settings
        |--------------------------------------------------------------------------
        */

        if ($action === 'save_settings') {

            $showReferences = isset($_POST['show_references']) ? 1 : 0;

            $referencesOnRequest = isset(
                $_POST['references_on_request']
            ) ? 1 : 0;


            $stmt = $pdo->prepare(
                'SELECT id
                 FROM resume_settings
                 WHERE user_id = ?
                 LIMIT 1'
            );

            $stmt->execute([$uid]);

            $existingSettings = $stmt->fetch();


            if ($existingSettings) {

                $stmt = $pdo->prepare(
                    'UPDATE resume_settings
                     SET
                        show_references = ?,
                        references_on_request = ?
                     WHERE user_id = ?'
                );

                $stmt->execute([
                    $showReferences,
                    $referencesOnRequest,
                    $uid
                ]);
            } else {

                $sectionOrder = json_encode([
                    'summary',
                    'experience',
                    'education',
                    'projects',
                    'skills',
                    'certifications',
                    'resume_references'
                ]);

                $stmt = $pdo->prepare(
                    'INSERT INTO resume_settings
                    (
                        user_id,
                        section_order,
                        show_references,
                        references_on_request
                    )
                    VALUES (?, ?, ?, ?)'
                );

                $stmt->execute([
                    $uid,
                    $sectionOrder,
                    $showReferences,
                    $referencesOnRequest
                ]);
            }


            flash(
                'success',
                'Resume reference settings saved successfully.'
            );

            redirectReferences();
        }


        /*
        |--------------------------------------------------------------------------
        | Delete Reference
        |--------------------------------------------------------------------------
        */

        if ($action === 'delete') {

            $id = (int) ($_POST['id'] ?? 0);

            if ($id <= 0) {
                throw new RuntimeException(
                    'Invalid reference.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | Delete only the reference belonging to the current user
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare(
                'DELETE FROM `resume_references`
                 WHERE id = ? AND user_id = ?'
            );

            $stmt->execute([
                $id,
                $uid
            ]);


            /*
            |--------------------------------------------------------------------------
            | Make sure a record was actually deleted
            |--------------------------------------------------------------------------
            */

            if ($stmt->rowCount() === 0) {
                throw new RuntimeException(
                    'Reference could not be deleted or was not found.'
                );
            }


            flash(
                'success',
                'Reference deleted successfully.'
            );

            redirectReferences();
        }


        /*
        |--------------------------------------------------------------------------
        | Save Reference
        |--------------------------------------------------------------------------
        */

        if ($action === 'save_reference') {

            $id = (int) ($_POST['id'] ?? 0);

            $name = trim(
                (string) ($_POST['name'] ?? '')
            );

            $position = trim(
                (string) ($_POST['position'] ?? '')
            );

            $company = trim(
                (string) ($_POST['company'] ?? '')
            );

            $relationship = trim(
                (string) ($_POST['relationship'] ?? '')
            );

            $email = trim(
                (string) ($_POST['email'] ?? '')
            );

            $phone = trim(
                (string) ($_POST['phone'] ?? '')
            );

            $notes = trim(
                (string) ($_POST['notes'] ?? '')
            );

            $isPublic = isset($_POST['is_public'])
                ? 1
                : 0;


            /*
            |--------------------------------------------------------------------------
            | Validation
            |--------------------------------------------------------------------------
            */

            if ($name === '') {
                throw new RuntimeException(
                    'Reference name is required.'
                );
            }


            if (
                $email !== '' &&
                !filter_var($email, FILTER_VALIDATE_EMAIL)
            ) {
                throw new RuntimeException(
                    'Please enter a valid email address.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | Update Reference
            |--------------------------------------------------------------------------
            */

            if ($id > 0) {

                $stmt = $pdo->prepare(
                    'SELECT id
                     FROM `resume_references`
                     WHERE id = ? AND user_id = ?
                     LIMIT 1'
                );

                $stmt->execute([
                    $id,
                    $uid
                ]);

                $existingReference = $stmt->fetch();


                if (!$existingReference) {
                    throw new RuntimeException(
                        'Reference not found.'
                    );
                }


                $stmt = $pdo->prepare(
                    'UPDATE `resume_references`
                     SET
                        name = ?,
                        position = ?,
                        company = ?,
                        email = ?,
                        phone = ?,
                        relationship = ?,
                        notes = ?,
                        is_public = ?
                     WHERE id = ? AND user_id = ?'
                );

                $stmt->execute([
                    $name,
                    $position !== '' ? $position : null,
                    $company !== '' ? $company : null,
                    $email !== '' ? $email : null,
                    $phone !== '' ? $phone : null,
                    $relationship !== '' ? $relationship : null,
                    $notes !== '' ? $notes : null,
                    $isPublic,
                    $id,
                    $uid
                ]);


                flash(
                    'success',
                    'Reference updated successfully.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | Add Reference
            |--------------------------------------------------------------------------
            */ else {

                $stmt = $pdo->prepare(
                    'INSERT INTO `resume_references`
                    (
                        user_id,
                        name,
                        position,
                        company,
                        email,
                        phone,
                        relationship,
                        notes,
                        is_public
                    )
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
                );

                $stmt->execute([
                    $uid,
                    $name,
                    $position !== '' ? $position : null,
                    $company !== '' ? $company : null,
                    $email !== '' ? $email : null,
                    $phone !== '' ? $phone : null,
                    $relationship !== '' ? $relationship : null,
                    $notes !== '' ? $notes : null,
                    $isPublic
                ]);


                flash(
                    'success',
                    'Reference added successfully.'
                );
            }


            redirectReferences();
        }


        throw new RuntimeException(
            'Invalid action.'
        );
    } catch (Throwable $e) {

        flash(
            'danger',
            $e->getMessage()
        );

        redirectReferences();
    }
}


/*
|--------------------------------------------------------------------------
| Load Settings
|--------------------------------------------------------------------------
*/

$settings = getReferenceSettings(
    $pdo,
    $uid
);


/*
|--------------------------------------------------------------------------
| Load References
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare(
    'SELECT *
     FROM `resume_references`
     WHERE user_id = ?
     ORDER BY created_at DESC, id DESC'
);

$stmt->execute([$uid]);

$references = $stmt->fetchAll();


$pageTitle = 'References';

require dirname(__DIR__) . '/includes/header.php';
?>


<div class="container-fluid">

    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">

        <div>

            <h1 class="h3 mb-1 text-gray-800">
                References
            </h1>

            <p class="mb-0 text-gray-600">
                Manage your professional references and choose whether they appear in your resume.
            </p>

        </div>

        <!--
        <div class="d-flex">

            <button
                type="button"
                class="btn btn-secondary btn-sm shadow-sm mr-2"
                data-toggle="modal"
                data-target="#settingsModal">
                <i class="fas fa-cog fa-sm mr-1"></i>
                Resume Settings
            </button>

        </div>
        -->

    </div>


    <!-- References Card -->
    <div class="card shadow mb-4">

        <div class="card-header py-3 d-flex justify-content-between align-items-center">

            <div>

                <h6 class="m-0 font-weight-bold text-primary">
                    My References
                </h6>

                <small class="text-muted">
                    <?= count($references) ?>
                    professional contact<?= count($references) !== 1 ? 's' : '' ?>
                </small>

            </div>


            <button
                type="button"
                class="btn btn-primary btn-sm"
                data-toggle="modal"
                data-target="#referenceModal">
                <i class="fas fa-plus mr-1"></i>
                Add Reference
            </button>

        </div>


        <div class="card-body">

            <?php if (!$references): ?>

                <!-- Empty State -->
                <div class="text-center py-5">

                    <div class="mb-3">
                        <i class="fas fa-user-friends fa-3x text-gray-300"></i>
                    </div>

                    <h5 class="font-weight-bold text-gray-800">
                        No references added
                    </h5>

                    <p class="text-muted mb-4">
                        Add a former manager, professor, client, or supervisor.
                    </p>

                    <button
                        type="button"
                        class="btn btn-primary"
                        data-toggle="modal"
                        data-target="#referenceModal">
                        <i class="fas fa-plus mr-1"></i>
                        Add Reference
                    </button>

                </div>

            <?php else: ?>

                <!-- Reference List -->
                <div class="list-group">

                    <?php foreach ($references as $reference): ?>

                        <?php
                        $position = trim(
                            (string) ($reference['position'] ?? '')
                        );

                        $company = trim(
                            (string) ($reference['company'] ?? '')
                        );

                        $positionCompany = '';

                        if ($position !== '' && $company !== '') {
                            $positionCompany =
                                $position . ' · ' . $company;
                        } elseif ($position !== '') {
                            $positionCompany = $position;
                        } elseif ($company !== '') {
                            $positionCompany = $company;
                        } else {
                            $positionCompany =
                                'Professional contact';
                        }
                        ?>


                        <div class="list-group-item py-4">

                            <div class="row align-items-center">

                                <!-- Icon -->
                                <div class="col-auto">

                                    <div
                                        class="icon-circle bg-primary"
                                        style="width: 50px; height: 50px;">
                                        <i class="fas fa-user-tie text-white"></i>
                                    </div>

                                </div>


                                <!-- Reference Information -->
                                <div class="col">

                                    <h5 class="font-weight-bold text-gray-800 mb-1">
                                        <?= e($reference['name']) ?>
                                    </h5>


                                    <div class="text-gray-600 mb-1">

                                        <i class="fas fa-briefcase mr-1"></i>

                                        <?= e($positionCompany) ?>

                                    </div>


                                    <?php if (!empty($reference['relationship'])): ?>

                                        <div class="small text-muted mb-2">

                                            <i class="fas fa-handshake mr-1"></i>

                                            <?= e($reference['relationship']) ?>

                                        </div>

                                    <?php endif; ?>


                                    <div class="d-flex flex-wrap">

                                        <span
                                            class="badge <?= !empty($reference['is_public'])
                                                                ? 'badge-success'
                                                                : 'badge-secondary' ?> mr-2 mb-1">

                                            <i
                                                class="fas <?= !empty($reference['is_public'])
                                                                ? 'fa-eye'
                                                                : 'fa-eye-slash' ?> mr-1"></i>

                                            <?= !empty($reference['is_public'])
                                                ? 'Available in resume'
                                                : 'Private' ?>

                                        </span>


                                        <?php if (!empty($reference['email'])): ?>

                                            <span class="badge badge-light mr-2 mb-1">

                                                <i class="fas fa-envelope mr-1"></i>

                                                <?= e($reference['email']) ?>

                                            </span>

                                        <?php endif; ?>


                                        <?php if (!empty($reference['phone'])): ?>

                                            <span class="badge badge-light mr-2 mb-1">

                                                <i class="fas fa-phone mr-1"></i>

                                                <?= e($reference['phone']) ?>

                                            </span>

                                        <?php endif; ?>

                                    </div>


                                    <?php if (!empty($reference['notes'])): ?>

                                        <p class="text-gray-600 small mt-2 mb-0">

                                            <?= nl2br(
                                                e($reference['notes'])
                                            ) ?>

                                        </p>

                                    <?php endif; ?>

                                </div>


                                <!-- Actions -->
                                <div class="col-auto">

                                    <div class="d-flex align-items-center">

                                        <!-- Edit -->
                                        <button
                                            type="button"
                                            class="btn btn-sm btn-outline-primary mr-1"
                                            data-toggle="modal"
                                            data-target="#editReference<?= (int) $reference['id'] ?>"
                                            title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </button>


                                        <!-- Delete -->
                                        <button
                                            type="button"
                                            class="btn btn-sm btn-outline-danger"
                                            data-toggle="modal"
                                            data-target="#deleteModal"
                                            data-reference-id="<?= (int) $reference['id'] ?>"
                                            onclick="document.getElementById('deleteReferenceId').value = this.getAttribute('data-reference-id');"
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
     ADD REFERENCE MODAL
========================================================= -->
<div
    class="modal fade"
    id="referenceModal"
    tabindex="-1"
    role="dialog"
    aria-labelledby="referenceModalLabel"
    aria-hidden="true">

    <div
        class="modal-dialog modal-lg"
        role="document">

        <form method="post">

            <input
                type="hidden"
                name="action"
                value="save_reference">

            <input
                type="hidden"
                name="id"
                value="0">

            <?= csrf_field() ?>


            <div class="modal-content">

                <div class="modal-header">

                    <h5
                        class="modal-title text-primary"
                        id="referenceModalLabel">
                        Add Reference
                    </h5>

                    <button
                        type="button"
                        class="close"
                        data-dismiss="modal"
                        aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>

                </div>


                <div
                    class="modal-body"
                    style="max-height: 70vh; overflow-y: auto;">

                    <div class="form-group">

                        <label for="reference_name">
                            Full Name
                            <span class="text-danger">*</span>
                        </label>

                        <input
                            type="text"
                            class="form-control"
                            id="reference_name"
                            name="name"
                            placeholder="e.g. Juan Dela Cruz"
                            required>

                    </div>


                    <div class="row">

                        <div class="col-md-6">

                            <div class="form-group">

                                <label for="reference_position">
                                    Position
                                </label>

                                <input
                                    type="text"
                                    class="form-control"
                                    id="reference_position"
                                    name="position"
                                    placeholder="e.g. Project Manager">

                            </div>

                        </div>


                        <div class="col-md-6">

                            <div class="form-group">

                                <label for="reference_company">
                                    Company / Organization
                                </label>

                                <input
                                    type="text"
                                    class="form-control"
                                    id="reference_company"
                                    name="company"
                                    placeholder="e.g. ABC Corporation">

                            </div>

                        </div>

                    </div>


                    <div class="form-group">

                        <label for="reference_relationship">
                            Relationship
                        </label>

                        <input
                            type="text"
                            class="form-control"
                            id="reference_relationship"
                            name="relationship"
                            placeholder="e.g. Former Supervisor, Professor, Client">

                    </div>


                    <div class="row">

                        <div class="col-md-6">

                            <div class="form-group">

                                <label for="reference_email">
                                    Email
                                </label>

                                <input
                                    type="email"
                                    class="form-control"
                                    id="reference_email"
                                    name="email"
                                    placeholder="example@email.com">

                            </div>

                        </div>


                        <div class="col-md-6">

                            <div class="form-group">

                                <label for="reference_phone">
                                    Phone
                                </label>

                                <input
                                    type="text"
                                    class="form-control"
                                    id="reference_phone"
                                    name="phone"
                                    placeholder="e.g. +63 912 345 6789">

                            </div>

                        </div>

                    </div>


                    <div class="form-group">

                        <label for="reference_notes">
                            Notes
                        </label>

                        <textarea
                            class="form-control"
                            id="reference_notes"
                            name="notes"
                            rows="4"
                            placeholder="Add any additional information about this reference..."></textarea>

                    </div>


                    <div class="form-group">

                        <div class="custom-control custom-checkbox">

                            <input
                                type="checkbox"
                                class="custom-control-input"
                                id="reference_is_public"
                                name="is_public"
                                value="1"
                                checked>

                            <label
                                class="custom-control-label"
                                for="reference_is_public">
                                Make this reference available in my resume
                            </label>

                        </div>

                        <small class="form-text text-muted">
                            Public references can be included in your resume.
                        </small>

                    </div>

                </div>


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
                        Save Reference
                    </button>

                </div>

            </div>

        </form>

    </div>

</div>


<!-- =========================================================
     EDIT REFERENCE MODALS
========================================================= -->
<?php foreach ($references as $reference): ?>

    <div
        class="modal fade"
        id="editReference<?= (int) $reference['id'] ?>"
        tabindex="-1"
        role="dialog"
        aria-hidden="true">

        <div
            class="modal-dialog modal-lg"
            role="document">

            <form method="post">

                <input
                    type="hidden"
                    name="action"
                    value="save_reference">

                <input
                    type="hidden"
                    name="id"
                    value="<?= (int) $reference['id'] ?>">

                <?= csrf_field() ?>


                <div class="modal-content">

                    <div class="modal-header">

                        <h5 class="modal-title text-primary">
                            Edit Reference
                        </h5>

                        <button
                            type="button"
                            class="close"
                            data-dismiss="modal"
                            aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>

                    </div>


                    <div
                        class="modal-body"
                        style="max-height: 70vh; overflow-y: auto;">

                        <div class="form-group">

                            <label>
                                Full Name
                                <span class="text-danger">*</span>
                            </label>

                            <input
                                type="text"
                                class="form-control"
                                name="name"
                                value="<?= e(
                                            $reference['name']
                                        ) ?>"
                                required>

                        </div>


                        <div class="row">

                            <div class="col-md-6">

                                <div class="form-group">

                                    <label>
                                        Position
                                    </label>

                                    <input
                                        type="text"
                                        class="form-control"
                                        name="position"
                                        value="<?= e(
                                                    $reference['position'] ?? ''
                                                ) ?>">

                                </div>

                            </div>


                            <div class="col-md-6">

                                <div class="form-group">

                                    <label>
                                        Company / Organization
                                    </label>

                                    <input
                                        type="text"
                                        class="form-control"
                                        name="company"
                                        value="<?= e(
                                                    $reference['company'] ?? ''
                                                ) ?>">

                                </div>

                            </div>

                        </div>


                        <div class="form-group">

                            <label>
                                Relationship
                            </label>

                            <input
                                type="text"
                                class="form-control"
                                name="relationship"
                                value="<?= e(
                                            $reference['relationship'] ?? ''
                                        ) ?>">

                        </div>


                        <div class="row">

                            <div class="col-md-6">

                                <div class="form-group">

                                    <label>
                                        Email
                                    </label>

                                    <input
                                        type="email"
                                        class="form-control"
                                        name="email"
                                        value="<?= e(
                                                    $reference['email'] ?? ''
                                                ) ?>">

                                </div>

                            </div>


                            <div class="col-md-6">

                                <div class="form-group">

                                    <label>
                                        Phone
                                    </label>

                                    <input
                                        type="text"
                                        class="form-control"
                                        name="phone"
                                        value="<?= e(
                                                    $reference['phone'] ?? ''
                                                ) ?>">

                                </div>

                            </div>

                        </div>


                        <div class="form-group">

                            <label>
                                Notes
                            </label>

                            <textarea
                                class="form-control"
                                name="notes"
                                rows="4"
                                placeholder="Add any additional information about this reference..."><?= e(
                                                                                                            $reference['notes'] ?? ''
                                                                                                        ) ?></textarea>

                        </div>


                        <div class="form-group">

                            <div class="custom-control custom-checkbox">

                                <input
                                    type="checkbox"
                                    class="custom-control-input"
                                    id="publicReference<?= (int) $reference['id'] ?>"
                                    name="is_public"
                                    value="1"
                                    <?= !empty($reference['is_public'])
                                        ? 'checked'
                                        : '' ?>>

                                <label
                                    class="custom-control-label"
                                    for="publicReference<?= (int) $reference['id'] ?>">
                                    Make this reference available in my resume
                                </label>

                            </div>

                            <small class="form-text text-muted">
                                Public references can be included in your resume.
                            </small>

                        </div>

                    </div>


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

                </div>

            </form>

        </div>

    </div>

<?php endforeach; ?>


<!-- =========================================================
     RESUME SETTINGS MODAL
========================================================= -->
<div
    class="modal fade"
    id="settingsModal"
    tabindex="-1"
    role="dialog"
    aria-labelledby="settingsModalLabel"
    aria-hidden="true">

    <div
        class="modal-dialog"
        role="document">

        <form method="post">

            <input
                type="hidden"
                name="action"
                value="save_settings">

            <?= csrf_field() ?>


            <div class="modal-content">

                <div class="modal-header">

                    <h5
                        class="modal-title text-primary"
                        id="settingsModalLabel">
                        Resume Reference Settings
                    </h5>

                    <button
                        type="button"
                        class="close"
                        data-dismiss="modal"
                        aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>

                </div>


                <div
                    class="modal-body"
                    style="max-height: 70vh; overflow-y: auto;">

                    <div class="form-group">

                        <div class="custom-control custom-checkbox">

                            <input
                                type="checkbox"
                                class="custom-control-input"
                                id="show_references"
                                name="show_references"
                                value="1"
                                <?= $settings['show_references']
                                    ? 'checked'
                                    : '' ?>>

                            <label
                                class="custom-control-label font-weight-bold"
                                for="show_references">
                                Show References section
                            </label>

                        </div>

                        <small class="form-text text-muted ml-4">
                            Allow the References section to be included in your resume.
                        </small>

                    </div>


                    <hr>


                    <div class="form-group mb-0">

                        <div class="custom-control custom-checkbox">

                            <input
                                type="checkbox"
                                class="custom-control-input"
                                id="references_on_request"
                                name="references_on_request"
                                value="1"
                                <?= $settings['references_on_request']
                                    ? 'checked'
                                    : '' ?>>

                            <label
                                class="custom-control-label font-weight-bold"
                                for="references_on_request">
                                References available upon request
                            </label>

                        </div>

                        <small class="form-text text-muted ml-4">
                            Show a request message when no public references are displayed.
                        </small>

                    </div>

                </div>


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
                        Save Settings
                    </button>

                </div>

            </div>

        </form>

    </div>

</div>


<!-- =========================================================
     DELETE REFERENCE MODAL
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

        <form method="post">

            <?= csrf_field() ?>

            <input
                type="hidden"
                name="action"
                value="delete">

            <input
                type="hidden"
                name="id"
                id="deleteReferenceId"
                value="">


            <div class="modal-content">

                <div class="modal-header">

                    <h5
                        class="modal-title"
                        id="deleteModalLabel">
                        Delete Reference
                    </h5>

                    <button
                        type="button"
                        class="close"
                        data-dismiss="modal"
                        aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>

                </div>


                <div class="modal-body">

                    <div class="text-center py-2">

                        <i class="fas fa-exclamation-triangle fa-3x text-warning mb-3"></i>

                        <p class="mb-1">
                            Are you sure you want to delete this reference?
                        </p>

                        <small class="text-muted">
                            This action cannot be undone.
                        </small>

                    </div>

                </div>


                <div class="modal-footer">

                    <button
                        type="button"
                        class="btn btn-secondary"
                        data-dismiss="modal">
                        Cancel
                    </button>

                    <button
                        type="submit"
                        class="btn btn-danger">
                        <i class="fas fa-trash mr-1"></i>
                        Delete
                    </button>

                </div>

            </div>

        </form>

    </div>

</div>


<!-- =========================================================
     JAVASCRIPT
========================================================= -->
<script>
    $(document).ready(function() {

        /*
        |--------------------------------------------------------------------------
        | Delete Reference
        |--------------------------------------------------------------------------
        */

        $('#deleteModal').on('hidden.bs.modal', function() {
            document.getElementById('deleteReferenceId').value = '';
        });

    });
</script>


<?php require dirname(__DIR__) . '/includes/footer.php'; ?>