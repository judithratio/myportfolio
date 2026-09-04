<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| MyPortfolio Configuration
|--------------------------------------------------------------------------
*/

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


/*
|--------------------------------------------------------------------------
| Application
|--------------------------------------------------------------------------
*/

define('APP_NAME', 'MyPortfolio');

define(
    'BASE_URL',
    'http://localhost/myportfolio'
);


// /*
// |--------------------------------------------------------------------------
// | Database
// |--------------------------------------------------------------------------
// */

// define('DB_HOST', '127.0.0.1');
// define('DB_NAME', 'myportfolio');
// define('DB_USER', 'root');
// define('DB_PASS', '');


/*
|--------------------------------------------------------------------------
| Database INFINITY FREE
|--------------------------------------------------------------------------
*/

define('DB_HOST', 'sql107.infinityfree.com');
define('DB_NAME', 'if0_42829624_myportfolio');
define('DB_USER', 'if0_42829624');
define('DB_PASS', 'xPCSgetKgL');

/*
|--------------------------------------------------------------------------
| Uploads
|--------------------------------------------------------------------------
*/

define(
    'UPLOAD_DIR',
    dirname(__DIR__) . '/uploads'
);

define(
    'MAX_UPLOAD_SIZE',
    5 * 1024 * 1024
);


/*
|--------------------------------------------------------------------------
| Timezone
|--------------------------------------------------------------------------
*/

date_default_timezone_set('Asia/Manila');


/*
|--------------------------------------------------------------------------
| Maintenance Mode
|--------------------------------------------------------------------------
|
| false = Website is online
| true  = Website is under maintenance
|
| IMPORTANT:
| This setting does NOT automatically redirect users.
| Maintenance checks are handled separately.
|
*/

define('MAINTENANCE_MODE', false);


/*
|--------------------------------------------------------------------------
| Composer Autoload
|--------------------------------------------------------------------------
*/

$autoload = dirname(__DIR__) . '/vendor/autoload.php';

if (file_exists($autoload)) {
    require_once $autoload;
}


/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
*/

require_once dirname(__DIR__) . '/includes/functions.php';


/*
|--------------------------------------------------------------------------
| Database
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/database.php';


/*
|--------------------------------------------------------------------------
| Helper: Is Admin
|--------------------------------------------------------------------------
*/

if (!function_exists('is_admin')) {

    function is_admin(): bool
    {
        return isset($_SESSION['user_role'])
            && strtolower((string) $_SESSION['user_role']) === 'admin';
    }
}


/*
|--------------------------------------------------------------------------
| Helper: Is Logged In
|--------------------------------------------------------------------------
*/

if (!function_exists('is_logged_in')) {

    function is_logged_in(): bool
    {
        return isset($_SESSION['user_id'])
            && !empty($_SESSION['user_id']);
    }
}


/*
|--------------------------------------------------------------------------
| Helper: Asset URL
|--------------------------------------------------------------------------
*/

if (!function_exists('asset')) {

    function asset(string $path = ''): string
    {
        return rtrim(BASE_URL, '/') . '/' . ltrim($path, '/');
    }
}


/*
|--------------------------------------------------------------------------
| Helper: Portfolio URL
|--------------------------------------------------------------------------
*/

if (!function_exists('portfolio_url')) {

    function portfolio_url(array $user): string
    {
        $username = trim(
            (string) ($user['username'] ?? '')
        );

        if ($username === '') {
            return asset('portfolio.php');
        }

        return asset(
            'portfolio.php?username=' . urlencode($username)
        );
    }
}