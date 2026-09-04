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
| Handle Form Submission
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    try {

        verify_csrf();

        $action = $_POST['action'] ?? '';


        /*
        |--------------------------------------------------------------------------
        | Publishing
        |--------------------------------------------------------------------------
        |
        | This form ONLY updates portfolio_public.
        |
        */

        if ($action === 'save_publishing') {

            $portfolioPublic =
                isset($_POST['portfolio_public']) ? 1 : 0;

            $stmt = $pdo->prepare(
                'UPDATE profiles
                 SET portfolio_public = ?
                 WHERE user_id = ?'
            );

            $stmt->execute([
                $portfolioPublic,
                $uid
            ]);

            flash(
                'success',
                'Portfolio publishing settings saved successfully.'
            );

            redirect('user/visibility.php');
        }


        /*
        |--------------------------------------------------------------------------
        | Portfolio Sections
        |--------------------------------------------------------------------------
        |
        | This form ONLY updates the show_* fields.
        |
        */

        if ($action === 'save_sections') {

            $showAbout =
                isset($_POST['show_about']) ? 1 : 0;

            $showProjects =
                isset($_POST['show_projects']) ? 1 : 0;

            $showExperience =
                isset($_POST['show_experience']) ? 1 : 0;

            $showEducation =
                isset($_POST['show_education']) ? 1 : 0;

            $showSkills =
                isset($_POST['show_skills']) ? 1 : 0;

            $showCertifications =
                isset($_POST['show_certifications']) ? 1 : 0;

            $showReferences =
                isset($_POST['show_references']) ? 1 : 0;

            $showSocials =
                isset($_POST['show_socials']) ? 1 : 0;

            $showProfessionalTitle =
                isset($_POST['show_professional_title'])
                ? 1
                : 0;


            $stmt = $pdo->prepare(
                'UPDATE profiles
                 SET
                    show_about = ?,
                    show_projects = ?,
                    show_experience = ?,
                    show_education = ?,
                    show_skills = ?,
                    show_certifications = ?,
                    show_references = ?,
                    show_socials = ?,
                    show_professional_title = ?
                 WHERE user_id = ?'
            );

            $stmt->execute([
                $showAbout,
                $showProjects,
                $showExperience,
                $showEducation,
                $showSkills,
                $showCertifications,
                $showReferences,
                $showSocials,
                $showProfessionalTitle,
                $uid
            ]);

            flash(
                'success',
                'Portfolio section visibility saved successfully.'
            );

            redirect('user/visibility.php');
        }


        throw new RuntimeException(
            'Invalid visibility action.'
        );
    } catch (Throwable $e) {

        flash(
            'danger',
            $e->getMessage()
        );

        redirect('user/visibility.php');
    }
}


/*
|--------------------------------------------------------------------------
| Load Profile
|--------------------------------------------------------------------------
*/

$profile = get_profile($uid);


/*
|--------------------------------------------------------------------------
| Load User
|--------------------------------------------------------------------------
*/

$user = get_user($uid);


/*
|--------------------------------------------------------------------------
| Public Portfolio URL
|--------------------------------------------------------------------------
*/

$username = $user['username'] ?? '';

$portfolioUrl = asset(
    'portfolio.php?username=' . urlencode($username)
);


/*
|--------------------------------------------------------------------------
| Portfolio Sections
|--------------------------------------------------------------------------
*/

$sections = [

    [
        'show_about',
        'About Me',
        'Your personal introduction and profile summary.',
        'fa-user'
    ],

    [
        'show_projects',
        'Projects',
        'Showcase your projects and creative work.',
        'fa-folder-open'
    ],

    [
        'show_experience',
        'Work Experience',
        'Display your employment and professional experience.',
        'fa-briefcase'
    ],

    [
        'show_education',
        'Education',
        'Display your schools, degrees, and academic background.',
        'fa-graduation-cap'
    ],

    [
        'show_skills',
        'Skills',
        'Display your technical and professional skills.',
        'fa-bolt'
    ],

    [
        'show_certifications',
        'Certifications',
        'Show your professional certifications and credentials.',
        'fa-certificate'
    ],

    [
        'show_references',
        'References',
        'Display your professional references on your portfolio.',
        'fa-user-tie'
    ],

    [
        'show_socials',
        'Social Links',
        'Display your professional social media links.',
        'fa-share-alt'
    ],

    [
        'show_professional_title',
        'Professional Title',
        'Display your professional title on your portfolio.',
        'fa-id-badge'
    ]

];


/*
|--------------------------------------------------------------------------
| Count Visible Sections
|--------------------------------------------------------------------------
*/

$visibleSections = 0;

foreach ($sections as $section) {

    if (!empty($profile[$section[0]])) {
        $visibleSections++;
    }
}

$totalSections = count($sections);

$portfolioIsPublic =
    !empty($profile['portfolio_public']);


/*
|--------------------------------------------------------------------------
| Page Title
|--------------------------------------------------------------------------
*/

$pageTitle = 'Portfolio Visibility';

require dirname(__DIR__) . '/includes/header.php';

?>


<style>
    /*
    |--------------------------------------------------------------------------
    | Visibility Page
    |--------------------------------------------------------------------------
    */

    .visibility-icon-circle {

        width: 45px;
        height: 45px;

        display: inline-flex;

        align-items: center;
        justify-content: center;

        border-radius: 50%;

    }


    .visibility-section {

        transition:
            background-color 0.15s ease;

    }


    .visibility-section:hover {

        background-color: #f8f9fc;

    }


    .visibility-section .custom-switch {

        transform: scale(1.05);

    }


    .visibility-status {

        font-size: 0.7rem;

    }


    .visibility-url {

        word-break: break-all;

    }


    @media (max-width: 767.98px) {

        .visibility-section .section-description {

            margin-top: 0.35rem;

        }

    }
</style>


<div class="container-fluid">


    <!-- ================================================================
         PAGE HEADING
    ================================================================= -->

    <div
        class="d-sm-flex
               align-items-center
               justify-content-between
               mb-4">

        <div>

            <h1 class="h3 mb-1 text-gray-800">

                Portfolio Visibility

            </h1>

            <p class="mb-0 text-muted">

                Control what visitors can see on your public portfolio.

            </p>

        </div>


        <div class="mt-3 mt-sm-0">

            <span class="badge badge-primary px-3 py-2">

                <i class="fas fa-eye mr-1"></i>

                <span id="visibleSectionCount">

                    <?= $visibleSections ?>

                </span>

                /

                <?= $totalSections ?>

                public sections

            </span>

        </div>

    </div>


    <!-- ================================================================
         MAIN CONTENT
    ================================================================= -->

    <div class="row">


        <!-- ============================================================
             LEFT COLUMN
        ============================================================= -->

        <div class="col-lg-8 mb-4">


            <!-- ========================================================
                 PORTFOLIO SECTIONS CARD
            ========================================================= -->

            <div class="card shadow h-100">


                <!-- =====================================================
                     CARD HEADER
                ====================================================== -->

                <div class="card-header py-3">

                    <div
                        class="d-flex
                               justify-content-between
                               align-items-center">

                        <div>

                            <h6
                                class="m-0
                                       font-weight-bold
                                       text-primary">

                                <i class="fas fa-eye mr-1"></i>

                                Portfolio Sections

                            </h6>

                            <small class="text-muted">

                                Choose which sections visitors can see.

                            </small>

                        </div>


                        <span class="badge badge-light">

                            <span id="sectionVisibleCount">

                                <?= $visibleSections ?>

                            </span>

                            of

                            <?= $totalSections ?>

                            visible

                        </span>

                    </div>

                </div>


                <!-- =====================================================
                     SECTIONS FORM
                ====================================================== -->

                <form
                    method="post"
                    id="sectionsVisibilityForm">

                    <input
                        type="hidden"
                        name="action"
                        value="save_sections">


                    <div class="card-body p-0">

                        <div class="list-group list-group-flush">


                            <?php foreach ($sections as $section): ?>

                                <?php

                                $field =
                                    $section[0];

                                $title =
                                    $section[1];

                                $description =
                                    $section[2];

                                $icon =
                                    $section[3];

                                $checked =
                                    !empty($profile[$field]);

                                ?>


                                <!-- =================================================
                                     SECTION ITEM
                                ================================================== -->

                                <div
                                    class="list-group-item
                                           visibility-section
                                           py-3">

                                    <div
                                        class="row
                                               align-items-center">


                                        <!-- =========================================
                                             ICON
                                        ========================================== -->

                                        <div class="col-auto">

                                            <div
                                                class="visibility-icon-circle
                                                       bg-primary">

                                                <i
                                                    class="fas
                                                           <?= e($icon) ?>
                                                           text-white"></i>

                                            </div>

                                        </div>


                                        <!-- =========================================
                                             INFORMATION
                                        ========================================== -->

                                        <div class="col">

                                            <div
                                                class="d-flex
                                                       align-items-center
                                                       flex-wrap">

                                                <h6
                                                    class="font-weight-bold
                                                           text-gray-800
                                                           mb-1
                                                           mr-2">

                                                    <?= e($title) ?>

                                                </h6>


                                                <span
                                                    class="badge
                                                           <?= $checked
                                                                ? 'badge-success'
                                                                : 'badge-secondary' ?>"
                                                    data-status-for="<?= e($field) ?>">

                                                    <i
                                                        class="fas
                                                               <?= $checked
                                                                    ? 'fa-eye'
                                                                    : 'fa-eye-slash' ?>
                                                               mr-1"></i>

                                                    <span class="status-text">

                                                        <?= $checked
                                                            ? 'Visible'
                                                            : 'Hidden' ?>

                                                    </span>

                                                </span>

                                            </div>


                                            <p
                                                class="small
                                                       text-muted
                                                       section-description
                                                       mb-0">

                                                <?= e($description) ?>

                                            </p>

                                        </div>


                                        <!-- =========================================
                                             SWITCH
                                        ========================================== -->

                                        <div class="col-auto">

                                            <div
                                                class="custom-control
                                                       custom-switch">

                                                <input
                                                    type="checkbox"
                                                    class="custom-control-input
                                                           visibility-toggle"
                                                    id="<?= e($field) ?>"
                                                    name="<?= e($field) ?>"
                                                    value="1"
                                                    data-title="<?= e($title) ?>"
                                                    <?= $checked
                                                        ? 'checked'
                                                        : '' ?>>

                                                <label
                                                    class="custom-control-label"
                                                    for="<?= e($field) ?>">

                                                    <span class="sr-only">

                                                        Show
                                                        <?= e($title) ?>

                                                    </span>

                                                </label>

                                            </div>

                                        </div>

                                    </div>

                                </div>


                            <?php endforeach; ?>


                        </div>

                    </div>


                    <!-- =====================================================
                         SAVE FOOTER
                    ====================================================== -->

                    <div
                        class="card-footer
                               bg-white
                               text-right">

                        <button
                            type="button"
                            class="btn btn-light border mr-2"
                            id="resetVisibilityBtn">

                            <i class="fas fa-undo mr-1"></i>

                            Reset

                        </button>


                        <?= csrf_field() ?>


                        <button
                            type="submit"
                            class="btn btn-primary"
                            id="saveSectionsBtn">

                            <i class="fas fa-save mr-1"></i>

                            Save Visibility

                        </button>

                    </div>


                </form>

            </div>

        </div>


        <!-- ============================================================
             RIGHT COLUMN
        ============================================================= -->

        <div class="col-lg-4">


            <!-- ========================================================
                 PUBLISHING CARD
            ========================================================= -->

            <div class="card shadow mb-4">


                <div class="card-header py-3">

                    <h6
                        class="m-0
                               font-weight-bold
                               text-primary">

                        <i class="fas fa-globe mr-1"></i>

                        Publishing

                    </h6>


                    <small class="text-muted">

                        Control whether your portfolio is publicly accessible.

                    </small>

                </div>


                <!-- =====================================================
                     PUBLISHING FORM
                ====================================================== -->

                <form method="post">


                    <input
                        type="hidden"
                        name="action"
                        value="save_publishing">


                    <div class="card-body">


                        <!-- ==============================================
                             CURRENT STATUS
                        =============================================== -->

                        <div
                            class="d-flex
                                   align-items-center
                                   mb-4">

                            <div class="mr-3">

                                <div
                                    class="visibility-icon-circle
                                           <?= $portfolioIsPublic
                                                ? 'bg-success'
                                                : 'bg-secondary' ?>">

                                    <i
                                        class="fas
                                               <?= $portfolioIsPublic
                                                    ? 'fa-globe'
                                                    : 'fa-lock' ?>
                                               text-white"></i>

                                </div>

                            </div>


                            <div>

                                <small
                                    class="text-muted d-block">

                                    Current Status

                                </small>


                                <h6
                                    class="font-weight-bold
                                           text-gray-800
                                           mb-0">

                                    <?= $portfolioIsPublic
                                        ? 'Published'
                                        : 'Private' ?>

                                </h6>

                            </div>

                        </div>


                        <!-- ==============================================
                             PUBLISH SWITCH
                        =============================================== -->

                        <div
                            class="border
                                   rounded
                                   p-3">

                            <div
                                class="d-flex
                                       align-items-center
                                       justify-content-between">


                                <div class="pr-3">

                                    <h6
                                        class="font-weight-bold
                                               text-gray-800
                                               mb-1">

                                        Publish Portfolio

                                    </h6>


                                    <p
                                        class="small
                                               text-muted
                                               mb-0">

                                        Allow visitors to access your
                                        public portfolio.

                                    </p>

                                </div>


                                <div
                                    class="custom-control
                                           custom-switch
                                           flex-shrink-0">

                                    <input
                                        type="checkbox"
                                        class="custom-control-input"
                                        id="portfolio_public"
                                        name="portfolio_public"
                                        value="1"
                                        <?= $portfolioIsPublic
                                            ? 'checked'
                                            : '' ?>>

                                    <label
                                        class="custom-control-label"
                                        for="portfolio_public">

                                        <span class="sr-only">

                                            Publish Portfolio

                                        </span>

                                    </label>

                                </div>

                            </div>

                        </div>


                        <!-- ==============================================
                             PUBLIC PORTFOLIO LINK
                        =============================================== -->

                        <?php if ($portfolioIsPublic): ?>

                            <div
                                class="mt-4
                                       pt-3
                                       border-top">

                                <label
                                    class="small
                                           font-weight-bold
                                           text-gray-700
                                           mb-2">

                                    Public Portfolio

                                </label>


                                <div class="input-group">

                                    <input
                                        type="text"
                                        class="form-control
                                               form-control-sm
                                               visibility-url"
                                        value="<?= e($portfolioUrl) ?>"
                                        readonly>


                                    <div class="input-group-append">

                                        <a
                                            href="<?= e($portfolioUrl) ?>"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="btn
                                                   btn-primary
                                                   btn-sm"
                                            title="Open published portfolio">

                                            <i
                                                class="fas
                                                       fa-external-link-alt"></i>

                                        </a>

                                    </div>

                                </div>


                                <small
                                    class="form-text text-muted">

                                    Open your public portfolio in a new tab.

                                </small>

                            </div>

                        <?php else: ?>

                            <div
                                class="alert
                                       alert-light
                                       border
                                       mt-4
                                       mb-0">

                                <i
                                    class="fas
                                           fa-lock
                                           text-muted
                                           mr-2"></i>

                                <small class="text-muted">

                                    Your portfolio is currently private.
                                    Turn on publishing to make it accessible
                                    to visitors.

                                </small>

                            </div>

                        <?php endif; ?>


                    </div>


                    <!-- ==============================================
                         SAVE PUBLISHING
                    =============================================== -->

                    <div
                        class="card-footer
                               bg-white
                               text-right">

                        <?= csrf_field() ?>


                        <button
                            type="submit"
                            class="btn btn-primary">

                            <i class="fas fa-save mr-1"></i>

                            Save Publishing

                        </button>

                    </div>


                </form>

            </div>

        </div>

    </div>

</div>


<script>
    document.addEventListener(
        'DOMContentLoaded',
        function() {


            /*
            |--------------------------------------------------------------------------
            | Section Visibility
            |--------------------------------------------------------------------------
            */

            const toggles =
                document.querySelectorAll(
                    '.visibility-toggle'
                );


            function updateVisibilityUI() {

                let visible = 0;


                toggles.forEach(
                    function(toggle) {

                        const row =
                            toggle.closest(
                                '.visibility-section'
                            );


                        const status =
                            document.querySelector(
                                '[data-status-for="' +
                                toggle.id +
                                '"]'
                            );


                        if (toggle.checked) {

                            visible++;


                            if (row) {

                                row.classList.add(
                                    'bg-light'
                                );

                            }


                            if (status) {

                                status.classList.remove(
                                    'badge-secondary'
                                );

                                status.classList.add(
                                    'badge-success'
                                );


                                const icon =
                                    status.querySelector(
                                        'i'
                                    );


                                const text =
                                    status.querySelector(
                                        '.status-text'
                                    );


                                if (icon) {

                                    icon.className =
                                        'fas fa-eye mr-1';

                                }


                                if (text) {

                                    text.textContent =
                                        'Visible';

                                }

                            }

                        } else {

                            if (row) {

                                row.classList.remove(
                                    'bg-light'
                                );

                            }


                            if (status) {

                                status.classList.remove(
                                    'badge-success'
                                );

                                status.classList.add(
                                    'badge-secondary'
                                );


                                const icon =
                                    status.querySelector(
                                        'i'
                                    );


                                const text =
                                    status.querySelector(
                                        '.status-text'
                                    );


                                if (icon) {

                                    icon.className =
                                        'fas fa-eye-slash mr-1';

                                }


                                if (text) {

                                    text.textContent =
                                        'Hidden';

                                }

                            }

                        }

                    }
                );


                /*
                |--------------------------------------------------------------------------
                | Update Counters
                |--------------------------------------------------------------------------
                */

                const total =
                    toggles.length;


                const count =
                    document.getElementById(
                        'visibleSectionCount'
                    );


                if (count) {

                    count.textContent =
                        visible;

                }


                const sectionCount =
                    document.getElementById(
                        'sectionVisibleCount'
                    );


                if (sectionCount) {

                    sectionCount.textContent =
                        visible;

                }


                const summaryVisible =
                    document.getElementById(
                        'summaryVisible'
                    );


                if (summaryVisible) {

                    summaryVisible.textContent =
                        visible;

                }


                const summaryHidden =
                    document.getElementById(
                        'summaryHidden'
                    );


                if (summaryHidden) {

                    summaryHidden.textContent =
                        total - visible;

                }

            }


            toggles.forEach(
                function(toggle) {

                    toggle.addEventListener(
                        'change',
                        updateVisibilityUI
                    );

                }
            );


            updateVisibilityUI();


            /*
            |--------------------------------------------------------------------------
            | Reset Button
            |--------------------------------------------------------------------------
            */

            const resetButton =
                document.getElementById(
                    'resetVisibilityBtn'
                );


            if (resetButton) {

                const originalStates = {};


                toggles.forEach(
                    function(toggle) {

                        originalStates[toggle.id] =
                            toggle.checked;

                    }
                );


                resetButton.addEventListener(
                    'click',
                    function() {

                        toggles.forEach(
                            function(toggle) {

                                if (
                                    Object.prototype.hasOwnProperty.call(
                                        originalStates,
                                        toggle.id
                                    )
                                ) {

                                    toggle.checked =
                                        originalStates[
                                            toggle.id
                                        ];

                                }

                            }
                        );


                        updateVisibilityUI();

                    }
                );

            }


            /*
            |--------------------------------------------------------------------------
            | Save Button
            |--------------------------------------------------------------------------
            */

            const sectionsForm =
                document.getElementById(
                    'sectionsVisibilityForm'
                );


            const saveButton =
                document.getElementById(
                    'saveSectionsBtn'
                );


            if (
                sectionsForm &&
                saveButton
            ) {

                sectionsForm.addEventListener(
                    'submit',
                    function() {

                        saveButton.disabled =
                            true;


                        saveButton.innerHTML =
                            '<i class="fas fa-spinner fa-spin mr-1"></i>' +
                            ' Saving...';

                    }
                );

            }


        }
    );
</script>


<?php

require dirname(__DIR__) . '/includes/footer.php';

?>