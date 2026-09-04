<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once __DIR__ . '/../includes/maintenance-check.php';

require_role('user');

$uid = current_user_id();


/*
|--------------------------------------------------------------------------
| POST - ADD / UPDATE PROJECT
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    verify_csrf();

    $image = null;

    try {

        /*
        |--------------------------------------------------------------------------
        | Project ID
        |--------------------------------------------------------------------------
        */

        $id = (int) ($_POST['id'] ?? 0);


        /*
        |--------------------------------------------------------------------------
        | Project Type
        |--------------------------------------------------------------------------
        */

        $projectType = trim(
            (string) ($_POST['project_type'] ?? 'General')
        );

        if (strcasecmp($projectType, 'Creative') === 0) {
            $projectType = 'Creative';
        } else {
            $projectType = 'General';
        }


        /*
        |--------------------------------------------------------------------------
        | Basic Information
        |--------------------------------------------------------------------------
        */

        $title = trim(
            (string) ($_POST['title'] ?? '')
        );

        $description = trim(
            (string) ($_POST['description'] ?? '')
        );


        /*
        |--------------------------------------------------------------------------
        | General Project Fields
        |--------------------------------------------------------------------------
        */

        $role = trim(
            (string) ($_POST['role'] ?? '')
        );

        $techStack = trim(
            (string) ($_POST['tech_stack'] ?? '')
        );


        /*
        |--------------------------------------------------------------------------
        | Creative Project Fields
        |--------------------------------------------------------------------------
        */

        $subjectMatter = trim(
            (string) ($_POST['subject_matter'] ?? '')
        );

        $medium = trim(
            (string) ($_POST['medium'] ?? '')
        );


        /*
        |--------------------------------------------------------------------------
        | Responsibilities
        |--------------------------------------------------------------------------
        */

        $responsibilities = $_POST['responsibilities'] ?? [];

        if (!is_array($responsibilities)) {
            $responsibilities = [$responsibilities];
        }

        $cleanResponsibilities = [];

        foreach ($responsibilities as $responsibility) {

            $responsibility = trim(
                (string) $responsibility
            );

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

        $responsibilitiesText = implode(
            PHP_EOL,
            $cleanResponsibilities
        );


        /*
        |--------------------------------------------------------------------------
        | Project Links
        |--------------------------------------------------------------------------
        */

        $websiteUrl = trim(
            (string) ($_POST['website_url'] ?? '')
        );

        $githubUrl = trim(
            (string) ($_POST['github_url'] ?? '')
        );


        /*
        |--------------------------------------------------------------------------
        | Project Dates
        |--------------------------------------------------------------------------
        */

        $startDate = !empty($_POST['start_date'])
            ? trim((string) $_POST['start_date'])
            : null;

        $endDate = !empty($_POST['end_date'])
            ? trim((string) $_POST['end_date'])
            : null;


        /*
        |--------------------------------------------------------------------------
        | Visibility
        |--------------------------------------------------------------------------
        */

        $isFeatured = isset($_POST['is_featured'])
            ? 1
            : 0;

        $isPublic = isset($_POST['is_public'])
            ? 1
            : 0;


        /*
        |--------------------------------------------------------------------------
        | Validation
        |--------------------------------------------------------------------------
        */

        if ($title === '') {
            throw new RuntimeException(
                'Project title is required.'
            );
        }

        if ($description === '') {
            throw new RuntimeException(
                'Project description is required.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | General Project Validation
        |--------------------------------------------------------------------------
        */

        /*
        |--------------------------------------------------------------------------
        | Creative Project Validation
        |--------------------------------------------------------------------------
        */

        if ($projectType === 'Creative') {

            if ($subjectMatter === '') {
                throw new RuntimeException(
                    'Subject matter is required.'
                );
            }

            if ($medium === '') {
                throw new RuntimeException(
                    'Medium is required.'
                );
            }
        }


        /*
        |--------------------------------------------------------------------------
        | Date Validation
        |--------------------------------------------------------------------------
        */

        if (!$startDate) {
            throw new RuntimeException(
                'Start date is required.'
            );
        }

        if (!$endDate) {
            throw new RuntimeException(
                'End date is required.'
            );
        }

        if ($endDate < $startDate) {
            throw new RuntimeException(
                'End date cannot be earlier than the start date.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Image Upload
        |--------------------------------------------------------------------------
        */

        $image = upload_file(
            'image',
            [
                'jpg',
                'jpeg',
                'png',
                'webp'
            ],
            'projects'
        );


        /*
        |--------------------------------------------------------------------------
        | UPDATE EXISTING PROJECT
        |--------------------------------------------------------------------------
        */

        if ($id > 0) {

            /*
            |--------------------------------------------------------------------------
            | Get Existing Project
            |--------------------------------------------------------------------------
            */

            $stmt = db()->prepare(
                'SELECT *
                 FROM projects
                 WHERE id = ?
                 AND user_id = ?'
            );

            $stmt->execute([
                $id,
                $uid
            ]);

            $oldProject = $stmt->fetch();

            if (!$oldProject) {
                throw new RuntimeException(
                    'Project not found.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | Update With New Image
            |--------------------------------------------------------------------------
            */

            if ($image) {

                $stmt = db()->prepare(
                    'UPDATE projects
                     SET
                        project_type = ?,
                        title = ?,
                        description = ?,
                        role = ?,
                        responsibilities = ?,
                        tech_stack = ?,
                        subject_matter = ?,
                        medium = ?,
                        image = ?,
                        website_url = ?,
                        github_url = ?,
                        start_date = ?,
                        end_date = ?,
                        is_featured = ?,
                        is_public = ?
                     WHERE id = ?
                     AND user_id = ?'
                );

                $stmt->execute([
                    $projectType,
                    $title,
                    $description,
                    $role,
                    $responsibilitiesText,
                    $techStack,
                    $subjectMatter,
                    $medium,
                    $image,
                    $websiteUrl,
                    $githubUrl,
                    $startDate,
                    $endDate,
                    $isFeatured,
                    $isPublic,
                    $id,
                    $uid
                ]);


                /*
                |--------------------------------------------------------------------------
                | Delete Old Image
                |--------------------------------------------------------------------------
                */

                if (!empty($oldProject['image'])) {
                    delete_upload(
                        $oldProject['image']
                    );
                }
            } else {

                /*
                |--------------------------------------------------------------------------
                | Update Without New Image
                |--------------------------------------------------------------------------
                */

                $stmt = db()->prepare(
                    'UPDATE projects
                     SET
                        project_type = ?,
                        title = ?,
                        description = ?,
                        role = ?,
                        responsibilities = ?,
                        tech_stack = ?,
                        subject_matter = ?,
                        medium = ?,
                        website_url = ?,
                        github_url = ?,
                        start_date = ?,
                        end_date = ?,
                        is_featured = ?,
                        is_public = ?
                     WHERE id = ?
                     AND user_id = ?'
                );

                $stmt->execute([
                    $projectType,
                    $title,
                    $description,
                    $role,
                    $responsibilitiesText,
                    $techStack,
                    $subjectMatter,
                    $medium,
                    $websiteUrl,
                    $githubUrl,
                    $startDate,
                    $endDate,
                    $isFeatured,
                    $isPublic,
                    $id,
                    $uid
                ]);
            }

            flash(
                'success',
                'Project updated successfully.'
            );
        } else {

            /*
            |--------------------------------------------------------------------------
            | ADD NEW PROJECT
            |--------------------------------------------------------------------------
            */

            $stmt = db()->prepare(
                'INSERT INTO projects (
                    user_id,
                    project_type,
                    title,
                    description,
                    role,
                    responsibilities,
                    tech_stack,
                    subject_matter,
                    medium,
                    image,
                    website_url,
                    github_url,
                    start_date,
                    end_date,
                    is_featured,
                    is_public
                )
                VALUES (
                    ?, ?, ?, ?, ?, ?, ?, ?,
                    ?, ?, ?, ?, ?, ?, ?, ?
                )'
            );

            $stmt->execute([
                $uid,
                $projectType,
                $title,
                $description,
                $role,
                $responsibilitiesText,
                $techStack,
                $subjectMatter,
                $medium,
                $image,
                $websiteUrl,
                $githubUrl,
                $startDate,
                $endDate,
                $isFeatured,
                $isPublic
            ]);

            flash(
                'success',
                'Project added successfully.'
            );
        }
    } catch (Throwable $e) {

        /*
        |--------------------------------------------------------------------------
        | Delete Uploaded Image If Saving Failed
        |--------------------------------------------------------------------------
        */

        if (!empty($image)) {
            delete_upload($image);
        }

        flash(
            'danger',
            $e->getMessage()
        );
    }

    redirect(
        'user/projects.php'
    );
}


/*
|--------------------------------------------------------------------------
| DELETE PROJECT
|--------------------------------------------------------------------------
*/

if (
    isset($_GET['delete']) &&
    (int) $_GET['delete'] > 0
) {

    $id = (int) $_GET['delete'];

    try {

        /*
        |--------------------------------------------------------------------------
        | Get Project Image <span class="text-muted font-weight-normal small">(Optional)</span>
        |--------------------------------------------------------------------------
        */

        $stmt = db()->prepare(
            'SELECT image
             FROM projects
             WHERE id = ?
             AND user_id = ?'
        );

        $stmt->execute([
            $id,
            $uid
        ]);

        $project = $stmt->fetch();


        if (!$project) {

            flash(
                'danger',
                'Project not found.'
            );
        } else {

            /*
            |--------------------------------------------------------------------------
            | Delete Image
            |--------------------------------------------------------------------------
            */

            if (!empty($project['image'])) {
                delete_upload(
                    $project['image']
                );
            }


            /*
            |--------------------------------------------------------------------------
            | Delete Project
            |--------------------------------------------------------------------------
            */

            $stmt = db()->prepare(
                'DELETE FROM projects
                 WHERE id = ?
                 AND user_id = ?'
            );

            $stmt->execute([
                $id,
                $uid
            ]);

            flash(
                'success',
                'Project deleted successfully.'
            );
        }
    } catch (Throwable $e) {

        flash(
            'danger',
            $e->getMessage()
        );
    }

    redirect(
        'user/projects.php'
    );
}


/*
|--------------------------------------------------------------------------
| GET PROJECTS
|--------------------------------------------------------------------------
*/

$stmt = db()->prepare(
    'SELECT *
     FROM projects
     WHERE user_id = ?
     ORDER BY
        COALESCE(start_date, created_at) DESC,
        id DESC'
);

$stmt->execute([
    $uid
]);

$projects = $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| PROJECT STATISTICS
|--------------------------------------------------------------------------
*/

$totalProjects = count($projects);

$publicProjects = count(
    array_filter(
        $projects,
        static function ($project) {
            return !empty($project['is_public']);
        }
    )
);

$featuredProjects = count(
    array_filter(
        $projects,
        static function ($project) {
            return !empty($project['is_featured']);
        }
    )
);

$creativeProjects = count(
    array_filter(
        $projects,
        static function ($project) {

            return strtolower(
                trim(
                    (string) (
                        $project['project_type']
                        ?? 'General'
                    )
                )
            ) === 'creative';
        }
    )
);


$pageTitle = 'Projects';


/*
|--------------------------------------------------------------------------
| HEADER
|--------------------------------------------------------------------------
*/

require dirname(__DIR__) . '/includes/header.php';


/*
|--------------------------------------------------------------------------
| PROJECT FORM RENDERER
|--------------------------------------------------------------------------
*/

$renderProjectFields = static function (
    array $formProject
): void {

    $projectId = (int) (
        $formProject['id'] ?? 0
    );

    $projectType = trim(
        (string) (
            $formProject['project_type']
            ?? 'General'
        )
    );


    /*
    |--------------------------------------------------------------------------
    | Responsibilities
    |--------------------------------------------------------------------------
    */

    $responsibilities = [];

    if (!empty($formProject['responsibilities'])) {

        $responsibilities = preg_split(
            '/\r\n|\r|\n/',
            (string) $formProject['responsibilities']
        ) ?: [];
    }

    if (!$responsibilities) {
        $responsibilities = [''];
    }

?>

    <input
        type="hidden"
        name="id"
        value="<?= $projectId ?>">


    <!-- =========================================================
         PROJECT TYPE
    ========================================================== -->

    <div class="form-group">

        <label
            for="project_type_<?= $projectId ?>"
            class="font-weight-bold">
            Project Type
        </label>

        <select
            class="form-control"
            id="project_type_<?= $projectId ?>"
            name="project_type">

            <option
                value="General"
                <?= strcasecmp(
                    $projectType,
                    'General'
                ) === 0 ? 'selected' : '' ?>>
                General Project
            </option>

            <option
                value="Creative"
                <?= strcasecmp(
                    $projectType,
                    'Creative'
                ) === 0 ? 'selected' : '' ?>>
                Creative / Artwork
            </option>

        </select>

        <small class="form-text text-muted">
            Choose the type of project you are adding.
        </small>

    </div>


    <!-- =========================================================
         TITLE
    ========================================================== -->

    <div class="form-group">

        <label
            for="title_<?= $projectId ?>"
            class="font-weight-bold">
            Project Title
        </label>

        <input
            type="text"
            class="form-control"
            id="title_<?= $projectId ?>"
            name="title"
            value="<?= e(
                        $formProject['title'] ?? ''
                    ) ?>"
            placeholder="Enter project title"
            required>

    </div>


    <!-- =========================================================
         DESCRIPTION
    ========================================================== -->

    <div class="form-group">

        <label
            for="description_<?= $projectId ?>"
            class="font-weight-bold">
            Description
        </label>

        <textarea
            class="form-control"
            id="description_<?= $projectId ?>"
            name="description"
            rows="4"
            placeholder="Describe your project..."
            required><?= e(
                            $formProject['description'] ?? ''
                        ) ?></textarea>

    </div>


    <!-- =========================================================
         GENERAL PROJECT FIELDS
    ========================================================== -->

    <div class="general-fields">

        <div class="form-group">

            <label
                for="role_<?= $projectId ?>"
                class="font-weight-bold">
                Your Role <span class="text-muted font-weight-normal small">(Optional)</span>
            </label>

            <input
                type="text"
                class="form-control"
                id="role_<?= $projectId ?>"
                name="role"
                value="<?= e(
                            $formProject['role'] ?? ''
                        ) ?>"
                placeholder="e.g. Full-Stack Developer">

        </div>


        <div class="form-group">

            <label
                for="tech_stack_<?= $projectId ?>"
                class="font-weight-bold">
                Technologies / Tech Stack
                <span class="text-muted font-weight-normal small">(Optional)</span>
            </label>

            <textarea
                class="form-control"
                id="tech_stack_<?= $projectId ?>"
                name="tech_stack"
                rows="3"
                placeholder="e.g. PHP, MySQL, JavaScript, Bootstrap"><?= e(
                                                                            $formProject['tech_stack'] ?? ''
                                                                        ) ?></textarea>

            <small class="form-text text-muted">
                Optional. List the technologies, frameworks, and tools used, such as PHP, MySQL, JavaScript, Bootstrap, or Git.
            </small>

        </div>

    </div>


    <!-- =========================================================
         CREATIVE PROJECT FIELDS
    ========================================================== -->

    <div class="creative-fields">

        <div class="form-group">

            <label
                for="subject_matter_<?= $projectId ?>"
                class="font-weight-bold">
                Subject Matter <span class="text-danger">*</span>
            </label>

            <input
                type="text"
                class="form-control"
                id="subject_matter_<?= $projectId ?>"
                name="subject_matter"
                value="<?= e(
                            $formProject['subject_matter'] ?? ''
                        ) ?>"
                placeholder="e.g. Portrait, Landscape, Digital Art">

        </div>


        <div class="form-group">

            <label
                for="medium_<?= $projectId ?>"
                class="font-weight-bold">
                Medium <span class="text-danger">*</span>
            </label>

            <input
                type="text"
                class="form-control"
                id="medium_<?= $projectId ?>"
                name="medium"
                value="<?= e(
                            $formProject['medium'] ?? ''
                        ) ?>"
                placeholder="e.g. Digital Painting, Acrylic, Photography">

        </div>

    </div>


    <!-- =========================================================
         RESPONSIBILITIES
    ========================================================== -->

    <div class="form-group">

        <label class="font-weight-bold">
            Responsibilities / Contributions <span class="text-muted font-weight-normal small">(Optional)</span>
        </label>

        <div class="responsibilities-container">

            <?php foreach ($responsibilities as $responsibility): ?>

                <div class="responsibility-row mb-2">

                    <div class="input-group">

                        <input
                            type="text"
                            class="form-control responsibility-input"
                            name="responsibilities[]"
                            maxlength="255"
                            value="<?= e(
                                        trim(
                                            (string) $responsibility
                                        )
                                    ) ?>"
                            placeholder="">

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
            Add each responsibility or contribution separately.
        </small>

    </div>


    <!-- =========================================================
         PROJECT IMAGE
    ========================================================== -->

    <div class="form-group">

        <label
            for="image_<?= $projectId ?>"
            class="font-weight-bold">
            Project Image
        </label>


        <?php if (!empty($formProject['image'])): ?>

            <?php

            $projectImage = filter_var(
                $formProject['image'],
                FILTER_VALIDATE_URL
            )
                ? $formProject['image']
                : asset($formProject['image']);

            ?>

            <div class="mb-2">

                <img
                    src="<?= e($projectImage) ?>"
                    alt="Project Image"
                    class="img-thumbnail"
                    style="
                        max-width: 180px;
                        max-height: 120px;
                        object-fit: cover;
                    ">

            </div>

        <?php endif; ?>


        <div class="custom-file">

            <input
                type="file"
                class="custom-file-input"
                id="image_<?= $projectId ?>"
                name="image"
                accept=".jpg,.jpeg,.png,.webp">

            <label
                class="custom-file-label"
                for="image_<?= $projectId ?>">
                Choose project image
            </label>

        </div>

        <small class="form-text text-muted">
            Accepted formats: JPG, JPEG, PNG, and WEBP.
        </small>

    </div>


    <!-- =========================================================
         PROJECT WEBSITE
    ========================================================== -->

    <div class="form-group">

        <label
            for="website_url_<?= $projectId ?>"
            class="font-weight-bold">
            <i class="fas fa-globe mr-1"></i>
            Project Website <span class="text-muted font-weight-normal small">(Optional)</span>
        </label>

        <input
            type="url"
            class="form-control"
            id="website_url_<?= $projectId ?>"
            name="website_url"
            value="<?= e(
                        $formProject['website_url'] ?? ''
                    ) ?>"
            placeholder="https://example.com">

    </div>


    <!-- =========================================================
         GITHUB
    ========================================================== -->

    <div class="form-group">

        <label
            for="github_url_<?= $projectId ?>"
            class="font-weight-bold">
            <i class="fab fa-github mr-1"></i>
            GitHub Repository <span class="text-muted font-weight-normal small">(Optional)</span>
        </label>

        <input
            type="url"
            class="form-control"
            id="github_url_<?= $projectId ?>"
            name="github_url"
            value="<?= e(
                        $formProject['github_url'] ?? ''
                    ) ?>"
            placeholder="https://github.com/username/project">

    </div>


    <!-- =========================================================
         PROJECT DATES
    ========================================================== -->

    <div class="row">

        <div class="col-md-6">

            <div class="form-group">

                <label
                    for="start_date_<?= $projectId ?>"
                    class="font-weight-bold">
                    Start Date <span class="text-danger">*</span>
                </label>

                <input
                    type="date"
                    class="form-control"
                    id="start_date_<?= $projectId ?>"
                    name="start_date"
                    value="<?= e(
                                $formProject['start_date'] ?? ''
                            ) ?>"
                    required>

            </div>

        </div>


        <div class="col-md-6">

            <div class="form-group">

                <label
                    for="end_date_<?= $projectId ?>"
                    class="font-weight-bold">
                    End Date <span class="text-danger">*</span>
                </label>

                <input
                    type="date"
                    class="form-control"
                    id="end_date_<?= $projectId ?>"
                    name="end_date"
                    value="<?= e(
                                $formProject['end_date'] ?? ''
                            ) ?>"
                    required>

            </div>

        </div>

    </div>


    <!-- =========================================================
         PORTFOLIO SETTINGS
    ========================================================== -->

    <div class="form-group mb-0">

        <label class="font-weight-bold d-block">
            Portfolio Settings
        </label>


        <div class="custom-control custom-switch mb-2">

            <input
                type="checkbox"
                class="custom-control-input"
                id="is_featured_<?= $projectId ?>"
                name="is_featured"
                <?= !empty($formProject['is_featured'])
                    ? 'checked'
                    : ''
                ?>>

            <label
                class="custom-control-label"
                for="is_featured_<?= $projectId ?>">
                Feature this project
            </label>

        </div>


        <div class="custom-control custom-switch">

            <input
                type="checkbox"
                class="custom-control-input"
                id="is_public_<?= $projectId ?>"
                name="is_public"
                <?= !empty($formProject['is_public'])
                    ? 'checked'
                    : ''
                ?>>

            <label
                class="custom-control-label"
                for="is_public_<?= $projectId ?>">
                Make this project public
            </label>

        </div>

    </div>

<?php
};
?>

<style>
    .project-modal .modal-content {
        border: 0;
        border-radius: .5rem;
        box-shadow: 0 .5rem 1.5rem rgba(58, 59, 69, .2);
        overflow: hidden
    }

    .project-modal .modal-header,
    .project-modal .modal-footer {
        background: #f8f9fc;
        border-color: #e3e6f0
    }

    .project-modal .modal-header {
        padding: 1rem 1.25rem
    }

    .project-modal .modal-body {
        padding: 1.25rem;
        background: #fff
    }

    .project-modal .modal-footer {
        padding: .85rem 1.25rem
    }

    .project-modal .modal-title {
        font-size: 1rem
    }

    .project-modal .form-group {
        margin-bottom: 1rem
    }

    .project-modal label {
        margin-bottom: .4rem;
        color: #3d4051
    }

    .project-modal .form-control {
        border-color: #d1d3e2;
        border-radius: .35rem
    }

    .project-modal .form-control:focus {
        border-color: #6f42c1;
        box-shadow: 0 0 0 .2rem rgba(111, 66, 193, .1)
    }

    .project-modal .modal-body::-webkit-scrollbar {
        width: 7px
    }

    .project-modal .modal-body::-webkit-scrollbar-thumb {
        background: #d1d3e2;
        border-radius: 10px
    }

    @media(max-width:575.98px) {
        .project-modal .modal-body {
            padding: 1rem
        }

        .project-modal .modal-footer {
            padding: .75rem 1rem
        }

        .project-modal .modal-footer .btn {
            width: 100%;
            margin: .15rem 0
        }
    }
</style>

<?php

/*
|--------------------------------------------------------------------------
| ADD PROJECT MODAL
|--------------------------------------------------------------------------
*/

?>

<div
    class="modal fade project-modal"
    id="addProjectModal"
    tabindex="-1"
    role="dialog"
    aria-labelledby="addProjectModalLabel"
    aria-hidden="true">

    <div
        class="modal-dialog modal-lg modal-dialog-centered"
        role="document">

        <div class="modal-content">


            <!-- Modal Header -->

            <div class="modal-header">

                <div>

                    <h5
                        class="modal-title font-weight-bold text-primary"
                        id="addProjectModalLabel">
                        <!-- <i class="fas fa-folder-plus mr-2"></i> -->
                        Add Project
                    </h5>

                    <small class="text-muted">
                        Add a new project to your portfolio. Required fields are marked with *.
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


            <!-- Form -->

            <form
                method="post"
                enctype="multipart/form-data">

                <?= csrf_field() ?>


                <div
                    class="modal-body"
                    style="
                        max-height: 70vh;
                        overflow-y: auto;
                    ">

                    <?php

                    $renderProjectFields([]);

                    ?>

                    <div class="small text-muted border-top pt-3 mt-3">
                        <span class="text-danger font-weight-bold">*</span> Required fields
                        <span class="mx-2">•</span>
                        Fields marked <span class="font-weight-bold">Optional</span> may be left blank.
                    </div>

                </div>


                <div class="modal-footer">

                    <button
                        type="button"
                        class="btn btn-secondary"
                        data-dismiss="modal">
                        <i class="fas fa-times mr-1"></i>
                        Cancel
                    </button>


                    <button
                        type="submit"
                        class="btn btn-primary">
                        <i class="fas fa-save mr-1"></i>
                        Save Project
                    </button>

                </div>

            </form>

        </div>

    </div>

</div>


<?php

/*
|--------------------------------------------------------------------------
| EDIT PROJECT MODALS
|--------------------------------------------------------------------------
*/

foreach ($projects as $project):

    $projectId = (int) $project['id'];

?>

    <div
        class="modal fade project-modal"
        id="editProject<?= $projectId ?>"
        tabindex="-1"
        role="dialog"
        aria-labelledby="editProjectLabel<?= $projectId ?>"
        aria-hidden="true">

        <div
            class="modal-dialog modal-lg modal-dialog-centered"
            role="document">

            <div class="modal-content">


                <!-- Modal Header -->

                <div class="modal-header">

                    <div>

                        <h5
                            class="modal-title font-weight-bold text-primary"
                            id="editProjectLabel<?= $projectId ?>">
                            <!-- <i class="fas fa-edit mr-2"></i> -->
                            Edit Project
                        </h5>

                        <small class="text-muted">
                            Update your project information. Required fields are marked with *.
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


                <!-- Form -->

                <form
                    method="post"
                    enctype="multipart/form-data">

                    <?= csrf_field() ?>


                    <div
                        class="modal-body"
                        style="
                            max-height: 70vh;
                            overflow-y: auto;
                        ">

                        <?php

                        $renderProjectFields(
                            $project
                        );

                        ?>

                        <div class="small text-muted border-top pt-3 mt-3">
                            <span class="text-danger font-weight-bold">*</span> Required fields
                            <span class="mx-2">•</span>
                            Fields marked <span class="font-weight-bold">Optional</span> may be left blank.
                        </div>

                    </div>


                    <div class="modal-footer">

                        <button
                            type="button"
                            class="btn btn-secondary"
                            data-dismiss="modal">
                            <i class="fas fa-times mr-1"></i>
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

<?php endforeach; ?>


<?php

/*
|--------------------------------------------------------------------------
| DELETE PROJECT MODAL
|--------------------------------------------------------------------------
*/

?>

<div
    class="modal fade project-modal"
    id="deleteProjectModal"
    tabindex="-1"
    role="dialog"
    aria-labelledby="deleteProjectModalLabel"
    aria-hidden="true">

    <div
        class="modal-dialog modal-dialog-centered"
        role="document">

        <div class="modal-content">


            <!-- Modal Header -->

            <div class="modal-header">

                <h5
                    class="modal-title font-weight-bold text-danger"
                    id="deleteProjectModalLabel">
                    <i class="fas fa-trash mr-2"></i>
                    Delete Project
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

            <div class="modal-body text-center">

                <div class="mb-3">

                    <i
                        class="fas fa-exclamation-triangle fa-3x text-warning"></i>

                </div>


                <h6 class="font-weight-bold text-gray-800">
                    Delete this project?
                </h6>


                <p class="text-muted mb-0">

                    This project and its uploaded image
                    will be permanently deleted.

                </p>

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
                    class="btn btn-danger"
                    id="confirmDeleteProject">
                    <i class="fas fa-trash mr-1"></i>
                    Delete Project
                </a>

            </div>

        </div>

    </div>

</div>


<!-- =============================================================
     PROJECT PAGE
============================================================== -->

<div class="container-fluid">


    <!-- =========================================================
         PAGE HEADER
    ========================================================== -->

    <div
        class="d-sm-flex align-items-center justify-content-between mb-4">

        <div>

            <h1 class="h3 mb-1 text-gray-800">
                Projects
            </h1>

            <p class="mb-0 text-gray-600">
                Manage the projects and creative work
                you want to showcase.
            </p>

        </div>

    </div>

    <!-- =========================================================
         PROJECT LIST
    ========================================================== -->

    <div class="card shadow mb-4">


        <!-- Card Header -->

        <div
            class="card-header py-3 d-flex align-items-center justify-content-between">

            <div>

                <h6 class="m-0 font-weight-bold text-primary">
                    Your Projects
                </h6>

                <small class="text-muted">
                    Add and manage the projects displayed
                    on your portfolio.
                </small>

            </div>


            <button
                type="button"
                class="btn btn-primary btn-sm"
                data-toggle="modal"
                data-target="#addProjectModal">

                <i class="fas fa-plus mr-1"></i>

                Add Project

            </button>

        </div>


        <!-- Card Body -->

        <div class="card-body">


            <?php if (!$projects): ?>


                <!-- Empty State -->

                <div class="text-center py-5">

                    <i
                        class="fas fa-folder-open fa-3x text-gray-300 mb-3"></i>


                    <h5 class="font-weight-bold text-gray-800">
                        No projects yet
                    </h5>


                    <p class="text-gray-500 mb-3">
                        Add your first project to start
                        building your portfolio.
                    </p>


                    <button
                        type="button"
                        class="btn btn-primary"
                        data-toggle="modal"
                        data-target="#addProjectModal">

                        <i class="fas fa-plus mr-1"></i>

                        Add Project

                    </button>

                </div>


            <?php else: ?>


                <div class="list-group">


                    <?php foreach ($projects as $project): ?>

                        <?php

                        $projectId = (int) $project['id'];

                        $projectType = trim(
                            (string) (
                                $project['project_type']
                                ?? 'General'
                            )
                        );

                        $isCreative = strcasecmp(
                            $projectType,
                            'Creative'
                        ) === 0;

                        ?>


                        <div class="list-group-item">

                            <div
                                class="row align-items-center">


                                <!-- Project Icon -->

                                <div class="col-auto">

                                    <div
                                        class="icon-circle bg-<?= $isCreative
                                                                    ? 'info'
                                                                    : 'primary'
                                                                ?>">

                                        <i
                                            class="fas <?= $isCreative
                                                            ? 'fa-palette'
                                                            : 'fa-folder-open'
                                                        ?> text-white"></i>

                                    </div>

                                </div>


                                <!-- Project Information -->

                                <div class="col">

                                    <div
                                        class="font-weight-bold text-gray-800">

                                        <?= e(
                                            $project['title']
                                                ?? 'Untitled Project'
                                        ) ?>

                                    </div>


                                    <div
                                        class="small text-muted mb-2">

                                        <?= $isCreative
                                            ? 'Creative / Artwork'
                                            : 'General Project'
                                        ?>

                                    </div>


                                    <!-- Badges -->

                                    <div>


                                        <?php if (!empty($project['is_public'])): ?>

                                            <span
                                                class="badge badge-success mr-1">

                                                <i
                                                    class="fas fa-eye mr-1"></i>

                                                Public

                                            </span>

                                        <?php else: ?>

                                            <span
                                                class="badge badge-secondary mr-1">

                                                <i
                                                    class="fas fa-eye-slash mr-1"></i>

                                                Private

                                            </span>

                                        <?php endif; ?>


                                        <?php if (!empty($project['is_featured'])): ?>

                                            <span
                                                class="badge badge-warning mr-1">

                                                <i
                                                    class="fas fa-star mr-1"></i>

                                                Featured

                                            </span>

                                        <?php endif; ?>


                                        <?php if ($isCreative): ?>

                                            <span
                                                class="badge badge-info">

                                                <i
                                                    class="fas fa-palette mr-1"></i>

                                                Creative

                                            </span>

                                        <?php endif; ?>

                                    </div>

                                </div>


                                <!-- Actions -->

                                <div class="col-auto">

                                    <button
                                        type="button"
                                        class="btn btn-outline-primary btn-sm"
                                        data-toggle="modal"
                                        data-target="#editProject<?= $projectId ?>"
                                        title="Edit Project">

                                        <i class="fas fa-edit"></i>

                                    </button>


                                    <button
                                        type="button"
                                        class="btn btn-outline-danger btn-sm"
                                        data-toggle="modal"
                                        data-target="#deleteProjectModal"
                                        data-delete-url="<?= e(
                                                                asset(
                                                                    'user/projects.php?delete='
                                                                        . $projectId
                                                                )
                                                            ) ?>"
                                        title="Delete Project">

                                        <i class="fas fa-trash"></i>

                                    </button>

                                </div>

                            </div>

                        </div>

                    <?php endforeach; ?>

                </div>


            <?php endif; ?>


        </div>

    </div>

</div>


<!-- =============================================================
     PROJECT JAVASCRIPT
============================================================== -->

<script>
    document.addEventListener(
        'DOMContentLoaded',
        function() {


            /*
            |--------------------------------------------------------------------------
            | PROJECT TYPE SWITCHING
            |--------------------------------------------------------------------------
            */

            document
                .querySelectorAll('form')
                .forEach(function(form) {

                    const typeSelect = form.querySelector(
                        'select[name="project_type"]'
                    );

                    if (!typeSelect) {
                        return;
                    }


                    const generalFields =
                        form.querySelectorAll(
                            '.general-fields'
                        );


                    const creativeFields =
                        form.querySelectorAll(
                            '.creative-fields'
                        );


                    function updateProjectType() {

                        const type = String(
                                typeSelect.value || ''
                            )
                            .trim()
                            .toLowerCase();


                        const isGeneral =
                            type === 'general';

                        const isCreative =
                            type === 'creative';


                        /*
                        |--------------------------------------------------------------------------
                        | General Fields
                        |--------------------------------------------------------------------------
                        */

                        generalFields.forEach(
                            function(section) {

                                section.style.display =
                                    isGeneral ?
                                    '' :
                                    'none';

                                section
                                    .querySelectorAll(
                                        'input, textarea, select'
                                    )
                                    .forEach(
                                        function(field) {

                                            if (
                                                field.dataset
                                                .originalRequired ===
                                                undefined
                                            ) {

                                                field.dataset
                                                    .originalRequired =
                                                    field.required ?
                                                    '1' :
                                                    '0';
                                            }


                                            field.required =
                                                isGeneral ?
                                                field.dataset
                                                .originalRequired ===
                                                '1' :
                                                false;

                                        }
                                    );

                            }
                        );


                        /*
                        |--------------------------------------------------------------------------
                        | Creative Fields
                        |--------------------------------------------------------------------------
                        */

                        creativeFields.forEach(
                            function(section) {

                                section.style.display =
                                    isCreative ?
                                    '' :
                                    'none';

                                section
                                    .querySelectorAll(
                                        'input, textarea, select'
                                    )
                                    .forEach(
                                        function(field) {

                                            if (
                                                field.dataset
                                                .originalRequired ===
                                                undefined
                                            ) {

                                                field.dataset
                                                    .originalRequired =
                                                    field.required ?
                                                    '1' :
                                                    '0';
                                            }


                                            field.required =
                                                isCreative ?
                                                field.dataset
                                                .originalRequired ===
                                                '1' :
                                                false;

                                        }
                                    );

                            }
                        );

                    }


                    typeSelect.addEventListener(
                        'change',
                        updateProjectType
                    );


                    updateProjectType();

                });


            /*
            |--------------------------------------------------------------------------
            | RESPONSIBILITIES
            |--------------------------------------------------------------------------
            */

            document
                .querySelectorAll('form')
                .forEach(function(form) {

                    const container =
                        form.querySelector(
                            '.responsibilities-container'
                        );


                    const addButton =
                        form.querySelector(
                            '.add-responsibility'
                        );


                    if (
                        !container ||
                        !addButton
                    ) {
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


                            const row =
                                document.createElement(
                                    'div'
                                );


                            row.className =
                                'responsibility-row mb-2';


                            row.innerHTML = `
                            <div class="input-group">

                                <input
                                    type="text"
                                    class="form-control responsibility-input"
                                    name="responsibilities[]"
                                    maxlength="255"
                                    placeholder=""
                                >

                                <div class="input-group-append">

                                    <button
                                        type="button"
                                        class="btn btn-outline-danger remove-responsibility"
                                        title="Remove responsibility"
                                    >
                                        <i class="fas fa-times"></i>
                                    </button>

                                </div>

                            </div>
                        `;


                            container.appendChild(
                                row
                            );


                            const input =
                                row.querySelector(
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

                            const button =
                                event.target.closest(
                                    '.remove-responsibility'
                                );


                            if (!button) {
                                return;
                            }


                            event.preventDefault();


                            const row =
                                button.closest(
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

                            const input =
                                event.target.closest(
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
            | FILE INPUT LABEL
            |--------------------------------------------------------------------------
            */

            document
                .querySelectorAll('.custom-file-input')
                .forEach(function(input) {

                    input.addEventListener(
                        'change',
                        function() {

                            const fileName =
                                this.files.length > 0 ?
                                this.files[0].name :
                                'Choose project image';


                            const label =
                                this.nextElementSibling;


                            if (label) {
                                label.textContent =
                                    fileName;
                            }

                        }
                    );

                });


            /*
            |--------------------------------------------------------------------------
            | DELETE PROJECT
            |--------------------------------------------------------------------------
            */

            const deleteModal =
                document.getElementById(
                    'deleteProjectModal'
                );


            const confirmDelete =
                document.getElementById(
                    'confirmDeleteProject'
                );


            if (
                deleteModal &&
                confirmDelete
            ) {

                document.addEventListener(
                    'click',
                    function(event) {

                        const button =
                            event.target.closest(
                                '[data-delete-url]'
                            );


                        if (!button) {
                            return;
                        }


                        const deleteUrl =
                            button.getAttribute(
                                'data-delete-url'
                            );


                        if (!deleteUrl) {
                            return;
                        }


                        confirmDelete.setAttribute(
                            'href',
                            deleteUrl
                        );

                    }
                );


                deleteModal.addEventListener(
                    'hidden.bs.modal',
                    function() {

                        confirmDelete.setAttribute(
                            'href',
                            '#'
                        );

                    }
                );

            }


        }
    );
</script>


<?php

/*
|--------------------------------------------------------------------------
| FOOTER
|--------------------------------------------------------------------------
*/

require dirname(__DIR__) . '/includes/footer.php';

?>