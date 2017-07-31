<?php

namespace RigCms\Controller;

use Silex\Application;
use Symfony\Component\HttpFoundation\StreamedResponse;
use RigCms\Model\UserProvider;

final class UserController extends CoreController
{
	public function __construct(Application $app)
	{
		parent::__construct($app);

		$this->model = $this->userModel();
	}

	public function loginAction()
	{
		if ($this->isGranted('IS_AUTHENTICATED_FULLY'))
		{
			return $this->response('/admin/dashboard/');
		}

		return $this->app['twig']->render('user/login.twig', array(
			'email' => $this->app['session']->get('_security.last_username'),
			'error' => $this->app['security.last_error']($this->getRequest()),
		));
	}

	public function dashboardAction()
	{
		return $this->app['twig']->render('admin/dashboard.twig');
	}

	public function indexAction()
	{
		$limit = 20;
		$page = (int) $this->getRequest()->get('page');

		if ($page < 1)
		{
			$page = 1;
		}

		$users = $this->model->get(array(
			'page' => $page,
			'limit' => $limit,
		));

		return $this->app['twig']->render('admin/user.twig', array(
			'users' => $users->getResult(),
			'page' => $page,
			'numPages' => ceil($users->getCount() / $limit),
		));
	}

	public function adminComposeAction()
	{
		$id = $this->getRequest()->get('id');
		$user = $id ? $this->model->getById($id)->getResult() : (array) $this->model->getEntity();
		$isLastAdmin = ($id && $user['role_id'] == 1 && $this->model->getByRoleId(1)->getCount() <= 1) ? true : false;

		if ($this->getRequest()->getMethod() === 'POST')
		{
			$newRoleId = $this->getRequest()->get('role_id');
			$plainPassword = $this->getRequest()->get('password');
			$this->hashPassword();

			if ($isLastAdmin || $newRoleId == 1 || !$id)
			{
				$this->getRequest()->request->set('is_active', true);
			}

			$success = $id ? $this->update() : $this->insert();

			if ($success)
			{
				if (!$isLastAdmin)
				{
					$this->model->setRole($this->data['id'], $newRoleId);

					$accountCreated = $id ? false : true;
					$statusChanged = $this->data['is_active'] !== (bool) $user['is_active'];
					$roleChanged = $newRoleId != $user['role_id'];

					$this->sendMail($plainPassword, $newRoleId, $accountCreated, $statusChanged, $roleChanged);
				}

				return $this->response('/admin/user/compose/' . $this->data['id'] . '/');
			}

			$user = $this->data;
			$user['role_id'] = $newRoleId;
		}

		if (!$user)
		{
			$this->app->abort(404, 'User not found');
		}

		return $this->app['twig']->render('admin/user-compose.twig', array(
			'user' => $user,
			'is_last_admin' => $isLastAdmin,
		));
	}

	public function userEditAction()
	{
		if ($this->isGranted('ROLE_ADMIN'))
		{
			return $this->response('/admin/user/compose/' . $this->getUserToken()->id . '/');
		}

		if ($this->getRequest()->getMethod() === 'POST')
		{
			$this->getRequest()->request->set('id', $this->getUserToken()->id);

			if ($this->update() || $this->isRest())
			{
				return $this->response('/admin/user/edit/');
			}

			$user = $this->data;
		}
		else
		{
			$user = $this->model->getById($this->getUserToken()->id)->getResult();
		}

		return $this->app['twig']->render('admin/user-compose.twig', array(
			'user' => $user,
		));
	}

	public function deleteAction()
	{
		$user = $this->model->getById($this->getRequest()->get('id'))->getResult();

		if (!$user)
		{
			$this->app->abort(404, 'User not found.');
		}

		if ($user['role_id'] == 1)
		{
			$this->app->abort(400, 'This user cannot be deleted.');
		}

		if ($this->getRequest()->getMethod() === 'POST')
		{
			$this->delete();

			if (!$this->model->hasError())
			{
				mail($this->data['email'], 'Your account', $this->app['twig']->render('email/account-deleted.twig', array(), new StreamedResponse()));
			}

			return $this->response('/admin/user/');
		}

		return $this->app['twig']->render('admin/delete.twig', array(
			'type' => 'user',
			'identifier' => $user['name'],
		));
	}

	public function forgotPasswordAction()
	{
		if ($this->isGranted('IS_AUTHENTICATED_FULLY'))
		{
			$this->app['security.token_storage']->setToken(null);
			$this->getRequest()->getSession()->invalidate();

			return $this->response('/reset-password/');
		}

		if ($this->getRequest()->getMethod() === 'POST')
		{
			$email = $this->getRequest()->get('email');
			$token = $this->generateToken();

			if ($this->model->forgotPassword($email, $token))
			{
				$mail = mail($email, 'Password reset', $this->app['twig']->render('email/reset-password.twig', array(
					'token' => $token,
				), new StreamedResponse()));
			}

			if (isset($result) && $mail === false)
			{
				$this->app['session']->getFlashBag()->add('error', 'Failed to send email. Please try again later or contact an administrator.');
			}
			else
			{
				$this->app['session']->getFlashBag()->add('message', 'Please check your email.');
			}

			return $this->response('/reset-password/');
		}

		return $this->app['twig']->render('user/forgot-password.twig');
	}

	public function resetPasswordAction()
	{
		if ($this->isGranted('IS_AUTHENTICATED_FULLY'))
		{
			$this->app['security.token_storage']->setToken(null);
			$this->getRequest()->getSession()->invalidate();

			return $this->response('/reset-password/' . $this->getRequest()->get('token') . '/');
		}

		if ($this->getRequest()->getMethod() === 'POST')
		{
			if ($this->model->resetPassword($this->getRequest()->get('token'), $this->getRequest()->get('email'), $this->hashPassword()))
			{
				$this->responseMessage = 'Your password was successfully reset.';

				return $this->response('/login/');
			}
			else
			{
				$this->responseCode = 400;
				$this->responseMessage = 'Failed to reset password.';

				return $this->response('/reset-password/' . $this->getRequest()->get('token') . '/');
			}
		}

		return $this->app['twig']->render('user/reset-password.twig');
	}

	private function sendMail($plainPassword, $roleId, $accountCreated, $statusChanged, $roleChanged)
	{
		if ($accountCreated)
		{
			mail($this->data['email'], 'Your account', $this->app['twig']->render('email/account-created.twig', array(
				'username' => $this->data['email'],
				'password' => $plainPassword,
				'account_active' => $this->data['is_active'],
			), new StreamedResponse()));
		}

		elseif ($roleChanged)
		{
			foreach ($this->model->getRoles()->getResult() as $role)
			{
				if ($role['id'] == $roleId)
				{
					break;
				}
			}

			mail($this->data['email'], 'Your account', $this->app['twig']->render('email/account-role.twig', array(
				'role' => $role['name'],
				'account_active' => $this->data['is_active'],
				'status_changed' => $statusChanged,
			), new StreamedResponse()));

			return;
		}

		elseif ($statusChanged)
		{
			mail($this->data['email'], 'Your account', $this->app['twig']->render('email/account-active.twig', array(
				'account_active' => $this->data['is_active'],
			), new StreamedResponse()));
		}
	}

	private function hashPassword()
	{
		if (!$this->getRequest()->get('password'))
		{
			return;
		}

		$encoder = $this->app['security.encoder_factory']->getEncoder(new UserProvider($this->app['db']));
		$salt = $this->generateToken();
		$password = $encoder->encodePassword($this->getRequest()->get('password'), $salt);

		$this->getRequest()->request->set('salt', $salt);
		$this->getRequest()->request->set('password', $password);

		return array(
			'password' => $password,
			'salt' => $salt,
		);
	}
}