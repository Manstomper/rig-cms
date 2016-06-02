<?php

namespace RigCms\Model;

use Symfony\Component\Security\Core\User\AdvancedUserInterface;

final class UserEntity implements AdvancedUserInterface
{
	public $id, $email, $name, $meta, $password, $salt, $token, $is_active;

	public function __construct()
	{
		$this->is_active = true;
	}

	public function getPassword()
	{
		return $this->password;
	}

	public function getSalt()
	{
		return $this->salt;
	}

	public function getUsername()
	{
		return $this->email;
	}

	public function isEnabled()
	{
		return $this->is_active;
	}

	public function isAccountNonExpired()
	{
		return true;
	}

	public function isCredentialsNonExpired()
	{
		return true;
	}

	public function isAccountNonLocked()
	{
		return true;
	}

	public function getRoles()
	{
		return $this->roles;
	}

	public function setRoles(array $roles = array())
	{
		$this->roles = $roles;
	}

	public function eraseCredentials()
	{
		$this->password = null;
		$this->salt = null;
	}

	public function getFilters()
	{
		return array(
			'name' => array('htmlspecialchars'),
			'meta' => array('meta'),
			'is_active' => array('boolean'),
		);
	}

	public function getValidationRules()
	{
		return array(
			'id' => array('required' => false),
			'email' => array('email' => true),
			'meta' => array('required' => false),
			'password' => array('required' => false, 'password' => true),
			'salt' => array('required' => false),
			'token' => array('required' => false),
		);
	}
}