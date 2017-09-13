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
		$page = (int) $this->getRequest()->get('page');

		if ($page < 1)
		{
			$page = 1;
		}

		if ($this->getRequest()->get('orderby'))
		{
			$order = array(
				$this->getRequest()->get('orderby') => 'ASC',
			);
		}
		else
		{
			$order = array(
				'date' => 'DESC',
			);
		}

		$options = array(
			'order' => $order,
			'page' => $page,
			'limit' => $limit,
		);

		$taxonomy = array_filter(explode(',', $this->getRequest()->get('taxonomy')));
		$q = $this->getRequest()->get('q');

		$articles = $this->model->getArticles($options, $taxonomy, $this->getRoleId(), $q);

		return $this->app['twig']->render('admin/article.twig', array(
			'articles' => $articles->getResult(),
			'page' => $page,
			'numPages' => ceil($articles->getCount() / $limit),
		));
	}

	public function composeAction()
	{
		$id = $this->getRequest()->get('id');

		if ($this->getRequest()->getMethod() === 'POST')
		{
			$this->getRequest()->request->set('user_id', $this->getUserToken()->id);

			if ($id)
			{
				$success = $this->update();
			}
			else
			{
				$success = $this->insert();
			}

			if ($success)
			{
				$this->model->detachTaxonomy($this->data['id']);
				$this->model->attachTaxonomy($this->data['id'], $this->getRequest()->get('taxonomy'));

				return $this->response('/admin/article/compose/' . $this->data['id'] . '/');
			}

			if ($this->isRest())
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
		if ($this->getRequest()->getMethod() === 'POST')
		{
			$this->delete();

			return $this->response('/admin/article/');
		}

		$article = $this->model->getById($this->getRequest()->get('id'))->getResult();

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
		$this->responseCode = 200;

		$id = $this->getRequest()->get('id');
		$action = $this->getRequest()->get('action');

		switch ($action)
		{
			case 'delete';
				if ($this->delete() === false)
				{
					$this->responseCode = 400;
				}
				break;

			case 'taxonomy-attach';
				$taxonomy = $this->getRequest()->get('taxonomy');

				if ($taxonomy && $this->model->attachTaxonomy($id, $taxonomy)->hasError())
				{
					$this->responseCode = 500;
				}
				break;

			case 'taxonomy-detach';
				$taxonomy = $this->getRequest()->get('taxonomy');

				if ($taxonomy && $this->model->detachTaxonomy($id, $taxonomy)->hasError())
				{
					$this->responseCode = 500;
				}
				break;

			default:
				$this->responseCode = 400;
				$this->responseMessage = 'Nothing to do.';
				break;
		}

		if ($this->responseCode === 200)
		{
			$this->responseMessage = 'Changes were saved.';
		}

		return $this->response('/admin/article/');
	}
}