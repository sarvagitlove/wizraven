<?php
require 'vendor/autoload.php';

$client = new Google_Client();
$client->setClientId(getenv('GOOGLE_CLIENT_ID'));
$client->setClientSecret(getenv('GOOGLE_CLIENT_SECRET'));
$client->setRedirectUri('urn:ietf:wg:oauth:2.0:oob');
$client->setScopes(['https://mail.google.com/']);
$client->setAccessType('offline');
$client->setApprovalPrompt('force');

echo $client->createAuthUrl();
