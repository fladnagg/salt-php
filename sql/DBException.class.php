<?php
/**
 * DBException class
 *
 * @author     Richaud Julien "Fladnag"
 * @package    salt\sql
 */
namespace salt;

/**
 * Exception from a query
 */
class DBException extends SaltException {

	/**
	 * @var string SQLSTATE exception code returned by \PDOException if table does not exists
	 */
	const TABLE_DOES_NOT_EXISTS = '42S02';

	/**
	 * @var string sql query */
	private $query;

	/**
	 * @var string SQLSTATE error Code */
	private $sqlStateErrorCode = NULL;

	/**
	 * Create a new DBException
	 * @param string $message Exception message
	 * @param string $query the SQL query
	 * @param \PDOException $previous previous exception
	 */
	public function __construct($message, $query, \PDOException $previous = NULL) {
		parent::__construct($message, NULL, $previous);
		$this->query = $query;
		if ($previous !== NULL) {
			$this->sqlStateErrorCode = $previous->getCode();
		}
	}

	/**
	 * Retrieve SQL text of the query
	 * @return string sql query text
	 */
	public function getQuery() {
		return $this->query;
	}

	/**
	 * Retrieve exception code
	 * @return string sql SQLSTATE error code
	 */
	public function getSqlStateErrorCode() {
		return $this->sqlStateErrorCode;
	}
}