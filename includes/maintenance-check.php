<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Maintenance Check
|--------------------------------------------------------------------------
|
| This file should only be included on pages that should be
| unavailable while maintenance mode is active.
|
*/


/*
|--------------------------------------------------------------------------
| Make sure configuration is loaded
|--------------------------------------------------------------------------
*/

if (!defined('MAINTENANCE_MODE')) {
    return;
}


/*
|--------------------------------------------------------------------------
| Maintenance Mode OFF
|--------------------------------------------------------------------------
*/

if (MAINTENANCE_MODE !== true) {
    return;
}


/*
|--------------------------------------------------------------------------
| Current Page
|--------------------------------------------------------------------------
*/

$currentScript = basename(
    $_SERVER['SCRIPT_NAME'] ?? ''
);


/*
|--------------------------------------------------------------------------
| Pages That Must Always Remain Accessible
|--------------------------------------------------------------------------
*/

$allowedPages = [
    'maintenance.php',
    'login.php',
    'google_login.php',
    'google-callback.php',
    'logout.php'
];

if (
    in_array(
        $currentScript,
        $allowedPages,
        true
    )
) {
    return;
}


/*
|--------------------------------------------------------------------------
| Allow Administrators
|--------------------------------------------------------------------------
|
| Admins can continue accessing the dashboard while
| maintenance mode is active.
|
*/

if (
    isset($_SESSION['user_id']) &&
    isset($_SESSION['user_role']) &&
    strtolower(
        (string) $_SESSION['user_role']
    ) === 'admin'
) {
    return;
}


/*
|--------------------------------------------------------------------------
| Redirect User to Maintenance Page
|--------------------------------------------------------------------------
*/

header(
    'Location: ' . BASE_URL . '/maintenance.php',
    true,
    302
);

exit;