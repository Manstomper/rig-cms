<?php

namespace RigCms\Controller;

use Silex\Application;
use RigCms\Model\UserModel;
use RigCms\Model\ArticleModel;
use RigCms\Model\TaxonomyModel;
use RigCms\Model\DiscussModel;

abstract class CoreController
{
	protected $app, $model, $data, $responseCode, $responseMessage;

	public function __construct(Application $app)
	{
		$this->app = $app;
		$this->responseCode = 200;
		$this->responseMessage = '';
		/*@TODO consider this: $this->responseMessage = array('message' => '', 'guruMeditation' => '');*/
	}

	public function articleModel()
	{
		return new ArticleModel($this->app['db']);
	}

	public function taxonomyModel()
	{
		return new TaxonomyModel($this->app['db']);
	}

	public function discussModel()
	{
		return new DiscussModel($this->app['db']);
	}

	public function userModel()
	{
		return new UserModel($this->app['db']);
	}

	public function isGranted($role)
	{
		return $this->app['security']->isGranted($role);
	}

	public function getUserToken()
	{
		return $this->app['security']->getToken()->getUser();
	}

	public function isRest()
	{
		return reset($this->app['request']->getAcceptableContentTypes()) === 'application/json' ? true : false;
	}

	public function hasError()
	{
		if ($this->responseCode >= 200 && $this->responseCode < 300)
		{
			return false;
		}

		if (!$this->responseMessage)
		{
			$this->responseMessage = 'An error has occurred.';
		}

		return true;
	}

	protected function response($redirectTo = null)
	{
		if ($this->isRest())
		{
			return $this->app->json(array(
				'message' => $this->responseMessage,
				'result' => $this->data,
			), $this->responseCode);
		}

		if ($this->responseMessage)
		{
			$this->app['session']->getFlashBag()->add(($this->hasError() ? 'error' : 'message'), $this->responseMessage);
		}

		return $this->app->redirect($this->app['site']['path'] . ($redirectTo ? $redirectTo : ''));
	}

	public function generateToken()
	{
		return md5(uniqid() . rand(0, 1000));
	}

	public function getSearchFilters(array $columns = array())
	{
		$q = $this->app['request']->get('q');

		if (empty($q) || strlen($q) <= 3)
		{
			return;
		}

		$terms = explode(' ', $q);
		$count = count($terms);
		$filters = array();

		for ($i = 0; $i < $count; $i++)
		{
			$term = $terms[$i];

			if (strpos($term, '"') === 0)
			{
				while (strrpos($term, '"') !== strlen($term) - 1)
				{
					$i++;

					if ($i >= $count)
					{
						break;
					}

					$term .= ' ' . $terms[$i];

				}

				$term = trim(str_replace('"', '', $term));
			}

			foreach ($columns as $column)
			{
				$filters[] = array(
					'relation' => 'OR',
					'column' => $column,
					'compare' => 'LIKE',
					'value' => '%' . $term . '%',
				);
			}
		}

		$filters[0]['relation'] = 'AND';

		return $filters;
	}

	protected function insert()
	{
		if ($this instanceof PublicController)
		{
			$this->app->abort(403, 'This method cannot be accessed from the public controller.');
		}

		$this->processRequestData();

		if (!empty($this->data['invalid']))
		{
			$this->responseCode = 400;
			$this->responseMessage = 'Invalid data.';

			return false;
		}

		$this->model->insert($this->data);

		if (!$this->model->hasError())
		{
			$this->data['id'] = $this->model->getLastInsertId();
			$this->responseCode = 201;
			$this->responseMessage = $this->model->getCount() . ' record' . ($this->model->getCount() !== 1 ? 's' : '') . ' created.';

			return true;
		}

		$this->responseCode = 500;
		$error = $this->model->getLastError();
		$errorCode = !empty($error[0]) ? $error[0] : '';
		$errorMessage = !empty($error[2]) ? $error[2] : '';
		$this->responseMessage = 'Failed to insert record. ' . $errorCode . ' ' . $errorMessage;

		return false;
	}

	protected function update()
	{
		if ($this instanceof PublicController)
		{
			$this->app->abort(403, 'This method cannot be accessed from the public controller.');
		}

		$this->processRequestData();

		if (!empty($this->data['invalid']))
		{
			$this->responseCode = 400;
			$this->responseMessage = 'Invalid data.';

			return false;
		}

		$this->model->update($this->data);

		if (!$this->model->hasError())
		{
			$this->responseMessage = $this->model->getCount() . ' record' . ($this->model->getCount() !== 1 ? 's' : '') . ' updated.';

			return true;
		}

		$this->responseCode = 500;
		$error = $this->model->getLastError();
		$errorCode = !empty($error[0]) ? $error[0] : '';
		$errorMessage = !empty($error[2]) ? $error[2] : '';
		$this->responseMessage = 'Failed to update record. ' . $errorCode . ' ' . $errorMessage;

		return false;
	}

	protected function delete()
	{
		if ($this instanceof PublicController)
		{
			$this->app->abort(403, 'This method cannot be accessed from the public controller.');
		}

		$this->model->delete($this->app['request']->get('id'));

		if ($this->model->getCount() > 0 && !$this->model->hasError())
		{
			$this->responseMessage = $this->model->getCount() . ' record' . ($this->model->getCount() !== 1 ? 's' : '') . ' deleted.';

			return true;
		}

		$this->responseCode = 500;
		$error = $this->model->getLastError();
		$errorCode = !empty($error[0]) ? $error[0] : '';
		$errorMessage = !empty($error[2]) ? $error[2] : '';
		$this->responseMessage = 'Failed to delete record. ' . $errorCode . ' ' . $errorMessage;

		return false;
	}

	protected function processRequestData()
	{
		$filters = $this->model->getEntity()->getFilters();
		$validationRules = $this->model->getEntity()->getValidationRules();

		foreach ($this->model->getEntity() as $column => $defaultValue)
		{
			$value = $this->app['request']->get($column);

			if (is_string($value))
			{
				$value = trim($value);
			}

			$isRequired = isset($validationRules[$column]['required']) ? false : true;

			if (isset($filters[$column]))
			{
				foreach ($filters[$column] as $name)
				{
					$value = $this->applyFilter($name, $value, $defaultValue);
				}
			}

			if (isset($validationRules[$column]))
			{
				foreach ($validationRules[$column] as $rule => $option)
				{
					if ($rule !== 'required' && $this->validateData($rule, $option, $value, $isRequired) === false)
					{
						$this->data['invalid'][$column] = 'Field "' . $column . '" is invalid.';
					}
				}
			}

			if ($isRequired && ($value === '' || $value === null))
			{
				$this->data['invalid'][$column] = 'Field "' . $column . '" is required.';
			}

			$this->data[$column] = $value;
		}
	}

	private function validateData($rule, $option, $value, $isRequired)
	{
		switch ($rule)
		{
			case 'regex':
				return $isRequired === false && $value === null || preg_match('/' . $option . '/', $value) ? true : false;
				break;

			case 'email':
				return filter_var($value, FILTER_VALIDATE_EMAIL) ? true : false;
				break;

			case 'password':
				return $this->app['request']->get('id') || (!$this->app['request']->get('id') && $value);
				break;

			case 'integer':
				return is_int($value);
				break;
		}
	}

	private function applyFilter($filter, $value, $defaultValue)
	{
		switch ($filter)
		{
			case 'boolean':
				return (bool) $value;
				break;

			case 'integer':
				return (int) $value;
				break;

			case 'string':
				return is_string($value) ? $value : '';
				break;

			case 'null':
				return $value !== '' ? $value : null;
				break;

			case 'default':
				return $value ? $value : $defaultValue;
				break;
		}

		if (is_callable($filter))
		{
			return $filter($value);
		}

		elseif (method_exists($this, $filter))
		{
			return $this->$filter($value);
		}
	}

	private function slug($value)
	{
		if (!$value)
		{
			$value = $this->app['request']->get('title') ? $this->app['request']->get('title') : $this->app['request']->get('name');
		}

		if (function_exists('iconv') && mb_detect_encoding($value) == 'UTF-8')
		{
			$value = iconv('UTF-8', 'ASCII//TRANSLIT', $value);
		}

		$value = preg_replace('/[^a-z0-9-]/', '', strtolower(str_replace(' ', '-', $value)));

		if ($value === '')
		{
			$value = hash('crc32b', uniqid());
		}

		return $value;
	}

	private function meta()
	{
		$data = $this->app['request']->get('meta');

		if (!$data)
		{
			return null;
		}

		$meta = array();

		foreach ($data as $key => $val)
		{
			if (is_string($val))
			{
				$key = htmlspecialchars($key, ENT_QUOTES, 'UTF-8');
				$val = htmlspecialchars($val, ENT_QUOTES, 'UTF-8');

				$meta[$key] = $val === 'on' ? 'true' : $val;
			}
			elseif (isset($val['key']) && $val['key'] !== '' && isset($val['val']) && $val['val'] !== '')
			{
				$key = htmlspecialchars($val['key'], ENT_QUOTES, 'UTF-8');
				$val = htmlspecialchars($val['val'], ENT_QUOTES, 'UTF-8');

				$meta[$key] = $val;
			}
		}

		return !empty($meta) ? json_encode($meta) : null;
	}
}