<?php 
/**
 * DBResult class
 *
 * @author     Richaud Julien "Fladnag"
 * @package    salt\sql
 */
namespace salt;

/**
 * Result of a DB query
 */
class DBResult {
	/**
	 * @var string[] text to display for each column */
	public $columns=array();
	/**
	 * @var Base[] every result is a Base child object */
	public $data=array();

	/**
	 * Return all columns excepts specified ones
	 * 
	 * @param string ... $excludeColumns list of columns to NOT return
	 * @return string[] list of columns excepts $excludeColumns ones
	 */
	public function columnsExcept($excludeColumns) {
		$cols = array();

		foreach($this->columns as $col) {
			if (!in_array($col, func_get_args())) {
				$cols[]=$col;
			}
		}

		return $cols;
	}
}