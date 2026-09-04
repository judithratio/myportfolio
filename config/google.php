<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

define('GOOGLE_CLIENT_ID', '249613628870-g5mi3kcq0ovr9funngl0ughdkie5dqa4.apps.googleusercontent.com');
define('GOOGLE_CLIENT_SECRET', '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');
define('GOOGLE_REDIRECT_URI', BASE_URL . '/auth/google-callback.php');

function googleClient(): Google\Client {
    $client = new Google\Client();
    $client->setClientId(GOOGLE_CLIENT_ID);
    $client->setClientSecret(GOOGLE_CLIENT_SECRET);
    $client->setRedirectUri(GOOGLE_REDIRECT_URI);
    $client->setAccessType('offline');
    $client->setPrompt('select_account');
    $client->addScope(['openid', 'email', 'profile']);
    return $client;
}
