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
| Delete Certification
|--------------------------------------------------------------------------
*/
if (isset($_GET['delete'])) {
    $id = (int) $_GET['delete'];

    try {
        $stmt = $pdo->prepare(
            'SELECT certificate_file
             FROM certifications
             WHERE id = ? AND user_id = ?'
        );
        $stmt->execute([$id, $uid]);

        $certification = $stmt->fetch();

        if ($certification) {
            if (!empty($certification['certificate_file'])) {
                delete_upload($certification['certificate_file']);
            }

            $stmt = $pdo->prepare(
                'DELETE FROM certifications
                 WHERE id = ? AND user_id = ?'
            );
            $stmt->execute([$id, $uid]);

            flash('success', 'Certification deleted successfully.');
        }
    } catch (Throwable $e) {
        flash('danger', $e->getMessage());
    }

    redirect('user/certifications.php');
}


/*
|--------------------------------------------------------------------------
| Add / Update Certification
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verify_csrf();

        $id = (int) ($_POST['id'] ?? 0);

        $name = trim((string) ($_POST['name'] ?? ''));
        $issuingOrganization = trim(
            (string) ($_POST['issuing_organization'] ?? '')
        );

        $issueDate = ($_POST['issue_date'] ?? '') !== ''
            ? $_POST['issue_date']
            : null;

        $expirationDate = ($_POST['expiration_date'] ?? '') !== ''
            ? $_POST['expiration_date']
            : null;

        $credentialId = trim(
            (string) ($_POST['credential_id'] ?? '')
        );

        $credentialUrl = trim(
            (string) ($_POST['credential_url'] ?? '')
        );

        $description = trim(
            (string) ($_POST['description'] ?? '')
        );

        $isPublic = isset($_POST['is_public']) ? 1 : 0;


        /*
        |--------------------------------------------------------------------------
        | Validation
        |--------------------------------------------------------------------------
        */
        if ($name === '') {
            throw new RuntimeException(
                'Certification name is required.'
            );
        }

        if (
            $credentialUrl !== '' &&
            !filter_var($credentialUrl, FILTER_VALIDATE_URL)
        ) {
            throw new RuntimeException(
                'Please enter a valid credential URL.'
            );
        }

        if (
            $issueDate &&
            $expirationDate &&
            $expirationDate < $issueDate
        ) {
            throw new RuntimeException(
                'Expiration date cannot be earlier than the issue date.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Certificate Upload
        |--------------------------------------------------------------------------
        */
        $certificateFile = upload_file(
            'certificate_file',
            ['pdf'],
            'certificates'
        );


        /*
        |--------------------------------------------------------------------------
        | Update
        |--------------------------------------------------------------------------
        */
        if ($id > 0) {
            $stmt = $pdo->prepare(
                'SELECT certificate_file
                 FROM certifications
                 WHERE id = ? AND user_id = ?'
            );
            $stmt->execute([$id, $uid]);

            $existing = $stmt->fetch();

            if (!$existing) {
                throw new RuntimeException(
                    'Certification not found.'
                );
            }


            /*
            |--------------------------------------------------------------
            | Update with new certificate
            |--------------------------------------------------------------
            */
            if ($certificateFile) {
                $stmt = $pdo->prepare(
                    'UPDATE certifications
                     SET
                        name = ?,
                        issuing_organization = ?,
                        issue_date = ?,
                        expiration_date = ?,
                        credential_id = ?,
                        credential_url = ?,
                        description = ?,
                        certificate_file = ?,
                        is_public = ?
                     WHERE id = ? AND user_id = ?'
                );

                $stmt->execute([
                    $name,
                    $issuingOrganization ?: null,
                    $issueDate,
                    $expirationDate,
                    $credentialId ?: null,
                    $credentialUrl ?: null,
                    $description ?: null,
                    $certificateFile,
                    $isPublic,
                    $id,
                    $uid
                ]);

                if (!empty($existing['certificate_file'])) {
                    delete_upload($existing['certificate_file']);
                }
            }

            /*
            |--------------------------------------------------------------
            | Update without replacing certificate
            |--------------------------------------------------------------
            */ else {
                $stmt = $pdo->prepare(
                    'UPDATE certifications
                     SET
                        name = ?,
                        issuing_organization = ?,
                        issue_date = ?,
                        expiration_date = ?,
                        credential_id = ?,
                        credential_url = ?,
                        description = ?,
                        is_public = ?
                     WHERE id = ? AND user_id = ?'
                );

                $stmt->execute([
                    $name,
                    $issuingOrganization ?: null,
                    $issueDate,
                    $expirationDate,
                    $credentialId ?: null,
                    $credentialUrl ?: null,
                    $description ?: null,
                    $isPublic,
                    $id,
                    $uid
                ]);
            }

            flash(
                'success',
                'Certification updated successfully.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Add
        |--------------------------------------------------------------------------
        */ else {
            $stmt = $pdo->prepare(
                'INSERT INTO certifications
                (
                    user_id,
                    name,
                    issuing_organization,
                    issue_date,
                    expiration_date,
                    credential_id,
                    credential_url,
                    description,
                    certificate_file,
                    is_public
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );

            $stmt->execute([
                $uid,
                $name,
                $issuingOrganization ?: null,
                $issueDate,
                $expirationDate,
                $credentialId ?: null,
                $credentialUrl ?: null,
                $description ?: null,
                $certificateFile,
                $isPublic
            ]);

            flash(
                'success',
                'Certification added successfully.'
            );
        }
    } catch (Throwable $e) {
        flash('danger', $e->getMessage());
    }

    redirect('user/certifications.php');
}


/*
|--------------------------------------------------------------------------
| Fetch Certifications
|--------------------------------------------------------------------------
*/
$stmt = $pdo->prepare(
    'SELECT *
     FROM certifications
     WHERE user_id = ?
     ORDER BY issue_date DESC, id DESC'
);

$stmt->execute([$uid]);

$certifications = $stmt->fetchAll();

$pageTitle = 'Certifications';

require dirname(__DIR__) . '/includes/header.php';
?>

<div class="container-fluid">

    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <div>
            <h1 class="h3 mb-1 text-gray-800">
                Certifications
            </h1>

            <p class="mb-0 text-gray-600">
                Store certificates, credentials, and professional training records.
            </p>
        </div>
    </div>


    <!-- Certifications Card -->
    <div class="card shadow mb-4">

        <div class="card-header py-3 d-flex justify-content-between align-items-center">

            <div>
                <h6 class="m-0 font-weight-bold text-primary">
                    Certifications
                </h6>

                <small class="text-muted">
                    Your professional credentials and certifications
                </small>
            </div>

            <button
                type="button"
                class="btn btn-primary btn-sm"
                data-toggle="modal"
                data-target="#certificationModal">
                <i class="fas fa-plus mr-1"></i>
                Add Certification
            </button>

        </div>


        <div class="card-body">

            <?php if (!$certifications): ?>

                <!-- Empty State -->
                <div class="text-center py-5">

                    <div class="mb-3">
                        <i class="fas fa-certificate fa-3x text-gray-300"></i>
                    </div>

                    <h5 class="font-weight-bold text-gray-800">
                        No certifications added
                    </h5>

                    <p class="text-muted mb-4">
                        Add a credential to strengthen your profile.
                    </p>

                    <button
                        type="button"
                        class="btn btn-primary"
                        data-toggle="modal"
                        data-target="#certificationModal">
                        <i class="fas fa-plus mr-1"></i>
                        Add Certification
                    </button>

                </div>

            <?php else: ?>

                <!-- Certification List -->
                <div class="list-group">

                    <?php foreach ($certifications as $certification): ?>

                        <div class="list-group-item py-4">

                            <div class="row align-items-center">

                                <!-- Icon -->
                                <div class="col-auto">

                                    <div
                                        class="icon-circle bg-primary"
                                        style="width: 50px; height: 50px;">
                                        <i class="fas fa-certificate text-white"></i>
                                    </div>

                                </div>


                                <!-- Certification Information -->
                                <div class="col">

                                    <h5 class="font-weight-bold text-gray-800 mb-1">
                                        <?= e($certification['name']) ?>
                                    </h5>

                                    <div class="text-gray-600 mb-2">
                                        <i class="fas fa-building mr-1"></i>

                                        <?= e(
                                            $certification['issuing_organization']
                                                ?: 'Issuing organization not set'
                                        ) ?>
                                    </div>


                                    <div class="d-flex flex-wrap">

                                        <?php if (!empty($certification['issue_date'])): ?>

                                            <span class="badge badge-light mr-2 mb-1">
                                                <i class="fas fa-calendar-alt mr-1"></i>
                                                <?= e(
                                                    format_date(
                                                        $certification['issue_date']
                                                    )
                                                ) ?>
                                            </span>

                                        <?php endif; ?>


                                        <?php if (!empty($certification['expiration_date'])): ?>

                                            <span class="badge badge-light mr-2 mb-1">
                                                <i class="fas fa-calendar-times mr-1"></i>
                                                Expires
                                                <?= e(
                                                    format_date(
                                                        $certification['expiration_date']
                                                    )
                                                ) ?>
                                            </span>

                                        <?php endif; ?>


                                        <span
                                            class="badge <?= !empty($certification['is_public'])
                                                                ? 'badge-success'
                                                                : 'badge-secondary' ?> mr-2 mb-1">
                                            <i
                                                class="fas <?= !empty($certification['is_public'])
                                                                ? 'fa-eye'
                                                                : 'fa-eye-slash' ?> mr-1"></i>

                                            <?= !empty($certification['is_public'])
                                                ? 'Public'
                                                : 'Private' ?>
                                        </span>


                                        <?php if (!empty($certification['certificate_file'])): ?>

                                            <span class="badge badge-info mb-1">
                                                <i class="fas fa-file-pdf mr-1"></i>
                                                Certificate
                                            </span>

                                        <?php endif; ?>

                                    </div>


                                    <?php if (!empty($certification['credential_id'])): ?>

                                        <div class="small text-muted mt-2">
                                            <strong>Credential ID:</strong>
                                            <?= e($certification['credential_id']) ?>
                                        </div>

                                    <?php endif; ?>


                                    <?php if (!empty($certification['description'])): ?>

                                        <p class="text-gray-600 small mt-2 mb-0">
                                            <?= nl2br(
                                                e($certification['description'])
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
                                            data-target="#editCertification<?= (int) $certification['id'] ?>"
                                            title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </button>


                                        <!-- Credential URL -->
                                        <?php if (!empty($certification['credential_url'])): ?>

                                            <a
                                                href="<?= e($certification['credential_url']) ?>"
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                class="btn btn-sm btn-outline-info mr-1"
                                                title="View Credential">
                                                <i class="fas fa-external-link-alt"></i>
                                            </a>

                                        <?php endif; ?>


                                        <!-- Certificate PDF -->
                                        <?php if (!empty($certification['certificate_file'])): ?>

                                            <a
                                                href="<?= e(asset($certification['certificate_file'])) ?>"
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                class="btn btn-sm btn-outline-secondary mr-1"
                                                title="View Certificate">
                                                <i class="fas fa-file-pdf"></i>
                                            </a>

                                        <?php endif; ?>


                                        <!-- Delete -->
                                        <button
                                            type="button"
                                            class="btn btn-sm btn-outline-danger"
                                            data-toggle="modal"
                                            data-target="#deleteModal"
                                            data-delete-url="<?= e(
                                                                    asset(
                                                                        'user/certifications.php?delete=' .
                                                                            (int) $certification['id']
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
     ADD CERTIFICATION MODAL
========================================================= -->
<div
    class="modal fade"
    id="certificationModal"
    tabindex="-1"
    role="dialog"
    aria-labelledby="certificationModalLabel"
    aria-hidden="true">

    <div class="modal-dialog modal-lg" role="document">

        <form
            method="post"
            enctype="multipart/form-data">

            <?= csrf_field() ?>

            <input
                type="hidden"
                name="id"
                value="0">

            <div class="modal-content">

                <div class="modal-header">

                    <h5
                        class="modal-title text-primary"
                        id="certificationModalLabel">
                        Add Certification
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
                        <label for="name">
                            Certification Name
                            <span class="text-danger">*</span>
                        </label>

                        <input
                            type="text"
                            class="form-control"
                            id="name"
                            name="name"
                            placeholder="e.g. Google IT Support Professional Certificate"
                            required>
                    </div>


                    <div class="form-group">
                        <label for="issuing_organization">
                            Issuing Organization
                        </label>

                        <input
                            type="text"
                            class="form-control"
                            id="issuing_organization"
                            name="issuing_organization"
                            placeholder="e.g. Google, Microsoft, Cisco">
                    </div>


                    <div class="row">

                        <div class="col-md-6">

                            <div class="form-group">
                                <label for="issue_date">
                                    Issue Date
                                </label>

                                <input
                                    type="date"
                                    class="form-control"
                                    id="issue_date"
                                    name="issue_date">
                            </div>

                        </div>


                        <div class="col-md-6">

                            <div class="form-group">
                                <label for="expiration_date">
                                    Expiration Date
                                </label>

                                <input
                                    type="date"
                                    class="form-control"
                                    id="expiration_date"
                                    name="expiration_date">

                                <small class="form-text text-muted">
                                    Leave blank if the certification does not expire.
                                </small>
                            </div>

                        </div>

                    </div>


                    <div class="form-group">
                        <label for="credential_id">
                            Credential ID
                        </label>

                        <input
                            type="text"
                            class="form-control"
                            id="credential_id"
                            name="credential_id"
                            placeholder="e.g. ABC-123456">
                    </div>


                    <div class="form-group">
                        <label for="credential_url">
                            Credential URL
                        </label>

                        <input
                            type="url"
                            class="form-control"
                            id="credential_url"
                            name="credential_url"
                            placeholder="https://example.com/credential">

                        <small class="form-text text-muted">
                            Link to verify or view your credential online.
                        </small>
                    </div>


                    <div class="form-group">
                        <label for="description">
                            Description
                        </label>

                        <textarea
                            class="form-control"
                            id="description"
                            name="description"
                            rows="4"
                            placeholder="Add details about this certification or training..."></textarea>
                    </div>


                    <div class="form-group">

                        <label for="certificate_file">
                            Certificate File
                        </label>

                        <div class="custom-file">

                            <input
                                type="file"
                                class="custom-file-input"
                                id="certificate_file"
                                name="certificate_file"
                                accept=".pdf">

                            <label
                                class="custom-file-label"
                                for="certificate_file">
                                Choose PDF file
                            </label>

                        </div>

                        <small class="form-text text-muted">
                            Upload your certificate in PDF format.
                        </small>

                    </div>


                    <div class="form-group">

                        <div class="custom-control custom-checkbox">

                            <input
                                type="checkbox"
                                class="custom-control-input"
                                id="is_public"
                                name="is_public"
                                value="1"
                                checked>

                            <label
                                class="custom-control-label"
                                for="is_public">
                                Make this certification public
                            </label>

                        </div>

                        <small class="form-text text-muted">
                            Public certifications can be displayed on your portfolio.
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
                        Save Certification
                    </button>

                </div>

            </div>

        </form>

    </div>

</div>


<!-- =========================================================
     EDIT CERTIFICATION MODALS
========================================================= -->
<?php foreach ($certifications as $certification): ?>

    <div
        class="modal fade"
        id="editCertification<?= (int) $certification['id'] ?>"
        tabindex="-1"
        role="dialog"
        aria-hidden="true">

        <div class="modal-dialog modal-lg" role="document">

            <form
                method="post"
                enctype="multipart/form-data">

                <?= csrf_field() ?>

                <input
                    type="hidden"
                    name="id"
                    value="<?= (int) $certification['id'] ?>">

                <div class="modal-content">

                    <div class="modal-header">

                        <h5 class="modal-title text-primary">
                            Edit Certification
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
                                Certification Name
                                <span class="text-danger">*</span>
                            </label>

                            <input
                                type="text"
                                class="form-control"
                                name="name"
                                value="<?= e($certification['name']) ?>"
                                required>
                        </div>


                        <div class="form-group">
                            <label>
                                Issuing Organization
                            </label>

                            <input
                                type="text"
                                class="form-control"
                                name="issuing_organization"
                                value="<?= e(
                                            $certification['issuing_organization'] ?? ''
                                        ) ?>">
                        </div>


                        <div class="row">

                            <div class="col-md-6">

                                <div class="form-group">
                                    <label>
                                        Issue Date
                                    </label>

                                    <input
                                        type="date"
                                        class="form-control"
                                        name="issue_date"
                                        value="<?= e(
                                                    $certification['issue_date'] ?? ''
                                                ) ?>">
                                </div>

                            </div>


                            <div class="col-md-6">

                                <div class="form-group">
                                    <label>
                                        Expiration Date
                                    </label>

                                    <input
                                        type="date"
                                        class="form-control"
                                        name="expiration_date"
                                        value="<?= e(
                                                    $certification['expiration_date'] ?? ''
                                                ) ?>">

                                    <small class="form-text text-muted">
                                        Leave blank if the certification does not expire.
                                    </small>
                                </div>

                            </div>

                        </div>


                        <div class="form-group">
                            <label>
                                Credential ID
                            </label>

                            <input
                                type="text"
                                class="form-control"
                                name="credential_id"
                                value="<?= e(
                                            $certification['credential_id'] ?? ''
                                        ) ?>">
                        </div>


                        <div class="form-group">
                            <label>
                                Credential URL
                            </label>

                            <input
                                type="url"
                                class="form-control"
                                name="credential_url"
                                value="<?= e(
                                            $certification['credential_url'] ?? ''
                                        ) ?>">

                            <small class="form-text text-muted">
                                Link to verify or view your credential online.
                            </small>
                        </div>


                        <div class="form-group">
                            <label>
                                Description
                            </label>

                            <textarea
                                class="form-control"
                                name="description"
                                rows="4"
                                placeholder="Add details about this certification or training..."><?= e($certification['description'] ?? '') ?></textarea>
                        </div>


                        <?php if (!empty($certification['certificate_file'])): ?>

                            <div class="alert alert-light border">

                                <div class="d-flex align-items-center">

                                    <i class="fas fa-file-pdf text-danger fa-2x mr-3"></i>

                                    <div>

                                        <strong>
                                            Current Certificate
                                        </strong>

                                        <div class="mt-1">

                                            <a
                                                href="<?= e(
                                                            asset(
                                                                $certification['certificate_file']
                                                            )
                                                        ) ?>"
                                                target="_blank"
                                                rel="noopener noreferrer">
                                                View current certificate
                                            </a>

                                        </div>

                                    </div>

                                </div>

                            </div>

                        <?php endif; ?>


                        <div class="form-group">

                            <label>
                                Replace Certificate
                            </label>

                            <div class="custom-file">

                                <input
                                    type="file"
                                    class="custom-file-input"
                                    id="certificate_file_<?= (int) $certification['id'] ?>"
                                    name="certificate_file"
                                    accept=".pdf">

                                <label
                                    class="custom-file-label"
                                    for="certificate_file_<?= (int) $certification['id'] ?>">
                                    Choose PDF file
                                </label>

                            </div>

                            <small class="form-text text-muted">
                                Leave empty to keep the current certificate.
                            </small>

                        </div>


                        <div class="form-group">

                            <div class="custom-control custom-checkbox">

                                <input
                                    type="checkbox"
                                    class="custom-control-input"
                                    id="public_<?= (int) $certification['id'] ?>"
                                    name="is_public"
                                    value="1"
                                    <?= !empty($certification['is_public'])
                                        ? 'checked'
                                        : '' ?>>

                                <label
                                    class="custom-control-label"
                                    for="public_<?= (int) $certification['id'] ?>">
                                    Make this certification public
                                </label>

                            </div>

                            <small class="form-text text-muted">
                                Public certifications can be displayed on your portfolio.
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

            <div class="modal-header">

                <h5
                    class="modal-title"
                    id="deleteModalLabel">
                    Delete Certification
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

                    <p class="mb-0">
                        Are you sure you want to delete this certification?
                    </p>

                    <small class="text-muted">
                        This will also remove the uploaded certificate file.
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
     JAVASCRIPT
========================================================= -->
<script>
    document.addEventListener('DOMContentLoaded', function() {

        /*
        |--------------------------------------------------------------------------
        | Delete Confirmation
        |--------------------------------------------------------------------------
        */
        $('#deleteModal').on('show.bs.modal', function(event) {

            const button = $(event.relatedTarget);

            const deleteUrl = button.data('delete-url');

            $('#confirmDelete').attr('href', deleteUrl);
        });


        $('#deleteModal').on('hidden.bs.modal', function() {

            $('#confirmDelete').attr('href', '#');
        });


        /*
        |--------------------------------------------------------------------------
        | Bootstrap Custom File Input
        |--------------------------------------------------------------------------
        */
        $('.custom-file-input').on('change', function() {

            const fileName = $(this).val().split('\\').pop();

            $(this)
                .siblings('.custom-file-label')
                .addClass('selected')
                .html(fileName || 'Choose PDF file');
        });

    });
</script>

<?php require dirname(__DIR__) . '/includes/footer.php'; ?>