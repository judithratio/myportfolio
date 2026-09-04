<?php

declare(strict_types=1);

require_once __DIR__ . '/config/config.php';


/*
|--------------------------------------------------------------------------
| HTTP Status
|--------------------------------------------------------------------------
*/

http_response_code(503);


/*
|--------------------------------------------------------------------------
| Page Information
|--------------------------------------------------------------------------
*/

$pageTitle = 'System Maintenance';


/*
|--------------------------------------------------------------------------
| Admin Status
|--------------------------------------------------------------------------
*/

$isAdmin =
    isset($_SESSION['user_role']) &&
    strtolower(
        (string) $_SESSION['user_role']
    ) === 'admin';

?>
<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0">

    <meta
        name="robots"
        content="noindex, nofollow">

    <title>
        Maintenance | <?= htmlspecialchars(APP_NAME) ?>
    </title>


    <!-- SB Admin 2 CSS -->
    <link
        href="<?= asset('vendor/fontawesome-free/css/all.min.css') ?>"
        rel="stylesheet"
        type="text/css">

    <link
        href="<?= asset('css/sb-admin-2.min.css') ?>"
        rel="stylesheet">

</head>


<body class="bg-gradient-primary">


    <div class="container">


        <div
            class="row justify-content-center align-items-center"
            style="min-height: 100vh;">


            <div
                class="col-xl-7 col-lg-8 col-md-10">


                <div
                    class="card border-0 shadow-lg">


                    <div class="card-body p-5">


                        <div class="text-center">


                            <!-- Icon -->

                            <div class="mb-4">

                                <i
                                    class="fas fa-tools fa-4x text-primary"></i>

                            </div>


                            <!-- Title -->

                            <h1
                                class="h2 font-weight-bold text-gray-800 mb-3">
                                We'll Be Back Soon
                            </h1>


                            <!-- Description -->

                            <p
                                class="text-gray-600 mb-3">
                                <?= htmlspecialchars(APP_NAME) ?>
                                is currently undergoing
                                scheduled maintenance.
                            </p>


                            <p
                                class="text-gray-600 mb-4">
                                We're working to improve the
                                system and make your experience
                                better.
                            </p>


                            <!-- Information -->

                            <div
                                class="alert alert-info text-left">

                                <div
                                    class="d-flex align-items-start">

                                    <div class="mr-3">

                                        <i
                                            class="fas fa-info-circle"></i>

                                    </div>

                                    <div>

                                        <strong>
                                            System temporarily unavailable
                                        </strong>

                                        <div class="small mt-1">

                                            Please check back
                                            again shortly.

                                        </div>

                                    </div>

                                </div>

                            </div>

                        </div>


                    </div>


                </div>


                <!-- Footer -->

                <div
                    class="text-center mt-4">

                    <span
                        class="small text-white">

                        &copy;
                        <?= date('Y') ?>

                        <?= htmlspecialchars(APP_NAME) ?>

                    </span>

                </div>


            </div>


        </div>


    </div>


</body>

</html>