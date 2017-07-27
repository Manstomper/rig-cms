<?php

date_default_timezone_set('Europe/Helsinki');

$app['site'] = array(
	'name' => 'Manstomper.com',
	'description' => 'Arrest this girl!',
	'path' => 'http://manstomper.dev',
	'theme' => 'manstomper',
);

$app['db'] = array(
	'driver' => 'mysql',
	'host' => '127.0.0.1',
	'dbname' => 'rigcms',
	'username' => 'root',
	'password' => '',
);

$app['debug'] = true;

require __DIR__ . '/app.php';

//$app->get('/', 'public.controller:templateAction');
//$app->get('/', 'public.controller:indexAction')->value('section', 'micro');
$app->get('/', 'public.controller:indexAction');
$app->get('/rss/', 'public.controller:feedAction');
$app->get('/search/', 'public.controller:searchAction');
$app->get('/blog/', 'public.controller:indexAction');
$app->get('/blog/{section}/', 'public.controller:indexAction');
$app->get('/portfolio/', function() use ($app) { return $app->redirect($app['site']['path'], 301); });
$app->get('/portfolio/{section}/', 'public.controller:indexAction');
$app->get('/about/', 'public.controller:templateAction');
$app->get('/id/{id}/', 'public.controller:articleAction');

$app->match('/{slug}/', 'public.controller:articleAction')->method('GET|POST');