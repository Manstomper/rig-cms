<?php

namespace RigCms\Model;

final class ArticleModel extends CoreModel
{
	public function __construct(\PDO $db)
	{
		$this->db = $db;
		$this->table = 'rig_article';
	}

	public function get(array $options = array())
	{
		$join = '';
		$where = '';
		$options['params'] = array();
		$i = 0;

		if (!empty($options['taxonomy']))
		{
			foreach ($options['taxonomy'] as $val)
			{
				$options['params'][':val' . $i] = $val;
				$i++;
			}

			$join = ' JOIN rig_article_taxonomy ON rig_article_taxonomy.article_id = rig_article.id'
					. ' JOIN rig_taxonomy ON rig_taxonomy.id = rig_article_taxonomy.taxonomy_id'
					. ' AND rig_taxonomy.slug IN (' . implode(', ', array_keys($options['params'])) . ') ';

			unset($options['taxonomy']);
		}

		if (!empty($options['filter']))
		{
			$where = $this->getWhere($options['filter'], $options['params'], $i);
		}

		$sth = $this->db->prepare('SELECT COUNT(*) FROM ' . $this->table . $join . $where);
		$sth->execute($options['params']);
		$this->count = (int) $sth->fetchColumn(0);

		if ($this->count === 0)
		{
			return $this;
		}

		$join .= ' JOIN rig_user ON rig_user.id = rig_article.user_id LEFT JOIN rig_discuss ON rig_discuss.article_id = rig_article.id AND rig_discuss.visible = 1 ';
		$options['filter'] = $join . $where . ' GROUP BY rig_article.id';
		$this->columns = $this->table . '.*, COUNT(rig_discuss.id) as comment_count, rig_user.name AS author, rig_user.email AS author_email, rig_user.meta AS author_meta';

		return parent::get($options);
	}

	public function getById($id, $slug = null)
	{
		if ($slug)
		{
			$where = ' WHERE rig_article.slug = :slug';
		}
		else
		{
			$where = ' WHERE rig_article.id = :id';
		}

		$sth = $this->db->prepare('SELECT rig_article.*, rig_taxonomy.name AS section_name, rig_taxonomy.slug AS section_slug, rig_role.name AS role_name FROM ' . $this->table
				. ' LEFT JOIN rig_article_taxonomy ON rig_article_taxonomy.article_id = rig_article.id'
				. ' LEFT JOIN rig_taxonomy ON rig_taxonomy.id = rig_article_taxonomy.taxonomy_id'
				. ' LEFT JOIN rig_role ON rig_role.id = rig_article.role_id'
				. $where
				. ' GROUP BY rig_article.id'
				. ' ORDER BY (CASE WHEN rig_taxonomy.hierarchy IS NULL THEN 1 ELSE 0 END), rig_taxonomy.hierarchy');

		if ($slug)
		{
			$sth->execute(array(
				':slug' => $slug,
			));
		}
		else
		{
			$sth->execute(array(
				':id' => $id,
			));
		}

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

	function getBySlug($slug)
	{
		return $this->getById(null, $slug);
	}

	public function attachTaxonomy($articleId, $taxonomyId)
	{
		$sth = $this->db->prepare('INSERT IGNORE INTO rig_article_taxonomy (article_id, taxonomy_id) VALUES (:article_id, :taxonomy_id)');
		$this->db->beginTransaction();

		if (is_array($taxonomyId))
		{
			foreach ($taxonomyId as $id)
			{
				$sth->execute(array(
					':article_id' => $articleId,
					':taxonomy_id' => $id,
				));
			}
		}
		elseif (is_array($articleId))
		{
			foreach ($articleId as $id)
			{
				$sth->execute(array(
					':article_id' => $id,
					':taxonomy_id' => $taxonomyId,
				));
			}
		}

		$this->db->commit();

		$this->lastError = $sth->errorInfo();
		$this->count = $sth->rowCount();

		return $this;
	}

	public function detachTaxonomy($articleId, $taxonomyId = null)
	{
		if ($taxonomyId === null)
		{
			$sth = $this->db->prepare('DELETE FROM rig_article_taxonomy WHERE article_id = :article_id');
			$sth->execute(array(':article_id' => $articleId));
		}
		else
		{
			$sth = $this->db->prepare('DELETE FROM rig_article_taxonomy WHERE article_id = :article_id AND taxonomy_id = :taxonomy_id');
			$this->db->beginTransaction();

			if (is_array($taxonomyId))
			{
				foreach ($taxonomyId as $id)
				{
					$sth->execute(array(
						':article_id' => $articleId,
						':taxonomy_id' => $id,
					));
				}
			}
			elseif (is_array($articleId))
			{
				foreach ($articleId as $id)
				{
					$sth->execute(array(
						':article_id' => $id,
						':taxonomy_id' => $taxonomyId,
					));
				}
			}

			$this->db->commit();
		}

		$this->lastError = $sth->errorInfo();
		$this->count = $sth->rowCount();

		return $this;
	}

	public function getEntity()
	{
		return new ArticleEntity();
	}
}