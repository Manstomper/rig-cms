<?php

namespace RigCms\Controller;

use Silex\Application;

final class TaxonomyController extends CoreController
{
	public function __construct(Application $app)
	{
		parent::__construct($app);

		$this->model = $this->taxonomyModel();
	}

	public function indexAction()
	{
		$limit = 200;
		$page = (int) $this->app['request']->get('page');

		if ($page < 1)
		{
			$page = 1;
		}

		$taxonomy = $this->model->get(array(
			'orderby' => $this->app['request']->get('orderby'),
			'page' => $page,
			'limit' => $limit,
		));

		return $this->app['twig']->render('admin/taxonomy.twig', array(
			'taxonomyList' => $taxonomy->getResult()->fetchAll(),
			'page' => $page,
			'numPages' => ceil($taxonomy->getCount() / $limit),
		));
	}

	public function composeAction()
	{
		$id = $this->app['request']->get('id');

		if ($this->app['request']->getMethod() === 'POST')
		{
			$success = $id ? $this->update() : $this->insert();

			if ($success || $this->isRest())
			{
				return $this->response('/admin/taxonomy/compose/' . $this->data['id'] . '/');
			}

			$taxonomy = $this->data;
		}
		else
		{
			$taxonomy = $id ? $this->model->getById($id)->getResult() : (array) $this->model->getEntity();
		}

		if (!$taxonomy)
		{
			$this->app->abort(404, 'Section not found.');
		}

		return $this->app['twig']->render('admin/taxonomy-compose.twig', array(
			'taxonomy' => $taxonomy,
			'taxonomyList' => $this->model->get(array('order' => array('name' => 'ASC')))->getResult(),
		));
	}

	public function deleteAction()
	{
		if ($this->app['request']->getMethod() === 'POST')
		{
			$this->delete();

			return $this->response('/admin/taxonomy/');
		}

		$taxonomy = $this->model->getById($this->app['request']->get('id'))->getResult();

		if (!$taxonomy)
		{
			$this->app->abort(404, 'Section not found.');
		}

		return $this->app['twig']->render('admin/delete.twig', array(
			'type' => 'section',
			'identifier' => $taxonomy['name'],
		));
	}
}