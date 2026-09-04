<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/maintenance-check.php';

require_role('user');

$uid = (int) current_user_id();
$pdo = db();
$pageTitle = 'Resume Builder';

date_default_timezone_set('Asia/Manila');

/* =========================================================
   HELPERS
========================================================= */

/* =========================================================
   PROFILE SELECTION HELPER
========================================================= */

function pdf_profile_selected(
    array $selected,
    string $canonical
): bool {

    $map = [

        'full_name' => [
            'full_name',
            'profile_full_name'
        ],

        'professional_title' => [
            'professional_title',
            'profile_professional_title'
        ],

        'email' => [
            'email',
            'profile_email'
        ],

        'phone' => [
            'phone',
            'profile_phone'
        ],

        'address' => [
            'address',
            'profile_address'
        ],

        'website' => [
            'website',
            'website_url',
            'profile_website',
            'profile_website_url'
        ],

        'github' => [
            'github',
            'github_url',
            'profile_github',
            'profile_github_url'
        ],

        'linkedin' => [
            'linkedin',
            'linkedin_url',
            'profile_linkedin',
            'profile_linkedin_url'
        ],

        'facebook' => [
            'facebook',
            'facebook_url',
            'profile_facebook',
            'profile_facebook_url'
        ],

        'profile_picture' => [
            'profile_picture',
            'profile_image',
            'profile_profile_picture',
            'profile_profile_image'
        ],

    ];

    foreach (
        $map[$canonical] ?? [$canonical]
        as $candidate
    ) {

        if (
            in_array(
                $candidate,
                $selected,
                true
            )
        ) {
            return true;
        }
    }

    return false;
}

function resume_builder_rows(PDO $pdo, string $table, int $userId): array
{
    $allowedTables = [
        'experience',
        'education',
        'projects',
        'skills',
        'certifications',
        'resume_references',
    ];

    if (!in_array($table, $allowedTables, true)) {
        return [];
    }

    /*
     * Do not use the word "references" as an unquoted table name.
     * resume_references is the application's reference table.
     */
    try {
        if ($table === 'resume_references') {
            $stmt = $pdo->prepare(
                "SELECT *
                 FROM `{$table}`
                 WHERE user_id = ?
                 ORDER BY created_at DESC"
            );
            $stmt->execute([$userId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        $stmt = $pdo->prepare(
            "SELECT *
             FROM `{$table}`
             WHERE user_id = ?
             AND (is_public = 1 OR is_public IS NULL)
             ORDER BY created_at DESC"
        );
        $stmt->execute([$userId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        /*
         * Some older tables may not contain is_public or created_at.
         * Fall back to id, then user_id only.
         */
        try {
            $stmt = $pdo->prepare(
                "SELECT *
                 FROM `{$table}`
                 WHERE user_id = ?
                 ORDER BY id DESC"
            );
            $stmt->execute([$userId]);

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e2) {
            try {
                $stmt = $pdo->prepare(
                    "SELECT *
                     FROM `{$table}`
                     WHERE user_id = ?"
                );
                $stmt->execute([$userId]);

                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (Throwable $e3) {
                return [];
            }
        }
    }
}

function resume_checked(bool $value): string
{
    return $value ? 'checked' : '';
}

function resume_value(array $row, array $keys, string $default = ''): string
{
    foreach ($keys as $key) {
        if (isset($row[$key]) && trim((string) $row[$key]) !== '') {
            return trim((string) $row[$key]);
        }
    }

    return $default;
}

function resume_item_id(array $row): int
{
    return (int) ($row['id'] ?? 0);
}

/* =========================================================
   PROFILE
========================================================= */

ensure_profile($uid);

$profile = get_profile($uid);
$user = get_user($uid);

$profile = is_array($profile) ? $profile : [];
$user = is_array($user) ? $user : [];

/*
 * Personal information
 */
$fullName = trim((string) (
    $profile['full_name']
    ?? $user['full_name']
    ?? $user['name']
    ?? ''
));

if ($fullName === '') {
    $fullName = trim(
        (string) ($user['first_name'] ?? '') . ' ' .
            (string) ($user['last_name'] ?? '')
    );
}

if ($fullName === '') {
    $fullName = 'Your Name';
}

$professionalTitle = trim((string) (
    $profile['professional_title']
    ?? $profile['job_title']
    ?? ''
));

$email = trim((string) (
    $profile['email']
    ?? $user['email']
    ?? ''
));

$phone = trim((string) (
    $profile['phone']
    ?? $profile['contact_number']
    ?? ''
));

$address = trim((string) (
    $profile['address']
    ?? ''
));

/*
 * Social links
 *
 * IMPORTANT:
 * These use the actual columns in the profiles table.
 */
$website = trim((string) (
    $profile['website_url']
    ?? $profile['website']
    ?? ''
));

$github = trim((string) (
    $profile['github_url']
    ?? $profile['github']
    ?? ''
));

$linkedin = trim((string) (
    $profile['linkedin_url']
    ?? $profile['linkedin']
    ?? ''
));

$facebook = trim((string) (
    $profile['facebook_url']
    ?? $profile['facebook']
    ?? ''
));

/*
 * Professional Summary
 *
 * IMPORTANT:
 * Read directly from professional_summary.
 */
$summary = trim((string) (
    $profile['professional_summary']
    ?? $profile['summary']
    ?? $profile['bio']
    ?? ''
));

/* =========================================================
   RESUME DATA
========================================================= */

$experience = resume_builder_rows($pdo, 'experience', $uid);
$education = resume_builder_rows($pdo, 'education', $uid);
$projects = resume_builder_rows($pdo, 'projects', $uid);
$skills = resume_builder_rows($pdo, 'skills', $uid);
$certifications = resume_builder_rows($pdo, 'certifications', $uid);
$references = resume_builder_rows($pdo, 'resume_references', $uid);

/* =========================================================
   SECTION DEFINITIONS
========================================================= */

$defaultSectionOrder = [
    'summary',
    'experience',
    'education',
    'projects',
    'skills',
    'certifications',
    'resume_references',
];

$sectionLabels = [
    'summary' => 'Professional Summary',
    'experience' => 'Experience',
    'education' => 'Education',
    'projects' => 'Projects',
    'skills' => 'Skills',
    'certifications' => 'Certifications',
    'resume_references' => 'References',
];

$sectionIcons = [
    'summary' => 'fa-align-left',
    'experience' => 'fa-briefcase',
    'education' => 'fa-graduation-cap',
    'projects' => 'fa-project-diagram',
    'skills' => 'fa-tools',
    'certifications' => 'fa-certificate',
    'resume_references' => 'fa-user-friends',
];

/* =========================================================
   LOAD SAVED RESUME SETTINGS
========================================================= */

$settings = [];

try {
    $stmt = $pdo->prepare(
        "SELECT *
         FROM resume_settings
         WHERE user_id = ?
         LIMIT 1"
    );
    $stmt->execute([$uid]);
    $settings = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
    $settings = [];
}

if (!$settings) {
    $settings = [
        'section_order' => json_encode($defaultSectionOrder),
        'show_summary' => 1,
        'show_experience' => 1,
        'show_education' => 1,
        'show_projects' => 1,
        'show_skills' => 1,
        'show_certifications' => 1,
        'show_references' => 0,
        'references_on_request' => 1,
        'document_type' => 'resume',
    ];
}

/* =========================================================
   NORMALIZE SECTION ORDER
========================================================= */

$sectionOrder = $defaultSectionOrder;

if (!empty($settings['section_order'])) {
    $decodedOrder = json_decode((string) $settings['section_order'], true);

    if (is_array($decodedOrder)) {
        $decodedOrder = array_values(
            array_intersect(
                array_map('strval', $decodedOrder),
                $defaultSectionOrder
            )
        );

        foreach ($defaultSectionOrder as $section) {
            if (!in_array($section, $decodedOrder, true)) {
                $decodedOrder[] = $section;
            }
        }

        $sectionOrder = $decodedOrder;
    }
}

/* =========================================================
   VISIBILITY
========================================================= */

$showSummary = array_key_exists('show_summary', $settings)
    ? (int) $settings['show_summary'] === 1
    : true;

$showExperience = array_key_exists('show_experience', $settings)
    ? (int) $settings['show_experience'] === 1
    : true;

$showEducation = array_key_exists('show_education', $settings)
    ? (int) $settings['show_education'] === 1
    : true;

$showProjects = array_key_exists('show_projects', $settings)
    ? (int) $settings['show_projects'] === 1
    : true;

$showSkills = array_key_exists('show_skills', $settings)
    ? (int) $settings['show_skills'] === 1
    : true;

$showCertifications = array_key_exists('show_certifications', $settings)
    ? (int) $settings['show_certifications'] === 1
    : true;

$showReferences = array_key_exists('show_references', $settings)
    ? (int) $settings['show_references'] === 1
    : false;

$referencesOnRequest = !empty($settings['references_on_request']);

/* =========================================================
   SECTION TOGGLES
========================================================= */

$sectionToggles = [
    [
        'id' => 'show_summary',
        'label' => 'Professional Summary',
        'checked' => $showSummary,
    ],
    [
        'id' => 'show_experience',
        'label' => 'Experience',
        'checked' => $showExperience,
    ],
    [
        'id' => 'show_education',
        'label' => 'Education',
        'checked' => $showEducation,
    ],
    [
        'id' => 'show_projects',
        'label' => 'Projects',
        'checked' => $showProjects,
    ],
    [
        'id' => 'show_skills',
        'label' => 'Skills',
        'checked' => $showSkills,
    ],
    [
        'id' => 'show_certifications',
        'label' => 'Certifications',
        'checked' => $showCertifications,
    ],
    [
        'id' => 'show_references',
        'label' => 'References',
        'checked' => $showReferences,
    ],
];

$documentType = strtolower(
    trim((string) ($settings['document_type'] ?? 'resume'))
);

if (!in_array($documentType, ['resume', 'cv'], true)) {
    $documentType = 'resume';
}

/* =========================================================
   SAVE SECTION SETTINGS
========================================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    try {
        verify_csrf();

        $postedOrder = $_POST['section_order'] ?? [];

        if (!is_array($postedOrder)) {
            $postedOrder = [];
        }

        $postedOrder = array_values(
            array_intersect(
                array_map('strval', $postedOrder),
                $defaultSectionOrder
            )
        );

        foreach ($defaultSectionOrder as $section) {
            if (!in_array($section, $postedOrder, true)) {
                $postedOrder[] = $section;
            }
        }

        $postedDocumentType = strtolower(
            trim((string) ($_POST['document_type'] ?? 'resume'))
        );

        if (!in_array($postedDocumentType, ['resume', 'cv'], true)) {
            $postedDocumentType = 'resume';
        }

        $saveSummary = isset($_POST['show_summary']) ? 1 : 0;
        $saveExperience = isset($_POST['show_experience']) ? 1 : 0;
        $saveEducation = isset($_POST['show_education']) ? 1 : 0;
        $saveProjects = isset($_POST['show_projects']) ? 1 : 0;
        $saveSkills = isset($_POST['show_skills']) ? 1 : 0;
        $saveCertifications = isset($_POST['show_certifications']) ? 1 : 0;
        $saveReferences = isset($_POST['show_references']) ? 1 : 0;
        $saveReferencesOnRequest = isset($_POST['references_on_request']) ? 1 : 0;

        $stmt = $pdo->prepare(
            "SELECT id
             FROM resume_settings
             WHERE user_id = ?
             LIMIT 1"
        );
        $stmt->execute([$uid]);
        $existingSettings = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existingSettings) {
            $stmt = $pdo->prepare(
                "UPDATE resume_settings
                 SET
                    section_order = ?,
                    show_summary = ?,
                    show_experience = ?,
                    show_education = ?,
                    show_projects = ?,
                    show_skills = ?,
                    show_certifications = ?,
                    show_references = ?,
                    references_on_request = ?,
                    document_type = ?
                 WHERE user_id = ?"
            );

            $stmt->execute([
                json_encode($postedOrder),
                $saveSummary,
                $saveExperience,
                $saveEducation,
                $saveProjects,
                $saveSkills,
                $saveCertifications,
                $saveReferences,
                $saveReferencesOnRequest,
                $postedDocumentType,
                $uid,
            ]);
        } else {
            $stmt = $pdo->prepare(
                "INSERT INTO resume_settings
                (
                    user_id,
                    section_order,
                    show_summary,
                    show_experience,
                    show_education,
                    show_projects,
                    show_skills,
                    show_certifications,
                    show_references,
                    references_on_request,
                    document_type
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );

            $stmt->execute([
                $uid,
                json_encode($postedOrder),
                $saveSummary,
                $saveExperience,
                $saveEducation,
                $saveProjects,
                $saveSkills,
                $saveCertifications,
                $saveReferences,
                $saveReferencesOnRequest,
                $postedDocumentType,
            ]);
        }

        flash('success', 'Resume settings saved successfully.');
        redirect('user/resume.php?saved=1');
    } catch (Throwable $e) {
        flash(
            'danger',
            'Unable to save resume settings: ' . $e->getMessage()
        );
        redirect('user/resume.php');
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<style>
    /* SB Admin 2 / Bootstrap-compatible resume builder styling */
    .resume-sections-card {
        overflow: hidden;
        border: 0;
    }

    .resume-sections-card .card-header {
        background: #fff;
        border-bottom: 1px solid #e3e6f0;
    }

    .resume-card-icon {
        width: 38px;
        height: 38px;
        border-radius: .35rem;
        display: flex;
        align-items: center;
        justify-content: center;
        background: #f0ecff;
        color: #4e73df;
        font-size: .9rem;
    }

    .resume-section-help {
        background: #f8f9fc;
        border-bottom: 1px solid #eaecf4;
    }

    .resume-section-list {
        display: flex;
        flex-direction: column;
        gap: .55rem;
    }

    .resume-section-item {
        display: flex;
        align-items: center;
        min-height: 58px;
        padding: .65rem .7rem;
        background: #fff;
        border: 1px solid #e3e6f0;
        border-radius: .35rem;
        cursor: grab;
        transition: border-color .15s ease, box-shadow .15s ease, transform .15s ease, background .15s ease;
    }

    .resume-section-item:hover {
        border-color: #b7b9cc;
        box-shadow: 0 .15rem .45rem rgba(58, 59, 69, .08);
    }

    .resume-section-item:active,
    .resume-section-item.dragging {
        cursor: grabbing;
        background: #f8f9fc;
        border-color: #4e73df;
        box-shadow: 0 .35rem .9rem rgba(78, 115, 223, .14);
        transform: translateY(-1px);
    }

    .resume-drag-handle {
        width: 26px;
        color: #b7b9cc;
        text-align: center;
        flex: 0 0 26px;
    }

    .resume-section-symbol {
        width: 34px;
        height: 34px;
        border-radius: .3rem;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-right: .7rem;
        background: #f8f9fc;
        color: #4e73df;
        border: 1px solid #eaecf4;
        flex: 0 0 34px;
        font-size: .78rem;
    }

    .resume-section-status {
        font-size: .68rem;
        margin-top: 1px;
    }

    .resume-sort-badge {
        margin-left: .5rem;
        width: 25px;
        height: 25px;
        border-radius: 50%;
        background: #f8f9fc;
        border: 1px solid #e3e6f0;
        color: #858796;
        display: flex;
        align-items: center;
        justify-content: center;
        flex: 0 0 25px;
    }

    .resume-sort-number {
        font-size: .7rem;
        font-weight: 700;
    }

    .resume-toggle-row {
        padding: .55rem .7rem;
        border: 1px solid transparent;
        border-radius: .3rem;
        transition: background .15s ease, border-color .15s ease;
    }

    .resume-toggle-row:hover {
        background: #f8f9fc;
        border-color: #eaecf4;
    }

    .resume-sections-card .custom-switch .custom-control-label::before {
        top: .1rem;
    }

    .resume-sections-card .custom-switch .custom-control-label::after {
        top: calc(.1rem + 2px);
    }

    .resume-sections-card .bg-gray-100 {
        background-color: #f8f9fc !important;
    }

    .resume-preview-card .card-header {
        background: #fff;
    }

    .resume-preview-toolbar .btn {
        white-space: nowrap;
    }

    @media (max-width: 991.98px) {
        .resume-sections-card .card-header {
            padding: 1rem;
        }
    }
</style>

<div class="container-fluid">

    <!-- PAGE HEADER -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <div>
            <h1 class="h3 mb-1 text-gray-800">Resume Builder</h1>
            <p class="mb-0 text-gray-600">
                Build your Harvard-style resume or CV and preview the exact document before downloading.
            </p>
        </div>

        <!-- HOW IT WORKS BUTTON -->
        <div class="mt-3 mt-sm-0">
            <button
                type="button"
                class="btn btn-primary shadow-sm"
                data-toggle="modal"
                data-target="#howItWorksModal">
                <i class="fas fa-info-circle mr-1"></i>
                How It Works
            </button>
        </div>
    </div>

    <form method="POST" id="resumeSettingsForm">
        <?= csrf_field() ?>

        <input type="hidden" name="save_settings" value="1">
        <div id="hiddenSectionOrderInputs"></div>

        <div class="row">

            <!-- =====================================================
                 LEFT COLUMN
            ====================================================== -->
            <div class="col-lg-4">

                <!-- DOCUMENT TYPE -->
                <!-- <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-file-alt mr-1"></i>
                            Resume Type
                        </h6>
                    </div>

                    <div class="card-body">
                        <label for="document_type" class="font-weight-bold text-gray-700">
                            Document Type
                        </label>

                        <select
                            class="form-control"
                            name="document_type"
                            id="document_type">
                            <option value="resume" <?= $documentType === 'resume' ? 'selected' : '' ?>>
                                Resume
                            </option>
                            <option value="cv" <?= $documentType === 'cv' ? 'selected' : '' ?>>
                                Curriculum Vitae (CV)
                            </option>
                        </select>

                        <small class="form-text text-muted">
                            The same document renderer is used for both preview and download.
                        </small>
                    </div>
                </div> -->

                <!-- RESUME SECTIONS -->
                <div class="card shadow mb-4 resume-sections-card">
                    <div class="card-header py-3">
                        <div class="d-flex align-items-center justify-content-between">
                            <div class="d-flex align-items-center">
                                <div class="resume-card-icon mr-3">
                                    <i class="fas fa-layer-group"></i>
                                </div>
                                <div>
                                    <h6 class="m-0 font-weight-bold text-primary">Resume Sections</h6>
                                    <!-- <small class="text-muted">Organize and control what appears on your resume</small> -->
                                </div>
                            </div>
                            <button type="button" class="btn btn-sm btn-light border" id="resetSectionOrder" title="Reset section order">
                                <i class="fas fa-undo mr-1"></i> Reset
                            </button>
                        </div>
                    </div>

                    <div class="card-body p-0">
                        <div class="resume-section-help px-4 py-3">
                            <div class="d-flex align-items-start">
                                <i class="fas fa-arrows-alt text-primary mt-1 mr-2"></i>
                                <div>
                                    <div class="font-weight-bold text-gray-800 small">Arrange your sections</div>
                                    <div class="small text-muted">Drag a section using the handle to change its order in the resume.</div>
                                </div>
                            </div>
                        </div>

                        <div class="px-3 py-3">
                            <div id="sectionSortable" class="resume-section-list">
                                <?php foreach ($sectionOrder as $section): ?>
                                    <div class="resume-section-item" data-section="<?= e($section) ?>" draggable="true">
                                        <div class="resume-drag-handle" title="Drag to reorder">
                                            <i class="fas fa-grip-vertical"></i>
                                        </div>
                                        <div class="resume-section-symbol">
                                            <i class="fas <?= e($sectionIcons[$section] ?? 'fa-file') ?>"></i>
                                        </div>
                                        <div class="flex-grow-1 min-width-0">
                                            <div class="font-weight-bold text-gray-800 small text-truncate">
                                                <?= e($sectionLabels[$section] ?? ucfirst($section)) ?>
                                            </div>
                                            <div class="small text-muted resume-section-status">Included in resume</div>
                                        </div>
                                        <div class="resume-sort-badge">
                                            <span class="resume-sort-number">1</span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="border-top px-4 py-3">
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <div>
                                    <div class="font-weight-bold text-gray-800 small">Section visibility</div>
                                    <div class="small text-muted">Choose which sections are shown.</div>
                                </div>
                                <span class="badge badge-light border" id="visibleSectionCount">0 visible</span>
                            </div>

                            <?php foreach ($sectionToggles as $toggle): ?>
                                <div class="resume-toggle-row">
                                    <div class="custom-control custom-switch flex-grow-1">
                                        <input
                                            type="checkbox"
                                            class="custom-control-input section-toggle"
                                            id="<?= e($toggle['id']) ?>"
                                            name="<?= e($toggle['id']) ?>"
                                            value="1"
                                            <?= resume_checked((bool) $toggle['checked']) ?>>
                                        <label class="custom-control-label font-weight-bold small" for="<?= e($toggle['id']) ?>">
                                            <?= e($toggle['label']) ?>
                                        </label>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="px-4 py-3 bg-gray-100 border-top">
                            <button type="submit" name="save_settings" value="1" class="btn btn-primary btn-block shadow-sm">
                                <i class="fas fa-save mr-1"></i> Save Resume Settings
                            </button>
                            <div class="small text-muted text-center mt-2">
                                Your order and visibility settings will be saved to your account.
                            </div>
                        </div>
                    </div>
                </div>

                <!-- SAVE -->
                <!-- <div class="card shadow mb-4">
                    <div class="card-body">
                        
                    </div>
                </div> -->

            </div>

            <!-- =====================================================
                 RIGHT COLUMN
            ====================================================== -->
            <div class="col-lg-8">

                <!-- LIVE PREVIEW -->
                <div class="card shadow mb-4 resume-preview-card">
                    <div class="card-header py-3">
                        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between resume-preview-toolbar">

                            <h6 class="m-0 font-weight-bold text-primary mb-2 mb-md-0">
                                <i class="fas fa-file-pdf mr-1"></i>
                                Live Resume Preview
                            </h6>

                            <div class="d-flex flex-wrap">
                                <button
                                    type="button"
                                    class="btn btn-sm btn-primary mr-2 mb-1"
                                    data-toggle="modal"
                                    data-target="#pdfItemsModal">
                                    <i class="fas fa-check-square mr-1"></i>
                                    Choose PDF Items
                                </button>

                                <button
                                    type="button"
                                    id="refreshPreviewBtn"
                                    class="btn btn-sm btn-outline-primary mr-2 mb-1">
                                    <i class="fas fa-sync-alt mr-1"></i>
                                    Refresh Preview
                                </button>

                                <button
                                    type="button"
                                    id="downloadPdfBtn"
                                    class="btn btn-sm btn-primary mb-1">
                                    <i class="fas fa-download mr-1"></i>
                                    Download PDF
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="card-body bg-light p-2 p-md-3">
                        <div class="embed-responsive" style="height: 1100px;">
                            <iframe
                                id="resumePdfPreview"
                                class="embed-responsive-item"
                                title="Live Resume PDF Preview"
                                loading="lazy"
                                allow="fullscreen">
                            </iframe>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </form>
</div>

<!-- =============================================================
     HOW IT WORKS MODAL
============================================================== -->
<div
    class="modal fade"
    id="howItWorksModal"
    tabindex="-1"
    role="dialog"
    aria-labelledby="howItWorksModalLabel"
    aria-hidden="true">

    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content">

            <div class="modal-header">
                <h5
                    class="modal-title font-weight-bold text-primary"
                    id="howItWorksModalLabel">
                    <i class="fas fa-info-circle mr-1"></i>
                    How It Works
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

                <div class="row">

                    <!-- STEP 1 -->
                    <div class="col-md-4 mb-4 mb-md-0">
                        <div class="text-primary mb-3">
                            <i class="fas fa-layer-group fa-2x"></i>
                        </div>

                        <h6 class="font-weight-bold">
                            1. Arrange Sections
                        </h6>

                        <p class="small text-muted mb-0">
                            Drag and drop the resume sections into the order
                            you want them to appear in your resume or CV.
                        </p>
                    </div>

                    <!-- STEP 2 -->
                    <div class="col-md-4 mb-4 mb-md-0">
                        <div class="text-primary mb-3">
                            <i class="fas fa-check-square fa-2x"></i>
                        </div>

                        <h6 class="font-weight-bold">
                            2. Choose Information
                        </h6>

                        <p class="small text-muted mb-0">
                            Use <strong>Choose PDF Items</strong> to select the
                            individual profile, experience, education, project,
                            skill, certification, and reference information you
                            want to include.
                        </p>
                    </div>

                    <!-- STEP 3 -->
                    <div class="col-md-4">
                        <div class="text-primary mb-3">
                            <i class="fas fa-file-pdf fa-2x"></i>
                        </div>

                        <h6 class="font-weight-bold">
                            3. Preview &amp; Download
                        </h6>

                        <p class="small text-muted mb-0">
                            The live preview and downloaded PDF use the same
                            <strong>resume-pdf.php</strong> renderer, so the
                            generated document matches the preview.
                        </p>
                    </div>

                </div>

                <hr class="my-4">

                <div class="alert alert-light border mb-0">
                    <div class="d-flex">

                        <div class="mr-3 text-primary">
                            <i class="fas fa-lightbulb"></i>
                        </div>

                        <div>
                            <h6 class="font-weight-bold mb-1">
                                Tip
                            </h6>

                            <p class="small text-muted mb-0">
                                Changes to section visibility, section order,
                                document type, and PDF item selection are
                                reflected in the live preview. Use
                                <strong>Save Resume Settings</strong> when you
                                want to save your resume configuration.
                            </p>
                        </div>

                    </div>
                </div>

            </div>

            <div class="modal-footer">
                <button
                    type="button"
                    class="btn btn-primary"
                    data-dismiss="modal">
                    <i class="fas fa-check mr-1"></i>
                    Got It
                </button>
            </div>

        </div>
    </div>
</div>

<!-- =============================================================
     CHOOSE PDF ITEMS MODAL
============================================================== -->
<div
    class="modal fade"
    id="pdfItemsModal"
    tabindex="-1"
    role="dialog"
    aria-labelledby="pdfItemsModalLabel"
    aria-hidden="true">

    <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
        <div class="modal-content">

            <div class="modal-header">
                <h5
                    class="modal-title font-weight-bold text-primary"
                    id="pdfItemsModalLabel">
                    <i class="fas fa-check-square mr-1"></i>
                    Choose PDF Items
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

                <p class="small text-muted">
                    Select exactly which information should appear in the resume/CV.
                    Changes are reflected in the live preview.
                </p>

                <div class="d-flex flex-wrap mb-3">
                    <button
                        type="button"
                        class="btn btn-sm btn-outline-primary mr-2 mb-1"
                        id="selectAllPdfItems">
                        <i class="fas fa-check-double mr-1"></i>
                        Select All
                    </button>

                    <button
                        type="button"
                        class="btn btn-sm btn-outline-secondary mb-1"
                        id="clearAllPdfItems">
                        <i class="fas fa-times mr-1"></i>
                        Clear All
                    </button>
                </div>

                <!-- PROFILE -->
                <h6 class="font-weight-bold text-primary border-bottom pb-2">
                    Profile Information
                </h6>

                <?php
                $profileItems = [
                    [
                        'id' => 'pdf_profile_name',
                        'value' => 'profile_full_name',
                        'label' => 'Full Name',
                        'visible' => $fullName !== '',
                    ],
                    [
                        'id' => 'pdf_profile_title',
                        'value' => 'profile_professional_title',
                        'label' => 'Professional Title',
                        'visible' => $professionalTitle !== '',
                    ],
                    [
                        'id' => 'pdf_profile_email',
                        'value' => 'profile_email',
                        'label' => 'Email',
                        'visible' => $email !== '',
                    ],
                    [
                        'id' => 'pdf_profile_phone',
                        'value' => 'profile_phone',
                        'label' => 'Phone',
                        'visible' => $phone !== '',
                    ],
                    [
                        'id' => 'pdf_profile_address',
                        'value' => 'profile_address',
                        'label' => 'Address',
                        'visible' => $address !== '',
                    ],
                    [
                        'id' => 'pdf_profile_website',
                        'value' => 'profile_website_url',
                        'label' => 'Website',
                        'visible' => $website !== '',
                    ],
                    [
                        'id' => 'pdf_profile_github',
                        'value' => 'profile_github_url',
                        'label' => 'GitHub',
                        'visible' => $github !== '',
                    ],
                    [
                        'id' => 'pdf_profile_linkedin',
                        'value' => 'profile_linkedin_url',
                        'label' => 'LinkedIn',
                        'visible' => $linkedin !== '',
                    ],
                    [
                        'id' => 'pdf_profile_facebook',
                        'value' => 'profile_facebook_url',
                        'label' => 'Facebook',
                        'visible' => $facebook !== '',
                    ],
                ];
                ?>

                <?php foreach ($profileItems as $item): ?>
                    <?php if ($item['visible']): ?>
                        <div class="custom-control custom-checkbox mb-2">
                            <input
                                type="checkbox"
                                class="custom-control-input pdf-item"
                                id="<?= e($item['id']) ?>"
                                value="<?= e($item['value']) ?>"
                                data-type="profile">

                            <label
                                class="custom-control-label"
                                for="<?= e($item['id']) ?>">
                                <?= e($item['label']) ?>
                            </label>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>

                <!-- SUMMARY -->
                <?php if ($summary !== ''): ?>
                    <h6 class="font-weight-bold text-primary border-bottom pb-2 mt-4">
                        Professional Summary
                    </h6>

                    <div class="custom-control custom-checkbox mb-2">
                        <input
                            type="checkbox"
                            class="custom-control-input pdf-item"
                            id="pdf_summary"
                            value="summary"
                            data-type="summary">

                        <label
                            class="custom-control-label"
                            for="pdf_summary">
                            Professional Summary
                        </label>
                    </div>
                <?php endif; ?>

                <!-- EXPERIENCE -->
                <?php if ($experience): ?>
                    <h6 class="font-weight-bold text-primary border-bottom pb-2 mt-4">
                        Experience
                    </h6>

                    <?php foreach ($experience as $item): ?>
                        <?php
                        $itemId = resume_item_id($item);

                        $jobTitle = resume_value(
                            $item,
                            ['job_title', 'position', 'role'],
                            'Experience'
                        );

                        $company = resume_value(
                            $item,
                            ['company', 'organization', 'organization_name']
                        );

                        $label = $jobTitle;

                        if ($company !== '') {
                            $label .= ' — ' . $company;
                        }
                        ?>

                        <div class="custom-control custom-checkbox mb-2">
                            <input
                                type="checkbox"
                                class="custom-control-input pdf-item"
                                id="pdf_experience_<?= $itemId ?>"
                                value="experience_<?= $itemId ?>"
                                data-type="experience"
                                data-id="<?= $itemId ?>">

                            <label
                                class="custom-control-label"
                                for="pdf_experience_<?= $itemId ?>">
                                <?= e($label) ?>
                            </label>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>

                <!-- EDUCATION -->
                <?php if ($education): ?>
                    <h6 class="font-weight-bold text-primary border-bottom pb-2 mt-4">
                        Education
                    </h6>

                    <?php foreach ($education as $item): ?>
                        <?php
                        $itemId = resume_item_id($item);

                        $degree = resume_value(
                            $item,
                            ['degree', 'program', 'course'],
                            'Education'
                        );

                        $institution = resume_value(
                            $item,
                            ['institution', 'school', 'university']
                        );

                        $label = $degree;

                        if ($institution !== '') {
                            $label .= ' — ' . $institution;
                        }
                        ?>

                        <div class="custom-control custom-checkbox mb-2">
                            <input
                                type="checkbox"
                                class="custom-control-input pdf-item"
                                id="pdf_education_<?= $itemId ?>"
                                value="education_<?= $itemId ?>"
                                data-type="education"
                                data-id="<?= $itemId ?>">

                            <label
                                class="custom-control-label"
                                for="pdf_education_<?= $itemId ?>">
                                <?= e($label) ?>
                            </label>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>

                <!-- PROJECTS -->
                <?php if ($projects): ?>
                    <h6 class="font-weight-bold text-primary border-bottom pb-2 mt-4">
                        Projects
                    </h6>

                    <?php foreach ($projects as $item): ?>
                        <?php
                        $itemId = resume_item_id($item);

                        $label = resume_value(
                            $item,
                            ['title', 'project_name', 'name'],
                            'Project'
                        );
                        ?>

                        <div class="custom-control custom-checkbox mb-2">
                            <input
                                type="checkbox"
                                class="custom-control-input pdf-item"
                                id="pdf_project_<?= $itemId ?>"
                                value="project_<?= $itemId ?>"
                                data-type="project"
                                data-id="<?= $itemId ?>">

                            <label
                                class="custom-control-label"
                                for="pdf_project_<?= $itemId ?>">
                                <?= e($label) ?>
                            </label>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>

                <!-- SKILLS -->
                <?php if ($skills): ?>
                    <h6 class="font-weight-bold text-primary border-bottom pb-2 mt-4">
                        Skills
                    </h6>

                    <?php foreach ($skills as $item): ?>
                        <?php
                        $itemId = resume_item_id($item);

                        $label = resume_value(
                            $item,
                            ['skill_name', 'name', 'skill'],
                            'Skill'
                        );
                        ?>

                        <div class="custom-control custom-checkbox mb-2">
                            <input
                                type="checkbox"
                                class="custom-control-input pdf-item"
                                id="pdf_skill_<?= $itemId ?>"
                                value="skill_<?= $itemId ?>"
                                data-type="skill"
                                data-id="<?= $itemId ?>">

                            <label
                                class="custom-control-label"
                                for="pdf_skill_<?= $itemId ?>">
                                <?= e($label) ?>
                            </label>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>

                <!-- CERTIFICATIONS -->
                <?php if ($certifications): ?>
                    <h6 class="font-weight-bold text-primary border-bottom pb-2 mt-4">
                        Certifications
                    </h6>

                    <?php foreach ($certifications as $item): ?>
                        <?php
                        $itemId = resume_item_id($item);

                        $label = resume_value(
                            $item,
                            ['name', 'title', 'certification_name'],
                            'Certification'
                        );
                        ?>

                        <div class="custom-control custom-checkbox mb-2">
                            <input
                                type="checkbox"
                                class="custom-control-input pdf-item"
                                id="pdf_certification_<?= $itemId ?>"
                                value="certification_<?= $itemId ?>"
                                data-type="certification"
                                data-id="<?= $itemId ?>">

                            <label
                                class="custom-control-label"
                                for="pdf_certification_<?= $itemId ?>">
                                <?= e($label) ?>
                            </label>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>

                <!-- REFERENCES -->
                <?php if ($references): ?>
                    <h6 class="font-weight-bold text-primary border-bottom pb-2 mt-4">
                        References
                    </h6>

                    <?php foreach ($references as $item): ?>
                        <?php
                        $itemId = resume_item_id($item);

                        $referenceName = resume_value(
                            $item,
                            ['name', 'full_name', 'reference_name'],
                            'Reference'
                        );

                        $referenceCompany = resume_value(
                            $item,
                            ['company', 'organization']
                        );

                        $label = $referenceName;

                        if ($referenceCompany !== '') {
                            $label .= ' — ' . $referenceCompany;
                        }
                        ?>

                        <div class="custom-control custom-checkbox mb-2">
                            <input
                                type="checkbox"
                                class="custom-control-input pdf-item"
                                id="pdf_reference_<?= $itemId ?>"
                                value="reference_<?= $itemId ?>"
                                data-type="reference"
                                data-id="<?= $itemId ?>">

                            <label
                                class="custom-control-label"
                                for="pdf_reference_<?= $itemId ?>">
                                <?= e($label) ?>
                            </label>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>

            </div>

            <div class="modal-footer">
                <button
                    type="button"
                    class="btn btn-secondary"
                    data-dismiss="modal">
                    Close
                </button>

                <button
                    type="button"
                    class="btn btn-primary"
                    id="applyPdfSelection"
                    data-dismiss="modal">
                    <i class="fas fa-check mr-1"></i>
                    Apply Selection
                </button>
            </div>

        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        'use strict';

        const preview = document.getElementById('resumePdfPreview');
        const downloadBtn = document.getElementById('downloadPdfBtn');
        const refreshBtn = document.getElementById('refreshPreviewBtn');
        const applySelection = document.getElementById('applyPdfSelection');

        const documentType = document.getElementById('document_type');
        const sortable = document.getElementById('sectionSortable');
        const hiddenOrder = document.getElementById('hiddenSectionOrderInputs');

        const referencesOnRequest = document.getElementById('referencesOnRequest');
        const resetOrder = document.getElementById('resetSectionOrder');

        const selectAllBtn = document.getElementById('selectAllPdfItems');
        const clearAllBtn = document.getElementById('clearAllPdfItems');

        const defaultOrder = [
            'summary',
            'experience',
            'education',
            'projects',
            'skills',
            'certifications',
            'resume_references'
        ];

        /*
         * Keep the current selection in memory while the user edits
         * the page. The values are sent directly to resume-pdf.php.
         */
        let selectedItems = new Set();

        /* =========================================================
           SECTION ORDER
        ========================================================= */

        function getSectionOrder() {
            if (!sortable) {
                return [];
            }

            return Array.from(
                sortable.querySelectorAll('[data-section]')
            ).map(function(item) {
                return item.dataset.section;
            });
        }

        function updateSectionOrderInputs() {
            if (!hiddenOrder || !sortable) {
                return;
            }

            hiddenOrder.innerHTML = '';

            getSectionOrder().forEach(function(section) {
                const input = document.createElement('input');

                input.type = 'hidden';
                input.name = 'section_order[]';
                input.value = section;

                hiddenOrder.appendChild(input);
            });
        }

        function updateSectionListUi() {
            if (!sortable) {
                return;
            }

            const toggleMap = {
                summary: 'show_summary',
                experience: 'show_experience',
                education: 'show_education',
                projects: 'show_projects',
                skills: 'show_skills',
                certifications: 'show_certifications',
                resume_references: 'show_references'
            };

            let visible = 0;
            sortable.querySelectorAll('[data-section]').forEach(function(item, index) {
                const number = item.querySelector('.resume-sort-number');
                const status = item.querySelector('.resume-section-status');
                const toggleId = toggleMap[item.dataset.section];
                const toggle = toggleId ? document.getElementById(toggleId) : null;
                const isVisible = !toggle || toggle.checked;

                if (number) number.textContent = index + 1;
                if (status) {
                    status.textContent = isVisible ? 'Included in resume' : 'Hidden from resume';
                    status.classList.toggle('text-muted', isVisible);
                    status.classList.toggle('text-warning', !isVisible);
                }

                item.classList.toggle('opacity-50', !isVisible);
                if (isVisible) visible++;
            });

            const count = document.getElementById('visibleSectionCount');
            if (count) count.textContent = visible + ' visible';
        }

        function resetSectionOrderList() {
            if (!sortable) {
                return;
            }

            const items = {};

            sortable.querySelectorAll('[data-section]').forEach(function(item) {
                items[item.dataset.section] = item;
            });

            defaultOrder.forEach(function(section) {
                if (items[section]) {
                    sortable.appendChild(items[section]);
                }
            });

            updateSectionOrderInputs();
            updateSectionListUi();
            refreshPreview();
        }

        let draggedItem = null;

        if (sortable) {
            sortable.querySelectorAll('[data-section]').forEach(function(item) {

                item.addEventListener('dragstart', function() {
                    draggedItem = this;
                    this.classList.add('shadow-sm');
                });

                item.addEventListener('dragover', function(event) {
                    event.preventDefault();
                });

                item.addEventListener('drop', function(event) {
                    event.preventDefault();

                    if (!draggedItem || draggedItem === this) {
                        return;
                    }

                    const rect = this.getBoundingClientRect();
                    const midpoint = rect.top + (rect.height / 2);

                    if (event.clientY < midpoint) {
                        sortable.insertBefore(draggedItem, this);
                    } else {
                        sortable.insertBefore(draggedItem, this.nextSibling);
                    }

                    updateSectionOrderInputs();
                    updateSectionListUi();
                    refreshPreview();
                });

                item.addEventListener('dragend', function() {
                    this.classList.remove('shadow-sm');
                    draggedItem = null;
                });
            });
        }

        if (resetOrder) {
            resetOrder.addEventListener('click', resetSectionOrderList);
        }

        /* =========================================================
   PDF ITEM SELECTION
========================================================= */

        function getPdfCheckboxes() {
            return Array.from(
                document.querySelectorAll('.pdf-item')
            );
        }

        function getSelectedItems() {

            return getPdfCheckboxes()
                .filter(function(checkbox) {
                    return checkbox.checked;
                })
                .map(function(checkbox) {
                    return checkbox.value;
                });
        }

        function syncSelectionSet() {

            selectedItems = new Set(
                getSelectedItems()
            );
        }

        function applySelectionSet() {

            getPdfCheckboxes().forEach(function(checkbox) {

                checkbox.checked =
                    selectedItems.has(
                        checkbox.value
                    );

            });
        }

        function selectAllPdfItems() {

            getPdfCheckboxes().forEach(function(checkbox) {
                checkbox.checked = true;
            });

            syncSelectionSet();

            refreshPreview();
        }

        function clearAllPdfItems() {

            getPdfCheckboxes().forEach(function(checkbox) {
                checkbox.checked = false;
            });

            syncSelectionSet();

            refreshPreview();
        }

        if (selectAllBtn) {

            selectAllBtn.addEventListener(
                'click',
                selectAllPdfItems
            );

        }

        if (clearAllBtn) {

            clearAllBtn.addEventListener(
                'click',
                clearAllPdfItems
            );

        }

        getPdfCheckboxes().forEach(function(checkbox) {

            checkbox.addEventListener(
                'change',
                function() {

                    syncSelectionSet();

                    refreshPreview();

                }
            );

        });

        if (applySelection) {

            applySelection.addEventListener(
                'click',
                function() {

                    syncSelectionSet();

                    refreshPreview();

                }
            );

        }
        /* =========================================================
           BUILD THE EXACT PDF URL
        ========================================================= */

        function buildPdfUrl(previewMode) {

            const params = new URLSearchParams();

            params.set(
                'type',
                documentType ?
                documentType.value :
                'resume'
            );

            params.set(
                'section_order',
                JSON.stringify(
                    getSectionOrder()
                )
            );

            const visibilityFields = [
                'show_summary',
                'show_experience',
                'show_education',
                'show_projects',
                'show_skills',
                'show_certifications',
                'show_references'
            ];

            visibilityFields.forEach(function(field) {

                const element =
                    document.getElementById(field);

                params.set(
                    field,
                    element && element.checked ?
                    '1' :
                    '0'
                );

            });

            params.set(
                'references_on_request',
                referencesOnRequest &&
                referencesOnRequest.checked ?
                '1' :
                '0'
            );

            /*
             * Send every selected PDF item.
             */
            getSelectedItems().forEach(function(item) {

                params.append(
                    'selected[]',
                    item
                );

            });

            if (previewMode) {

                params.set(
                    'preview',
                    '1'
                );

            }

            return 'resume-pdf.php?' +
                params.toString();
        }

        /* =========================================================
           PREVIEW
        ========================================================= */

        let previewTimer = null;

        function refreshPreview() {
            if (!preview) {
                return;
            }

            /*
             * Prevent several immediate checkbox changes from causing
             * multiple PDF loads at exactly the same moment.
             */
            clearTimeout(previewTimer);

            previewTimer = setTimeout(function() {
                const url = buildPdfUrl(true);

                preview.src =
                    url +
                    (url.indexOf('?') !== -1 ? '&' : '?') +
                    '_preview=' +
                    Date.now();
            }, 100);
        }

        /* =========================================================
           DOWNLOAD
        ========================================================= */

        function downloadPdf() {
            syncSelectionSet();

            if (!getSelectedItems().length) {
                alert('Please choose at least one PDF item.');
                return;
            }

            /*
             * This is intentionally the same URL used by the preview,
             * except preview=1 is omitted. resume-pdf.php therefore
             * renders the same document using the same data/settings.
             */
            window.location.href = buildPdfUrl(false);
        }

        if (downloadBtn) {
            downloadBtn.addEventListener('click', downloadPdf);
        }

        if (refreshBtn) {
            refreshBtn.addEventListener('click', refreshPreview);
        }

        /* =========================================================
           LIVE SETTINGS
        ========================================================= */

        document.querySelectorAll('.section-toggle').forEach(function(checkbox) {
            checkbox.addEventListener('change', function() {
                updateSectionListUi();
                refreshPreview();
            });
        });

        if (referencesOnRequest) {
            referencesOnRequest.addEventListener('change', refreshPreview);
        }

        if (documentType) {
            documentType.addEventListener('change', refreshPreview);
        }

        /*
         * Before submitting Save Settings, always synchronize the
         * current section order with the hidden form fields.
         */
        const settingsForm = document.getElementById('resumeSettingsForm');

        if (settingsForm) {
            settingsForm.addEventListener('submit', function() {
                updateSectionOrderInputs();
            });
        }

        /* =========================================================
           INITIAL SELECTION
        ========================================================= */

        /*
         * First load: select every available item so the user sees
         * all of their information in the initial resume.
         *
         * The user can then remove individual items from the modal.
         */
        getPdfCheckboxes().forEach(function(checkbox) {
            checkbox.checked = true;
        });

        syncSelectionSet();

        /* =========================================================
           INITIALIZATION
        ========================================================= */

        updateSectionOrderInputs();
        updateSectionListUi();
        refreshPreview();
    });
</script>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>