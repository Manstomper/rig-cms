<?php

namespace RigCms\Model;

class DiscussEntity
{
	public $id, $article_id, $parent_id, $author, $email, $body, $date, $is_visible;

	public function __construct()
	{
		$this->date = date('Y-m-d H:i:s');
		$this->is_visible = false;
	}

	public function getFilters()
	{
		return array(
			'body' => array(function($value) {
				if (!$value)
				{
					return null;
				}

				$value = htmlspecialchars(str_replace("\t", '', $value), ENT_QUOTES, 'UTF-8');

				$value = '<p>' . preg_replace(array(
					'/\R{2,}/',
					'/\R{1}/',
					'/\s{2,}/',
				), array(
					'</p><p>',
					'<br>',
					' ',
				), $value) . '</p>';

				$value = str_replace(array(
					'> ',
					' <',
				), array(
					'>',
					'<',
				), $value);

				return $value;
			}),
			'date' => array('default'),
			'is_visible' => array('boolean'),
		);
	}

	public function getValidationRules()
	{
		return array(
			'id' => array('required' => false),
			'article_id' => array('required' => false),
			'parent_id' => array('required' => false),
			'email' => array('required' => false, 'email' => true),
			'date' => array('regex' => '^[0-9]{4}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}:[0-9]{2}$'),
		);
	}
}