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
| Delete Skill
|--------------------------------------------------------------------------
*/
if (isset($_GET['delete'])) {

    $deleteId = (int) $_GET['delete'];

    try {

        $stmt = $pdo->prepare(
            'DELETE FROM skills
             WHERE id = ?
             AND user_id = ?'
        );

        $stmt->execute([
            $deleteId,
            $uid
        ]);

        flash(
            'success',
            'Skill deleted successfully.'
        );
    } catch (Throwable $e) {

        flash(
            'danger',
            'Unable to delete skill.'
        );
    }

    redirect('user/skills.php');
}


/*
|--------------------------------------------------------------------------
| Add / Update Skill
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    try {

        verify_csrf();

        $id = (int) ($_POST['id'] ?? 0);

        $skillName = trim(
            (string) ($_POST['skill_name'] ?? '')
        );

        $category = trim(
            (string) ($_POST['category'] ?? '')
        );

        $isPublic = isset($_POST['is_public'])
            ? 1
            : 0;


        /*
        |--------------------------------------------------------------------------
        | Validation
        |--------------------------------------------------------------------------
        */

        if ($skillName === '') {

            throw new RuntimeException(
                'Skill name is required.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Update Existing Skill
        |--------------------------------------------------------------------------
        */
        if ($id > 0) {

            $stmt = $pdo->prepare(
                'UPDATE skills
                 SET
                    skill_name = ?,
                    category = ?,
                    is_public = ?
                 WHERE id = ?
                 AND user_id = ?'
            );

            $stmt->execute([
                $skillName,
                $category !== '' ? $category : null,
                $isPublic,
                $id,
                $uid
            ]);

            flash(
                'success',
                'Skill updated successfully.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Add New Skill
        |--------------------------------------------------------------------------
        */ else {

            $stmt = $pdo->prepare(
                'INSERT INTO skills (
                    user_id,
                    skill_name,
                    category,
                    is_public
                )
                VALUES (?, ?, ?, ?)'
            );

            $stmt->execute([
                $uid,
                $skillName,
                $category !== '' ? $category : null,
                $isPublic
            ]);

            flash(
                'success',
                'Skill added successfully.'
            );
        }
    } catch (Throwable $e) {

        flash(
            'danger',
            $e->getMessage()
        );
    }

    redirect('user/skills.php');
}


/*
|--------------------------------------------------------------------------
| Get Skills
|--------------------------------------------------------------------------
*/
$stmt = $pdo->prepare(
    'SELECT *
     FROM skills
     WHERE user_id = ?
     ORDER BY
        category ASC,
        skill_name ASC'
);

$stmt->execute([$uid]);

$skills = $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| Page
|--------------------------------------------------------------------------
*/
$pageTitle = 'Skills';

require dirname(__DIR__) . '/includes/header.php';
?>


<div class="container-fluid">


    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">

        <div>

            <h1 class="h3 mb-1 text-gray-800">
                Skills
            </h1>

            <p class="mb-0 text-muted">
                Manage your technical, professional, and creative skills.
            </p>

        </div>

    </div>


    <!-- Skills -->
    <div class="card shadow mb-4">

        <div class="card-header py-3">

            <div
                class="d-flex flex-column flex-md-row align-items-md-center justify-content-between">

                <div>

                    <h6 class="m-0 font-weight-bold text-primary">
                        Skills
                    </h6>

                    <small class="text-muted">
                        Your skills and areas of expertise
                    </small>

                </div>


                <button
                    type="button"
                    class="btn btn-primary btn-sm mt-3 mt-md-0"
                    data-toggle="modal"
                    data-target="#skillModal">

                    <i class="fas fa-plus mr-1"></i>

                    Add Skill

                </button>

            </div>

        </div>


        <div class="card-body">

            <?php if (!$skills): ?>


                <!-- Empty State -->
                <div class="text-center py-5">

                    <div class="mb-3">

                        <i
                            class="fas fa-lightbulb fa-3x text-gray-300"></i>

                    </div>


                    <h5 class="text-gray-800">
                        No skills added
                    </h5>


                    <p class="text-muted mb-4">
                        Add your skills and areas of expertise to your profile.
                    </p>


                    <button
                        type="button"
                        class="btn btn-primary"
                        data-toggle="modal"
                        data-target="#skillModal">

                        <i class="fas fa-plus mr-1"></i>

                        Add Skill

                    </button>

                </div>


            <?php else: ?>


                <div class="list-group">


                    <?php foreach ($skills as $item): ?>


                        <div class="list-group-item py-4">

                            <div class="row align-items-center">


                                <!-- Icon -->
                                <div class="col-auto">

                                    <div class="icon-circle bg-primary">

                                        <i
                                            class="fas fa-lightbulb text-white"></i>

                                    </div>

                                </div>


                                <!-- Skill Details -->
                                <div class="col">


                                    <!-- Skill Name -->
                                    <div class="font-weight-bold text-gray-800">

                                        <?= e($item['skill_name']) ?>

                                    </div>


                                    <!-- Category -->
                                    <?php if (!empty($item['category'])): ?>

                                        <div class="text-primary font-weight-bold mt-1">

                                            <i
                                                class="fas fa-layer-group mr-1"></i>

                                            <?= e($item['category']) ?>

                                        </div>

                                    <?php endif; ?>


                                    <!-- Status Badges -->
                                    <div class="mt-2">

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


                                </div>


                                <!-- Actions -->
                                <div class="col-auto">

                                    <div class="btn-group">


                                        <!-- Edit -->
                                        <button
                                            type="button"
                                            class="btn btn-outline-primary mr-2 btn-sm"
                                            data-toggle="modal"
                                            data-target="#editSkill<?= (int) $item['id'] ?>"
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
                                                                        'user/skills.php?delete=' .
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
     ADD SKILL MODAL
========================================================= -->

<div
    class="modal fade"
    id="skillModal"
    tabindex="-1"
    role="dialog"
    aria-labelledby="skillModalLabel"
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
                        id="skillModalLabel">

                        <!-- <i
                            class="fas fa-lightbulb mr-2 text-primary"></i> -->

                        Add Skill

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


                    <!-- Skill Name -->
                    <div class="form-group">

                        <label for="add_skill_name">

                            Skill Name

                            <span class="text-danger">
                                *
                            </span>

                        </label>


                        <input
                            type="text"
                            class="form-control"
                            id="add_skill_name"
                            name="skill_name"
                            placeholder=""
                            required>

                    </div>


                    <!-- Category -->
                    <div class="form-group">

                        <label for="add_category">
                            Category
                        </label>


                        <input
                            type="text"
                            class="form-control"
                            id="add_category"
                            name="category"
                            placeholder="">


                        <small class="form-text text-muted">
                            Optional. Use a category to organize your skills.
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
                                Make this skill visible on my public portfolio
                            </label>

                        </div>


                        <small class="form-text text-muted">
                            Private skills will only be visible to you.
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

                        Save Skill

                    </button>

                </div>


                <?= csrf_field() ?>


            </div>

        </form>

    </div>

</div>


<!-- =========================================================
     EDIT SKILL MODALS
========================================================= -->

<?php foreach ($skills as $item): ?>

    <div
        class="modal fade"
        id="editSkill<?= (int) $item['id'] ?>"
        tabindex="-1"
        role="dialog"
        aria-labelledby="editSkillLabel<?= (int) $item['id'] ?>"
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
                            id="editSkillLabel<?= (int) $item['id'] ?>">

                            <!-- <i
                                class="fas fa-edit mr-2 text-primary"></i> -->

                            Edit Skill

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


                        <!-- Skill Name -->
                        <div class="form-group">

                            <label
                                for="skill_name_<?= (int) $item['id'] ?>">

                                Skill Name

                                <span class="text-danger">
                                    *
                                </span>

                            </label>


                            <input
                                type="text"
                                class="form-control"
                                id="skill_name_<?= (int) $item['id'] ?>"
                                name="skill_name"
                                value="<?= e($item['skill_name']) ?>"
                                required>

                        </div>


                        <!-- Category -->
                        <div class="form-group">

                            <label
                                for="category_<?= (int) $item['id'] ?>">
                                Category
                            </label>


                            <input
                                type="text"
                                class="form-control"
                                id="category_<?= (int) $item['id'] ?>"
                                name="category"
                                value="<?= e($item['category'] ?? '') ?>"
                                placeholder="">


                            <small class="form-text text-muted">
                                Optional. Use a category to organize your skills.
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
                                    Make this skill visible on my public portfolio
                                </label>

                            </div>


                            <small class="form-text text-muted">
                                Private skills will only be visible to you.
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

                    Delete Skill

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
                    Are you sure you want to delete this skill?
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
    document.addEventListener(
        'DOMContentLoaded',
        function() {


            /*
            |--------------------------------------------------------------------------
            | Delete Confirmation
            |--------------------------------------------------------------------------
            */

            const confirmDelete =
                document.getElementById('confirmDelete');


            if (confirmDelete) {

                $('[data-delete-url]').on(
                    'click',
                    function() {

                        const deleteUrl =
                            this.getAttribute(
                                'data-delete-url'
                            );


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

        }
    );
</script>


<?php
require dirname(__DIR__) . '/includes/footer.php';
?>