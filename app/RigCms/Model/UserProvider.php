<?php

namespace RigCms\Model;

use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;

final class UserProvider implements UserProviderInterface
{
	private $db;

	public function __construct(\PDO $db)
	{
		$this->db = $db;
	}

	public function loadUserByUsername($username)
	{
		$sth = $this->db->prepare('SELECT rig_user.*, rig_role.name AS role_name FROM rig_user'
				. ' JOIN rig_user_role ON rig_user_role.user_id = rig_user.id'
				. ' JOIN rig_role ON rig_role.id = rig_user_role.role_id'
				. ' WHERE email = :email');
		$sth->execute(array(':email' => $username));

		$user = $sth->fetch();
		$entity = new UserEntity();

		if ($user)
		{
			$entity->id = $user['id'];
			$entity->email = $user['email'];
			$entity->name = $user['name'];
			$entity->meta = !empty($user['meta']) ? json_decode($user['meta'], true) : null;
			$entity->password = $user['password'];
			$entity->salt = $user['salt'];
			$entity->token = $user['token'];
			$entity->is_active = $user['is_active'];

			$entity->setRoles(array($user['role_name']));
		}

		return $entity;
	}

	public function refreshUser(UserInterface $user)
	{
		if (!$user instanceof UserEntity)
		{
			throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', get_class($user)));
		}

		return $this->loadUserByUsername($user->getUsername());
	}

	public function supportsClass($class)
	{
		return $class === 'RigCms\Model\UserEntity';
	}
}