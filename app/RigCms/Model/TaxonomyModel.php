<?php

namespace RigCms\Model;

final class TaxonomyModel extends CoreModel
{
	public function __construct(\PDO $db)
	{
		$this->db = $db;
		$this->table = 'rig_taxonomy';
	}

	public function getEntity()
	{
		return new TaxonomyEntity();
	}

	public function getBySlug($slug)
	{
		if (is_array($slug))
		{
			$params = array();

			foreach ($slug as $key => $val)
			{
				$params[':slug' . $key] = $val;
			}
		}
		else
		{
			$params = array(':slug0' => $slug);
		}

		$sth = $this->db->prepare('SELECT * FROM rig_taxonomy WHERE slug IN (' . implode(', ', array_keys($params)) . ')');
		$sth->execute($params);

		$this->result = $sth;

		return $this;
	}

	public function getByArticleId($id)
	{
		$sth = $this->db->prepare('SELECT rig_taxonomy.* FROM ' . $this->table
				. ' JOIN rig_article_taxonomy ON rig_article_taxonomy.taxonomy_id = rig_taxonomy.id'
				. ' AND rig_article_taxonomy.article_id = :id');
		$sth->execute(array(':id' => $id));

		$this->result = $sth;

		return $this;
	}

	public function getWithArticleId($id)
	{
		$sth = $this->db->prepare('SELECT rig_taxonomy.*, rig_article_taxonomy.article_id AS article_id FROM ' . $this->table
				. ' LEFT JOIN rig_article_taxonomy ON rig_article_taxonomy.taxonomy_id = rig_taxonomy.id'
				. ' AND rig_article_taxonomy.article_id = :id');
		$sth->execute(array(':id' => $id));

		$this->result = $sth;

		return $this;
	}

	public function getWithArticleCount($parentId = null)
	{
		if (!empty($parentId))
		{
			$where = ' WHERE rig_taxonomy.parent_id = :id';
			$params = array(':id' => $parentId);
		}
		else
		{
			$where = '';
			$params = null;
		}

		$sth = $this->db->prepare('SELECT rig_taxonomy.*, COUNT(rig_article_taxonomy.article_id) AS article_count' . ' FROM ' . $this->table
				. ' LEFT JOIN rig_article_taxonomy ON rig_article_taxonomy.taxonomy_id = rig_taxonomy.id'
				. $where
				. ' GROUP BY rig_taxonomy.id'
				. ' ORDER BY ' . $this->table. '.name');
		$sth->execute($params);

		$this->result = $sth;

		return $this;
	}

	public function getSyndicated()
	{
		$this->result = $this->db->query('SELECT slug FROM rig_taxonomy WHERE syndicate = 1');

		return $this;
	}
}