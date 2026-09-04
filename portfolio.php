<?php

declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}


/*
|--------------------------------------------------------------------------
| HELPER FUNCTIONS
|--------------------------------------------------------------------------
*/

if (!function_exists('p_value')) {
    function p_value(
        array $row,
        string $key,
        string $default = ''
    ): string {
        $value = $row[$key] ?? $default;

        if ($value === null) {
            return $default;
        }

        return trim((string) $value);
    }
}


if (!function_exists('p_date')) {
    function p_date(
        array $row,
        string $key
    ): string {
        $value = trim(
            (string) ($row[$key] ?? '')
        );

        if ($value === '') {
            return '';
        }

        $timestamp = strtotime($value);

        if ($timestamp === false) {
            return $value;
        }

        return date('M Y', $timestamp);
    }
}


if (!function_exists('full_date')) {
    function full_date(
        array $row,
        string $key
    ): string {
        $value = trim(
            (string) ($row[$key] ?? '')
        );

        if ($value === '') {
            return '';
        }

        $timestamp = strtotime($value);

        if ($timestamp === false) {
            return $value;
        }

        return date('F j, Y', $timestamp);
    }
}


if (!function_exists('social_link')) {
    function social_link(string $url): string
    {
        $url = trim($url);

        if ($url === '') {
            return '';
        }

        if (!preg_match('~^https?://~i', $url)) {
            $url = 'https://' . $url;
        }

        return $url;
    }
}


/*
|--------------------------------------------------------------------------
| PROFILE IMAGE
|--------------------------------------------------------------------------
*/

if (!function_exists('profile_image_url')) {
    function profile_image_url(array $profile): string
    {
        $image = trim(
            (string) (
                $profile['profile_image'] ?? ''
            )
        );

        if ($image === '') {
            return '';
        }

        if (
            filter_var(
                $image,
                FILTER_VALIDATE_URL
            )
        ) {
            return $image;
        }

        $image = str_replace(
            '\\',
            '/',
            $image
        );

        $image = ltrim(
            $image,
            '/'
        );

        if (
            str_starts_with(
                strtolower($image),
                'uploads/'
            )
        ) {
            return $image;
        }

        if (
            str_starts_with(
                strtolower($image),
                'profile/'
            )
        ) {
            return 'uploads/' . $image;
        }

        return 'uploads/profile/' . basename($image);
    }
}


/*
|--------------------------------------------------------------------------
| PROJECT IMAGE
|--------------------------------------------------------------------------
*/

if (!function_exists('project_image_url')) {
    function project_image_url(array $project): string
    {
        $image = '';

        $possibleFields = [
            'image',
            'project_image',
            'image_path',
            'thumbnail'
        ];

        foreach ($possibleFields as $field) {

            if (
                isset($project[$field]) &&
                trim((string) $project[$field]) !== ''
            ) {
                $image = trim(
                    (string) $project[$field]
                );

                break;
            }
        }

        if ($image === '') {
            return '';
        }

        if (
            filter_var(
                $image,
                FILTER_VALIDATE_URL
            )
        ) {
            return $image;
        }

        $image = str_replace(
            '\\',
            '/',
            $image
        );

        $image = ltrim(
            $image,
            '/'
        );

        if (
            str_starts_with(
                strtolower($image),
                'uploads/'
            )
        ) {
            return $image;
        }

        if (
            str_starts_with(
                strtolower($image),
                'projects/'
            )
        ) {
            return 'uploads/' . $image;
        }

        return 'uploads/projects/' . basename($image);
    }
}


/*
|--------------------------------------------------------------------------
| PORTFOLIO UNAVAILABLE
|--------------------------------------------------------------------------
*/

if (!function_exists('portfolio_unavailable')) {

    function portfolio_unavailable(
        string $title,
        string $message
    ): never {

        http_response_code(404);
?>
        <!DOCTYPE html>

        <html lang="en">

        <head>

            <meta charset="UTF-8">

            <meta
                name="viewport"
                content="width=device-width, initial-scale=1.0">

            <title>
                <?= e($title) ?>
            </title>

            <link
                rel="preconnect"
                href="https://fonts.googleapis.com">

            <link
                rel="preconnect"
                href="https://fonts.gstatic.com"
                crossorigin>

            <link
                href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap"
                rel="stylesheet">

            <link
                rel="stylesheet"
                href="https://cdn.jsdelivr.net/npm/@mdi/font@7.4.47/css/materialdesignicons.min.css">

            <link
                rel="stylesheet"
                href="css/portfolio.css">

        </head>

        <body>

            <main
                style="
            min-height:100vh;
            display:flex;
            align-items:center;
            justify-content:center;
            padding:30px;
            background:#f7faff;
        ">

                <div
                    style="
                width:100%;
                max-width:520px;
                padding:45px 35px;
                text-align:center;
                background:#ffffff;
                border:1px solid #e7edf5;
                border-radius:18px;
                box-shadow:0 12px 35px rgba(30,64,175,.07);
            ">

                    <div
                        style="
                    width:64px;
                    height:64px;
                    margin:0 auto 20px;
                    display:flex;
                    align-items:center;
                    justify-content:center;
                    border-radius:16px;
                    background:#eef5ff;
                    color:#0d6efd;
                    font-size:30px;
                ">

                        <i class="mdi mdi-web-off"></i>

                    </div>

                    <h1
                        style="
                    margin:0 0 10px;
                    color:#172033;
                    font-size:25px;
                    font-weight:800;
                ">

                        <?= e($title) ?>

                    </h1>

                    <p
                        style="
                    margin:0;
                    color:#64748b;
                    font-size:14px;
                    line-height:1.8;
                ">

                        <?= e($message) ?>

                    </p>

                </div>

            </main>

        </body>

        </html>
<?php
        exit;
    }
}


/*
|--------------------------------------------------------------------------
| GET USERNAME
|--------------------------------------------------------------------------
*/

$username = trim(
    (string) ($_GET['username'] ?? '')
);

if ($username === '') {

    portfolio_unavailable(
        'Portfolio Not Found',
        'The portfolio you are looking for could not be found.'
    );
}


/*
|--------------------------------------------------------------------------
| DATABASE
|--------------------------------------------------------------------------
*/

$pdo = db();


/*
|--------------------------------------------------------------------------
| GET USER
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare(
    "
    SELECT
        id,
        username,
        email,
        role,
        account_status
    FROM users
    WHERE username = ?
    LIMIT 1
    "
);

$stmt->execute([
    $username
]);

$user = $stmt->fetch(
    PDO::FETCH_ASSOC
);

if (!$user) {

    portfolio_unavailable(
        'Portfolio Not Found',
        'This portfolio does not exist or may have been removed.'
    );
}


/*
|--------------------------------------------------------------------------
| ACCOUNT STATUS
|--------------------------------------------------------------------------
*/

$accountStatus = strtolower(
    trim(
        (string) (
            $user['account_status'] ?? 'active'
        )
    )
);

if (
    $accountStatus !== '' &&
    !in_array(
        $accountStatus,
        [
            'active',
            'approved',
            'enabled'
        ],
        true
    )
) {

    portfolio_unavailable(
        'Portfolio Unavailable',
        'This portfolio is currently unavailable.'
    );
}


/*
|--------------------------------------------------------------------------
| GET PROFILE
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare(
    "
    SELECT *
    FROM profiles
    WHERE user_id = ?
    LIMIT 1
    "
);

$stmt->execute([
    (int) $user['id']
]);

$profile = $stmt->fetch(
    PDO::FETCH_ASSOC
);

if (!$profile) {

    portfolio_unavailable(
        'Portfolio Unavailable',
        'This user has not created a public portfolio yet.'
    );

    http_response_code(404);

    require __DIR__ . '/404.php';

    exit;
}


/*
|--------------------------------------------------------------------------
| PORTFOLIO VISIBILITY
|--------------------------------------------------------------------------
*/

$portfolioPublic = (int) (
    $profile['portfolio_public'] ?? 0
);

if ($portfolioPublic !== 1) {

    portfolio_unavailable(
        'Portfolio Private',
        'This portfolio is currently private.'
    );
}


/*
|--------------------------------------------------------------------------
| PROFILE INFORMATION
|--------------------------------------------------------------------------
*/

$fullName = p_value(
    $profile,
    'full_name',
    $user['username'] ?? 'Portfolio'
);

$professionalTitle = p_value(
    $profile,
    'professional_title',
    'Professional'
);

$professionalSummary = p_value(
    $profile,
    'professional_summary'
);

$bio = p_value(
    $profile,
    'bio'
);

$profileImage = profile_image_url(
    $profile
);


/*
|--------------------------------------------------------------------------
| SOCIAL LINKS
|--------------------------------------------------------------------------
*/

$github = social_link(
    p_value(
        $profile,
        'github_url'
    )
);

$linkedin = social_link(
    p_value(
        $profile,
        'linkedin_url'
    )
);

$facebook = social_link(
    p_value(
        $profile,
        'facebook_url'
    )
);

$website = social_link(
    p_value(
        $profile,
        'website_url'
    )
);


/*
|--------------------------------------------------------------------------
| SECTION VISIBILITY
|--------------------------------------------------------------------------
*/

$showAbout = (int) (
    $profile['show_about'] ?? 1
);

$showProjects = (int) (
    $profile['show_projects'] ?? 1
);

$showExperience = (int) (
    $profile['show_experience'] ?? 1
);

$showEducation = (int) (
    $profile['show_education'] ?? 1
);

$showSkills = (int) (
    $profile['show_skills'] ?? 1
);

$showCertifications = (int) (
    $profile['show_certifications'] ?? 1
);


/*
|--------------------------------------------------------------------------
| REFERENCES VISIBILITY
|--------------------------------------------------------------------------
*/

$showReferences = (int) (
    $profile['show_references'] ?? 1
);


$showSocials = (int) (
    $profile['show_socials'] ?? 1
);


/*
|--------------------------------------------------------------------------
| GET PUBLIC ROWS
|--------------------------------------------------------------------------
*/

function get_public_rows(
    PDO $pdo,
    string $table,
    int $userId,
    string $orderBy
): array {

    $allowedTables = [
        'projects',
        'experience',
        'education',
        'skills',
        'certifications'
    ];

    $allowedOrder = [
        'created_at DESC',
        'start_date DESC',
        'issue_date DESC',
        'category ASC, skill_name ASC'
    ];

    if (
        !in_array(
            $table,
            $allowedTables,
            true
        )
    ) {
        return [];
    }

    if (
        !in_array(
            $orderBy,
            $allowedOrder,
            true
        )
    ) {
        $orderBy = 'created_at DESC';
    }

    $sql = "
        SELECT *
        FROM {$table}
        WHERE user_id = ?
          AND is_public = 1
        ORDER BY {$orderBy}
    ";

    $stmt = $pdo->prepare(
        $sql
    );

    $stmt->execute([
        $userId
    ]);

    return $stmt->fetchAll(
        PDO::FETCH_ASSOC
    );
}


/*
|--------------------------------------------------------------------------
| USER ID
|--------------------------------------------------------------------------
*/

$userId = (int) $user['id'];


/*
|--------------------------------------------------------------------------
| PROJECTS
|--------------------------------------------------------------------------
*/

$projects = [];

if ($showProjects === 1) {

    $projects = get_public_rows(
        $pdo,
        'projects',
        $userId,
        'created_at DESC'
    );
}


/*
|--------------------------------------------------------------------------
| EXPERIENCE
|--------------------------------------------------------------------------
*/

$experience = [];

if ($showExperience === 1) {

    $experience = get_public_rows(
        $pdo,
        'experience',
        $userId,
        'start_date DESC'
    );
}


/*
|--------------------------------------------------------------------------
| EDUCATION
|--------------------------------------------------------------------------
*/

$education = [];

if ($showEducation === 1) {

    $education = get_public_rows(
        $pdo,
        'education',
        $userId,
        'start_date DESC'
    );
}


/*
|--------------------------------------------------------------------------
| SKILLS
|--------------------------------------------------------------------------
*/

$skills = [];

if ($showSkills === 1) {

    $skills = get_public_rows(
        $pdo,
        'skills',
        $userId,
        'category ASC, skill_name ASC'
    );
}


/*
|--------------------------------------------------------------------------
| CERTIFICATIONS
|--------------------------------------------------------------------------
*/

$certifications = [];

if ($showCertifications === 1) {

    $certifications = get_public_rows(
        $pdo,
        'certifications',
        $userId,
        'issue_date DESC'
    );
}


/*
|--------------------------------------------------------------------------
| REFERENCES
|--------------------------------------------------------------------------
|
| IMPORTANT:
| References are only queried when show_references is enabled.
|
*/

$references = [];

if ($showReferences === 1) {

    try {

        $stmt = $pdo->prepare(
            "
            SELECT *
            FROM resume_references
            WHERE user_id = ?
              AND (
                    is_public = 1
                    OR is_public IS NULL
                  )
            ORDER BY created_at DESC
            "
        );

        $stmt->execute([
            $userId
        ]);

        $references = $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );
    } catch (Throwable $e) {

        $references = [];
    }
}


/*
|--------------------------------------------------------------------------
| CONTACT TOKEN
|--------------------------------------------------------------------------
*/

if (
    empty($_SESSION['portfolio_contact_token'])
) {

    $_SESSION['portfolio_contact_token'] =
        bin2hex(
            random_bytes(32)
        );
}

$contactToken =
    $_SESSION['portfolio_contact_token'];


/*
|--------------------------------------------------------------------------
| CONTACT EMAIL
|--------------------------------------------------------------------------
*/

$contactEmail = p_value(
    $profile,
    'contact_email'
);

if ($contactEmail === '') {

    $contactEmail = p_value(
        $user,
        'email'
    );
}


/*
|--------------------------------------------------------------------------
| CONTACT FORM STATE
|--------------------------------------------------------------------------
*/

$contactName = '';
$contactSenderEmail = '';
$contactSubject = '';
$contactMessage = '';
$contactErrors = [];
$contactSuccess = false;


/*
|--------------------------------------------------------------------------
| PROCESS CONTACT FORM ON THIS SAME PAGE
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['contact_form'])
) {

    $submittedToken = trim(
        (string) ($_POST['token'] ?? '')
    );

    if (
        $submittedToken === ''
        || !hash_equals(
            $contactToken,
            $submittedToken
        )
    ) {
        $contactErrors[] =
            'Your session has expired. Please refresh the page and try again.';
    }

    // Honeypot spam protection.
    if (
        trim((string) ($_POST['website'] ?? '')) !== ''
    ) {
        $contactErrors[] =
            'Unable to process your message.';
    }

    $contactName = trim(
        (string) ($_POST['name'] ?? '')
    );

    $contactSenderEmail = trim(
        (string) ($_POST['email'] ?? '')
    );

    $contactSubject = trim(
        (string) ($_POST['subject'] ?? '')
    );

    $contactMessage = trim(
        (string) ($_POST['message'] ?? '')
    );

    if ($contactName === '') {
        $contactErrors[] = 'Please enter your name.';
    } elseif (mb_strlen($contactName) < 2) {
        $contactErrors[] = 'Your name must be at least 2 characters.';
    } elseif (mb_strlen($contactName) > 100) {
        $contactErrors[] = 'Your name is too long.';
    }

    if ($contactSenderEmail === '') {
        $contactErrors[] = 'Please enter your email address.';
    } elseif (!filter_var($contactSenderEmail, FILTER_VALIDATE_EMAIL)) {
        $contactErrors[] = 'Please enter a valid email address.';
    }

    if ($contactSubject === '') {
        $contactErrors[] = 'Please enter a subject.';
    } elseif (mb_strlen($contactSubject) > 200) {
        $contactErrors[] = 'The subject is too long.';
    }

    if ($contactMessage === '') {
        $contactErrors[] = 'Please enter your message.';
    } elseif (mb_strlen($contactMessage) < 5) {
        $contactErrors[] = 'Your message is too short.';
    } elseif (mb_strlen($contactMessage) > 5000) {
        $contactErrors[] = 'Your message is too long. Maximum 5000 characters.';
    }

    if (
        $contactEmail === ''
        || !filter_var($contactEmail, FILTER_VALIDATE_EMAIL)
    ) {
        $contactErrors[] =
            'This portfolio does not have a valid contact email address.';
    }

    if (empty($contactErrors)) {

        try {

            $mail = new PHPMailer(true);

            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;

            /*
             * IMPORTANT:
             * Replace these with the Gmail account created for MyPortfolio
             * and its Google App Password.
             */
            $mail->Username = 'myportfolio.sender@gmail.com';
            $mail->Password = 'oopegpzckpktwbss';

            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;
            $mail->CharSet = 'UTF-8';

            // The From address must be the authenticated Gmail account.
            $mail->setFrom(
                'myportfolio.sender@gmail.com',
                APP_NAME
            );

            // Send to the portfolio owner's contact_email/users.email.
            $mail->addAddress(
                $contactEmail,
                $fullName
            );

            // Reply button in Gmail will reply directly to the visitor.
            $mail->addReplyTo(
                $contactSenderEmail,
                $contactName
            );

            $mail->Subject = $contactSubject;
            $mail->isHTML(true);

            $mail->Body = '
                <!DOCTYPE html>
                <html lang="en">
                <head>
                    <meta charset="UTF-8">
                    <title>New Portfolio Message</title>
                </head>
                <body style="margin:0;padding:30px 15px;background:#f7faff;font-family:Arial,Helvetica,sans-serif;color:#172033;">
                    <div style="width:100%;max-width:650px;margin:0 auto;background:#ffffff;border:1px solid #e7edf5;border-radius:16px;overflow:hidden;">
                        <div style="padding:28px 30px;background:#0d6efd;color:#ffffff;">
                            <h2 style="margin:0;font-size:22px;">New Message from Your MyPortfolio</h2>
                            <p style="margin:8px 0 0;font-size:14px;">Someone contacted you through your MyPortfolio website.</p>
                        </div>
                        <div style="padding:30px;">
                            <p><strong>Name</strong><br>' . e($contactName) . '</p>
                            <p><strong>Email</strong><br>' . e($contactSenderEmail) . '</p>
                            <p><strong>Subject</strong><br>' . e($contactSubject) . '</p>
                            <div style="margin-top:25px;padding:18px;background:#f7faff;border-left:4px solid #0d6efd;border-radius:8px;line-height:1.7;">
                                <strong>Message</strong><br><br>
                                ' . nl2br(e($contactMessage)) . '
                            </div>
                        </div>
                        <div style="padding:18px 30px;border-top:1px solid #e7edf5;color:#64748b;font-size:12px;">
                            Sent through ' . e(APP_NAME) . '
                        </div>
                    </div>
                </body>
                </html>
            ';

            $mail->AltBody =
                "New message from your portfolio\n\n"
                . "Name: " . $contactName . "\n"
                . "Email: " . $contactSenderEmail . "\n"
                . "Subject: " . $contactSubject . "\n\n"
                . "Message:\n" . $contactMessage;

            $mail->send();

            $contactSuccess = true;

            $contactName = '';
            $contactSenderEmail = '';
            $contactSubject = '';
            $contactMessage = '';

            // Prevent the same token from being reused after a successful send.
            $_SESSION['portfolio_contact_token'] =
                bin2hex(random_bytes(32));

            $contactToken =
                $_SESSION['portfolio_contact_token'];
        } catch (Exception $e) {

            // Show the real PHPMailer error while configuring SMTP.
            $contactErrors[] =
                'Mailer Error: ' . $e->getMessage();
        }
    }
}


/*
|--------------------------------------------------------------------------
| PAGE TITLE
|--------------------------------------------------------------------------
*/

$pageTitle =
    $fullName;

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0">

    <meta
        name="description"
        content="<?= e(
                        $professionalSummary !== ''
                            ? $professionalSummary
                            : $fullName . ' - Professional Portfolio'
                    ) ?>">

    <title>
        <?= e($pageTitle) ?>
    </title>


    <!-- GOOGLE FONT -->

    <link
        rel="preconnect"
        href="https://fonts.googleapis.com">

    <link
        rel="preconnect"
        href="https://fonts.gstatic.com"
        crossorigin>

    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">


    <!-- MATERIAL DESIGN ICONS -->

    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/@mdi/font@7.4.47/css/materialdesignicons.min.css">


    <!-- BOOTSTRAP 5 -->

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet">


    <!-- YOUR EXISTING PORTFOLIO CSS -->

    <link
        rel="stylesheet"
        href="css/portfolio.css">

</head>


<body>


    <!-- =========================================================
     NAVBAR
========================================================= -->

    <nav
        class="portfolio-navbar"
        id="portfolioNavbar">

        <div class="portfolio-container navbar-inner">


            <a
                href="#home"
                class="portfolio-brand">

                <!-- <span class="brand-icon">

                    <i class="mdi mdi-briefcase-variant-outline"></i>

                </span> -->

                <span>

                    <?= e($fullName) ?>

                </span>

            </a>


            <button
                type="button"
                class="mobile-menu-btn"
                id="mobileMenuBtn"
                aria-label="Toggle navigation"
                aria-expanded="false">

                <i class="mdi mdi-menu"></i>

            </button>


            <div
                class="portfolio-nav-links"
                id="portfolioNavLinks">


                <a
                    href="#home"
                    class="nav-link active">

                    Home

                </a>


                <?php if ($showAbout === 1): ?>

                    <a
                        href="#about"
                        class="nav-link">

                        About

                    </a>

                <?php endif; ?>


                <?php if (
                    $showExperience === 1 ||
                    $showEducation === 1
                ): ?>

                    <a
                        href="#resume"
                        class="nav-link">

                        Resume

                    </a>

                <?php endif; ?>


                <?php if ($showProjects === 1): ?>

                    <a
                        href="#projects"
                        class="nav-link">

                        Projects

                    </a>

                <?php endif; ?>


                <?php if ($showSkills === 1): ?>

                    <a
                        href="#skills"
                        class="nav-link">

                        Skills

                    </a>

                <?php endif; ?>


                <?php if ($showCertifications === 1): ?>

                    <a
                        href="#certifications"
                        class="nav-link">

                        Certifications

                    </a>

                <?php endif; ?>


                <!-- REFERENCES NAVIGATION -->

                <?php if (
                    $showReferences === 1 &&
                    count($references) > 0
                ): ?>

                    <a
                        href="#references"
                        class="nav-link">

                        References

                    </a>

                <?php endif; ?>


                <a
                    href="#contact"
                    class="nav-contact-btn">

                    Contact

                </a>

            </div>

        </div>

    </nav>


    <!-- =========================================================
     HERO
========================================================= -->

    <section
        class="portfolio-hero"
        id="home">

        <div
            class="hero-background-shape shape-one">
        </div>

        <div
            class="hero-background-shape shape-two">
        </div>


        <div class="portfolio-container">

            <div class="hero-grid">


                <div class="hero-content reveal">

                    <!-- <span class="hero-label">

                        <span class="online-dot"></span>

                        Available for opportunities

                    </span> -->


                    <h1 class="hero-name">

                        <?= e($fullName) ?>

                    </h1>


                    <?php if (
                        $professionalTitle !== ''
                    ): ?>

                        <div class="hero-title">

                            <span id="typewriter"></span>

                            <span class="typewriter-cursor">
                                |
                            </span>

                        </div>

                    <?php endif; ?>


                    <?php if (
                        $bio !== ''
                    ): ?>

                        <p class="hero-description">

                            <?= e(
                                $bio
                            ) ?>

                        </p>

                    <?php elseif (
                        $bio !== ''
                    ): ?>

                        <p class="hero-description">

                            <?= e(
                                $professionalSummary
                            ) ?>

                        </p>

                    <?php endif; ?>


                    <div class="hero-actions">


                        <?php if (
                            $showProjects === 1 &&
                            count($projects) > 0
                        ): ?>

                            <a
                                href="#projects"
                                class="primary-btn">

                                View My Work

                                <i class="mdi mdi-arrow-right"></i>

                            </a>

                        <?php endif; ?>


                        <a
                            href="#contact"
                            class="secondary-btn">

                            Let's Connect

                            <i class="mdi mdi-email-outline"></i>

                        </a>

                    </div>


                    <?php if (
                        $showSocials === 1 &&
                        (
                            $github !== '' ||
                            $linkedin !== '' ||
                            $facebook !== '' ||
                            $website !== ''
                        )
                    ): ?>

                        <div class="hero-socials">

                            <span class="social-label">

                                Find me on

                            </span>


                            <?php if (
                                $github !== ''
                            ): ?>

                                <a
                                    href="<?= e($github) ?>"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    aria-label="GitHub">

                                    <i class="mdi mdi-github"></i>

                                </a>

                            <?php endif; ?>


                            <?php if (
                                $linkedin !== ''
                            ): ?>

                                <a
                                    href="<?= e($linkedin) ?>"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    aria-label="LinkedIn">

                                    <i class="mdi mdi-linkedin"></i>

                                </a>

                            <?php endif; ?>


                            <?php if (
                                $facebook !== ''
                            ): ?>

                                <a
                                    href="<?= e($facebook) ?>"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    aria-label="Facebook">

                                    <i class="mdi mdi-facebook"></i>

                                </a>

                            <?php endif; ?>


                            <?php if (
                                $website !== ''
                            ): ?>

                                <a
                                    href="<?= e($website) ?>"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    aria-label="Website">

                                    <i class="mdi mdi-web"></i>

                                </a>

                            <?php endif; ?>


                        </div>

                    <?php endif; ?>


                </div>


                <div class="hero-profile-area reveal">

                    <div class="hero-profile-decoration"></div>

                    <div class="hero-profile-card">


                        <?php if (
                            $profileImage !== ''
                        ): ?>

                            <img
                                src="<?= e($profileImage) ?>"
                                alt="<?= e($fullName) ?>"
                                class="hero-profile-image">

                        <?php else: ?>

                            <div class="hero-profile-placeholder">

                                <i class="mdi mdi-account-outline"></i>

                            </div>

                        <?php endif; ?>


                    </div>

                </div>


            </div>

        </div>

    </section>


    <!-- =========================================================
     ABOUT
========================================================= -->

    <?php if ($showAbout === 1): ?>
        <section id="about" class="portfolio-section">
            <div class="portfolio-container">

                <div class="section-heading reveal">
                    <span class="section-eyebrow">ABOUT ME</span>
                    <h2>Get to Know Me</h2>
                    <div class="heading-line"></div>
                </div>

                <!-- SINGLE ABOUT CARD -->
                <div class="about-main-card reveal">

                    <!-- <div class="about-icon">
                        <i class="fas fa-user"></i>
                    </div> -->

                    <div class="about-content">

                        <h3>About Me</h3>

                        <?php
                        $aboutText = trim(
                            (string) (
                                $profile['professional_summary']
                                ?? $profile['bio']
                                ?? ''
                            )
                        );
                        ?>

                        <?php if ($aboutText !== ''): ?>

                            <?php
                            $aboutParagraphs = preg_split(
                                "/\r\n|\r|\n/",
                                $aboutText
                            );
                            ?>

                            <?php foreach ($aboutParagraphs as $paragraph): ?>
                                <?php $paragraph = trim($paragraph); ?>

                                <?php if ($paragraph !== ''): ?>
                                    <p>
                                        <?= e($paragraph) ?>
                                    </p>
                                <?php endif; ?>

                            <?php endforeach; ?>

                        <?php else: ?>

                            <p>
                                Welcome to my portfolio. This section contains
                                information about my background, skills, experience,
                                and professional journey.
                            </p>

                        <?php endif; ?>

                    </div>

                </div>

            </div>
        </section>
    <?php endif; ?>



    <!-- =========================================================
     RESUME
========================================================= -->

    <?php if (
        $showExperience === 1 ||
        $showEducation === 1
    ): ?>

        <section
            class="portfolio-section section-light"
            id="resume">

            <div class="portfolio-container">


                <div class="section-heading reveal">

                    <span class="section-eyebrow">
                        My Background
                    </span>

                    <h2>
                        Experience & Education
                    </h2>

                    <p>
                        A timeline of my professional and academic journey.
                    </p>

                    <div class="heading-line"></div>

                </div>


                <div class="resume-columns">


                    <!-- EXPERIENCE -->

                    <?php if (
                        $showExperience === 1
                    ): ?>

                        <div class="resume-column reveal">

                            <div class="resume-column-heading">

                                <div class="resume-heading-icon">

                                    <i class="mdi mdi-briefcase-outline"></i>

                                </div>

                                <div>

                                    <span>
                                        Career
                                    </span>

                                    <h3>
                                        Experience
                                    </h3>

                                </div>

                            </div>


                            <?php if (
                                count($experience) > 0
                            ): ?>

                                <div class="timeline">

                                    <?php foreach (
                                        $experience
                                        as $item
                                    ): ?>

                                        <?php

                                        $position = p_value(
                                            $item,
                                            'position',
                                            p_value(
                                                $item,
                                                'job_title',
                                                'Position'
                                            )
                                        );

                                        $company = p_value(
                                            $item,
                                            'company',
                                            p_value(
                                                $item,
                                                'company_name'
                                            )
                                        );

                                        $description = p_value(
                                            $item,
                                            'description'
                                        );

                                        $startDate = p_date(
                                            $item,
                                            'start_date'
                                        );

                                        $endDate = p_date(
                                            $item,
                                            'end_date'
                                        );

                                        $current = (int) (
                                            $item['is_current'] ?? 0
                                        );


                                        if (
                                            $current === 1 ||
                                            $endDate === ''
                                        ) {

                                            $dateText =
                                                $startDate !== ''
                                                ? $startDate . ' - Present'
                                                : 'Present';
                                        } else {

                                            $dateText =
                                                $startDate !== ''
                                                ? $startDate . ' - ' . $endDate
                                                : $endDate;
                                        }

                                        ?>

                                        <div class="timeline-item">

                                            <div class="timeline-dot"></div>

                                            <div class="timeline-card">


                                                <?php if (
                                                    $dateText !== ''
                                                ): ?>

                                                    <span class="timeline-date">

                                                        <?= e(
                                                            $dateText
                                                        ) ?>

                                                    </span>

                                                <?php endif; ?>


                                                <h4>

                                                    <?= e(
                                                        $position
                                                    ) ?>

                                                </h4>


                                                <?php if (
                                                    $company !== ''
                                                ): ?>

                                                    <div class="timeline-company">

                                                        <i class="mdi mdi-domain"></i>

                                                        <?= e(
                                                            $company
                                                        ) ?>

                                                    </div>

                                                <?php endif; ?>


                                                <?php if (
                                                    $description !== ''
                                                ): ?>

                                                    <p>

                                                        <?= nl2br(
                                                            e(
                                                                $description
                                                            )
                                                        ) ?>

                                                    </p>

                                                <?php endif; ?>


                                            </div>

                                        </div>

                                    <?php endforeach; ?>

                                </div>

                            <?php else: ?>

                                <div class="timeline-card">

                                    <p>
                                        No public experience entries available.
                                    </p>

                                </div>

                            <?php endif; ?>


                        </div>

                    <?php endif; ?>


                    <!-- EDUCATION -->

                    <?php if (
                        $showEducation === 1
                    ): ?>

                        <div class="resume-column reveal">

                            <div class="resume-column-heading">

                                <div class="resume-heading-icon">

                                    <i class="mdi mdi-school-outline"></i>

                                </div>

                                <div>

                                    <span>
                                        Academic
                                    </span>

                                    <h3>
                                        Education
                                    </h3>

                                </div>

                            </div>


                            <?php if (
                                count($education) > 0
                            ): ?>

                                <div class="timeline">

                                    <?php foreach (
                                        $education
                                        as $item
                                    ): ?>

                                        <?php

                                        $degree = p_value(
                                            $item,
                                            'degree',
                                            p_value(
                                                $item,
                                                'program',
                                                'Degree'
                                            )
                                        );

                                        $school = p_value(
                                            $item,
                                            'institution',
                                            p_value(
                                                $item,
                                                'school',
                                                p_value(
                                                    $item,
                                                    'school_name'
                                                )
                                            )
                                        );

                                        $description = p_value(
                                            $item,
                                            'description'
                                        );

                                        $startDate = p_date(
                                            $item,
                                            'start_date'
                                        );

                                        $endDate = p_date(
                                            $item,
                                            'end_date'
                                        );


                                        if (
                                            $startDate !== '' &&
                                            $endDate !== ''
                                        ) {

                                            $dateText =
                                                $startDate .
                                                ' - ' .
                                                $endDate;
                                        } elseif (
                                            $startDate !== ''
                                        ) {

                                            $dateText =
                                                $startDate .
                                                ' - Present';
                                        } else {

                                            $dateText =
                                                $endDate;
                                        }

                                        ?>

                                        <div class="timeline-item">

                                            <div class="timeline-dot"></div>

                                            <div class="timeline-card">


                                                <?php if (
                                                    $dateText !== ''
                                                ): ?>

                                                    <span class="timeline-date">

                                                        <?= e(
                                                            $dateText
                                                        ) ?>

                                                    </span>

                                                <?php endif; ?>


                                                <h4>

                                                    <?= e(
                                                        $degree
                                                    ) ?>

                                                </h4>


                                                <?php if (
                                                    $school !== ''
                                                ): ?>

                                                    <div class="timeline-company">

                                                        <i class="mdi mdi-school-outline"></i>

                                                        <?= e(
                                                            $school
                                                        ) ?>

                                                    </div>

                                                <?php endif; ?>


                                                <?php if (
                                                    $description !== ''
                                                ): ?>

                                                    <p>

                                                        <?= nl2br(
                                                            e(
                                                                $description
                                                            )
                                                        ) ?>

                                                    </p>

                                                <?php endif; ?>


                                            </div>

                                        </div>

                                    <?php endforeach; ?>

                                </div>

                            <?php else: ?>

                                <div class="timeline-card">

                                    <p>
                                        No public education entries available.
                                    </p>

                                </div>

                            <?php endif; ?>


                        </div>

                    <?php endif; ?>


                </div>

            </div>

        </section>

    <?php endif; ?>


    <!-- =========================================================
     PROJECTS
========================================================= -->

    <?php if (
        $showProjects === 1
    ): ?>

        <section
            class="portfolio-section"
            id="projects">

            <div class="portfolio-container">


                <div class="section-heading reveal">

                    <span class="section-eyebrow">
                        Featured Work
                    </span>

                    <h2>
                        Projects I've Built
                    </h2>

                    <p>
                        A selection of projects that showcase my
                        skills, experience, and creativity.
                    </p>

                    <div class="heading-line"></div>

                </div>


                <?php if (
                    count($projects) > 0
                ): ?>

                    <div class="projects-grid">


                        <?php foreach (
                            $projects
                            as $index => $project
                        ): ?>

                            <?php

                            /*
                    |--------------------------------------------------------------------------
                    | PROJECT BASIC INFORMATION
                    |--------------------------------------------------------------------------
                    */

                            $projectId = (int) (
                                $project['id']
                                ?? ($index + 1)
                            );

                            $modalId =
                                'projectModal' .
                                $projectId;


                            $projectTitle = p_value(
                                $project,
                                'title',
                                'Untitled Project'
                            );


                            $projectType = p_value(
                                $project,
                                'project_type'
                            );


                            $projectDescription = p_value(
                                $project,
                                'description',
                                'No description available.'
                            );


                            $projectRole = p_value(
                                $project,
                                'role'
                            );


                            $responsibilities = p_value(
                                $project,
                                'responsibilities'
                            );


                            $techStack = p_value(
                                $project,
                                'tech_stack'
                            );


                            $subjectMatter = p_value(
                                $project,
                                'subject_matter'
                            );


                            $medium = p_value(
                                $project,
                                'medium'
                            );


                            $projectImage =
                                project_image_url(
                                    $project
                                );


                            $websiteUrl =
                                social_link(
                                    p_value(
                                        $project,
                                        'website_url'
                                    )
                                );


                            $githubUrl =
                                social_link(
                                    p_value(
                                        $project,
                                        'github_url'
                                    )
                                );


                            $startDate =
                                p_date(
                                    $project,
                                    'start_date'
                                );


                            $endDate =
                                p_date(
                                    $project,
                                    'end_date'
                                );


                            $duration =
                                p_value(
                                    $project,
                                    'duration'
                                );


                            /*
                    |--------------------------------------------------------------------------
                    | PROJECT DATE
                    |--------------------------------------------------------------------------
                    */

                            if (
                                $startDate !== '' &&
                                $endDate !== ''
                            ) {

                                $projectDate =
                                    $startDate .
                                    ' - ' .
                                    $endDate;
                            } elseif (
                                $startDate !== ''
                            ) {

                                $projectDate =
                                    $startDate .
                                    ' - Present';
                            } elseif (
                                $endDate !== ''
                            ) {

                                $projectDate =
                                    $endDate;
                            } else {

                                $projectDate = '';
                            }


                            /*
                    |--------------------------------------------------------------------------
                    | TECHNOLOGIES
                    |--------------------------------------------------------------------------
                    */

                            $technologyList = [];

                            if (
                                $techStack !== ''
                            ) {

                                $technologyList =
                                    preg_split(
                                        '/[,|;\n]+/',
                                        $techStack
                                    );

                                $technologyList =
                                    array_values(
                                        array_filter(
                                            array_map(
                                                'trim',
                                                $technologyList
                                            ),
                                            static function (
                                                string $value
                                            ): bool {

                                                return $value !== '';
                                            }
                                        )
                                    );
                            }


                            /*
                    |--------------------------------------------------------------------------
                    | RESPONSIBILITIES
                    |--------------------------------------------------------------------------
                    */

                            $responsibilityList = [];

                            if (
                                $responsibilities !== ''
                            ) {

                                $responsibilityList =
                                    preg_split(
                                        '/\r\n|\r|\n|•/',
                                        $responsibilities
                                    );

                                $responsibilityList =
                                    array_values(
                                        array_filter(
                                            array_map(
                                                'trim',
                                                $responsibilityList
                                            ),
                                            static function (
                                                string $value
                                            ): bool {

                                                return $value !== '';
                                            }
                                        )
                                    );
                            }

                            ?>


                            <!-- =====================================================
                         PROJECT CARD
                    ====================================================== -->

                            <article
                                class="project-card reveal">


                                <?php if (
                                    $projectImage !== ''
                                ): ?>

                                    <div class="project-image">

                                        <img
                                            src="<?= e($projectImage) ?>"
                                            alt="<?= e($projectTitle) ?>"
                                            loading="lazy">

                                    </div>

                                <?php else: ?>

                                    <div class="project-image">

                                        <div class="project-image-placeholder">

                                            <i
                                                class="mdi mdi-folder-star-outline">
                                            </i>

                                        </div>

                                    </div>

                                <?php endif; ?>


                                <div class="project-body">


                                    <div class="project-number">

                                        <?= str_pad(
                                            (string) (
                                                $index + 1
                                            ),
                                            2,
                                            '0',
                                            STR_PAD_LEFT
                                        ) ?>

                                    </div>


                                    <?php if (
                                        $projectType !== ''
                                    ): ?>

                                        <span
                                            class="project-type-label">

                                            <?= e(
                                                $projectType
                                            ) ?>

                                        </span>

                                    <?php endif; ?>


                                    <h3>

                                        <?= e(
                                            $projectTitle
                                        ) ?>

                                    </h3>


                                    <p>

                                        <?= e(
                                            mb_strimwidth(
                                                $projectDescription,
                                                0,
                                                160,
                                                '...'
                                            )
                                        ) ?>

                                    </p>


                                    <?php if (
                                        count($technologyList) > 0
                                    ): ?>

                                        <div
                                            class="project-tags">

                                            <?php foreach (
                                                array_slice(
                                                    $technologyList,
                                                    0,
                                                    4
                                                )
                                                as $technology
                                            ): ?>

                                                <span>

                                                    <?= e(
                                                        $technology
                                                    ) ?>

                                                </span>

                                            <?php endforeach; ?>


                                            <?php if (
                                                count(
                                                    $technologyList
                                                ) > 4
                                            ): ?>

                                                <span>

                                                    +
                                                    <?= count(
                                                        $technologyList
                                                    ) - 4 ?>

                                                </span>

                                            <?php endif; ?>

                                        </div>

                                    <?php endif; ?>


                                    <a
                                        href="#"
                                        class="project-link project-details-btn"
                                        data-bs-toggle="modal"
                                        data-bs-target="#<?= e($modalId) ?>">

                                        View Details

                                        <i
                                            class="mdi mdi-arrow-right">
                                        </i>

                                    </a>


                                </div>

                            </article>


                            <!-- =====================================================
                         PROJECT DETAILS MODAL
                    ====================================================== -->

                            <div
                                class="modal fade project-modal"
                                id="<?= e($modalId) ?>"
                                tabindex="-1"
                                aria-labelledby="<?= e($modalId) ?>Label"
                                aria-hidden="true">


                                <div
                                    class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">


                                    <div
                                        class="modal-content">


                                        <!-- MODAL HEADER -->

                                        <div
                                            class="modal-header">

                                            <div>

                                                <?php if (
                                                    $projectType !== ''
                                                ): ?>

                                                    <span
                                                        class="modal-project-label">

                                                        <?= e(
                                                            $projectType
                                                        ) ?>

                                                    </span>

                                                <?php endif; ?>


                                                <h2
                                                    class="modal-title"
                                                    id="<?= e($modalId) ?>Label">

                                                    <?= e(
                                                        $projectTitle
                                                    ) ?>

                                                </h2>

                                            </div>


                                            <button
                                                type="button"
                                                class="btn-close"
                                                data-bs-dismiss="modal"
                                                aria-label="Close">
                                            </button>

                                        </div>


                                        <!-- MODAL BODY -->

                                        <div
                                            class="modal-body">


                                            <div
                                                class="project-modal-layout">


                                                <!-- =================================================
                                             LEFT SIDE
                                        ================================================== -->

                                                <div
                                                    class="project-modal-left">


                                                    <?php if (
                                                        $projectImage !== ''
                                                    ): ?>

                                                        <div
                                                            class="project-modal-image">

                                                            <img
                                                                src="<?= e($projectImage) ?>"
                                                                alt="<?= e($projectTitle) ?>">

                                                        </div>

                                                    <?php else: ?>

                                                        <div
                                                            class="
                                                        project-modal-image
                                                        project-modal-placeholder
                                                    ">

                                                            <i
                                                                class="
                                                            mdi
                                                            mdi-folder-star-outline
                                                        ">
                                                            </i>

                                                        </div>

                                                    <?php endif; ?>


                                                    <!-- PROJECT INFORMATION -->

                                                    <div
                                                        class="project-modal-meta-grid">


                                                        <?php if (
                                                            $projectDate !== ''
                                                        ): ?>

                                                            <div
                                                                class="project-meta-item">

                                                                <div
                                                                    class="project-meta-icon">

                                                                    <i
                                                                        class="mdi mdi-calendar-outline">
                                                                    </i>

                                                                </div>

                                                                <div>

                                                                    <span>
                                                                        Project Date
                                                                    </span>

                                                                    <strong>

                                                                        <?= e(
                                                                            $projectDate
                                                                        ) ?>

                                                                    </strong>

                                                                </div>

                                                            </div>

                                                        <?php endif; ?>


                                                        <?php if (
                                                            $duration !== ''
                                                        ): ?>

                                                            <div
                                                                class="project-meta-item">

                                                                <div
                                                                    class="project-meta-icon">

                                                                    <i
                                                                        class="mdi mdi-clock-outline">
                                                                    </i>

                                                                </div>

                                                                <div>

                                                                    <span>
                                                                        Duration
                                                                    </span>

                                                                    <strong>

                                                                        <?= e(
                                                                            $duration
                                                                        ) ?>

                                                                    </strong>

                                                                </div>

                                                            </div>

                                                        <?php endif; ?>


                                                        <?php if (
                                                            $projectType !== ''
                                                        ): ?>

                                                            <div
                                                                class="project-meta-item">

                                                                <div
                                                                    class="project-meta-icon">

                                                                    <i
                                                                        class="mdi mdi-folder-outline">
                                                                    </i>

                                                                </div>

                                                                <div>

                                                                    <span>
                                                                        Project Type
                                                                    </span>

                                                                    <strong>

                                                                        <?= e(
                                                                            $projectType
                                                                        ) ?>

                                                                    </strong>

                                                                </div>

                                                            </div>

                                                        <?php endif; ?>


                                                        <?php if (
                                                            $medium !== ''
                                                        ): ?>

                                                            <div
                                                                class="project-meta-item">

                                                                <div
                                                                    class="project-meta-icon">

                                                                    <i
                                                                        class="mdi mdi-layers-outline">
                                                                    </i>

                                                                </div>

                                                                <div>

                                                                    <span>
                                                                        Medium
                                                                    </span>

                                                                    <strong>

                                                                        <?= e(
                                                                            $medium
                                                                        ) ?>

                                                                    </strong>

                                                                </div>

                                                            </div>

                                                        <?php endif; ?>


                                                    </div>


                                                </div>


                                                <!-- =================================================
                                             RIGHT SIDE
                                        ================================================== -->

                                                <div
                                                    class="project-modal-content">


                                                    <!-- DESCRIPTION -->

                                                    <div
                                                        class="project-modal-section">

                                                        <div
                                                            class="project-section-heading">

                                                            <div>

                                                                <i
                                                                    class="mdi mdi-text-box-outline">
                                                                </i>

                                                            </div>

                                                            <h6>
                                                                About the Project
                                                            </h6>

                                                        </div>


                                                        <p>

                                                            <?= nl2br(
                                                                e(
                                                                    $projectDescription
                                                                )
                                                            ) ?>

                                                        </p>

                                                    </div>


                                                    <!-- ROLE -->

                                                    <?php if (
                                                        $projectRole !== ''
                                                    ): ?>

                                                        <div
                                                            class="project-modal-section">

                                                            <div
                                                                class="project-section-heading">

                                                                <div>

                                                                    <i
                                                                        class="mdi mdi-account-outline">
                                                                    </i>

                                                                </div>

                                                                <h6>
                                                                    My Role
                                                                </h6>

                                                            </div>


                                                            <div
                                                                class="project-highlight-box">

                                                                <?= e(
                                                                    $projectRole
                                                                ) ?>

                                                            </div>

                                                        </div>

                                                    <?php endif; ?>


                                                    <!-- RESPONSIBILITIES -->

                                                    <?php if (
                                                        count(
                                                            $responsibilityList
                                                        ) > 0
                                                    ): ?>

                                                        <div
                                                            class="project-modal-section">

                                                            <div
                                                                class="project-section-heading">

                                                                <div>

                                                                    <i
                                                                        class="mdi mdi-format-list-checks">
                                                                    </i>

                                                                </div>

                                                                <h6>
                                                                    Responsibilities
                                                                </h6>

                                                            </div>


                                                            <ul
                                                                class="project-responsibilities">

                                                                <?php foreach (
                                                                    $responsibilityList
                                                                    as $responsibility
                                                                ): ?>

                                                                    <li>

                                                                        <i
                                                                            class="mdi mdi-check-circle-outline">
                                                                        </i>

                                                                        <span>

                                                                            <?= e(
                                                                                $responsibility
                                                                            ) ?>

                                                                        </span>

                                                                    </li>

                                                                <?php endforeach; ?>

                                                            </ul>

                                                        </div>

                                                    <?php endif; ?>


                                                    <!-- TECHNOLOGIES -->

                                                    <?php if (
                                                        count(
                                                            $technologyList
                                                        ) > 0
                                                    ): ?>

                                                        <div
                                                            class="project-modal-section">

                                                            <div
                                                                class="project-section-heading">

                                                                <div>

                                                                    <i
                                                                        class="mdi mdi-code-tags">
                                                                    </i>

                                                                </div>

                                                                <h6>
                                                                    Technologies Used
                                                                </h6>

                                                            </div>


                                                            <div
                                                                class="project-modal-tech">

                                                                <?php foreach (
                                                                    $technologyList
                                                                    as $technology
                                                                ): ?>

                                                                    <span>

                                                                        <i
                                                                            class="mdi mdi-check">
                                                                        </i>

                                                                        <?= e(
                                                                            $technology
                                                                        ) ?>

                                                                    </span>

                                                                <?php endforeach; ?>

                                                            </div>

                                                        </div>

                                                    <?php endif; ?>


                                                    <!-- SUBJECT MATTER -->

                                                    <?php if (
                                                        $subjectMatter !== ''
                                                    ): ?>

                                                        <div
                                                            class="project-modal-section">

                                                            <div
                                                                class="project-section-heading">

                                                                <div>

                                                                    <i
                                                                        class="mdi mdi-book-open-page-variant-outline">
                                                                    </i>

                                                                </div>

                                                                <h6>
                                                                    Subject Matter
                                                                </h6>

                                                            </div>


                                                            <p>

                                                                <?= nl2br(
                                                                    e(
                                                                        $subjectMatter
                                                                    )
                                                                ) ?>

                                                            </p>

                                                        </div>

                                                    <?php endif; ?>


                                                    <!-- MEDIUM -->

                                                    <?php if (
                                                        $medium !== ''
                                                    ): ?>

                                                        <div
                                                            class="project-modal-section">

                                                            <div
                                                                class="project-section-heading">

                                                                <div>

                                                                    <i
                                                                        class="mdi mdi-palette-outline">
                                                                    </i>

                                                                </div>

                                                                <h6>
                                                                    Medium
                                                                </h6>

                                                            </div>


                                                            <p>

                                                                <?= e(
                                                                    $medium
                                                                ) ?>

                                                            </p>

                                                        </div>

                                                    <?php endif; ?>


                                                    <!-- LINKS -->

                                                    <?php if (
                                                        $websiteUrl !== '' ||
                                                        $githubUrl !== ''
                                                    ): ?>

                                                        <div
                                                            class="project-modal-actions">


                                                            <?php if (
                                                                $websiteUrl !== ''
                                                            ): ?>

                                                                <a
                                                                    href="<?= e($websiteUrl) ?>"
                                                                    target="_blank"
                                                                    rel="noopener noreferrer"
                                                                    class="primary-btn">

                                                                    <i
                                                                        class="mdi mdi-web">
                                                                    </i>

                                                                    Live Project

                                                                </a>

                                                            <?php endif; ?>


                                                            <?php if (
                                                                $githubUrl !== ''
                                                            ): ?>

                                                                <a
                                                                    href="<?= e($githubUrl) ?>"
                                                                    target="_blank"
                                                                    rel="noopener noreferrer"
                                                                    class="secondary-btn">

                                                                    <i
                                                                        class="mdi mdi-github">
                                                                    </i>

                                                                    View on GitHub

                                                                </a>

                                                            <?php endif; ?>


                                                        </div>

                                                    <?php endif; ?>


                                                </div>

                                            </div>

                                        </div>


                                        <!-- MODAL FOOTER -->

                                        <div
                                            class="modal-footer">

                                            <button
                                                type="button"
                                                class="secondary-btn"
                                                data-bs-dismiss="modal">

                                                Close

                                            </button>

                                        </div>


                                    </div>

                                </div>

                            </div>


                        <?php endforeach; ?>


                    </div>

                <?php else: ?>

                    <div
                        style="
                    padding:60px 20px;
                    text-align:center;
                ">

                        <div
                            class="project-empty-icon">

                            <i
                                class="mdi mdi-folder-open-outline">
                            </i>

                        </div>

                        <h3>
                            No Projects Yet
                        </h3>

                        <p>
                            No public projects have been added yet.
                        </p>

                    </div>

                <?php endif; ?>


            </div>

        </section>

    <?php endif; ?>


    <!-- =========================================================
     SKILLS
========================================================= -->

    <?php if (
        $showSkills === 1
    ): ?>

        <section
            class="portfolio-section section-light"
            id="skills">

            <div class="portfolio-container">


                <div class="section-heading reveal">

                    <span class="section-eyebrow">
                        My Expertise
                    </span>

                    <h2>
                        Skills & Technologies
                    </h2>

                    <p>
                        Tools and technologies I use to create
                        useful and effective solutions.
                    </p>

                    <div class="heading-line"></div>

                </div>


                <?php

                $skillGroups = [];

                foreach (
                    $skills
                    as $skill
                ) {

                    $category = p_value(
                        $skill,
                        'category',
                        'Other'
                    );

                    $skillGroups[$category][] = $skill;
                }

                ?>


                <?php if (
                    count($skillGroups) > 0
                ): ?>

                    <div class="skills-groups">

                        <?php foreach (
                            $skillGroups
                            as $category => $categorySkills
                        ): ?>

                            <div
                                class="skills-group reveal">

                                <div
                                    class="skills-category-title">

                                    <div
                                        class="skills-category-icon">

                                        <i
                                            class="mdi mdi-code-tags">
                                        </i>

                                    </div>

                                    <h3>

                                        <?= e(
                                            $category
                                        ) ?>

                                    </h3>

                                </div>


                                <div class="skills-list">

                                    <?php foreach (
                                        $categorySkills
                                        as $skill
                                    ): ?>

                                        <?php

                                        $skillName =
                                            p_value(
                                                $skill,
                                                'skill_name',
                                                p_value(
                                                    $skill,
                                                    'name',
                                                    'Skill'
                                                )
                                            );

                                        ?>

                                        <div
                                            class="skill-card">

                                            <div
                                                class="skill-icon">

                                                <i
                                                    class="mdi mdi-check">
                                                </i>

                                            </div>

                                            <span>

                                                <?= e(
                                                    $skillName
                                                ) ?>

                                            </span>

                                        </div>

                                    <?php endforeach; ?>

                                </div>

                            </div>

                        <?php endforeach; ?>

                    </div>

                <?php else: ?>

                    <div
                        style="
                    text-align:center;
                    color:#64748b;
                    font-size:13px;
                ">

                        No public skills available.

                    </div>

                <?php endif; ?>


            </div>

        </section>

    <?php endif; ?>


    <!-- =========================================================
     CERTIFICATIONS
========================================================= -->

    <?php if (
        $showCertifications === 1
    ): ?>

        <section
            class="portfolio-section"
            id="certifications">

            <div class="portfolio-container">


                <div class="section-heading reveal">

                    <span class="section-eyebrow">
                        Credentials
                    </span>

                    <h2>
                        Certifications
                    </h2>

                    <p>
                        Professional certifications and credentials
                        I have earned.
                    </p>

                    <div class="heading-line"></div>

                </div>


                <?php if (
                    count($certifications) > 0
                ): ?>

                    <div class="certifications-grid">

                        <?php foreach (
                            $certifications
                            as $certification
                        ): ?>

                            <?php

                            $certificationName =
                                p_value(
                                    $certification,
                                    'name',
                                    p_value(
                                        $certification,
                                        'title',
                                        'Certification'
                                    )
                                );

                            $issuer =
                                p_value(
                                    $certification,
                                    'issuer',
                                    p_value(
                                        $certification,
                                        'issuing_organization'
                                    )
                                );

                            $issueDate =
                                p_date(
                                    $certification,
                                    'issue_date'
                                );

                            $credentialUrl =
                                social_link(
                                    p_value(
                                        $certification,
                                        'credential_url'
                                    )
                                );

                            ?>


                            <div
                                class="certification-card reveal">

                                <div
                                    class="certification-icon">

                                    <i
                                        class="mdi mdi-certificate-outline">
                                    </i>

                                </div>


                                <div
                                    class="certification-content">


                                    <?php if (
                                        $issueDate !== ''
                                    ): ?>

                                        <span
                                            class="certification-date">

                                            <?= e(
                                                $issueDate
                                            ) ?>

                                        </span>

                                    <?php endif; ?>


                                    <h3>

                                        <?= e(
                                            $certificationName
                                        ) ?>

                                    </h3>


                                    <?php if (
                                        $issuer !== ''
                                    ): ?>

                                        <p
                                            class="certification-issuer">

                                            <i
                                                class="mdi mdi-office-building-outline">
                                            </i>

                                            <?= e(
                                                $issuer
                                            ) ?>

                                        </p>

                                    <?php endif; ?>


                                    <?php if (
                                        $credentialUrl !== ''
                                    ): ?>

                                        <a
                                            href="<?= e($credentialUrl) ?>"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="credential-link">

                                            View Credential

                                            <i
                                                class="mdi mdi-open-in-new">
                                            </i>

                                        </a>

                                    <?php endif; ?>


                                </div>

                            </div>

                        <?php endforeach; ?>

                    </div>

                <?php else: ?>

                    <div
                        style="
                    text-align:center;
                    color:#64748b;
                    font-size:13px;
                ">

                        No public certifications available.

                    </div>

                <?php endif; ?>


            </div>

        </section>

    <?php endif; ?>


    <!-- =========================================================
     REFERENCES
========================================================= -->

    <?php if (
        $showReferences === 1 &&
        count($references) > 0
    ): ?>

        <section
            class="portfolio-section section-light"
            id="references">

            <div class="portfolio-container">


                <div class="section-heading reveal">

                    <span class="section-eyebrow">
                        Recommendations
                    </span>

                    <h2>
                        References
                    </h2>

                    <p>
                        Professional references and contacts.
                    </p>

                    <div class="heading-line"></div>

                </div>


                <div class="references-grid">


                    <?php foreach (
                        $references
                        as $reference
                    ): ?>

                        <?php

                        $referenceName =
                            p_value(
                                $reference,
                                'name',
                                p_value(
                                    $reference,
                                    'full_name',
                                    'Reference'
                                )
                            );


                        $referencePosition =
                            p_value(
                                $reference,
                                'position',
                                p_value(
                                    $reference,
                                    'job_title'
                                )
                            );


                        $referenceCompany =
                            p_value(
                                $reference,
                                'company',
                                p_value(
                                    $reference,
                                    'organization'
                                )
                            );


                        $referenceEmail =
                            p_value(
                                $reference,
                                'email'
                            );


                        $initial =
                            strtoupper(
                                mb_substr(
                                    $referenceName,
                                    0,
                                    1
                                )
                            );

                        ?>


                        <div
                            class="reference-card reveal">


                            <div
                                class="reference-avatar">

                                <?= e(
                                    $initial
                                ) ?>

                            </div>


                            <div
                                class="reference-content">


                                <h3>

                                    <?= e(
                                        $referenceName
                                    ) ?>

                                </h3>


                                <?php if (
                                    $referencePosition !== ''
                                ): ?>

                                    <span>

                                        <?= e(
                                            $referencePosition
                                        ) ?>

                                    </span>

                                <?php endif; ?>


                                <?php if (
                                    $referenceCompany !== ''
                                ): ?>

                                    <span>

                                        <?= e(
                                            $referenceCompany
                                        ) ?>

                                    </span>

                                <?php endif; ?>


                                <?php if (
                                    $referenceEmail !== ''
                                ): ?>

                                    <a
                                        href="mailto:<?= e($referenceEmail) ?>">

                                        <?= e(
                                            $referenceEmail
                                        ) ?>

                                    </a>

                                <?php endif; ?>


                            </div>

                        </div>


                    <?php endforeach; ?>


                </div>

            </div>

        </section>

    <?php endif; ?>


    <!-- =========================================================
     CONTACT
========================================================= -->

    <section
        class="portfolio-section contact-section"
        id="contact">

        <div class="portfolio-container">


            <div
                class="contact-card reveal">


                <div
                    class="contact-content">

                    <span class="section-eyebrow">
                        Get In Touch
                    </span>


                    <h2>
                        Let's work together.
                    </h2>


                    <p>

                        Have a project, opportunity, or question?
                        Feel free to send me a message.

                    </p>


                    <div
                        class="contact-details">


                        <?php if (
                            $contactEmail !== ''
                        ): ?>

                            <div
                                class="contact-detail">

                                <span>

                                    <i
                                        class="mdi mdi-email-outline">
                                    </i>

                                </span>

                                <div>

                                    <small>
                                        Email
                                    </small>

                                    <a
                                        href="mailto:<?= e($contactEmail) ?>">

                                        <?= e(
                                            $contactEmail
                                        ) ?>

                                    </a>

                                </div>

                            </div>

                        <?php endif; ?>


                        <?php if (
                            $website !== ''
                        ): ?>

                            <div
                                class="contact-detail">

                                <span>

                                    <i
                                        class="mdi mdi-web">
                                    </i>

                                </span>

                                <div>

                                    <small>
                                        Website
                                    </small>

                                    <a
                                        href="<?= e($website) ?>"
                                        target="_blank"
                                        rel="noopener noreferrer">

                                        Visit Website

                                    </a>

                                </div>

                            </div>

                        <?php endif; ?>


                        <?php if (
                            $showSocials === 1 &&
                            (
                                $github !== '' ||
                                $linkedin !== '' ||
                                $facebook !== ''
                            )
                        ): ?>

                            <div
                                class="contact-detail">

                                <span>

                                    <i
                                        class="mdi mdi-share-variant-outline">
                                    </i>

                                </span>

                                <div>

                                    <small>
                                        Social
                                    </small>

                                    <div
                                        class="contact-socials">


                                        <?php if (
                                            $github !== ''
                                        ): ?>

                                            <a
                                                href="<?= e($github) ?>"
                                                target="_blank"
                                                rel="noopener noreferrer">

                                                GitHub

                                            </a>

                                        <?php endif; ?>


                                        <?php if (
                                            $linkedin !== ''
                                        ): ?>

                                            <a
                                                href="<?= e($linkedin) ?>"
                                                target="_blank"
                                                rel="noopener noreferrer">

                                                LinkedIn

                                            </a>

                                        <?php endif; ?>


                                        <?php if (
                                            $facebook !== ''
                                        ): ?>

                                            <a
                                                href="<?= e($facebook) ?>"
                                                target="_blank"
                                                rel="noopener noreferrer">

                                                Facebook

                                            </a>

                                        <?php endif; ?>


                                    </div>

                                </div>

                            </div>

                        <?php endif; ?>


                    </div>

                </div>


                <!-- CONTACT FORM -->

                <form
                    action="<?= e(
                                BASE_URL
                                    . '/portfolio.php?username='
                                    . urlencode($username)
                                    . '#contact'
                            ) ?>"
                    method="POST"
                    class="portfolio-contact-form">

                    <input
                        type="hidden"
                        name="contact_form"
                        value="1">

                    <input
                        type="hidden"
                        name="user_id"
                        value="<?= (int) $userId ?>">

                    <input
                        type="hidden"
                        name="username"
                        value="<?= e($user['username']) ?>">

                    <input
                        type="hidden"
                        name="token"
                        value="<?= e($contactToken) ?>">

                    <!-- Honeypot field for basic spam protection -->
                    <div
                        style="
                            position:absolute;
                            left:-9999px;
                            width:1px;
                            height:1px;
                            overflow:hidden;
                        "
                        aria-hidden="true">

                        <label for="contact_website">
                            Website
                        </label>

                        <input
                            type="text"
                            id="contact_website"
                            name="website"
                            tabindex="-1"
                            autocomplete="off">

                    </div>


                    <div class="form-group">

                        <label for="contact_name">
                            Your Name
                        </label>

                        <input
                            type="text"
                            id="contact_name"
                            name="name"
                            placeholder="Enter your name"
                            value="<?= e($contactName) ?>"
                            required
                            maxlength="100"
                            autocomplete="name">

                    </div>


                    <div class="form-group">

                        <label for="contact_email">
                            Your Email
                        </label>

                        <input
                            type="email"
                            id="contact_email"
                            name="email"
                            placeholder="you@example.com"
                            value="<?= e($contactSenderEmail) ?>"
                            required
                            maxlength="150"
                            autocomplete="email">

                    </div>


                    <div class="form-group">

                        <label for="contact_subject">
                            Subject
                        </label>

                        <input
                            type="text"
                            id="contact_subject"
                            name="subject"
                            placeholder="What would you like to discuss?"
                            value="<?= e($contactSubject) ?>"
                            required
                            maxlength="200">

                    </div>


                    <div class="form-group">

                        <label for="contact_message">
                            Message
                        </label>

                        <textarea
                            id="contact_message"
                            name="message"
                            placeholder="Write your message here..."
                            required
                            maxlength="5000"><?= e($contactMessage) ?></textarea>

                    </div>


                    <?php if (!empty($contactErrors)): ?>

                        <div
                            class="contact-form-alert contact-form-error"
                            role="alert">

                            <div
                                style="
                                    display:flex;
                                    align-items:flex-start;
                                    gap:10px;
                                ">

                                <i class="mdi mdi-alert-circle-outline"></i>

                                <div>

                                    <?php foreach ($contactErrors as $contactError): ?>

                                        <div>
                                            <?= e($contactError) ?>
                                        </div>

                                    <?php endforeach; ?>

                                </div>

                            </div>

                        </div>

                    <?php endif; ?>


                    <?php if ($contactSuccess): ?>

                        <div
                            class="contact-form-alert contact-form-success"
                            role="status">

                            <div
                                style="
                                    display:flex;
                                    align-items:flex-start;
                                    gap:10px;
                                ">

                                <i class="mdi mdi-check-circle-outline"></i>

                                <div>

                                    <strong>
                                        Message sent successfully!
                                    </strong>

                                    <br>

                                    Your message has been sent to
                                    <?= e($fullName) ?>.

                                </div>

                            </div>

                        </div>

                    <?php endif; ?>


                    <button
                        type="submit"
                        class="primary-btn contact-submit-btn">

                        <i class="mdi mdi-send-outline"></i>

                        Send Message

                    </button>

                </form>


            </div>

        </div>

    </section>


    <!-- =========================================================
     FOOTER
========================================================= -->

    <footer
        class="portfolio-footer">

        <div
            class="portfolio-container footer-inner">


            <p>

                © <?= date('Y') ?>

                <?= e($fullName) ?>.

                All rights reserved.

            </p>


            <a
                href="#home"
                class="footer-top"
                aria-label="Back to top">

                <i
                    class="mdi mdi-arrow-up">
                </i>

            </a>


        </div>

    </footer>


    <!-- =========================================================
     BOOTSTRAP JS
========================================================= -->

    <script
        src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js">
    </script>


    <!-- =========================================================
     PORTFOLIO JAVASCRIPT
========================================================= -->

    <script>
        document.addEventListener(
            'DOMContentLoaded',
            function() {


                /*
                |--------------------------------------------------------------------------
                | NAVBAR
                |--------------------------------------------------------------------------
                */

                const navbar =
                    document.getElementById(
                        'portfolioNavbar'
                    );


                function updateNavbar() {

                    if (!navbar) {
                        return;
                    }

                    if (
                        window.scrollY > 20
                    ) {

                        navbar.classList.add(
                            'scrolled'
                        );

                    } else {

                        navbar.classList.remove(
                            'scrolled'
                        );

                    }

                }


                updateNavbar();


                window.addEventListener(
                    'scroll',
                    updateNavbar, {
                        passive: true
                    }
                );


                /*
                |--------------------------------------------------------------------------
                | MOBILE NAVIGATION
                |--------------------------------------------------------------------------
                */

                const mobileMenuBtn =
                    document.getElementById(
                        'mobileMenuBtn'
                    );


                const navLinks =
                    document.getElementById(
                        'portfolioNavLinks'
                    );


                if (
                    mobileMenuBtn &&
                    navLinks
                ) {

                    mobileMenuBtn.addEventListener(
                        'click',
                        function() {

                            navLinks.classList.toggle(
                                'show'
                            );


                            const expanded =
                                navLinks.classList.contains(
                                    'show'
                                );


                            mobileMenuBtn.setAttribute(
                                'aria-expanded',
                                expanded ?
                                'true' :
                                'false'
                            );


                            const icon =
                                mobileMenuBtn.querySelector(
                                    'i'
                                );


                            if (icon) {

                                icon.className =
                                    expanded ?
                                    'mdi mdi-close' :
                                    'mdi mdi-menu';

                            }

                        }
                    );


                    navLinks
                        .querySelectorAll(
                            '.nav-link, .nav-contact-btn'
                        )
                        .forEach(
                            function(link) {

                                link.addEventListener(
                                    'click',
                                    function() {

                                        navLinks.classList.remove(
                                            'show'
                                        );


                                        mobileMenuBtn.setAttribute(
                                            'aria-expanded',
                                            'false'
                                        );


                                        const icon =
                                            mobileMenuBtn.querySelector(
                                                'i'
                                            );


                                        if (icon) {

                                            icon.className =
                                                'mdi mdi-menu';

                                        }

                                    }
                                );

                            }
                        );

                }


                /*
                |--------------------------------------------------------------------------
                | TYPEWRITER
                |--------------------------------------------------------------------------
                */

                const typewriter =
                    document.getElementById(
                        'typewriter'
                    );


                if (typewriter) {

                    const words = [
                        <?= json_encode(
                            $professionalTitle !== ''
                                ? $professionalTitle
                                : 'Professional'
                        ) ?>
                    ];


                    let wordIndex = 0;

                    let characterIndex = 0;

                    let deleting = false;


                    function typeEffect() {

                        const currentWord =
                            words[wordIndex];


                        if (!deleting) {

                            characterIndex++;


                            typewriter.textContent =
                                currentWord.substring(
                                    0,
                                    characterIndex
                                );


                            if (
                                characterIndex >=
                                currentWord.length
                            ) {

                                deleting = true;


                                setTimeout(
                                    typeEffect,
                                    1800
                                );


                                return;

                            }

                        } else {

                            characterIndex--;


                            typewriter.textContent =
                                currentWord.substring(
                                    0,
                                    characterIndex
                                );


                            if (
                                characterIndex <= 0
                            ) {

                                deleting = false;


                                wordIndex =
                                    (
                                        wordIndex + 1
                                    ) %
                                    words.length;

                            }

                        }


                        setTimeout(
                            typeEffect,
                            deleting ?
                            45 :
                            80
                        );

                    }


                    typeEffect();

                }


                /*
                |--------------------------------------------------------------------------
                | REVEAL ANIMATION
                |--------------------------------------------------------------------------
                */

                const revealElements =
                    document.querySelectorAll(
                        '.reveal'
                    );


                if (
                    'IntersectionObserver' in window
                ) {

                    const observer =
                        new IntersectionObserver(
                            function(
                                entries,
                                observerInstance
                            ) {

                                entries.forEach(
                                    function(entry) {

                                        if (
                                            entry.isIntersecting
                                        ) {

                                            entry.target.classList.add(
                                                'revealed'
                                            );


                                            observerInstance.unobserve(
                                                entry.target
                                            );

                                        }

                                    }
                                );

                            }, {
                                threshold: 0.10
                            }
                        );


                    revealElements.forEach(
                        function(element) {

                            observer.observe(
                                element
                            );

                        }
                    );

                } else {

                    revealElements.forEach(
                        function(element) {

                            element.classList.add(
                                'revealed'
                            );

                        }
                    );

                }


                /*
                |--------------------------------------------------------------------------
                | ACTIVE NAVIGATION
                |--------------------------------------------------------------------------
                */

                const sections =
                    document.querySelectorAll(
                        'section[id]'
                    );


                const links =
                    document.querySelectorAll(
                        '.portfolio-nav-links .nav-link'
                    );


                function updateActiveNav() {

                    let current =
                        'home';


                    sections.forEach(
                        function(section) {

                            const top =
                                section.offsetTop -
                                120;


                            if (
                                window.scrollY >= top
                            ) {

                                current =
                                    section.id;

                            }

                        }
                    );


                    links.forEach(
                        function(link) {

                            link.classList.remove(
                                'active'
                            );


                            const href =
                                link.getAttribute(
                                    'href'
                                );


                            if (
                                href ===
                                '#' + current
                            ) {

                                link.classList.add(
                                    'active'
                                );

                            }

                        }
                    );

                }


                window.addEventListener(
                    'scroll',
                    updateActiveNav, {
                        passive: true
                    }
                );


                updateActiveNav();


                /*
                |--------------------------------------------------------------------------
                | PROJECT DETAILS BUTTON
                |--------------------------------------------------------------------------
                */

                document
                    .querySelectorAll(
                        '.project-details-btn'
                    )
                    .forEach(
                        function(button) {

                            button.addEventListener(
                                'click',
                                function(event) {

                                    event.preventDefault();

                                }
                            );

                        }
                    );


                /*
                |--------------------------------------------------------------------------
                | SMOOTH SCROLL
                |--------------------------------------------------------------------------
                */

                document
                    .querySelectorAll(
                        'a[href^="#"]'
                    )
                    .forEach(
                        function(link) {

                            link.addEventListener(
                                'click',
                                function(event) {

                                    const targetId =
                                        link.getAttribute(
                                            'href'
                                        );


                                    if (
                                        !targetId ||
                                        targetId === '#'
                                    ) {

                                        return;

                                    }


                                    /*
                                     * Bootstrap modal triggers
                                     * are handled by Bootstrap.
                                     */

                                    if (
                                        link.hasAttribute(
                                            'data-bs-toggle'
                                        )
                                    ) {

                                        return;

                                    }


                                    const target =
                                        document.querySelector(
                                            targetId
                                        );


                                    if (!target) {

                                        return;

                                    }


                                    event.preventDefault();


                                    const navbarHeight =
                                        navbar ?
                                        navbar.offsetHeight :
                                        0;


                                    const targetPosition =
                                        target
                                        .getBoundingClientRect()
                                        .top +
                                        window.scrollY -
                                        navbarHeight;


                                    window.scrollTo({
                                        top: targetPosition,
                                        behavior: 'smooth'
                                    });

                                }
                            );

                        }
                    );

            }
        );
    </script>


</body>

</html>