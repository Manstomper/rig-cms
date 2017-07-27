<?php

namespace RigCms\Model;

final class DiscussModel extends CoreModel
{
	public function __construct(\PDO $db)
	{
		$this->db = $db;
		$this->table = 'rig_discuss';
	}

	public function getEntity()
	{
		return new DiscussEntity();
	}

	public function getByArticleId($id)
	{
		$sth = $this->db->prepare('SELECT COUNT(*) FROM rig_discuss WHERE article_id = :id AND is_visible = 1');
		$sth->execute(array(':id' => $id));
		$this->count = (int) $sth->fetchColumn(0);

		if ($this->count > 0)
		{
			$sth = $this->db->prepare('SELECT * FROM rig_discuss WHERE article_id = :id AND is_visible = 1');
			$sth->execute(array(':id' => $id));
			$this->result = $sth;
		}

		return $this;
	}

	public function moderate($id)
	{
		$sth = $this->db->prepare('UPDATE rig_discuss SET is_visible = (CASE WHEN is_visible = 1 THEN 0 ELSE 1 END) WHERE id = :id');

		if (is_array($id))
		{
			foreach ($id as $val)
			{
				$sth->execute(array(':id' => $val));
			}
		}
		else
		{
			$sth->execute(array(':id' => $id));
		}

		$this->lastError = $sth->errorInfo();
		$this->count = $sth->rowCount();

		return $this;
	}
}