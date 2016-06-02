<?php

namespace RigCms\Model;

abstract class CoreModel
{
	protected $db, $table, $result, $count, $lastError, $columns;

	public function get(array $options = array())
	{
		$filter = !empty($options['filter']) ? $options['filter'] : null;
		$params = !empty($options['params']) ? $options['params'] : null;
		$limit = !empty($options['limit']) ? (int) $options['limit'] : 100;
		$start = !empty($options['page']) ? ($options['page'] - 1) * $limit : 0;

		if (!$this->columns)
		{
			$this->columns = $this->table . '.*';
		}

		if ($this->count === null)
		{
			$sth = $this->db->prepare('SELECT COUNT(*) FROM ' . $this->table . ' ' . $filter);
			$sth->execute($params);
			$this->count = (int) $sth->fetchColumn(0);
		}

		if ($this->count > 0)
		{
			if (empty($options['order']))
			{
				$order = $this->table . '.id ASC';
			}
			else
			{
				$order = array();
				$entity = $this->getEntity();

				foreach ($options['order'] as $key => $val)
				{
					if (property_exists($entity, $key))
					{
						$order[] = $key . ($val != "DESC" ? '' : ' DESC');
					}
				}

				$order = implode(', ', $order);
			}

			$sth = $this->db->prepare('SELECT ' . $this->columns . ' FROM ' . $this->table . ' ' . $filter . ' ORDER BY ' . $order . ' LIMIT ' . $start . ', ' . $limit);
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
		return empty($this->lastError[0]) || $this->lastError[0] === '00000' ? false : true;
	}

	/*@TODO work on this*/

	protected function getWhere($filters, &$params, &$i)
	{
		$where = '';

		foreach ($filters as $filter)
		{
			if (isset($filter[0]) && is_array($filter[0]))
			{
				$nestedWhere = '';

				foreach ($filter as $nestedFilter)
				{
					$compare = !empty($nestedFilter['compare']) ? $this->getComparison($nestedFilter['compare']) : ' = ';

					if ($nestedWhere === '')
					{
						if ($where === '')
						{
							$relation = !empty($nestedFilter['relation']) ? ' (' : ' (';
						}
						else
						{
							$relation = !empty($nestedFilter['relation']) && $nestedFilter['relation'] != 'AND' ? ' OR (' : ' AND (';
						}
					}
					else
					{
						$relation = !empty($nestedFilter['relation']) && $nestedFilter['relation'] != 'AND' ? ' OR ' : ' AND ';
					}

					$nestedWhere .= $relation . $this->table . '.' . $nestedFilter['column'] . $compare;

					if (!empty($nestedFilter['value']))
					{
						$nestedWhere .= ':val' . $i;
						$params[':val' . $i] = $nestedFilter['value'];
						$i++;
					}
				}

				$where .= $nestedWhere . ')';
			}
			else
			{
				$compare = !empty($filter['compare']) ? $this->getComparison($filter['compare']) : ' = ';

				if ($where !== '')
				{
					$relation = !empty($filter['relation']) && $filter['relation'] != 'AND' ? ' OR ' : ' AND ';
				}
				else
				{
					$relation = '';
				}

				$where .= $relation . $this->table . '.' . $filter['column'] . $compare;

				if (!empty($filter['value']))
				{
					if (is_array($filter['value']))
					{
						$in = array();

						foreach ($filter['value'] as $value)
						{
							$in[] = ':val' . $i;
							$params[':val' . $i] = $filter['value'];
							$i++;
						}

						$where .= '(' . implode(', ', $in) . ')';
					}
					else
					{
						$where .= ':val' . $i;
						$params[':val' . $i] = $filter['value'];
						$i++;
					}
				}
			}
		}

		return ' WHERE ' . $where;
	}

	/*@TODO work on this*/

	protected function getComparison($str)
	{
		$valid = array(
			'=',
			'<',
			'<=',
			'>',
			'>=',
			'LIKE',
			'IS NULL',
			'IN',
		);

		if (in_array($str, $valid))
		{
			return ' ' . $str . ' ';
		}

		return ' = ';
	}
}