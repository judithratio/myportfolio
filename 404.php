<?php

declare(strict_types=1);

require_once __DIR__ . '/config/config.php';

http_response_code(404);

$isLoggedIn = isset($_SESSION['user_id'])
    && !empty($_SESSION['user_id']);

$isAdmin = isset($_SESSION['user_role'])
    && $_SESSION['user_role'] === 'admin';

if ($isLoggedIn) {

    if ($isAdmin) {
        $backUrl = asset('admin/index.php');
    } else {
        $backUrl = asset('user/index.php');
    }
} else {

    $backUrl = asset('login.php');
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
        404 - Page Not Found | <?= htmlspecialchars(APP_NAME) ?>
    </title>

    <!-- Font Awesome -->
    <link
        href="vendor/fontawesome-free/css/all.min.css"
        rel="stylesheet">

    <!-- SB Admin 2 -->
    <link
        href="css/sb-admin-2.min.css"
        rel="stylesheet">

</head>

<body class="bg-gradient-primary">

    <div class="container">

        <div
            class="row justify-content-center align-items-center"
            style="min-height: 100vh;">

            <div class="col-xl-7 col-lg-8 col-md-10">

                <div class="card border-0 shadow-lg">

                    <div class="card-body p-5">

                        <div class="text-center">

                            <div class="mb-4">

                                <i
                                    class="fas fa-exclamation-triangle fa-4x text-warning"></i>

                            </div>

                            <h1 class="display-1 font-weight-bold text-gray-800">
                                404
                            </h1>

                            <h2 class="h3 text-gray-800 mb-3">
                                Page Not Found
                            </h2>

                            <p class="text-gray-600 mb-4">
                                Sorry, the page you're looking for
                                doesn't exist or may have been moved.
                            </p>

                            <a
                                href="<?= htmlspecialchars($backUrl) ?>"
                                class="btn btn-primary">

                                <i class="fas fa-home mr-2"></i>

                                <?php if ($isLoggedIn): ?>

                                    Back to Dashboard

                                <?php else: ?>

                                    Back to Login

                                <?php endif; ?>

                            </a>

                        </div>

                    </div>

                </div>

                <div class="text-center mt-4">

                    <span class="small text-white">
                        &copy; <?= date('Y') ?>
                        <?= htmlspecialchars(APP_NAME) ?>
                    </span>

                </div>

            </div>

        </div>

    </div>

</body>

</html>