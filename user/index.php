<?php

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/maintenance-check.php';

require_role('user');

$userId = $_SESSION['user_id'];

$profile = get_profile($userId);
$user    = get_user($userId);

$pageTitle = 'Dashboard';

$pdo = db();


/* =========================================================
   COUNT HELPER
   ========================================================= */

function dashboard_count($pdo, $table, $userId)
{
    $allowedTables = [
        'projects',
        'experience',
        'education',
        'skills',
        'certifications',
        'resume_references'
    ];

    if (!in_array($table, $allowedTables, true)) {
        return 0;
    }

    try {

        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM `{$table}` WHERE user_id = ?"
        );

        $stmt->execute([$userId]);

        return (int) $stmt->fetchColumn();
    } catch (Throwable $e) {

        return 0;
    }
}


/* =========================================================
   COUNTS
   ========================================================= */

$projectCount       = dashboard_count($pdo, 'projects', $userId);
$experienceCount    = dashboard_count($pdo, 'experience', $userId);
$educationCount     = dashboard_count($pdo, 'education', $userId);
$skillCount         = dashboard_count($pdo, 'skills', $userId);
$certificationCount = dashboard_count($pdo, 'certifications', $userId);
$referenceCount     = dashboard_count($pdo, 'resume_references', $userId);


/* =========================================================
   PROFILE COMPLETION
   ========================================================= */

$profileInformationComplete =
    !empty($profile['full_name']) &&
    !empty($profile['professional_title']) &&
    !empty($profile['bio']) &&
    !empty($profile['professional_summary']) &&
    !empty($profile['profile_image']);


$profileChecks = [

    'Profile Information' => $profileInformationComplete,

    'Experience' => $experienceCount > 0,

    'Education' => $educationCount > 0,

    'Skills' => $skillCount > 0,

    'Projects' => $projectCount > 0,

    'Certifications' => $certificationCount > 0,

    'References' => $referenceCount > 0

];


$totalChecks     = count($profileChecks);
$completedChecks = count(array_filter($profileChecks));

$profileStrength = $totalChecks > 0
    ? round(($completedChecks / $totalChecks) * 100)
    : 0;


/* =========================================================
   PORTFOLIO
   ========================================================= */

$portfolioPublic = !empty($profile['portfolio_public']);

$username = trim(
    (string) (
        $profile['username']
        ?? $user['username']
        ?? ''
    )
);

$portfolioUrl = asset(
    'portfolio.php?username=' . urlencode($username)
);


/* =========================================================
   DISPLAY NAME
   ========================================================= */

$displayName = trim(
    (string) (
        $profile['full_name']
        ?? $user['name']
        ?? 'User'
    )
);

if ($displayName === '') {
    $displayName = 'User';
}


/* =========================================================
   RECENT CONTENT
   ========================================================= */

$recentItems = [];

try {

    $stmt = $pdo->prepare("
        SELECT
            'Project' AS item_type,
            COALESCE(name, title, 'Untitled Project') AS item_name,
            created_at
        FROM projects
        WHERE user_id = ?

        UNION ALL

        SELECT
            'Experience' AS item_type,
            COALESCE(position, job_title, title, 'Experience') AS item_name,
            created_at
        FROM experience
        WHERE user_id = ?

        UNION ALL

        SELECT
            'Education' AS item_type,
            COALESCE(school, institution, school_name, 'Education') AS item_name,
            created_at
        FROM education
        WHERE user_id = ?

        UNION ALL

        SELECT
            'Skill' AS item_type,
            COALESCE(name, skill_name, 'Skill') AS item_name,
            created_at
        FROM skills
        WHERE user_id = ?

        UNION ALL

        SELECT
            'Certification' AS item_type,
            COALESCE(name, title, certification_name, 'Certification') AS item_name,
            created_at
        FROM certifications
        WHERE user_id = ?

        ORDER BY created_at DESC
        LIMIT 5
    ");

    $stmt->execute([
        $userId,
        $userId,
        $userId,
        $userId,
        $userId
    ]);

    $recentItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {

    $recentItems = [];
}


/* =========================================================
   HEADER
   ========================================================= */

require_once __DIR__ . '/../includes/header.php';

?>

<div class="container-fluid">


    <!-- =====================================================
         PAGE HEADING
         ===================================================== -->

    <div class="d-sm-flex align-items-center justify-content-between mb-4">

        <div>

            <h1 class="h3 mb-1 text-gray-800">
                Dashboard
            </h1>

            <p class="mb-0 text-gray-600">

                Welcome back,
                <strong>
                    <?= htmlspecialchars($displayName); ?>
                </strong>!

                Manage your professional profile,
                portfolio, and resume from here.

            </p>

        </div>


        <div class="mt-3 mt-sm-0">

            <?php if ($portfolioPublic): ?>

                <a
                    href="<?= htmlspecialchars($portfolioUrl); ?>"
                    target="_blank"
                    rel="noopener"
                    class="btn btn-primary btn-sm shadow-sm">

                    <i class="fas fa-globe fa-sm text-white-50 mr-1"></i>

                    View Portfolio

                </a>

            <?php else: ?>

                <a
                    href="visibility.php"
                    class="btn btn-primary btn-sm shadow-sm">

                    <i class="fas fa-globe fa-sm text-white-50 mr-1"></i>

                    Publish Portfolio

                </a>

            <?php endif; ?>

        </div>

    </div>


    <!-- =====================================================
         STATISTICS
         ===================================================== -->

    <div class="row">


        <!-- PROJECTS -->

        <div class="col-xl-3 col-md-6 mb-4">

            <div class="card border-left-primary shadow h-100 py-2">

                <div class="card-body">

                    <div class="row no-gutters align-items-center">

                        <div class="col mr-2">

                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                Projects
                            </div>

                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?= $projectCount; ?>
                            </div>

                        </div>

                        <div class="col-auto">

                            <i class="fas fa-folder-open fa-2x text-gray-300"></i>

                        </div>

                    </div>

                </div>

            </div>

        </div>


        <!-- EXPERIENCE -->

        <div class="col-xl-3 col-md-6 mb-4">

            <div class="card border-left-success shadow h-100 py-2">

                <div class="card-body">

                    <div class="row no-gutters align-items-center">

                        <div class="col mr-2">

                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                Experience
                            </div>

                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?= $experienceCount; ?>
                            </div>

                        </div>

                        <div class="col-auto">

                            <i class="fas fa-briefcase fa-2x text-gray-300"></i>

                        </div>

                    </div>

                </div>

            </div>

        </div>


        <!-- EDUCATION -->

        <div class="col-xl-3 col-md-6 mb-4">

            <div class="card border-left-info shadow h-100 py-2">

                <div class="card-body">

                    <div class="row no-gutters align-items-center">

                        <div class="col mr-2">

                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                Education
                            </div>

                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?= $educationCount; ?>
                            </div>

                        </div>

                        <div class="col-auto">

                            <i class="fas fa-graduation-cap fa-2x text-gray-300"></i>

                        </div>

                    </div>

                </div>

            </div>

        </div>


        <!-- SKILLS -->

        <div class="col-xl-3 col-md-6 mb-4">

            <div class="card border-left-warning shadow h-100 py-2">

                <div class="card-body">

                    <div class="row no-gutters align-items-center">

                        <div class="col mr-2">

                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                Skills
                            </div>

                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?= $skillCount; ?>
                            </div>

                        </div>

                        <div class="col-auto">

                            <i class="fas fa-tools fa-2x text-gray-300"></i>

                        </div>

                    </div>

                </div>

            </div>

        </div>

    </div>


    <!-- =====================================================
         MAIN DASHBOARD
         ===================================================== -->

    <div class="row">


        <!-- =================================================
             LIVE PORTFOLIO PREVIEW
             ================================================= -->

        <div class="col-xl-8 col-lg-7 mb-4">

            <div class="card shadow h-100">


                <!-- HEADER -->

                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">

                    <h6 class="m-0 font-weight-bold text-primary">

                        <i class="fas fa-desktop mr-1"></i>

                        Live Portfolio Preview

                    </h6>


                    <?php if ($portfolioPublic): ?>

                        <div>

                            <!-- <button
                                type="button"
                                id="refreshPortfolioPreview"
                                class="btn btn-sm btn-outline-primary mr-1">

                                <i class="fas fa-sync-alt mr-1"></i>

                                Refresh

                            </button> -->


                            <a
                                href="<?= htmlspecialchars($portfolioUrl); ?>"
                                target="_blank"
                                rel="noopener"
                                class="btn btn-sm btn-primary">

                                <i class="fas fa-external-link-alt mr-1"></i>

                                Open

                            </a>

                        </div>

                    <?php endif; ?>

                </div>


                <!-- BODY -->

                <div class="card-body p-0">


                    <?php if ($portfolioPublic): ?>

                        <iframe
                            id="portfolioPreviewFrame"
                            src="<?= htmlspecialchars($portfolioUrl); ?>"
                            title="Portfolio Preview"
                            width="100%"
                            height="650"
                            loading="eager"
                            style="border: 0;"></iframe>

                    <?php else: ?>

                        <div class="p-5 text-center">

                            <div class="mb-3">

                                <i class="fas fa-eye-slash fa-3x text-gray-300"></i>

                            </div>


                            <h5 class="font-weight-bold text-gray-800">

                                Portfolio Preview Unavailable

                            </h5>


                            <p class="text-gray-500 mb-4">

                                Your portfolio is currently private.
                                Publish your portfolio to see the live
                                preview here.

                            </p>


                            <a
                                href="visibility.php"
                                class="btn btn-primary btn-sm">

                                <i class="fas fa-globe mr-1"></i>

                                Manage Portfolio Visibility

                            </a>

                        </div>

                    <?php endif; ?>


                </div>


                <?php if ($portfolioPublic): ?>

                    <div class="card-footer bg-white">

                        <div class="small text-gray-500">

                            <i class="fas fa-info-circle mr-1"></i>

                            This preview displays your public portfolio
                            exactly as visitors see it.

                        </div>

                    </div>

                <?php endif; ?>


            </div>

        </div>


        <div class="col-xl-4 col-lg-5 mb-4">

            <div class="card shadow h-100">

                <!-- HEADER -->
                <div class="card-header py-3 d-flex align-items-center justify-content-between">

                    <div>
                        <h6 class="m-0 font-weight-bold text-primary">
                            <!-- <i class="fas fa-user-check mr-1"></i> -->
                            Profile Strength
                        </h6>

                        <small class="text-muted">
                            Keep your professional profile up to date
                        </small>
                    </div>

                    <span class="badge badge-light text-primary">
                        <?= $profileStrength; ?>%
                    </span>

                </div>


                <!-- BODY -->
                <div class="card-body">


                    <!-- =========================================
                 PROGRESS SUMMARY
                 ========================================= -->

                    <div class="text-center mb-3">

                        <div class="display-4 font-weight-bold text-gray-800">
                            <?= $profileStrength; ?>%
                        </div>

                        <?php if ($profileStrength >= 100): ?>

                            <div class="text-success font-weight-bold small">
                                <i class="fas fa-check-circle mr-1"></i>
                                Your profile is complete
                            </div>

                        <?php elseif ($profileStrength >= 70): ?>

                            <div class="text-info font-weight-bold small">
                                <i class="fas fa-chart-line mr-1"></i>
                                Almost there
                            </div>

                        <?php elseif ($profileStrength >= 40): ?>

                            <div class="text-primary font-weight-bold small">
                                <i class="fas fa-arrow-up mr-1"></i>
                                Good progress
                            </div>

                        <?php else: ?>

                            <div class="text-warning font-weight-bold small">
                                <i class="fas fa-rocket mr-1"></i>
                                Let's get started
                            </div>

                        <?php endif; ?>

                    </div>


                    <!-- PROGRESS BAR -->

                    <div class="progress mb-4" style="height: 10px;">

                        <div
                            class="progress-bar bg-primary"
                            role="progressbar"
                            style="width: <?= $profileStrength; ?>%;"
                            aria-valuenow="<?= $profileStrength; ?>"
                            aria-valuemin="0"
                            aria-valuemax="100">
                        </div>

                    </div>


                    <!-- =========================================
                 COMPLETION SUMMARY
                 ========================================= -->

                    <!-- <div class="row text-center mb-4">

                        <div class="col-4">

                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?= $completedChecks; ?>
                            </div>

                            <div class="small text-gray-500">
                                Completed
                            </div>

                        </div>


                        <div class="col-4 border-left border-right">

                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?= $totalChecks - $completedChecks; ?>
                            </div>

                            <div class="small text-gray-500">
                                Remaining
                            </div>

                        </div>


                        <div class="col-4">

                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?= $totalChecks; ?>
                            </div>

                            <div class="small text-gray-500">
                                Total
                            </div>

                        </div>

                    </div> -->


                    <!-- =========================================
                 PROFILE CHECKLIST
                 ========================================= -->

                    <div class="mb-3">

                        <?php foreach ($profileChecks as $label => $complete): ?>

                            <?php

                            $icon = 'fa-user';

                            switch ($label) {

                                case 'Profile Information':
                                    $icon = 'fa-user';
                                    break;

                                case 'Experience':
                                    $icon = 'fa-briefcase';
                                    break;

                                case 'Education':
                                    $icon = 'fa-graduation-cap';
                                    break;

                                case 'Skills':
                                    $icon = 'fa-tools';
                                    break;

                                case 'Projects':
                                    $icon = 'fa-folder-open';
                                    break;

                                case 'Certifications':
                                    $icon = 'fa-certificate';
                                    break;

                                case 'References':
                                    $icon = 'fa-users';
                                    break;
                            }

                            ?>

                            <div class="d-flex align-items-center justify-content-between py-2">

                                <div class="d-flex align-items-center">

                                    <?php if ($complete): ?>

                                        <i class="fas fa-check-circle text-success mr-2"></i>

                                    <?php else: ?>

                                        <i class="fas fa-circle text-gray-300 mr-2"
                                            style="font-size: 7px;"></i>

                                    <?php endif; ?>


                                    <i class="fas <?= $icon; ?> text-gray-400 mr-2"></i>


                                    <span class="<?= $complete ? 'text-gray-800' : 'text-gray-500'; ?> small">

                                        <?= htmlspecialchars($label); ?>

                                    </span>

                                </div>


                                <?php if ($complete): ?>

                                    <span class="text-success small font-weight-bold">
                                        Complete
                                    </span>

                                <?php else: ?>

                                    <span class="text-gray-400 small">
                                        Incomplete
                                    </span>

                                <?php endif; ?>

                            </div>

                        <?php endforeach; ?>

                    </div>


                    <!-- =========================================
                 ACTION
                 ========================================= -->

                    <?php if ($profileStrength < 100): ?>

                        <a
                            href="profile.php"
                            class="btn btn-primary btn-block btn-sm">

                            <i class="fas fa-user-edit mr-1"></i>

                            Complete Your Profile

                        </a>

                    <?php else: ?>

                        <div class="alert alert-success mb-0 py-2 text-center">

                            <i class="fas fa-check-circle mr-1"></i>

                            <small class="font-weight-bold">
                                Your profile is ready to showcase!
                            </small>

                        </div>

                    <?php endif; ?>

                </div>

            </div>

        </div>


    </div>
</div>


<?php if ($portfolioPublic): ?>

    <script>
        document.addEventListener('DOMContentLoaded', function() {

            const refreshButton =
                document.getElementById('refreshPortfolioPreview');

            const previewFrame =
                document.getElementById('portfolioPreviewFrame');


            if (refreshButton && previewFrame) {

                refreshButton.addEventListener('click', function() {

                    const icon = refreshButton.querySelector('i');

                    refreshButton.disabled = true;

                    if (icon) {
                        icon.classList.add('fa-spin');
                    }

                    previewFrame.src =
                        previewFrame.src.split('#')[0] +
                        (previewFrame.src.includes('?') ? '&' : '?') +
                        '_preview=' +
                        Date.now();


                    setTimeout(function() {

                        refreshButton.disabled = false;

                        if (icon) {
                            icon.classList.remove('fa-spin');
                        }

                    }, 800);

                });

            }

        });
    </script>

<?php endif; ?>


<?php

require_once __DIR__ . '/../includes/footer.php';

?>