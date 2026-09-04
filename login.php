<?php

declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/google.php';

$pageTitle = 'Login';

/*
|--------------------------------------------------------------------------
| START SESSION
|--------------------------------------------------------------------------
*/

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


/*
|--------------------------------------------------------------------------
| GOOGLE CLIENT
|--------------------------------------------------------------------------
*/

$client = googleClient();


/*
|--------------------------------------------------------------------------
| LOGIN ALERT
|--------------------------------------------------------------------------
|
| The Google callback stores login messages in:
|
| $_SESSION['login_alert']
|
| Example:
|
| [
|     'type'    => 'danger',
|     'icon'    => 'fas fa-exclamation-circle',
|     'title'   => 'Login Failed',
|     'message' => 'Your account was not found.'
| ]
|
|--------------------------------------------------------------------------
*/

$loginAlert = $_SESSION['login_alert'] ?? null;

/*
|--------------------------------------------------------------------------
| REMOVE ALERT AFTER READING
|--------------------------------------------------------------------------
|
| This allows the message to appear only once after the redirect.
|
|--------------------------------------------------------------------------
*/

unset($_SESSION['login_alert']);


/*
|--------------------------------------------------------------------------
| ALREADY LOGGED IN
|--------------------------------------------------------------------------
*/

if (!empty($_SESSION['user_id'])) {

    $redirectPage =
        isset($_SESSION['role']) &&
        $_SESSION['role'] === 'admin'
        ? 'admin/index.php'
        : 'user/index.php';

    header('Location: ' . $redirectPage);
    exit;
}

?>
<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0">

    <title>
        MyPortfolio
    </title>


    <!-- Bootstrap -->
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet">


    <!-- Bootstrap Icons -->
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
        rel="stylesheet">


    <!-- Font Awesome -->
    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">


    <!-- Google Font -->
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">


    <!-- MyPortfolio Login CSS -->
    <link
        rel="stylesheet"
        href="css/login.css">

</head>


<body>

    <!-- =========================================================
     HERO / LOGIN
========================================================= -->

    <section
        class="mp-hero"
        id="home">

        <div class="container">

            <div class="row align-items-center">


                <!-- =================================================
                 HERO CONTENT
            ================================================== -->

                <div class="col-lg-6">

                    <div class="hero-content">


                        <!-- <div class="hero-badge">

                            <i class="bi bi-stars"></i>

                            <span>
                                Build. Organize. Showcase.
                            </span>

                        </div> -->


                        <h1 class="hero-title">

                            Build your <span>professional
                                portfolio</span>
                            with ease.

                        </h1>


                        <p class="hero-description">

                            MyPortfolio helps you create, organize,
                            and showcase your professional experience,
                            education, projects, skills, and achievements
                            in one place.

                        </p>

                    </div>

                </div>



                <!-- =================================================
                 LOGIN CARD
            ================================================== -->

                <div
                    class="col-lg-5 offset-lg-1"
                    id="login">

                    <div class="login-card">


                        <!-- =================================================
                         LOGIN HEADER
                    ================================================== -->

                        <div class="login-card-header">

                            <div class="login-logo">

                                <!-- <div class="login-logo-icon">

                                    <i class="bi bi-briefcase-fill"></i>

                                </div> -->

                            </div>


                            <h2>
                                Welcome to MyPortfolio
                            </h2>


                            <p>
                                Sign in to create and manage your
                                professional portfolio.
                            </p>

                        </div>



                        <!-- =================================================
                         LOGIN ALERT
                    ================================================== -->

                        <?php if (!empty($loginAlert)): ?>

                            <div
                                class="alert alert-<?= htmlspecialchars(
                                                        (string)($loginAlert['type'] ?? 'danger'),
                                                        ENT_QUOTES,
                                                        'UTF-8'
                                                    ) ?> login-alert"
                                role="alert">


                                <div class="login-alert-icon">

                                    <i
                                        class="<?= htmlspecialchars(
                                                    (string)($loginAlert['icon'] ?? 'fas fa-exclamation-circle'),
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ) ?>"></i>

                                </div>


                                <div class="login-alert-content">


                                    <?php if (!empty($loginAlert['title'])): ?>

                                        <strong class="login-alert-title">

                                            <?= htmlspecialchars(
                                                (string)$loginAlert['title'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>

                                        </strong>

                                    <?php endif; ?>


                                    <?php if (!empty($loginAlert['message'])): ?>

                                        <span class="login-alert-message">

                                            <?= htmlspecialchars(
                                                (string)$loginAlert['message'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>

                                        </span>

                                    <?php endif; ?>


                                </div>


                                <button
                                    type="button"
                                    class="login-alert-close"
                                    onclick="this.parentElement.remove();"
                                    aria-label="Close">

                                    <i class="bi bi-x"></i>

                                </button>


                            </div>

                        <?php endif; ?>



                        <!-- =================================================
                         LOGIN BODY
                    ================================================== -->

                        <div class="login-card-body">


                            <!-- GOOGLE LOGIN -->

                            <a
                                href="<?= htmlspecialchars(
                                            $client->createAuthUrl(),
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>"
                                class="google-login-button"
                                id="googleLoginButton">

                                <span class="google-icon">

                                    <svg
                                        width="20"
                                        height="20"
                                        viewBox="0 0 24 24"
                                        xmlns="http://www.w3.org/2000/svg">

                                        <path
                                            fill="#4285F4"
                                            d="M21.35 12.27c0-.79-.07-1.55-.2-2.27H12v4.3h5.24a4.48 4.48 0 0 1-1.94 2.94v2.45h3.14c1.84-1.69 2.91-4.18 2.91-7.42z" />

                                        <path
                                            fill="#34A853"
                                            d="M12 21.75c2.63 0 4.84-.87 6.45-2.36l-3.14-2.45c-.87.58-1.98.92-3.31.92-2.54 0-4.69-1.72-5.46-4.03H3.29v2.53A9.75 9.75 0 0 0 12 21.75z" />

                                        <path
                                            fill="#FBBC05"
                                            d="M6.54 13.83A5.86 5.86 0 0 1 6.23 12c0-.64.11-1.26.31-1.83V7.64H3.29A9.74 9.74 0 0 0 2.25 12c0 1.57.38 3.05 1.04 4.36l3.25-2.53z" />

                                        <path
                                            fill="#EA4335"
                                            d="M12 6.14c1.43 0 2.71.49 3.72 1.45l2.79-2.79C16.84 3.24 14.63 2.25 12 2.25a9.75 9.75 0 0 0-8.71 5.39l3.25 2.53c.77-2.31 2.92-4.03 5.46-4.03z" />

                                    </svg>

                                </span>


                                <span class="google-button-text">
                                    Continue with Google
                                </span>

                            </a>



                            <!-- DIVIDER -->

                            <div class="login-divider">

                                <span>
                                    Secure sign in
                                </span>
                            </div>



                            <!-- TERMS -->

                            <div class="login-body">
                                <p class="login-security-text text-center">
                                    Your Google account is used only
                                    to securely sign you in.
                                </p>

                            </div>


                        </div>

                    </div>

                </div>


            </div>

        </div>

    </section>


    <!-- =========================================================
     BOOTSTRAP JS
========================================================= -->

    <script
        src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>


    <!-- =========================================================
     LOGIN JS
========================================================= -->

    <script src="js/login.js"></script>


    <!-- =========================================================
     LOGIN ALERT FALLBACK JS
========================================================= -->

    <script>
        document.addEventListener(
            "DOMContentLoaded",
            function() {

                /*
                |--------------------------------------------------------------------------
                | AUTO HIDE LOGIN ALERT
                |--------------------------------------------------------------------------
                */

                const alerts =
                    document.querySelectorAll(".login-alert");


                alerts.forEach(function(alert) {

                    setTimeout(function() {

                        if (!alert || !alert.parentElement) {
                            return;
                        }


                        alert.style.transition =
                            "opacity 0.4s ease, transform 0.4s ease";


                        alert.style.opacity = "0";


                        alert.style.transform =
                            "translateY(-5px)";


                        setTimeout(function() {

                            if (alert && alert.parentElement) {
                                alert.remove();
                            }

                        }, 400);


                    }, 6000);

                });


                /*
                |--------------------------------------------------------------------------
                | GOOGLE LOGIN BUTTON
                |--------------------------------------------------------------------------
                */

                const googleButton =
                    document.getElementById(
                        "googleLoginButton"
                    );


                if (googleButton) {

                    googleButton.addEventListener(
                        "click",
                        function() {

                            googleButton.classList.add(
                                "disabled"
                            );


                            googleButton.style.pointerEvents =
                                "none";


                            const buttonText =
                                googleButton.querySelector(
                                    ".google-button-text"
                                );


                            if (buttonText) {

                                buttonText.textContent =
                                    "Connecting to Google...";

                            }

                        }
                    );

                }


                /*
                |--------------------------------------------------------------------------
                | MOBILE NAVBAR
                |--------------------------------------------------------------------------
                */

                const navLinks =
                    document.querySelectorAll(
                        ".mp-navbar .nav-link"
                    );


                const navbar =
                    document.getElementById(
                        "mainNavbar"
                    );


                navLinks.forEach(function(link) {

                    link.addEventListener(
                        "click",
                        function() {

                            if (
                                navbar &&
                                navbar.classList.contains("show")
                            ) {

                                const collapse =
                                    bootstrap.Collapse
                                    .getInstance(navbar);


                                if (collapse) {
                                    collapse.hide();
                                }

                            }

                        }
                    );

                });


            }

        );
    </script>


</body>

</html>