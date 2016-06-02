<?php

namespace RigCms\Model;

class ArticleEntity
{
	public $id, $title, $body, $excerpt, $slug, $date, $expires, $meta, $role_id, $user_id;

	public function __construct()
	{
		$this->date = date('Y-m-d H:i:s');
	}

	public function getFilters()
	{
		return array(
			'title' => array('htmlspecialchars', 'null'),
			'body' => array(function($value) {
				return ($value === '' ? null : preg_replace('/<script.+?<\/script>/', '', $value));
			}),
			'excerpt' => array(function($value) {
				return ($value === '' ? null : preg_replace('/<script.+?<\/script>/', '', $value));
			}),
			'slug' => array('slug'),
			'expires' => array('null'),
			'meta' => array('meta'),
			'role_id' => array('null'),
		);
	}

	public function getValidationRules()
	{
		return array(
			'id' => array('required' => false),
			'title' => array('required' => false),
			'body' => array('required' => false),
			'excerpt' => array('required' => false),
			'date' => array('regex' => '^[0-9]{4}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}:[0-9]{2}$'),
			'expires' => array('required' => false, 'regex' => '^[0-9]{4}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}:[0-9]{2}$'),
			'meta' => array('required' => false),
			'role_id' => array('required' => false),
		);
	}
}