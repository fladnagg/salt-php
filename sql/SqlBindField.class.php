<?php 
/**
 * SqlBindField class
 *
 * @author     Richaud Julien "Fladnag"
 * @package    salt\sql
 */
namespace salt;

/**
 * Handling Fields and Binds for Query and SqlExpr
 */
class SqlBindField {

	/**
	 * @var int bind unique number */
	protected static $bindNumber=0;

	/**
	 * @var mixed[] list of binds
	 * @content <pre>array of bindName => array(
	 * 					'value' => mixed // value of bind
	 * 					'type' => int FieldType // type of field
	 * 			)</pre> */
	protected $binds = array();

	/**
	 * Add a bind
	 * @param mixed $value value of bind
	 * @param int $type (FieldType) type of the field
	 * @return string the bind name
	 */
	protected function addBind($value, $type) {
		$bind = ':v'.(self::$bindNumber++);

		$this->binds[$bind]=array(
				'value' => $value,
				'type' => $type);

		return $bind;
	}

	/**
	 * Return all binds
	 * @return mixed[][] list of binds : array of array('value' => ..., 'type' => ...)
	 */
	public function getBinds() {
		return $this->binds;
	}
}
