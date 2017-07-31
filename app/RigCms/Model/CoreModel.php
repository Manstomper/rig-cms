<?php

namespace RigCms\Model;

abstract class CoreModel
{
	protected $db, $table, $result, $count, $lastError, $columns;

	public function get(array $options = array())
	{
		$filter = !empty($options['filter']) ? $options['filter'] : null;
		$params = !empty($options['params']) ? $options['params'] : null;

		if (!$this->columns)
		{
			$this->columns = $this->table . '.*';
		}

		if ($this->count === null)
		{
			$sth = $this->db->prepare('SELECT COUNT(*) FROM ' . $this->table . ' ' . $filter);
			$sth->execute($params);
			$this->count = $sth->fetchColumn(0);
		}

		if ($this->count > 0)
		{
			$order = !empty($options['order']) ? $this->getOrder($options['order']) : ' ORDER BY ' . $this->table . '.id ASC';
			$limit = !empty($options['limit']) ? (int) $options['limit'] : 100;
			$start = !empty($options['page']) ? ($options['page'] - 1) * $limit : 0;

			$sth = $this->db->prepare('SELECT ' . $this->columns . ' FROM ' . $this->table . ' ' . $filter . ' ' . $order . ' LIMIT ' . $start . ', ' . $limit);
			$sth->execute($params);
			$this->result = $sth;
		}

		return $this;
	}

	public function getById($id)
	{
		$sth = $this->db->prepare('SELECT * FROM ' . $this->table . ' WHERE id = :id');
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

	public function insert($data)
	{
		unset($data['id']);

		$columns = array_keys($data);

		foreach ($data as $key => $val)
		{
			$params[':' . $key] = $val;
		}

		$sth = $this->db->prepare('INSERT INTO ' . $this->table . ' (' . implode(', ', $columns) . ') VALUES (' . ':' . implode(', :', $columns) . ')');
		$sth->execute($params);

		$this->lastError = $sth->errorInfo();
		$this->count = $sth->rowCount();

		return $this;
	}

	public function update($data)
	{
		$set = array();
		$params = array();

		foreach ($data as $key => $val)
		{
			if ($key !== 'id')
			{
				$set[] = $key . ' = :' . $key;
			}

			$params[':' . $key] = $val;
		}

		$sth = $this->db->prepare('UPDATE ' . $this->table . ' SET ' . implode(', ', $set) . ' WHERE id = :id');
		$sth->execute($params);

		$this->lastError = $sth->errorInfo();
		$this->count = $sth->rowCount();

		return $this;
	}

	public function delete($id)
	{
		$sth = $this->db->prepare('DELETE FROM ' . $this->table . ' WHERE id = :id');
		$this->db->beginTransaction();
		$this->count = 0;

		if (is_array($id))
		{
			foreach ($id as $val)
			{
				$sth->execute(array(':id' => $val));
				$this->count += $sth->rowCount();
			}
		}
		else
		{
			$sth->execute(array(':id' => $id));
			$this->count = $sth->rowCount();
		}

		$this->db->commit();

		$this->lastError = $sth->errorInfo();

		return $this;
	}

	public function getCount()
	{
		return (int) $this->count;
	}

	public function getResult()
	{
		return $this->result;
	}

	public function getLastInsertId()
	{
		return (int) $this->db->lastInsertId();
	}

	public function getLastError()
	{
		return $this->lastError;
	}

	public function hasError()
	{
		if (empty($this->lastError[0]) || $this->lastError[0] === '00000')
		{
			return false;
		}

		return true;
	}

	protected function getOrder(array $order)
	{
		$entity = $this->getEntity();
		$q = array();

		foreach ($order as $key => $val)
		{
			if (property_exists($entity, $key))
			{
				$q[] = $this->table . '.' . $key . ($val !== 'DESC' ? ' ASC' : ' DESC');
			}
		}

		return ' ORDER BY ' . implode(', ', $q);
	}
}