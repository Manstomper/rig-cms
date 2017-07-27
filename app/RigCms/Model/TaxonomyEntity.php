<?php

namespace RigCms\Model;

class TaxonomyEntity
{
	public $id, $parent_id, $name, $slug, $syndicate;

	public function __construct()
	{
		$this->is_default = false;
		$this->syndicate = false;
	}

	public function getFilters()
	{
		return array(
			'parent_id' => array('null'),
			'name' => array('htmlspecialchars'),
			'slug' => array('slug'),
			'syndicate' => array('boolean'),
		);
	}

	public function getValidationRules()
	{
		return array(
			'id' => array('required' => false),
			'parent_id' => array('required' => false),
		);
	}
}