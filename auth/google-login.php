<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/google.php';
$client = googleClient();
header('Location: ' . $client->createAuthUrl());
exit;
