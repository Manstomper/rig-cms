<?php

namespace RigCms\Controller;

use Silex\Application;
use Symfony\Component\HttpFoundation\Response;

final class DiscussController extends CoreController
{
	public function __construct(Application $app)
	{
		parent::__construct($app);

		$this->model = $this->discussModel();
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
				'is_visible' => 'ASC',
				'date' => 'DESC',
			);
		}

		$options = array(
			'order' => $order,
			'page' => $page,
			'limit' => $limit,
		);

		$this->model->get($options);

		return $this->app['twig']->render('admin/discuss.twig', array(
			'comments' => $this->model->getResult(),
			'page' => $page,
			'numPages' => ceil($this->model->getCount() / $limit),
		));
	}

	public function composeAction()
	{
		$id = $this->getRequest()->get('id');

		if ($this->getRequest()->getMethod() === 'POST')
		{
			if ($id)
			{
				$success = $this->update();
			}
			else
			{
				$success = $this->insert();
			}

			if ($success || $this->isRest())
			{
				return $this->response('/admin/comment/');
			}

			$comment = $this->data;
		}
		else
		{
			$comment = $id ? $this->model->getById($id)->getResult() : $this->model->getEntity();
		}

		if (!$comment)
		{
			$this->app->abort(404, 'Comment not found.');
		}

		return $this->app['twig']->render('admin/discuss-compose.twig', array(
			'comment' => $comment,
		));
	}

	public function moderateAction()
	{
		$this->model->moderate($this->getRequest()->get('id'));

		if ($this->model->hasError())
		{
			$this->responseCode = 500;
			$this->responseMessage = 'Failed to update record. ' . $this->model->getResult();
		}
		else
		{
			$this->responseMessage = 'Record updated.';
		}

		return $this->response('/admin/comment/');
	}

	public function deleteAction()
	{
		if ($this->getRequest()->getMethod() === 'POST')
		{
			$this->delete();

			return $this->response('/admin/comment/');
		}

		$comment = $this->model->getById($this->getRequest()->get('id'))->getResult();

		if (!$comment)
		{
			$this->app->abort(404, 'Comment not found.');
		}

		return $this->app['twig']->render('admin/delete.twig', array(
			'type' => 'comment',
			'identifier' => $comment['body'],
		));
	}

	public function multieditAction()
	{
		if (!$this->getRequest()->get('id'))
		{
			$this->app->abort(400, 'Nothing to do.');
		}

		if ($this->getRequest()->get('action') === 'delete')
		{
			return $this->deleteAction();
		}

		if ($this->getRequest()->get('action') === 'moderate')
		{
			return $this->moderateAction();
		}

		$this->app->abort(400, 'Nothing to do.');
	}
}