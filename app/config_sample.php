<?php

/*
Rename this file to config.php.
If you want to have separate settings for the development environment, copy as config_dev.php and use the front controller index_dev.php
*/

date_default_timezone_set('Europe/Helsinki');

$app['site'] = array(
	'name' => '',
	'description' => '',
	'path' => '',
	'theme' => '',
);

$app['db'] = array(
	'driver' => 'mysql',
	'host' => '127.0.0.1',
	'dbname' => '',
	'username' => '',
	'password' => '',
);

$app['debug'] = false;

require __DIR__ . '/app.php';

$app->get('/', 'public.controller:templateAction');
$app->get('/rss/', 'public.controller:feedAction');
$app->get('/search/', 'public.controller:searchAction');
$app->get('/id/{id}/', 'public.controller:articleAction');
$app->get('/section/{section}/', 'public.controller:indexAction');
$app->get('/{section}/{subsection}/', 'public.controller:indexAction');
$app->get('/{slug}/', 'public.controller:articleAction');

$app->post('/{slug}/', 'public.controller:articleAction');