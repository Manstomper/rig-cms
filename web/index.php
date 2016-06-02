<?php

$loader = require_once __DIR__ . '/../vendor/autoload.php';
$loader->add('RigCms', __DIR__ . '/../app');

$app = new Silex\Application();

require __DIR__ . '/../app/config.php';

$app->run();