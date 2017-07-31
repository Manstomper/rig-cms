<?php

namespace RigCms\Controller;

use Silex\Application;
use Symfony\Component\HttpFoundation\Response;

class PublicController extends CoreController
{
	public function indexAction($section = null)
	{
		$segments = array_filter(explode('/', trim($this->getRequest()->getPathInfo(), '/')));

		if (empty($segments) && !empty($section))
		{
			$segments = array($section);
		}

		$taxonomy = $this->taxonomyModel()->getBySlug($segments)->getResult()->fetchAll();
		$template = 'index';

		foreach ($segments as $key => $value)
		{
			if (!isset($taxonomy[$key]) || isset($taxonomy[$key - 1]) && $taxonomy[$key]['parent_id'] !== $taxonomy[$key - 1]['id'])
			{
				$this->app->abort('404', 'Section not found.');
			}

			if ($this->app['twig']->getLoader()->exists('section-' . $value . '.twig'))
			{
				$template = 'section-' . $value;
			}
		}

		$limit = 5;
		$page = (int) $this->getRequest()->get('page');

		if ($page < 1)
		{
			$page = 1;
		}

		$options = array(
			'order' => array(
				'date' => 'DESC',
			),
			'page' => $page,
			'limit' => $limit,
		);

		$tax = end($taxonomy);
		$tax = array_filter(array($tax['slug']));

		$articles = $this->articleModel()->get($options, $tax, $this->getRoleId());

		return $this->app['twig']->render($template . '.twig', array(
			'articles' => $articles->getResult(),
			'section' => $taxonomy,
			'count' => $articles->getCount(),
			'page' => $page,
			'numPages' => ceil($articles->getCount() / $limit),
		));
	}

	/*@TODO think about taxonomy*/
	public function articleAction()
	{
		$id = $this->getRequest()->get('id');
		$slug = $this->getRequest()->get('slug');

		if ($id)
		{
			$article = $this->articleModel()->getById($id)->getResult();
		}
		else
		{
			$article = $this->articleModel()->getBySlug($slug)->getResult();
		}

		if (!$article)
		{
			$this->app->abort(404, 'Article not found.');
		}

		if (!$this->isGranted($this->getRoleName($article['role_id'])))
		{
			$this->app->abort(401, 'This article is protected.');
		}

		if ($this->getRequest()->getMethod() === 'POST')
		{
			if (!empty($article['meta']['comments_disabled']))
			{
				$this->app['session']->getFlashBag()->add('comment_status', 'Comments are disabled.');
			}
			else
			{
				$this->getRequest()->request->set('article_id', $article['id']);

				if ($this->processComment())
				{
					return $this->app->redirect($this->app['site']['path'] . '/' . $article['slug'] . '#comment');
				}
			}
		}

		$article['section'] = array(
			'slug' => $article['section_slug'],
			'name' => $article['section_name'],
		);

		unset($article['section_slug']);
		unset($article['section_name']);

		//dump($article);

		$template = 'article';

		if ($this->app['twig']->getLoader()->exists('article-' . $article['id'] . '.twig'))
		{
			$template = 'article-' . $article['id'];
		}
		elseif ($this->app['twig']->getLoader()->exists('article-section-' . $article['section']['slug'] . '.twig'))
		{
			$template = 'article-section-' . $article['section']['slug'];
		}

		return $this->app['twig']->render($template . '.twig', array(
			'article' => $article,
			'c' => $this->data,
		));
	}

	public function templateAction()
	{
		$template = trim($this->getRequest()->getPathInfo(), '/');

		if (!$template)
		{
			$template = 'front';
		}

		return $this->app['twig']->render('page-' . $template . '.twig');
	}

	public function searchAction()
	{
		$q = $this->getRequest()->get('q');

		if (empty($q) || strlen($q) <= 3)
		{
			$this->app->abort(400, 'No search term entered, or search term is three characters or shorter.');
		}

		$limit = 10;
		$page = (int) $this->getRequest()->get('page');

		if ($page < 1)
		{
			$page = 1;
		}

		$options = array(
			'page' => $page,
			'limit' => $limit,
		);

		$taxonomy = array_filter(explode(',', $this->getRequest()->get('taxonomy')));

		$articles = $this->articleModel()->get($options, $taxonomy, $this->getRoleId(), $q);

		return $this->app['twig']->render('search.twig', array(
			'results' => $articles->getResult(),
			'terms' => htmlspecialchars($q, ENT_QUOTES, 'UTF-8'),
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
			'taxonomy' => $taxonomy,
			'limit' => 5,
			'order' => array(
				'date' => 'DESC',
			),
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

		if (!$this->getRequest()->get('confirm') || isset($this->data['invalid']))
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