<?php

namespace RigCms\Model;

final class DiscussModel extends CoreModel
{
	public function __construct(\PDO $db)
	{
		$this->db = $db;
		$this->table = 'rig_discuss';
	}

	public function get(array $options = array())
	{
		$this->columns = $this->table . '.*, rig_article.title AS article_title';
		$options['filter'] = 'JOIN rig_article ON rig_article.id = rig_discuss.article_id';

		return parent::get($options);
	}

	public function getByArticleId($id)
	{
		$sth = $this->db->prepare('SELECT COUNT(*) FROM rig_discuss WHERE article_id = :id AND visible > 0');
		$sth->execute(array(':id' => $id));
		$this->count = (int) $sth->fetchColumn(0);

		if ($this->count > 0)
		{
			$sth = $this->db->prepare('SELECT * FROM rig_discuss WHERE article_id = :id AND visible > 0');
			$sth->execute(array(':id' => $id));
			$this->result = $sth;
		}

		return $this;
	}

	public function moderate($id)
	{
		$sth = $this->db->prepare('UPDATE rig_discuss SET visible = (CASE WHEN visible = 1 THEN 0 ELSE 1 END) WHERE id = :id');

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

	public function getEntity()
	{
		return new DiscussEntity();
	}
}