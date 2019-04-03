<?php

/**
 * @file.
 * This is the wrapper listener for Github webhooks.
 */

require(__DIR__ . '/../vendor/autoload.php');

include_once 'push.php';

webhook_push_callback('');
return;


// Send a response so we don't trigger Github timeout.
echo 'OK';
fastcgi_finish_request();

// Detect the payload as it may varies depending the request's content type.
$payload = FALSE;
switch ($_SERVER['CONTENT_TYPE']) {
  case 'application/json':
    $payload = file_get_contents('php://input');
    break;
  case 'application/x-www-form-urlencoded':
    $payload = $_POST['payload'];
    break;
}

// We don't do anything if we don't have a valid payload.
if (!$payload) {
  error_log('Not able to get the payload from ' . $_SERVER['HTTP_X_GITHUB_DELIVERY'] . ' request.');
  return;
}

error_log(var_export($payload, 1));
error_log('test');

// Decode the payload.
$payload = json_decode($payload);

// Detect the webhook event type and trigger proper process.
switch($_SERVER['HTTP_X_GITHUB_EVENT']) {
  case 'push':
    webhook_push_callback($payload);
    break;

  default:
    error_log('Unknown event ' . $_SERVER['HTTP_X_GITHUB_EVENT']);
}