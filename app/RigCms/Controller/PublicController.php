<?php

namespace RigCms\Controller;

use Silex\Application;
use Symfony\Component\HttpFoundation\Response;

class PublicController extends CoreController
{
	public function __construct(Application $app)
	{
		parent::__construct($app);
	}

	public function indexAction()
	{
		$section = $this->app['request']->get('section');
		$taxonomy = null;

		if (!$section)
		{
			$section = current(explode('/', trim($this->app['request']->getPathInfo(), '/')));
		}

		if (!$section)
		{
			$taxEntities = $this->taxonomyModel()->getDefault()->getResult()->fetchAll();

			foreach ($taxEntities as $val)
			{
				$taxonomy[] = $val['slug'];
			}

			$template = 'index';
		}
		else
		{
			$subsection = $this->app['request']->get('subsection');

			if (!$subsection)
			{
				$taxEntities = $this->taxonomyModel()->getBySlug($section)->getResult()->fetchAll();
				$taxonomy = array($taxEntities[0]['slug']);

				$template = 'section-' . $taxonomy[0];

				if (!$this->app['twig']->getLoader()->exists($template . '.twig'))
				{
					$template = 'index';
				}
			}
			else
			{
				$taxEntities = $this->taxonomyModel()->getBySlug(array($section, $subsection))->getResult()->fetchAll();

				if (count($taxEntities) !== 2)
				{
					$this->app->abort('404', 'Section not found.');
				}

				if ($taxEntities[0]['parent_id'] === $taxEntities[1]['id'])
				{
					$taxEntities = array_reverse($taxEntities);
				}

				if ($taxEntities[0]['slug'] !== $section || $taxEntities[1]['slug'] !== $subsection || $taxEntities[1]['parent_id'] !== $taxEntities[0]['id'])
				{
					$this->app->abort('404', 'Section hierarchy is incorrect.');
				}

				$taxonomy = array($taxEntities[1]['slug']);
				$template = 'section-' . $taxEntities[1]['slug'];

				if (!$this->app['twig']->getLoader()->exists($template . '.twig'))
				{
					$template = 'section-' . $taxEntities[0]['slug'];

					if (!$this->app['twig']->getLoader()->exists($template . '.twig'))
					{
						$template = 'index';
					}
				}
			}
		}

		$page = (int) $this->app['request']->get('page');

		if ($page < 1)
		{
			$page = 1;
		}

		$options = array(
			'filter' => array(
				array(
					array(
						'column' => 'expires',
						'compare' => '>',
						'value' => date('Y-m-d H:i:s'),
					),
					array(
						'relation' => 'OR',
						'column' => 'expires',
						'compare' => 'IS NULL',
					),
				),
			),
			'taxonomy' => $taxonomy,
			'order' => array('date' => 'DESC'),
			'page' => $page,
			'limit' => 5,
		);

		if (!$this->isGranted('ROLE_ADMIN'))
		{
			if ($this->isGranted('ROLE_SUBSCRIBER'))
			{
				$options['filter'][] = array(
					array(
						'column' => 'role_id',
						'compare' => '>=',
						'value' => ($this->isGranted('ROLE_PUBLISHER') ? 2 : 3),
					),
					array(
						'relation' => 'OR',
						'column' => 'role_id',
						'compare' => 'IS NULL',
					)
				);
			}
			else
			{
				$options['filter'][] = array(
					array(
						'column' => 'role_id',
						'compare' => 'IS NULL',
					)
				);
			}
		}

		$articles = $this->articleModel()->get($options);

		return $this->app['twig']->render($template . '.twig', array(
			'articles' => $articles->getResult(),
			'section' => $taxEntities,
			'count' => $articles->getCount(),
			'page' => $page,
			'numPages' => ceil($articles->getCount() / $options['limit']),
		));
	}

	public function singleAction()
	{
		if ($slug = $this->app['request']->get('slug'))
		{
			$article = $this->articleModel()->getBySlug($slug)->getResult();
		}
		else
		{
			$article = $this->articleModel()->getById($this->app['request']->get('id'))->getResult();
		}

		if (!$article)
		{
			$this->app->abort(404, 'Article not found.');
		}

		if ($article['role_name'] !== null && !$this->isGranted($article['role_name']))
		{
			if ($this->isGranted('IS_AUTHENTICATED_FULLY'))
			{
				$this->app->abort(403, 'You do not have sufficient privileges to view this article.');
			}

			$this->app->abort(401, 'This article is protected.');
		}

		if ($this->app['request']->getMethod() === 'POST')
		{
			if (!empty($article['meta']['comments_disabled']))
			{
				$this->app['session']->getFlashBag()->add('comment_status', 'Comments are disabled.');
			}
			else
			{
				$this->app['request']->request->set('article_id', $article['id']);

				if ($this->processComment())
				{
					return $this->app->redirect($this->app['site']['path'] . '/' . $article['slug'] . '#comment');
				}
			}
		}

		$template = 'single-' . $article['id'];

		if (!$this->app['twig']->getLoader()->exists($template . '.twig'))
		{
			$template = 'single-section-' . $article['section_slug'];

			if (!$this->app['twig']->getLoader()->exists($template . '.twig'))
			{
				$template = 'single';
			}
		}

		return $this->app['twig']->render($template . '.twig', array(
			'article' => $article,
			'c' => $this->data,
		));
	}

	public function templateAction()
	{
		$template = trim($this->app['request']->getPathInfo(), '/');

		if (!$template)
		{
			$template = 'front';
		}

		return $this->app['twig']->render('page-' . $template . '.twig');
	}

	public function searchAction()
	{
		$limit = 10;
		$page = (int) $this->app['request']->get('page');

		if ($page < 1)
		{
			$page = 1;
		}

		$options = array(
			'filter' => array(
				array(
					'column' => 'role_id',
					'compare' => 'IS NULL',
				),
				array(
					array(
						'column' => 'expires',
						'compare' => '>',
						'value' => date('Y-m-d H:i:s'),
					),
					array(
						'relation' => 'OR',
						'column' => 'expires',
						'compare' => 'IS NULL',
					),
				),
			),
			'page' => $page,
			'limit' => $limit,
		);

		$options['filter'][] = $this->getSearchFilters(array('title', 'body'));

		if ($this->app['request']->get('taxonomy'))
		{
			$options['taxonomy'] = explode(',', $this->app['request']->get('taxonomy'));
		}

		$articles = $this->articleModel()->get($options);

		return $this->app['twig']->render('search.twig', array(
			'results' => $articles->getResult(),
			'count' => $articles->getCount(),
			'page' => $page,
			'numPages' => ceil($articles->getCount() / $limit),
		));
	}

	public function feedAction()
	{
		$taxonomy = array();

		foreach ($this->taxonomyModel()->getSyndicated()->getResult() as $val)
		{
			$taxonomy[] = $val['slug'];
		}

		$articles = $this->articleModel()->get(array(
			'filter' => array(
					array(
						'column' => 'role_id',
						'compare' => 'IS NULL',
					),
					array(
						array(
							'column' => 'expires',
							'compare' => '>',
							'value' => date('Y-m-d H:i:s'),
						),
						array(
							'relation' => 'OR',
							'column' => 'expires',
							'compare' => 'IS NULL',
						),
					),
			),
			'taxonomy' => $taxonomy,
			'limit' => 5,
			'order' => array('date' => 'DESC'),
		))->getResult();

		if ($articles)
		{
			$articles = $articles->fetchAll();
		}

		return new Response($this->app['twig']->render('rss.twig', array('articles' => $articles)), 200, array('Content-Type' => 'text/xml'));
	}

	/*@TODO rethink this*/

	private function processComment()
	{
		$this->model = $this->discussModel();
		$this->processRequestData();

		if (!$this->app['request']->get('confirm') || isset($this->data['invalid']))
		{
			return false;
		}

		if ($this->discussModel()->insert($this->data)->getLastInsertId())
		{
			$title = $this->app['site']['name'] . ': New comment';
			$message = 'A new comment is awaiting moderation.';

			foreach ($this->userModel()->getAdmins()->getResult() as $admin)
			{
				mail($admin['email'], $title, $message);
			}

			$this->app['session']->getFlashBag()->add('comment_status', 'Thank you. Your comment is awaiting moderation.');
		}
		else
		{
			$this->app['session']->getFlashBag()->add('comment_status', 'Failed to add comment.');
		}

		return true;
	}
}