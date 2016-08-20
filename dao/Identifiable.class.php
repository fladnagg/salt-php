<?php 
/**
 * Identifiable class
 *
 * @author     Richaud Julien "Fladnag"
 * @package    salt\dao
 */
namespace salt;

/**
 * Unique Id for classes
 */
class Identifiable {
	/**
	 * @var integer last instance id
	 */
	private static $_salt_id_sequence = 0;
	/**
	 * @var integer current instance id
	 */
	private $_salt_id;

	/**
	 * Construct a new Identifiable object
	 * 
	 * Have to be called by child classes for having an internal ID
	 */
	public function __construct() {
		$this->_salt_id = self::$_salt_id_sequence++;
	}

	/**
	 * Get the internal unique id
	 * @return integer Unique ID of an instance
	 */
	public function getInternalId() {
		return $this->_salt_id;
	}
}