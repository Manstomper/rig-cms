<?php

if (isset($_SERVER['HTTP_CLIENT_IP']) || isset($_SERVER['HTTP_X_FORWARDED_FOR']) || !(in_array(@$_SERVER['REMOTE_ADDR'], array('127.0.0.1', 'fe80::1', '::1'))))
{
	header('HTTP/1.0 403 Forbidden');
	header('Content-type: text/plain');
	exit('You are not allowed to access this file. Check ' . basename(__FILE__) . ' for more information.');
}

$loader = require_once __DIR__ . '/../vendor/autoload.php';
$loader->add('RigCms', __DIR__ . '/../app');

$app = new Silex\Application();

require __DIR__ . '/../app/config_dev.php';

$app->run();