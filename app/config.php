<?php

date_default_timezone_set('Europe/Helsinki');

$app['site'] = array(
	'name' => 'Manstomper.com',
	'description' => 'Arrest this girl!',
	'path' => 'http://manstomper.com',
	'theme' => 'manstomper',
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
$app->get('/blog/', 'public.controller:indexAction');
$app->get('/portfolio/', function() use ($app) { return $app->redirect($app['site']['path'], 301); });
$app->get('/id/{id}/', 'public.controller:articleAction');
$app->get('/section/{section}/', 'public.controller:indexAction');
$app->get('/{section}/{subsection}/', 'public.controller:indexAction');
$app->get('/{slug}/', 'public.controller:articleAction');

$app->post('/{slug}/', 'public.controller:articleAction');