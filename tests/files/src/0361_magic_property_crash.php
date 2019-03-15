<?php

/**
 * Entity.
 *
 * @property int $dataId
 */
class Entity
{
	/**
	 * @var int
	 */
	protected $dataId;

	/**
	 * @var string
	 */
	protected $privilege;

	public function __get(string $name) {
		return $this->{$name};
	}
}
echo strlen((new Entity())->dataId);  // this is accessible outside the class and of type int
