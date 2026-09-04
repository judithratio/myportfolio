<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| MyPortfolio - Google OAuth Callback
|--------------------------------------------------------------------------
*/

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/google.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {

    /*
    |--------------------------------------------------------------------------
    | CHECK GOOGLE AUTHORIZATION CODE
    |--------------------------------------------------------------------------
    */

    if (
        !isset($_GET['code']) ||
        trim((string) $_GET['code']) === ''
    ) {
        $_SESSION['login_alert'] = [
            'type' => 'danger',
            'icon' => 'fas fa-exclamation-circle',
            'title' => 'Login Failed',
            'message' => 'Google did not return an authorization code. Please try again.'
        ];

        header('Location: ../login.php');
        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | CREATE GOOGLE CLIENT
    |--------------------------------------------------------------------------
    */

    $client = googleClient();


    /*
    |--------------------------------------------------------------------------
    | EXCHANGE AUTHORIZATION CODE FOR ACCESS TOKEN
    |--------------------------------------------------------------------------
    */

    $token = $client->fetchAccessTokenWithAuthCode(
        (string) $_GET['code']
    );

    if (
        !is_array($token) ||
        isset($token['error']) ||
        empty($token['access_token'])
    ) {

        $tokenError = 'Unknown error';

        if (is_array($token)) {
            if (!empty($token['error_description'])) {
                $tokenError = (string) $token['error_description'];
            } elseif (!empty($token['error'])) {
                $tokenError = (string) $token['error'];
            }
        }

        error_log(
            'MyPortfolio Google OAuth Token Error: ' . $tokenError
        );

        $_SESSION['login_alert'] = [
            'type' => 'danger',
            'icon' => 'fas fa-exclamation-circle',
            'title' => 'Google Login Failed',
            'message' => 'We could not authenticate your Google account. Please try again.'
        ];

        header('Location: ../login.php');
        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | SET ACCESS TOKEN
    |--------------------------------------------------------------------------
    */

    $client->setAccessToken($token);


    /*
    |--------------------------------------------------------------------------
    | GET GOOGLE USER INFORMATION
    |--------------------------------------------------------------------------
    */

    $oauth = new Google\Service\Oauth2($client);

    $info = $oauth->userinfo->get();


    /*
    |--------------------------------------------------------------------------
    | GET GOOGLE ACCOUNT DATA
    |--------------------------------------------------------------------------
    */

    $googleId = trim(
        (string) ($info->id ?? '')
    );

    $email = strtolower(
        trim((string) ($info->email ?? ''))
    );

    $name = trim(
        (string) ($info->name ?? '')
    );

    $picture = null;

    if (!empty($info->picture)) {
        $picture = trim(
            (string) $info->picture
        );
    }


    /*
    |--------------------------------------------------------------------------
    | VALIDATE GOOGLE DATA
    |--------------------------------------------------------------------------
    */

    if ($googleId === '') {
        throw new RuntimeException(
            'Google did not return a Google account ID.'
        );
    }

    if ($email === '') {
        throw new RuntimeException(
            'Google did not return an email address.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | DATABASE CONNECTION
    |--------------------------------------------------------------------------
    */

    $pdo = db();


    /*
    |--------------------------------------------------------------------------
    | FIND EXISTING USER
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare(
        "SELECT *
         FROM users
         WHERE google_id = ?
            OR email = ?
         LIMIT 1"
    );

    $stmt->execute([
        $googleId,
        $email
    ]);

    $user = $stmt->fetch();


    /*
    |--------------------------------------------------------------------------
    | CREATE NEW USER
    |--------------------------------------------------------------------------
    */

    if (!$user) {

        /*
        |--------------------------------------------------------------------------
        | GENERATE BASE USERNAME
        |--------------------------------------------------------------------------
        */

        $baseUsername = strtolower(
            trim(
                $name !== ''
                    ? $name
                    : 'user'
            )
        );

        $baseUsername = preg_replace(
            '/[^a-z0-9]+/',
            '',
            $baseUsername
        );

        if (
            !is_string($baseUsername) ||
            $baseUsername === ''
        ) {
            $baseUsername = 'user';
        }


        /*
        |--------------------------------------------------------------------------
        | MAKE USERNAME UNIQUE
        |--------------------------------------------------------------------------
        */

        $username = $baseUsername;
        $counter = 1;

        while (true) {

            $stmt = $pdo->prepare(
                "SELECT id
                 FROM users
                 WHERE username = ?
                 LIMIT 1"
            );

            $stmt->execute([
                $username
            ]);

            $existingUsername = $stmt->fetch();

            if (!$existingUsername) {
                break;
            }

            $counter++;

            $username = $baseUsername . $counter;
        }


        /*
        |--------------------------------------------------------------------------
        | INSERT USER
        |--------------------------------------------------------------------------
        */

        $stmt = $pdo->prepare(
            "INSERT INTO users
            (
                google_id,
                username,
                email,
                role,
                account_status
            )
            VALUES
            (
                ?,
                ?,
                ?,
                ?,
                ?
            )"
        );

        $stmt->execute([
            $googleId,
            $username,
            $email,
            'user',
            'active'
        ]);


        /*
        |--------------------------------------------------------------------------
        | GET NEW USER ID
        |--------------------------------------------------------------------------
        */

        $userId = (int) $pdo->lastInsertId();

        if ($userId <= 0) {
            throw new RuntimeException(
                'User was created but no valid user ID was returned.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | CREATE PROFILE
        |--------------------------------------------------------------------------
        */

        $stmt = $pdo->prepare(
            "INSERT INTO profiles
            (
                user_id,
                full_name,
                profile_image
            )
            VALUES
            (
                ?,
                ?,
                ?
            )"
        );

        $stmt->execute([
            $userId,
            $name,
            $picture
        ]);


        /*
        |--------------------------------------------------------------------------
        | LOAD NEW USER
        |--------------------------------------------------------------------------
        */

        $user = get_user($userId);

        if (!$user) {
            throw new RuntimeException(
                'The user was created but could not be loaded.'
            );
        }
    } else {

        /*
        |--------------------------------------------------------------------------
        | EXISTING USER
        |--------------------------------------------------------------------------
        */

        $userId = (int) $user['id'];


        /*
        |--------------------------------------------------------------------------
        | CHECK ACCOUNT STATUS
        |--------------------------------------------------------------------------
        */

        $accountStatus = strtolower(
            trim(
                (string) (
                    $user['account_status'] ?? ''
                )
            )
        );


        /*
        |--------------------------------------------------------------------------
        | INACTIVE ACCOUNT
        |--------------------------------------------------------------------------
        */

        if ($accountStatus === 'inactive') {

            $_SESSION['login_alert'] = [
                'type' => 'warning',
                'icon' => 'fas fa-user-slash',
                'title' => 'Account Inactive',
                'message' => 'This account is inactive. Contact the administrator (judith.ratio1@gmail.com).'
            ];

            header('Location: ../login.php');
            exit;
        }


        /*
        |--------------------------------------------------------------------------
        | SUSPENDED ACCOUNT
        |--------------------------------------------------------------------------
        */

        if ($accountStatus === 'suspended') {

            $_SESSION['login_alert'] = [
                'type' => 'danger',
                'icon' => 'fas fa-ban',
                'title' => 'Account Suspended',
                'message' => 'Your account has been suspended. Please contact the administrator.'
            ];

            header('Location: ../login.php');
            exit;
        }


        /*
        |--------------------------------------------------------------------------
        | PENDING ACCOUNT
        |--------------------------------------------------------------------------
        */

        if ($accountStatus === 'pending') {

            $_SESSION['login_alert'] = [
                'type' => 'info',
                'icon' => 'fas fa-clock',
                'title' => 'Account Pending',
                'message' => 'Your account is waiting for administrator approval.'
            ];

            header('Location: ../login.php');
            exit;
        }


        /*
        |--------------------------------------------------------------------------
        | UNKNOWN ACCOUNT STATUS
        |--------------------------------------------------------------------------
        */

        if ($accountStatus !== 'active') {

            $_SESSION['login_alert'] = [
                'type' => 'warning',
                'icon' => 'fas fa-user-slash',
                'title' => 'Account Unavailable',
                'message' => 'Your account cannot be accessed at this time. Please contact the administrator.'
            ];

            header('Location: ../login.php');
            exit;
        }


        /*
        |--------------------------------------------------------------------------
        | ADD GOOGLE ID IF MISSING
        |--------------------------------------------------------------------------
        */

        $existingGoogleId = trim(
            (string) (
                $user['google_id'] ?? ''
            )
        );

        if ($existingGoogleId === '') {

            $stmt = $pdo->prepare(
                "UPDATE users
                 SET google_id = ?
                 WHERE id = ?"
            );

            $stmt->execute([
                $googleId,
                $userId
            ]);
        }


        /*
        |--------------------------------------------------------------------------
        | MAKE SURE PROFILE EXISTS
        |--------------------------------------------------------------------------
        */

        ensure_profile($userId);


        /*
        |--------------------------------------------------------------------------
        | GET PROFILE
        |--------------------------------------------------------------------------
        */

        $profile = get_profile($userId);


        /*
        |--------------------------------------------------------------------------
        | UPDATE NAME ONLY IF PROFILE NAME IS EMPTY
        |--------------------------------------------------------------------------
        */

        if ($name !== '') {

            $stmt = $pdo->prepare(
                "UPDATE profiles
                 SET full_name = ?
                 WHERE user_id = ?
                 AND (
                     full_name IS NULL
                     OR TRIM(full_name) = ''
                 )"
            );

            $stmt->execute([
                $name,
                $userId
            ]);
        }


        /*
        |--------------------------------------------------------------------------
        | PRESERVE EXISTING PROFILE IMAGE
        |--------------------------------------------------------------------------
        |
        | Google picture is only saved when the user does not already
        | have a profile image.
        |
        */

        $existingProfileImage = trim(
            (string) (
                $profile['profile_image'] ?? ''
            )
        );

        if (
            $existingProfileImage === '' &&
            $picture !== null &&
            $picture !== ''
        ) {

            $stmt = $pdo->prepare(
                "UPDATE profiles
                 SET profile_image = ?
                 WHERE user_id = ?"
            );

            $stmt->execute([
                $picture,
                $userId
            ]);
        }


        /*
        |--------------------------------------------------------------------------
        | CREATE USERNAME IF MISSING
        |--------------------------------------------------------------------------
        */

        $existingUsername = trim(
            (string) (
                $user['username'] ?? ''
            )
        );

        if ($existingUsername === '') {

            /*
            |--------------------------------------------------------------------------
            | GENERATE BASE USERNAME
            |--------------------------------------------------------------------------
            */

            $baseUsername = strtolower(
                trim(
                    $name !== ''
                        ? $name
                        : 'user'
                )
            );

            $baseUsername = preg_replace(
                '/[^a-z0-9]+/',
                '',
                $baseUsername
            );

            if (
                !is_string($baseUsername) ||
                $baseUsername === ''
            ) {
                $baseUsername = 'user';
            }


            /*
            |--------------------------------------------------------------------------
            | MAKE USERNAME UNIQUE
            |--------------------------------------------------------------------------
            */

            $username = $baseUsername;
            $counter = 1;

            while (true) {

                $stmt = $pdo->prepare(
                    "SELECT id
                     FROM users
                     WHERE username = ?
                       AND id != ?
                     LIMIT 1"
                );

                $stmt->execute([
                    $username,
                    $userId
                ]);

                $existingUsernameRecord = $stmt->fetch();

                if (!$existingUsernameRecord) {
                    break;
                }

                $counter++;

                $username = $baseUsername . $counter;
            }


            /*
            |--------------------------------------------------------------------------
            | SAVE USERNAME
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare(
                "UPDATE users
                 SET username = ?
                 WHERE id = ?"
            );

            $stmt->execute([
                $username,
                $userId
            ]);
        }


        /*
        |--------------------------------------------------------------------------
        | RELOAD USER
        |--------------------------------------------------------------------------
        */

        $user = get_user($userId);

        if (!$user) {
            throw new RuntimeException(
                'Unable to retrieve account information after login.'
            );
        }
    }


    /*
    |--------------------------------------------------------------------------
    | FINAL ACCOUNT STATUS CHECK
    |--------------------------------------------------------------------------
    */

    $accountStatus = strtolower(
        trim(
            (string) (
                $user['account_status'] ?? ''
            )
        )
    );

    if ($accountStatus !== 'active') {

        $_SESSION['login_alert'] = [
            'type' => 'warning',
            'icon' => 'fas fa-user-slash',
            'title' => 'Account Unavailable',
            'message' => 'Your account is currently unavailable. Please contact the administrator.'
        ];

        header('Location: ../login.php');
        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | REGENERATE SESSION ID
    |--------------------------------------------------------------------------
    */

    session_regenerate_id(true);


    /*
    |--------------------------------------------------------------------------
    | STORE USER SESSION
    |--------------------------------------------------------------------------
    */

    $_SESSION['user_id'] = (int) $user['id'];

    $_SESSION['email'] = (string) (
        $user['email'] ?? $email
    );

    $_SESSION['user_role'] = (string) (
        $user['role'] ?? 'user'
    );

    /*
    |--------------------------------------------------------------------------
    | KEEP ROLE FOR EXISTING PAGES
    |--------------------------------------------------------------------------
    */

    $_SESSION['role'] = (string) (
        $user['role'] ?? 'user'
    );

    $_SESSION['name'] = $name !== ''
        ? $name
        : (string) (
            $user['full_name'] ?? ''
        );

    /*
    |--------------------------------------------------------------------------
    | USE DATABASE PROFILE IMAGE WHEN AVAILABLE
    |--------------------------------------------------------------------------
    */

    $finalProfile = get_profile(
        (int) $user['id']
    );

    $finalProfileImage = trim(
        (string) (
            $finalProfile['profile_image'] ?? ''
        )
    );

    if ($finalProfileImage !== '') {
        $_SESSION['picture'] = $finalProfileImage;
    } else {
        $_SESSION['picture'] = $picture;
    }


    /*
    |--------------------------------------------------------------------------
    | CLEAR LOGIN ERROR
    |--------------------------------------------------------------------------
    */

    unset(
        $_SESSION['login_alert']
    );


    /*
    |--------------------------------------------------------------------------
    | REDIRECT USER
    |--------------------------------------------------------------------------
    */

    if (
        strtolower(
            (string) (
                $user['role'] ?? 'user'
            )
        ) === 'admin'
    ) {

        redirect('admin/index.php');
    } else {

        redirect('user/index.php');
    }


    /*
|--------------------------------------------------------------------------
| ERROR HANDLER
|--------------------------------------------------------------------------
*/
} catch (Throwable $e) {

    /*
    |--------------------------------------------------------------------------
    | LOG ACTUAL ERROR
    |--------------------------------------------------------------------------
    */

    error_log(
        'MyPortfolio Google Login Error: '
            . $e->getMessage()
            . ' | File: '
            . $e->getFile()
            . ' | Line: '
            . $e->getLine()
    );


    /*
    |--------------------------------------------------------------------------
    | TEMPORARILY DISPLAY THE REAL ERROR
    |--------------------------------------------------------------------------
    |
    | This is intentional for debugging.
    | Once Google login works, change this back to the normal
    | login redirect.
    |
    */

    echo '<!DOCTYPE html>';
    echo '<html lang="en">';
    echo '<head>';
    echo '<meta charset="UTF-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
    echo '<title>Google Login Error</title>';

    echo '<style>';
    echo '
        body {
            margin: 0;
            padding: 40px 20px;
            background: #f8f9fc;
            font-family: Arial, sans-serif;
            color: #333;
        }

        .error-card {
            max-width: 850px;
            margin: 40px auto;
            background: #fff;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 4px 20px rgba(0,0,0,.08);
            border-left: 5px solid #e74a3b;
        }

        h1 {
            margin-top: 0;
            color: #e74a3b;
            font-size: 24px;
        }

        .label {
            font-weight: bold;
            margin-top: 20px;
            margin-bottom: 6px;
        }

        .value {
            background: #f8f9fc;
            padding: 12px;
            border-radius: 6px;
            word-break: break-word;
            font-family: Consolas, monospace;
            font-size: 14px;
        }

        .back-button {
            display: inline-block;
            margin-top: 25px;
            padding: 11px 18px;
            background: #4e73df;
            color: #fff;
            text-decoration: none;
            border-radius: 6px;
        }

        .back-button:hover {
            background: #2e59d9;
        }
    ';
    echo '</style>';

    echo '</head>';
    echo '<body>';

    echo '<div class="error-card">';

    echo '<h1>Google Login Error</h1>';

    echo '<div class="label">Error Message</div>';

    echo '<div class="value">'
        . htmlspecialchars(
            $e->getMessage(),
            ENT_QUOTES,
            'UTF-8'
        )
        . '</div>';

    echo '<div class="label">File</div>';

    echo '<div class="value">'
        . htmlspecialchars(
            $e->getFile(),
            ENT_QUOTES,
            'UTF-8'
        )
        . '</div>';

    echo '<div class="label">Line</div>';

    echo '<div class="value">'
        . htmlspecialchars(
            (string) $e->getLine(),
            ENT_QUOTES,
            'UTF-8'
        )
        . '</div>';

    echo '<a class="back-button" href="../login.php">';
    echo 'Back to Login';
    echo '</a>';

    echo '</div>';

    echo '</body>';
    echo '</html>';

    exit;
}
