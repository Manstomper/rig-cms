<?php

use Silex\Provider\ServiceControllerServiceProvider;
use Silex\Provider\SessionServiceProvider;
use Silex\Provider\TwigServiceProvider;
use Symfony\Component\Security\Core\Encoder\EncoderFactory;
use Symfony\Component\Security\Core\Encoder\MessageDigestPasswordEncoder;
use Symfony\Component\HttpFoundation\Response;
use RigCms\Model\UserProvider;
use RigCms\Model\UserModel;
use RigCms\Model\ArticleModel;
use RigCms\Model\TaxonomyModel;
use RigCms\Model\DiscussModel;
use RigCms\Controller\PublicController;
use RigCms\Controller\UserController;
use RigCms\Controller\ArticleController;
use RigCms\Controller\TaxonomyController;
use RigCms\Controller\DiscussController;

$app['db'] = new \PDO('mysql:host=' . $app['db']['host'] . ';dbname=' . $app['db']['dbname'] . ';charset=utf8', $app['db']['username'], $app['db']['password']);
$app['db']->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);

$app->register(new ServiceControllerServiceProvider());
$app->register(new SessionServiceProvider());

$app->register(new Silex\Provider\SecurityServiceProvider(), array(
	'security.firewalls' => array(
		'main' => array(
			'pattern' => '/',
			'form' => array(
				'login_path' => '/login',
				'check_path' => '/login_check',
				'default_target_path' => '/admin/dashboard/',
			),
			'logout' => array('logout_path' => '/admin/logout'),
			'users' => $app->share(function($app)
			{
				return new UserProvider($app['db']);
			}),
			'anonymous' => true,
		),
	),
	'security.role_hierarchy' => array(
		'ROLE_ADMIN' => array('ROLE_PUBLISHER', 'ROLE_SUBSCRIBER'),
		'ROLE_PUBLISHER' => array('ROLE_SUBSCRIBER'),
	),
	'security.access_rules' => array(
		array('^/admin/user/delete/?$', 'ROLE_ADMIN'),
		array('^/admin/article/compose/[0-9]+/?$', 'ROLE_PUBLISHER'),
		array('^/admin/article/compose/?$', 'ROLE_PUBLISHER'),
		array('^/admin/article/?$', 'ROLE_PUBLISHER'),
		array('^/admin/dashboard/?$', 'IS_AUTHENTICATED_FULLY'),
		array('^/admin/user/edit/?$', 'IS_AUTHENTICATED_FULLY'),
		array('^/admin', 'ROLE_ADMIN'),
		array('/', 'IS_AUTHENTICATED_ANONYMOUSLY'),
	),
	'security.encoder_factory' => $app->share(function($app) {
		return new EncoderFactory(array(
			'Symfony\Component\Security\Core\User\UserInterface' => $app['security.encoder.digest'],
			'RigCms\Model\UserProvider' => new MessageDigestPasswordEncoder(),
		));
	}),
));

$app->register(new TwigServiceProvider(), array(
	'twig.path' => array(
		__DIR__ . '/../app/RigCms/view',
		__DIR__ . '/../web/themes/' . $app['site']['theme'],
	),
	'twig.options' => array(
		'autoescape' => false,
		'cache' => false,
	),
));

/*@TODO Do something with this*/

$app['twig'] = $app->share($app->extend('twig', function ($twig, $app)
{
	$twig->addGlobal('site', $app['site']);

	$twig->addFunction(new \Twig_SimpleFunction('is_granted', function($role) use ($app)
	{
		return $app['security']->isGranted($role);
	}));

	$twig->addFunction(new \Twig_SimpleFunction('user', function() use ($app)
	{
		return $app['security']->getToken()->getUser();
	}));

	$twig->addFunction(new \Twig_SimpleFunction('get_controller', function() use ($app)
	{
		$controller = str_replace('admin/', '', trim($app['request']->getPathInfo(), '/'));

		if (strpos($controller, '/'))
		{
			$controller = substr($controller, 0, strpos($controller, '/'));
		}

		return $controller;
	}));

	$twig->addFunction(new \Twig_SimpleFunction('get_user', function($id) use ($app)
	{
		$userModel = new UserModel($app['db']);

		return $userModel->getById($id)->eraseCredentials()->getResult();
	}));

	$twig->addFunction(new \Twig_SimpleFunction('get_roles', function() use ($app)
	{
		$userModel = new UserModel($app['db']);

		return $userModel->getRoles()->getResult();
	}));

	$twig->addFunction(new \Twig_SimpleFunction('get_articles', function(array $options = array()) use ($app)
	{
		$articleModel = new ArticleModel($app['db']);

		return $articleModel->get($options)->getResult();
	}));

	$twig->addFunction(new \Twig_SimpleFunction('get_sections', function() use ($app)
	{
		$taxonomyModel = new TaxonomyModel($app['db']);

		return $taxonomyModel->get()->getResult()->fetchAll();
	}));

	$twig->addFunction(new \Twig_SimpleFunction('get_comments', function($id) use ($app)
	{
		$discussModel = new DiscussModel($app['db']);

		return $discussModel->getByArticleId($id)->getResult();
	}));

	$twig->addFunction(new \Twig_SimpleFunction('tag_cloud', function($parentId = null) use ($app)
	{
		$taxonomyModel = new TaxonomyModel($app['db']);

		return $taxonomyModel->getWithArticleCount($parentId)->getResult();
	}));

	$twig->addFunction(new \Twig_SimpleFunction('gravatar', function($email, $size = 80, $rating = 'g', $default = 'identicon') use ($app)
	{
		$email = md5(trim(strtolower($email)));
		$default = urlencode($default);

		return 'http://www.gravatar.com/avatar/' . $email . '?s=' . $size . '&amp;r=' . $rating . '&amp;d=' . $default;
	}));

	$twig->addFunction(new \Twig_SimpleFunction('query_params', function() use ($app)
	{
		$params = array();

		foreach ($app['request']->query as $key => $val)
		{
			if ($key !== 'page')
			{
				$params[] = $key . '=' . urlencode($val);
			}
		}

		return !empty($params) ? '?' . implode('&', $params) : '';
	}));

	$twig->addFilter(new \Twig_SimpleFilter('json_decode', function($value)
	{
		return json_decode($value, true);
	}));

	$twig->addFilter(new \Twig_SimpleFilter('htmlspecialchars', function($value)
	{
		return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
	}));

	$twig->addFilter(new \Twig_SimpleFilter('ucfirst', function($value)
	{
		return ucfirst(strtolower($value));
	}));

	$twig->addFilter(new \Twig_SimpleFilter('concat', function($value, $length)
	{
		if ($value === '' || $value === null)
		{
			return '&#x2026;';
		}

		$value = trim(preg_replace(array('/<.+?>/', '/\s{2,}/'), ' ', $value));
		$length = $length > 0 ? (int) $length : 1;

		return strlen($value) > $length ? mb_substr($value, 0, $length) . '&#x2026;' : $value;
	}));

	$twig->addFilter(new \Twig_SimpleFilter('br2nl', function($value)
	{
		$value = str_replace(array(
			'</p><p>',
			'<br>',
			'<p>',
			'</p>',
		), array(
			"\r\n\r\n",
			"\r\n",
			'',
		), $value);

		return $value;
	}));

	return $twig;
}));

$app->error(function (\Exception $e, $code) use ($app)
{
	if ($app['debug'] or !$app['security']->getToken())
	{
		return;
	}

	if ($this->isGranted('ROLE_PUBLISHER'))
	{
		$message = $e->getMessage();
	}
	else
	{
		$message = $code == 404 ? 'Page not found.' : 'An error has occurred.';
	}

	$controller = $app['request']->attributes->get('_controller');

	if (is_string($controller) && strpos($controller, 'public.controller') === false)
	{
		$template = 'admin/error';
	}
	else
	{
		$template = 'error';
	}

	if ($app['twig']->getLoader()->exists($template . '.twig'))
	{
		return new Response($app['twig']->render($template . '.twig', array('code' => $code, 'message' => $message)));
	}
});

$app['public.controller'] = $app->share(function($app) { return new PublicController($app); });
$app['user.controller'] = $app->share(function($app) { return new UserController($app); });
$app['article.controller'] = $app->share(function($app) { return new ArticleController($app); });
$app['taxonomy.controller'] = $app->share(function($app) { return new TaxonomyController($app); });
$app['discuss.controller'] = $app->share(function($app) { return new DiscussController($app); });

$app->get('/id/{id}', 'public.controller:singleAction');

$app->get('/login', 'user.controller:loginAction');
$app->get('/login/', 'user.controller:loginAction');
$app->get('/reset-password/', 'user.controller:forgotPasswordAction');
$app->post('/reset-password/', 'user.controller:forgotPasswordAction');
$app->get('/reset-password/{token}/', 'user.controller:resetPasswordAction');
$app->post('/reset-password/{token}/', 'user.controller:resetPasswordAction');

$app->get('/admin/dashboard/', 'user.controller:dashboardAction');
$app->get('/admin', function() use ($app) { return $app->redirect($app['site']['path'] . '/admin/dashboard/', 301); });
$app->get('/admin/', function() use ($app) { return $app->redirect($app['site']['path'] . '/admin/dashboard/', 301); });

$app->get('/admin/article/', 'article.controller:indexAction');
$app->post('/admin/article/multiedit/', 'article.controller:multieditAction');
$app->get('/admin/article/compose/', 'article.controller:composeAction');
$app->post('/admin/article/compose/', 'article.controller:composeAction');
$app->get('/admin/article/compose/{id}/', 'article.controller:composeAction');
$app->post('/admin/article/compose/{id}/', 'article.controller:composeAction');
$app->get('/admin/article/delete/{id}/', 'article.controller:deleteAction');
$app->post('/admin/article/delete/{id}/', 'article.controller:deleteAction');

$app->get('/admin/taxonomy/', 'taxonomy.controller:indexAction');
$app->get('/admin/taxonomy/compose/', 'taxonomy.controller:composeAction');
$app->post('/admin/taxonomy/compose/', 'taxonomy.controller:composeAction');
$app->get('/admin/taxonomy/compose/{id}/', 'taxonomy.controller:composeAction');
$app->post('/admin/taxonomy/compose/{id}/', 'taxonomy.controller:composeAction');
$app->get('/admin/taxonomy/delete/{id}/', 'taxonomy.controller:deleteAction');
$app->post('/admin/taxonomy/delete/{id}/', 'taxonomy.controller:deleteAction');

$app->get('/admin/comment/', 'discuss.controller:indexAction');
$app->post('/admin/comment/', 'discuss.controller:multieditAction');
$app->get('/admin/comment/compose/{id}/', 'discuss.controller:composeAction');
$app->post('/admin/comment/compose/{id}/', 'discuss.controller:composeAction');
$app->get('/admin/comment/delete/{id}/', 'discuss.controller:deleteAction');
$app->post('/admin/comment/delete/{id}/', 'discuss.controller:deleteAction');
$app->get('/admin/comment/moderate/{id}/', 'discuss.controller:moderateAction');

$app->get('/admin/user/', 'user.controller:indexAction');
$app->get('/admin/user/edit/', 'user.controller:userEditAction');
$app->post('/admin/user/edit/', 'user.controller:userEditAction');
$app->get('/admin/user/compose/', 'user.controller:adminComposeAction');
$app->post('/admin/user/compose/', 'user.controller:adminComposeAction');
$app->get('/admin/user/compose/{id}/', 'user.controller:adminComposeAction');
$app->post('/admin/user/compose/{id}/', 'user.controller:adminComposeAction');
$app->get('/admin/user/delete/{id}/', 'user.controller:deleteAction');
$app->post('/admin/user/delete/{id}/', 'user.controller:deleteAction');