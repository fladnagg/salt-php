<?php 
/**
 * RowCountException class
 *
 * @author     Richaud Julien "Fladnag"
 * @package    salt\sql
 */
namespace salt;

use salt\DBException;

/**
 * An exception thrown when a query don't return the expected rows number
 */
class RowCountException extends DBException {

	/**
	 * @var int real number of rows changed by query
	 */
	public $rowCount;
	/**
	 * @var int expected number of rows changed by query
	 */
	public $expectedRowCount;

	/**
	 * Create a new RowCountException
	 * @param string $message error message
	 * @param string $query sql text query
	 * @param int $rowCount number of rows changed by query
	 * @param int $expectedRowCount expected number of rows changed by query
	 */
	public function __construct($message, $query, $rowCount, $expectedRowCount) {
		parent::__construct($message, $query, NULL);
		$this->rowCount;
		$this->expectedRowCount;
	}
}