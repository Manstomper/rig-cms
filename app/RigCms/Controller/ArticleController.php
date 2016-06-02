<?php

namespace RigCms\Controller;

use Silex\Application;

final class ArticleController extends CoreController
{
	public function __construct(Application $app)
	{
		parent::__construct($app);

		$this->model = $this->articleModel();
	}

	public function indexAction()
	{
		$limit = 20;
		$page = (int) $this->app['request']->get('page');

		if ($page < 1)
		{
			$page = 1;
		}

		$options = array(
			'taxonomy' => array_filter(explode(',', $this->app['request']->get('taxonomy'))),
			'filter' => $this->getSearchFilters(array('title', 'body')),
			'order' => $this->app['request']->get('orderby') ? array($this->app['request']->get('orderby') => 'ASC') : array('date' => 'DESC'),
			'page' => $page,
			'limit' => $limit,
		);

		$articles = $this->model->get($options);

		return $this->app['twig']->render('admin/article.twig', array(
			'articles' => $articles->getResult(),
			'page' => $page,
			'numPages' => ceil($articles->getCount() / $limit),
		));
	}

	public function composeAction()
	{
		$id = $this->app['request']->get('id');

		if ($this->app['request']->getMethod() === 'POST')
		{
			$this->app['request']->request->set('user_id', $this->getUserToken()->id);
			$success = $id ? $this->update() : $this->insert();

			if ($success)
			{
				$this->model->detachTaxonomy($this->data['id']);
				$this->model->attachTaxonomy($this->data['id'], $this->app['request']->get('taxonomy'));

				return $this->response('/admin/article/compose/' . $this->data['id'] . '/');
			}
			elseif ($this->isRest())
			{
				return $this->response();
			}

			$article = $this->data;
		}
		elseif ($id)
		{
			$article = $this->model->getById($id)->getResult();

			if (!$this->isGranted('ROLE_ADMIN') && $this->getUserToken()->id != $article['user_id'])
			{
				$this->app->abort(403, 'You are not authorized to edit this article.');
			}
		}
		else
		{
			$article = (array) $this->model->getEntity();
		}

		if (!$article)
		{
			$this->app->abort(404, 'Article not found.');
		}

		$taxonomyModel = $this->taxonomyModel();

		return $this->app['twig']->render('admin/article-compose.twig', array(
			'article' => $article,
			'taxonomyList' => $taxonomyModel->getWithArticleId($article['id'])->getResult()->fetchAll(),
			'taxonomy' => $taxonomyModel->getEntity(),
		));
	}

	public function deleteAction()
	{
		if ($this->app['request']->getMethod() === 'POST')
		{
			$this->delete();

			return $this->response('/admin/article/');
		}

		$article = $this->model->getById($this->app['request']->get('id'))->getResult();

		if (!$article)
		{
			$this->app->abort(404, 'Article not found.');
		}

		return $this->app['twig']->render('admin/delete.twig', array(
			'type' => 'article',
			'identifier' => $article['title'],
		));
	}

	public function multieditAction()
	{
		$id = $this->app['request']->get('id');
		$action = $this->app['request']->get('action');

		$this->responseCode = 400;
		$this->responseMessage = 'Nothing to do.';

		if (!$id || !$action)
		{
			return $this->response('/admin/article/');
		}

		switch ($action)
		{
			case 'delete';
				if ($this->delete())
				{
					$this->responseCode = 200;
				}
				break;

			case 'taxonomy-attach';
				if ($taxonomy = $this->app['request']->get('taxonomy'))
				{
					if ($this->model->attachTaxonomy($id, $taxonomy)->hasError())
					{
						$this->responseCode = 500;
					}
					else
					{
						$this->responseCode = 200;
					}
				}
				break;

			case 'taxonomy-detach';
				if ($taxonomy = $this->app['request']->get('taxonomy'))
				{
					if ($this->model->detachTaxonomy($id, $taxonomy)->hasError())
					{
						$this->responseCode = 500;
					}
					else
					{
						$this->responseCode = 200;
					}
				}
				break;
		}

		if ($this->responseCode === 200)
		{
			$this->responseMessage = 'Changes were saved.';
		}

		return $this->response('/admin/article/');
	}
}