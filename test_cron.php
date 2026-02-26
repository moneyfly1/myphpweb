<?php
define('APP_PATH', __DIR__ . '/Application/');
define('RUNTIME_PATH', __DIR__ . '/Runtime/');
require_once APP_PATH . 'Common/Common/EmailQueue.class.php';
$queue = new \EmailQueue();
var_dump($queue->getPendingEmails(1));
