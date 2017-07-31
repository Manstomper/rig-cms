<?php

namespace RigCms\Model;

final class UserModel extends CoreModel
{
	public function __construct(\PDO $db)
	{
		$this->db = $db;
		$this->table = 'rig_user';
	}

	public function getEntity()
	{
		return new UserEntity();
	}

	public function get(array $options = array())
	{
		$join = ' LEFT JOIN rig_user_role ON rig_user_role.user_id = rig_user.id'
				. ' LEFT JOIN rig_role ON rig_role.id = rig_user_role.role_id';

		$sth = $this->db->prepare('SELECT COUNT(*) FROM ' . $this->table . $join);
		$sth->execute();
		$this->count = (int) $sth->fetchColumn(0);

		if ($this->count > 0)
		{
			$columns = $this->table . '.*, rig_role.id AS role_id, rig_role.name AS role_name';
			$limit = !empty($options['limit']) ? (int) $options['limit'] : 100;
			$start = !empty($options['page']) ? ($options['page'] - 1) * $limit : 0;

			$sth = $this->db->prepare('SELECT ' . $columns . ' FROM rig_user ' . $join . ' ORDER BY rig_role.id, rig_user.name LIMIT ' . $start . ', ' . $limit);
			$sth->execute();
			$this->result = $sth;
		}

		return $this;
	}

	public function getById($id)
	{
		$sth = $this->db->prepare('SELECT rig_user.*, rig_user_role.role_id AS role_id FROM ' . $this->table
				. ' LEFT JOIN rig_user_role ON rig_user_role.user_id = rig_user.id'
				. ' WHERE rig_user.id = :id');
		$sth->execute(array(':id' => $id));

		$this->result = $sth->fetch();

		if ($this->result)
		{
			$this->count = 1;

			if (!empty($this->result['meta']))
			{
				$this->result['meta'] = json_decode($this->result['meta'], true);
			}
		}

		return $this;
	}

	public function getByRoleId($roledId)
	{
		$q = ' FROM ' . $this->table
				. ' JOIN rig_user_role ON rig_user_role.user_id = rig_user.id'
				. ' AND rig_user_role.role_id = ' . (int) $roledId
				. ' WHERE rig_user.is_active = 1';

		$this->count = (int) $this->db->query('SELECT COUNT(*)' . $q)->fetchColumn(0);
		$this->result = $this->db->query('SELECT ' . $this->table . '.*' . $q);

		return $this;
	}

	public function getRoles()
	{
		$this->result = $this->db->query('SELECT * FROM rig_role WHERE rig_role.name != \'IS_AUTHENTICATED_ANONYMOUSLY\' ORDER BY id ASC');

		return $this;
	}

	public function update($data)
	{
		unset($data['password']);
		unset($data['salt']);
		unset($data['token']);

		return parent::update($data);
	}

	public function setRole($userId, $roleId)
	{
		$sth = $this->db->prepare('DELETE FROM rig_user_role WHERE user_id = :user_id');
		$sth->execute(array(':user_id' => $userId));

		$sth = $this->db->prepare('INSERT INTO rig_user_role VALUES (:user_id, :role_id)');
		$sth->execute(array(
			':user_id' => $userId,
			':role_id' => $roleId,
		));

		$this->lastError = $sth->errorInfo();
		$this->count = $sth->rowCount();

		return $this;
	}

	public function forgotPassword($email, $token)
	{
		$sth = $this->db->prepare('SELECT id FROM rig_user WHERE email = :email');
		$sth->execute(array(':email' => $email));

		$result = $sth->fetch();

		if (!$result)
		{
			return false;
		}

		$sth = $this->db->prepare('UPDATE rig_user SET token = :token WHERE id = :id');
		$sth->execute(array(
			':token' => $token,
			':id' => $result['id'],
		));

		if ($sth->rowCount() === 1)
		{
			return true;
		}

		return false;
	}

	public function resetPassword($token, $email, array $credentials)
	{
		$sth = $this->db->prepare('SELECT id FROM rig_user WHERE email = :email AND token = :token');
		$sth->execute(array(
			':email' => $email,
			':token' => $token,
		));

		$result = $sth->fetch();

		if (!$result)
		{
			return false;
		}

		$sth = $this->db->prepare('UPDATE rig_user SET token = NULL, password = :password, salt = :salt WHERE id = :id');
		$sth->execute(array(
			':password' => $credentials['password'],
			':salt' => $credentials['salt'],
			':id' => $result['id'],
		));

		if ($sth->rowCount() === 1)
		{
			return true;
		}

		return false;
	}

	public function eraseCredentials()
	{
		$this->result['password'] = null;
		$this->result['salt'] = null;

		return $this;
	}
}