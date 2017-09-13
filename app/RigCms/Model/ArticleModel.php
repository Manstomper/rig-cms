<?php

namespace RigCms\Model;

final class ArticleModel extends CoreModel
{
	public function __construct(\PDO $db)
	{
		$this->db = $db;
		$this->table = 'rig_article';
	}

	public function getEntity()
	{
		return new ArticleEntity();
	}

	public function getArticles(array $options = array(), array $taxonomy = array(), $roleId = 4, $searchTerms = '')
	{
		$filter = '';
		$params = array();

		if (!empty($taxonomy))
		{
			foreach ($taxonomy as $key => $val)
			{
				$params[':tax' . $key] = $val;
			}

			$filter = ' JOIN rig_article_taxonomy ON rig_article_taxonomy.article_id = rig_article.id'
					. ' JOIN rig_taxonomy ON rig_taxonomy.id = rig_article_taxonomy.taxonomy_id'
					. ' AND rig_taxonomy.slug IN (' . implode(', ', array_keys($params)) . ')';
		}

		$filter .= ' WHERE rig_article.role_id >= ' . (int) $roleId;

		if (!empty($searchTerms))
		{
			$searchFilter = $this->getSearchFilter($searchTerms);
			$filter .= ' AND (' . $searchFilter['filter'] . ')';
			$params = array_merge($params, $searchFilter['params']);
		}

		$sth = $this->db->prepare('SELECT COUNT(*) FROM ' . $this->table . $filter);
		$sth->execute($params);
		$this->count = (int) $sth->fetchColumn(0);

		if ($this->count === 0)
		{
			return $this;
		}

		$options['filter'] = $filter;
		$options['params'] = $params;

		return parent::get($options);
	}

	public function getById($id)
	{
		return $this->getByIdOrSlug($id, null);
	}

	public function getBySlug($slug)
	{
		return $this->getByIdOrSlug(null, $slug);
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

	private function getByIdOrSlug($id, $slug)
	{
		$sth = $this->db->prepare('SELECT rig_article.*, rig_taxonomy.name AS section_name, rig_taxonomy.slug AS section_slug FROM ' . $this->table
				. ' LEFT JOIN rig_article_taxonomy ON rig_article_taxonomy.article_id = rig_article.id'
				. ' LEFT JOIN rig_taxonomy ON rig_taxonomy.id = rig_article_taxonomy.taxonomy_id'
				. ' AND rig_taxonomy.parent_id IS NULL'
				. ' WHERE rig_article.' . ($slug ? 'slug' : 'id') . ' = :val');

		$sth->execute(array(
			':val' => $slug ? $slug : $id,
		));

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

	private function getSearchFilter($terms)
	{
		$terms = explode(' ', $terms);
		$count = count($terms);
		$where = array();
		$params = array();

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

			$where[] = 'rig_article.title LIKE :q' . $i . ' OR rig_article.body LIKE :q' . $i;
			$params[':q' . $i] = '%' . $term . '%';
		}

		return array(
			'filter' => implode(' OR ', $where),
			'params' => $params,
		);
	}
}