<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/maintenance-check.php';

require_role('user');

$userId = current_user_id();
$pdo    = db();

/*
|--------------------------------------------------------------------------
| Ensure show_professional_title column exists
|--------------------------------------------------------------------------
*/

try {

    $checkColumn = $pdo->query("
        SHOW COLUMNS FROM profiles LIKE 'show_professional_title'
    ");

    if (!$checkColumn->fetch()) {

        $pdo->exec("
            ALTER TABLE profiles
            ADD COLUMN show_professional_title TINYINT(1) NOT NULL DEFAULT 1
        ");
    }
} catch (Throwable $e) {

    // Ignore migration error if column already exists
    // or migration cannot be performed.

}

/*
|--------------------------------------------------------------------------
| Get current profile
|--------------------------------------------------------------------------
*/

$profile = get_profile($userId);
$user    = get_user($userId);

/*
|--------------------------------------------------------------------------
| Update profile
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    try {

        verify_csrf();

        /*
        |--------------------------------------------------------------------------
        | Basic profile information
        |--------------------------------------------------------------------------
        */

        $fullName = trim(
            (string) ($_POST['full_name'] ?? '')
        );

        $address = trim(
            (string) ($_POST['address'] ?? '')
        );

        $phone = trim(
            (string) ($_POST['phone'] ?? '')
        );

        $professionalTitle = trim(
            (string) ($_POST['professional_title'] ?? '')
        );

        $bio = trim(
            (string) ($_POST['bio'] ?? '')
        );

        $professionalSummary = trim(
            (string) ($_POST['professional_summary'] ?? '')
        );

        /*
        |--------------------------------------------------------------------------
        | Social links
        |--------------------------------------------------------------------------
        */

        $githubUrl = trim(
            (string) ($_POST['github_url'] ?? '')
        );

        $linkedinUrl = trim(
            (string) ($_POST['linkedin_url'] ?? '')
        );

        $facebookUrl = trim(
            (string) ($_POST['facebook_url'] ?? '')
        );

        $websiteUrl = trim(
            (string) ($_POST['website_url'] ?? '')
        );

        /*
        |--------------------------------------------------------------------------
        | Visibility settings
        |--------------------------------------------------------------------------
        */

        $showProfessionalTitle = isset(
            $_POST['show_professional_title']
        ) ? 1 : 0;

        $showSocials = isset(
            $_POST['show_socials']
        ) ? 1 : 0;

        /*
        |--------------------------------------------------------------------------
        | Upload profile image
        |--------------------------------------------------------------------------
        */

        $newProfileImage = null;

        if (
            isset($_FILES['profile_image']) &&
            isset($_FILES['profile_image']['error']) &&
            $_FILES['profile_image']['error'] !== UPLOAD_ERR_NO_FILE
        ) {

            $newProfileImage = upload_file(
                'profile_image',
                [
                    'jpg',
                    'jpeg',
                    'png',
                    'webp'
                ],
                'profile'
            );

            if (empty($newProfileImage)) {

                throw new RuntimeException(
                    'The profile photo could not be uploaded.'
                );
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Update profile
        |--------------------------------------------------------------------------
        */

        if (!empty($newProfileImage)) {

            $stmt = $pdo->prepare("
                UPDATE profiles
                SET
                    full_name = ?,
                    address = ?,
                    phone = ?,
                    professional_title = ?,
                    show_professional_title = ?,
                    bio = ?,
                    professional_summary = ?,
                    github_url = ?,
                    linkedin_url = ?,
                    facebook_url = ?,
                    website_url = ?,
                    show_socials = ?,
                    profile_image = ?
                WHERE user_id = ?
            ");

            $stmt->execute([
                $fullName,
                $address,
                $phone,
                $professionalTitle,
                $showProfessionalTitle,
                $bio,
                $professionalSummary,
                $githubUrl,
                $linkedinUrl,
                $facebookUrl,
                $websiteUrl,
                $showSocials,
                $newProfileImage,
                $userId
            ]);

            flash(
                'success',
                'Profile and profile photo updated successfully.'
            );
        } else {

            $stmt = $pdo->prepare("
                UPDATE profiles
                SET
                    full_name = ?,
                    address = ?,
                    phone = ?,
                    professional_title = ?,
                    show_professional_title = ?,
                    bio = ?,
                    professional_summary = ?,
                    github_url = ?,
                    linkedin_url = ?,
                    facebook_url = ?,
                    website_url = ?,
                    show_socials = ?
                WHERE user_id = ?
            ");

            $stmt->execute([
                $fullName,
                $address,
                $phone,
                $professionalTitle,
                $showProfessionalTitle,
                $bio,
                $professionalSummary,
                $githubUrl,
                $linkedinUrl,
                $facebookUrl,
                $websiteUrl,
                $showSocials,
                $userId
            ]);

            flash(
                'success',
                'Profile updated successfully.'
            );
        }
    } catch (Throwable $e) {

        flash(
            'danger',
            $e->getMessage()
        );
    }

    redirect('user/profile.php');
}

/*
|--------------------------------------------------------------------------
| Refresh profile after update
|--------------------------------------------------------------------------
*/

$profile = get_profile($userId);
$user    = get_user($userId);

/*
|--------------------------------------------------------------------------
| Profile image
|--------------------------------------------------------------------------
*/

$profileImage = '';

if (!empty($profile['profile_image'])) {

    $storedImage = (string) $profile['profile_image'];

    if (
        filter_var(
            $storedImage,
            FILTER_VALIDATE_URL
        )
    ) {

        $profileImage = $storedImage;
    } else {

        $profileImage = asset(
            ltrim($storedImage, '/')
        );
    }
}

/*
|--------------------------------------------------------------------------
| Initials
|--------------------------------------------------------------------------
*/

$fullNameForInitials = trim(
    (string) (
        $profile['full_name']
        ?? $user['name']
        ?? $user['username']
        ?? 'User'
    )
);

$nameParts = preg_split(
    '/\s+/',
    $fullNameForInitials
);

$initials = '';

if (!empty($nameParts)) {

    $initials .= strtoupper(
        substr(
            (string) $nameParts[0],
            0,
            1
        )
    );

    if (count($nameParts) > 1) {

        $initials .= strtoupper(
            substr(
                (string) $nameParts[count($nameParts) - 1],
                0,
                1
            )
        );
    }
}

if ($initials === '') {
    $initials = 'U';
}

/*
|--------------------------------------------------------------------------
| Email
|--------------------------------------------------------------------------
*/

$email = (string) (
    $_SESSION['email']
    ?? $user['email']
    ?? ''
);

$pageTitle = 'Profile';

require_once __DIR__ . '/../includes/header.php';

?>

<!-- ==============================================================
     Custom Profile / Modal UI
     Uses SB Admin 2 Bootstrap classes
     ============================================================== -->

<style>
    /* -----------------------------------------------------------------
   Edit Profile Modal
   ----------------------------------------------------------------- */

    .profile-edit-modal .modal-dialog {
        max-width: 900px;
        margin: 1.75rem auto;
    }

    .profile-edit-modal .modal-content {
        border: 0;
        border-radius: .5rem;
        overflow: hidden;
        box-shadow: 0 .5rem 1.5rem rgba(58, 59, 69, .25);
    }

    .profile-edit-modal .modal-header {
        background: #4e73df;
        color: #fff;
        border-bottom: 0;
        padding: 1rem 1.5rem;
    }

    .profile-edit-modal .modal-header .modal-title {
        font-size: 1rem;
        font-weight: 700;
    }

    .profile-edit-modal .modal-header .close {
        color: #fff;
        opacity: 1;
        text-shadow: none;
    }

    .profile-edit-modal .modal-header .close:hover {
        color: #fff;
        opacity: .75;
    }

    .profile-edit-modal .modal-body {
        padding: 1.5rem;
        max-height: calc(100vh - 210px);
        overflow-y: auto;
    }

    /* Section headings */

    .profile-section-title {
        display: flex;
        align-items: center;
        margin-bottom: 1rem;
        padding-bottom: .65rem;
        border-bottom: 1px solid #e3e6f0;
    }

    .profile-section-title i {
        width: 30px;
        height: 30px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        margin-right: .6rem;
        border-radius: 50%;
        background: rgba(78, 115, 223, .1);
        color: #4e73df;
    }

    .profile-section-title h6 {
        margin: 0;
        font-weight: 700;
        color: #4e73df;
    }

    /* Form */

    .profile-edit-modal .form-group {
        margin-bottom: 1rem;
    }

    .profile-edit-modal label {
        font-size: .85rem;
        font-weight: 600;
        color: #5a5c69;
        margin-bottom: .4rem;
    }

    .profile-edit-modal .required-mark {
        color: #e74a3b;
        font-weight: 700;
    }

    .profile-edit-modal .form-control {
        border: 1px solid #d1d3e2;
        border-radius: .35rem;
        font-size: .875rem;
    }

    .profile-edit-modal .form-control:focus {
        border-color: #4e73df;
        box-shadow: 0 0 0 .2rem rgba(78, 115, 223, .15);
    }

    .profile-edit-modal textarea.form-control {
        resize: vertical;
    }

    /* Profile photo */

    .profile-photo-editor {
        display: flex;
        align-items: center;
        padding: 1rem;
        background: #f8f9fc;
        border: 1px solid #e3e6f0;
        border-radius: .5rem;
        margin-bottom: 1.25rem;
    }

    .profile-photo-preview {
        width: 65px;
        height: 65px;
        min-width: 65px;
        object-fit: cover;
        border-radius: 50%;
        border: 3px solid #fff;
        box-shadow: 0 .15rem .4rem rgba(0, 0, 0, .12);
        margin-right: 1rem;
    }

    .profile-photo-initials {
        width: 65px;
        height: 65px;
        min-width: 65px;
        border-radius: 50%;
        background: #4e73df;
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
        font-weight: 700;
        margin-right: 1rem;
    }

    .profile-photo-info {
        flex: 1;
    }

    .profile-photo-info .title {
        font-size: .875rem;
        font-weight: 700;
        color: #5a5c69;
        margin-bottom: .2rem;
    }

    .profile-photo-info .description {
        font-size: .75rem;
        color: #858796;
        margin-bottom: 0;
    }

    /* File input */

    .profile-edit-modal .custom-file-label {
        font-weight: 400;
        color: #858796;
    }

    .profile-edit-modal .custom-file-label::after {
        content: "Browse";
    }

    /* Social links */

    .social-input-icon {
        position: relative;
    }

    .social-input-icon>i {
        position: absolute;
        left: .85rem;
        top: 50%;
        transform: translateY(-50%);
        z-index: 2;
        color: #858796;
    }

    .social-input-icon .form-control {
        padding-left: 2.4rem;
    }

    /* Visibility */

    .visibility-box {
        background: #f8f9fc;
        border: 1px solid #e3e6f0;
        border-radius: .35rem;
        padding: 1rem;
    }

    .visibility-box .custom-control {
        margin-bottom: .75rem;
    }

    .visibility-box .custom-control:last-child {
        margin-bottom: 0;
    }

    .visibility-box .custom-control-label {
        font-size: .875rem;
        font-weight: 400;
        color: #5a5c69;
    }

    /* Required note */

    .required-note {
        font-size: .75rem;
        color: #858796;
    }

    .required-note .required-mark {
        color: #e74a3b;
        font-weight: 700;
    }

    /* Footer */

    .profile-edit-modal .modal-footer {
        background: #f8f9fc;
        border-top: 1px solid #e3e6f0;
        padding: .85rem 1.5rem;
    }

    .profile-edit-modal .modal-footer .btn {
        min-width: 110px;
    }

    /* Scrollbar */

    .profile-edit-modal .modal-body::-webkit-scrollbar {
        width: 7px;
    }

    .profile-edit-modal .modal-body::-webkit-scrollbar-track {
        background: #f8f9fc;
    }

    .profile-edit-modal .modal-body::-webkit-scrollbar-thumb {
        background: #d1d3e2;
        border-radius: 10px;
    }

    .profile-edit-modal .modal-body::-webkit-scrollbar-thumb:hover {
        background: #b7b9c4;
    }

    /* Mobile */

    @media (max-width: 767.98px) {

        .profile-edit-modal .modal-dialog {
            max-width: calc(100% - 1rem);
            margin: .5rem auto;
        }

        .profile-edit-modal .modal-body {
            padding: 1rem;
            max-height: calc(100vh - 130px);
        }

        .profile-edit-modal .modal-header {
            padding: .9rem 1rem;
        }

        .profile-edit-modal .modal-footer {
            padding: .75rem 1rem;
        }

        .profile-edit-modal .modal-footer .btn {
            width: 100%;
            margin: .15rem 0;
        }

        .profile-photo-editor {
            align-items: flex-start;
        }
    }
</style>


<div class="container-fluid">

    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">

        <div>

            <h1 class="h3 mb-0 text-gray-800">
                Profile
            </h1>

            <p class="mb-0 text-muted">
                Manage your personal information and professional profile.
            </p>

        </div>

    </div>


    <!-- Profile Hero -->
    <div class="card shadow mb-4">

        <div class="card-body">

            <div class="row align-items-center">

                <div class="col-md-2 text-center mb-3 mb-md-0">

                    <?php if ($profileImage !== ''): ?>

                        <img
                            src="<?= e($profileImage) ?>"
                            alt="Profile Photo"
                            class="img-profile rounded-circle img-thumbnail"
                            width="130"
                            height="130">

                    <?php else: ?>

                        <div
                            class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center mx-auto"
                            style="width:130px;height:130px;font-size:42px;">

                            <?= e($initials) ?>

                        </div>

                    <?php endif; ?>

                </div>


                <div class="col-md-7">

                    <h2 class="h4 text-gray-900 mb-1">

                        <?= e(
                            $profile['full_name']
                                ?? 'Your Name'
                        ) ?>

                    </h2>

                    <?php if (!empty($profile['professional_title'])): ?>

                        <p class="text-primary mb-2">

                            <?= e(
                                $profile['professional_title']
                            ) ?>

                        </p>

                    <?php endif; ?>


                    <?php if ($email !== ''): ?>

                        <p class="text-muted mb-0">

                            <i class="fas fa-envelope mr-2"></i>

                            <?= e($email) ?>

                        </p>

                    <?php endif; ?>

                </div>


                <div class="col-md-3 text-md-right mt-3 mt-md-0">

                    <button
                        type="button"
                        class="btn btn-primary"
                        data-toggle="modal"
                        data-target="#editProfileModal">

                        <i class="fas fa-edit mr-1"></i>

                        Edit Profile

                    </button>

                </div>

            </div>

        </div>

    </div>


    <!-- Profile Information -->
    <div class="card shadow mb-4">

        <div class="card-header py-3">

            <h6 class="m-0 font-weight-bold text-primary">

                Profile Information

            </h6>

        </div>


        <div class="card-body">

            <div class="row">

                <!-- Full Name -->
                <div class="col-md-6 mb-4">

                    <label class="font-weight-bold text-gray-700">
                        Full Name
                    </label>

                    <p class="text-gray-600 mb-0">

                        <?= e(
                            $profile['full_name']
                                ?? 'Not provided'
                        ) ?>

                    </p>

                </div>


                <!-- Professional Title -->
                <div class="col-md-6 mb-4">

                    <label class="font-weight-bold text-gray-700">
                        Professional Title
                    </label>

                    <p class="text-gray-600 mb-0">

                        <?= e(
                            $profile['professional_title']
                                ?? 'Not provided'
                        ) ?>

                    </p>

                </div>


                <!-- Phone -->
                <div class="col-md-6 mb-4">

                    <label class="font-weight-bold text-gray-700">
                        Phone
                    </label>

                    <p class="text-gray-600 mb-0">

                        <?= !empty($profile['phone'])
                            ? e($profile['phone'])
                            : 'Not provided'
                        ?>

                    </p>

                </div>


                <!-- Address -->
                <div class="col-md-6 mb-4">

                    <label class="font-weight-bold text-gray-700">
                        Address
                    </label>

                    <p class="text-gray-600 mb-0">

                        <?= !empty($profile['address'])
                            ? e($profile['address'])
                            : 'Not provided'
                        ?>

                    </p>

                </div>


                <!-- GitHub -->
                <div class="col-md-6 mb-4">

                    <label class="font-weight-bold text-gray-700">
                        GitHub
                    </label>

                    <p class="mb-0">

                        <?php if (!empty($profile['github_url'])): ?>

                            <a
                                href="<?= e($profile['github_url']) ?>"
                                target="_blank"
                                rel="noopener noreferrer">

                                <i class="fab fa-github mr-1"></i>

                                <?= e(
                                    $profile['github_url']
                                ) ?>

                            </a>

                        <?php else: ?>

                            <span class="text-gray-600">
                                Not provided
                            </span>

                        <?php endif; ?>

                    </p>

                </div>


                <!-- LinkedIn -->
                <div class="col-md-6 mb-4">

                    <label class="font-weight-bold text-gray-700">
                        LinkedIn
                    </label>

                    <p class="mb-0">

                        <?php if (!empty($profile['linkedin_url'])): ?>

                            <a
                                href="<?= e($profile['linkedin_url']) ?>"
                                target="_blank"
                                rel="noopener noreferrer">

                                <i class="fab fa-linkedin mr-1"></i>

                                <?= e(
                                    $profile['linkedin_url']
                                ) ?>

                            </a>

                        <?php else: ?>

                            <span class="text-gray-600">
                                Not provided
                            </span>

                        <?php endif; ?>

                    </p>

                </div>


                <!-- Facebook -->
                <div class="col-md-6 mb-4">

                    <label class="font-weight-bold text-gray-700">
                        Facebook
                    </label>

                    <p class="mb-0">

                        <?php if (!empty($profile['facebook_url'])): ?>

                            <a
                                href="<?= e($profile['facebook_url']) ?>"
                                target="_blank"
                                rel="noopener noreferrer">

                                <i class="fab fa-facebook mr-1"></i>

                                <?= e(
                                    $profile['facebook_url']
                                ) ?>

                            </a>

                        <?php else: ?>

                            <span class="text-gray-600">
                                Not provided
                            </span>

                        <?php endif; ?>

                    </p>

                </div>


                <!-- Personal Website -->
                <div class="col-md-6 mb-4">

                    <label class="font-weight-bold text-gray-700">
                        Personal Website
                    </label>

                    <p class="mb-0">

                        <?php if (!empty($profile['website_url'])): ?>

                            <a
                                href="<?= e($profile['website_url']) ?>"
                                target="_blank"
                                rel="noopener noreferrer">

                                <i class="fas fa-globe mr-1"></i>

                                <?= e(
                                    $profile['website_url']
                                ) ?>

                            </a>

                        <?php else: ?>

                            <span class="text-gray-600">
                                Not provided
                            </span>

                        <?php endif; ?>

                    </p>

                </div>


                <!-- Bio -->
                <div class="col-12 mb-4">

                    <label class="font-weight-bold text-gray-700">
                        Bio
                    </label>

                    <p class="text-gray-600 mb-0">

                        <?= !empty($profile['bio'])
                            ? nl2br(
                                e($profile['bio'])
                            )
                            : 'Not provided'
                        ?>

                    </p>

                </div>


                <!-- Professional Summary -->
                <div class="col-12">

                    <label class="font-weight-bold text-gray-700">
                        Professional Summary
                    </label>

                    <p class="text-gray-600 mb-0">

                        <?= !empty($profile['professional_summary'])
                            ? nl2br(
                                e(
                                    $profile['professional_summary']
                                )
                            )
                            : 'Not provided'
                        ?>

                    </p>

                </div>

            </div>

        </div>

    </div>

</div>


<!-- ==============================================================
     Edit Profile Modal
     ============================================================== -->

<div
    class="modal fade profile-edit-modal"
    id="editProfileModal"
    tabindex="-1"
    role="dialog"
    aria-labelledby="editProfileModalLabel"
    aria-hidden="true">

    <div
        class="modal-dialog modal-xl modal-dialog-centered"
        role="document">

        <div class="modal-content">

            <!-- Modal Header -->
            <div class="modal-header">

                <h5
                    class="modal-title"
                    id="editProfileModalLabel">

                    <i class="fas fa-user-edit mr-2"></i>

                    Edit Profile

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


            <!-- IMPORTANT:
                 enctype is required for profile photo uploads.
            -->

            <form
                method="POST"
                action="<?= e(asset('user/profile.php')) ?>"
                enctype="multipart/form-data">

                <?= csrf_field() ?>


                <!-- Modal Body -->
                <div class="modal-body">

                    <!-- Required note -->
                    <div class="d-flex justify-content-end mb-3">

                        <span class="required-note">

                            <span class="required-mark">*</span>
                            Required fields

                        </span>

                    </div>


                    <!-- ==================================================
                         Profile Photo
                         ================================================== -->

                    <div class="profile-section-title">

                        <i class="fas fa-camera"></i>

                        <h6>
                            Profile Photo
                        </h6>

                    </div>


                    <div class="profile-photo-editor">

                        <?php if ($profileImage !== ''): ?>

                            <img
                                src="<?= e($profileImage) ?>"
                                alt="Current Profile Photo"
                                class="profile-photo-preview">

                        <?php else: ?>

                            <div class="profile-photo-initials">

                                <?= e($initials) ?>

                            </div>

                        <?php endif; ?>


                        <div class="profile-photo-info">

                            <div class="title">
                                Update your profile photo
                            </div>

                            <p class="description">
                                JPG, JPEG, PNG, or WEBP.
                            </p>

                        </div>

                    </div>


                    <div class="form-group">

                        <label
                            for="profile_image">

                            Choose New Photo
                            <span class="text-muted font-weight-normal">
                                (Optional)
                            </span>

                        </label>

                        <div class="custom-file">

                            <input
                                type="file"
                                class="custom-file-input"
                                id="profile_image"
                                name="profile_image"
                                accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">

                            <label
                                class="custom-file-label"
                                for="profile_image">

                                Choose file

                            </label>

                        </div>

                    </div>


                    <hr class="my-4">


                    <!-- ==================================================
                         Personal Information
                         ================================================== -->

                    <div class="profile-section-title">

                        <i class="fas fa-user"></i>

                        <h6>
                            Personal Information
                        </h6>

                    </div>


                    <div class="row">

                        <!-- Full Name -->
                        <div class="col-md-6">

                            <div class="form-group">

                                <label for="full_name">

                                    Full Name
                                    <span class="required-mark">*</span>

                                </label>

                                <input
                                    type="text"
                                    class="form-control"
                                    id="full_name"
                                    name="full_name"
                                    value="<?= e(
                                                $profile['full_name']
                                                    ?? ''
                                            ) ?>"
                                    placeholder="Enter your full name"
                                    required>

                            </div>

                        </div>


                        <!-- Professional Title -->
                        <div class="col-md-6">

                            <div class="form-group">

                                <label for="professional_title">

                                    Professional Title
                                    <span class="text-muted font-weight-normal">
                                        (Optional)
                                    </span>

                                </label>

                                <input
                                    type="text"
                                    class="form-control"
                                    id="professional_title"
                                    name="professional_title"
                                    value="<?= e(
                                                $profile['professional_title']
                                                    ?? ''
                                            ) ?>"
                                    placeholder="e.g. Web Developer">

                            </div>

                        </div>


                        <!-- Email -->
                        <div class="col-md-6">

                            <div class="form-group">

                                <label for="email">

                                    Email

                                </label>

                                <input
                                    type="email"
                                    class="form-control"
                                    id="email"
                                    value="<?= e($email) ?>"
                                    disabled>

                                <small class="form-text text-muted">

                                    <i class="fas fa-info-circle mr-1"></i>

                                    Your email address is managed through
                                    your Google account.

                                </small>

                            </div>

                        </div>


                        <!-- Phone -->
                        <div class="col-md-6">

                            <div class="form-group">

                                <label for="phone">

                                    Phone
                                    <span class="text-muted font-weight-normal">
                                        (Optional)
                                    </span>

                                </label>

                                <input
                                    type="text"
                                    class="form-control"
                                    id="phone"
                                    name="phone"
                                    value="<?= e(
                                                $profile['phone']
                                                    ?? ''
                                            ) ?>"
                                    placeholder="Enter your phone number">

                            </div>

                        </div>


                        <!-- Address -->
                        <div class="col-12">

                            <div class="form-group">

                                <label for="address">

                                    Address
                                    <span class="text-muted font-weight-normal">
                                        (Optional)
                                    </span>

                                </label>

                                <input
                                    type="text"
                                    class="form-control"
                                    id="address"
                                    name="address"
                                    value="<?= e(
                                                $profile['address']
                                                    ?? ''
                                            ) ?>"
                                    placeholder="Enter your address">

                            </div>

                        </div>

                    </div>


                    <!-- ==================================================
                         About
                         ================================================== -->

                    <div class="profile-section-title mt-3">

                        <i class="fas fa-align-left"></i>

                        <h6>
                            About You
                        </h6>

                    </div>


                    <div class="row">

                        <!-- Bio -->
                        <div class="col-md-6">

                            <div class="form-group">

                                <label for="bio">

                                    Bio
                                    <span class="text-muted font-weight-normal">
                                        (Optional)
                                    </span>

                                </label>

                                <textarea
                                    class="form-control"
                                    id="bio"
                                    name="bio"
                                    rows="5"
                                    placeholder="Tell visitors a little about yourself."><?= e(
                                                                                                $profile['bio']
                                                                                                    ?? ''
                                                                                            ) ?></textarea>

                            </div>

                        </div>


                        <!-- Professional Summary -->
                        <div class="col-md-6">

                            <div class="form-group">

                                <label for="professional_summary">

                                    Professional Summary
                                    <span class="text-muted font-weight-normal">
                                        (Optional)
                                    </span>

                                </label>

                                <textarea
                                    class="form-control"
                                    id="professional_summary"
                                    name="professional_summary"
                                    rows="5"
                                    placeholder="Write a short professional summary."><?= e(
                                                                                            $profile['professional_summary']
                                                                                                ?? ''
                                                                                        ) ?></textarea>

                            </div>

                        </div>

                    </div>


                    <hr class="my-4">


                    <!-- ==================================================
                         Social Links
                         ================================================== -->

                    <div class="profile-section-title">

                        <i class="fas fa-share-alt"></i>

                        <h6>
                            Social Links
                        </h6>

                    </div>


                    <div class="row">

                        <!-- GitHub -->
                        <div class="col-md-6">

                            <div class="form-group">

                                <label for="github_url">

                                    <i class="fab fa-github mr-1"></i>

                                    GitHub URL

                                    <span class="text-muted font-weight-normal">
                                        (Optional)
                                    </span>

                                </label>

                                <div class="social-input-icon">

                                    <i class="fab fa-github"></i>

                                    <input
                                        type="url"
                                        class="form-control"
                                        id="github_url"
                                        name="github_url"
                                        value="<?= e(
                                                    $profile['github_url']
                                                        ?? ''
                                                ) ?>"
                                        placeholder="https://github.com/username">

                                </div>

                            </div>

                        </div>


                        <!-- LinkedIn -->
                        <div class="col-md-6">

                            <div class="form-group">

                                <label for="linkedin_url">

                                    <i class="fab fa-linkedin mr-1"></i>

                                    LinkedIn URL

                                    <span class="text-muted font-weight-normal">
                                        (Optional)
                                    </span>

                                </label>

                                <div class="social-input-icon">

                                    <i class="fab fa-linkedin"></i>

                                    <input
                                        type="url"
                                        class="form-control"
                                        id="linkedin_url"
                                        name="linkedin_url"
                                        value="<?= e(
                                                    $profile['linkedin_url']
                                                        ?? ''
                                                ) ?>"
                                        placeholder="https://linkedin.com/in/username">

                                </div>

                            </div>

                        </div>


                        <!-- Facebook -->
                        <div class="col-md-6">

                            <div class="form-group">

                                <label for="facebook_url">

                                    <i class="fab fa-facebook mr-1"></i>

                                    Facebook URL

                                    <span class="text-muted font-weight-normal">
                                        (Optional)
                                    </span>

                                </label>

                                <div class="social-input-icon">

                                    <i class="fab fa-facebook"></i>

                                    <input
                                        type="url"
                                        class="form-control"
                                        id="facebook_url"
                                        name="facebook_url"
                                        value="<?= e(
                                                    $profile['facebook_url']
                                                        ?? ''
                                                ) ?>"
                                        placeholder="https://facebook.com/username">

                                </div>

                            </div>

                        </div>


                        <!-- Website -->
                        <div class="col-md-6">

                            <div class="form-group">

                                <label for="website_url">

                                    <i class="fas fa-globe mr-1"></i>

                                    Personal Website

                                    <span class="text-muted font-weight-normal">
                                        (Optional)
                                    </span>

                                </label>

                                <div class="social-input-icon">

                                    <i class="fas fa-globe"></i>

                                    <input
                                        type="url"
                                        class="form-control"
                                        id="website_url"
                                        name="website_url"
                                        value="<?= e(
                                                    $profile['website_url']
                                                        ?? ''
                                                ) ?>"
                                        placeholder="https://example.com">

                                </div>

                            </div>

                        </div>

                    </div>


                    <hr class="my-4">


                    <!-- ==================================================
                         Visibility
                         ================================================== -->

                    <div class="profile-section-title">

                        <i class="fas fa-eye"></i>

                        <h6>
                            Visibility Settings
                        </h6>

                    </div>


                    <div class="visibility-box">

                        <div class="custom-control custom-checkbox">

                            <input
                                type="checkbox"
                                class="custom-control-input"
                                id="show_professional_title"
                                name="show_professional_title"
                                value="1"
                                <?= !empty($profile['show_professional_title'])
                                    ? 'checked'
                                    : ''
                                ?>>

                            <label
                                class="custom-control-label"
                                for="show_professional_title">

                                Show professional title

                            </label>

                        </div>


                        <div class="custom-control custom-checkbox">

                            <input
                                type="checkbox"
                                class="custom-control-input"
                                id="show_socials"
                                name="show_socials"
                                value="1"
                                <?= !empty($profile['show_socials'])
                                    ? 'checked'
                                    : ''
                                ?>>

                            <label
                                class="custom-control-label"
                                for="show_socials">

                                Show social links

                            </label>

                        </div>

                    </div>

                </div>


                <!-- Modal Footer -->
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


<!-- ==============================================================
     File Input Filename
     ============================================================== -->

<script>
    document.addEventListener(
        'DOMContentLoaded',
        function() {

            var fileInput =
                document.getElementById(
                    'profile_image'
                );

            if (fileInput) {

                fileInput.addEventListener(
                    'change',
                    function() {

                        var fileName = '';

                        if (
                            this.files &&
                            this.files.length > 0
                        ) {

                            fileName =
                                this.files[0].name;
                        }

                        var label =
                            document.querySelector(
                                'label[for="profile_image"]'
                            );

                        if (label) {

                            label.textContent =
                                fileName !== '' ?
                                fileName :
                                'Choose file';
                        }

                    }
                );
            }

        }
    );
</script>


<?php

require_once __DIR__ . '/../includes/footer.php';

?>