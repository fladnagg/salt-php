<?php
/**
 * StatementDelegate class
 *
 * @author     Richaud Julien "Fladnag"
 * @package    salt\sql
 */
namespace salt;

/**
 * A child of PDOStatement with some extra methods
 */
class SqlStatement extends \PDOStatement {

	/**
	 * Construct a new SqlStatement
	 */
	protected function __construct() {
		// do nothing
	}

	/**
	 * Retrieve next object from executed statement
	 * @param Base $bindingObject The type of object to return
	 * @return Base|FALSE the next row as Base object or FALSE if there is no more rows
	 */
	public function getAs(Base $bindingObject) {
		$binding = get_class($bindingObject);

		try {
			$this->setFetchMode(\PDO::FETCH_CLASS | \PDO::FETCH_PROPS_LATE, $binding, array(array()));
			$result = $this->fetch();
		} catch (\PDOException $ex) {
			throw new DBException(L::error_query_fetch($this->queryString), $this->queryString, $ex);
		} catch (\Exception $ex) {
			throw new SaltException(L::error_query_fetch($this->queryString), $ex->getCode(), $ex);
		}

		if ($result !== FALSE) {
			$result->afterLoad();
		}

		return $result;
	}

	/**
	 * Retrieve all objects from executed statement
	 * @param Base $bindingObject The type of object to return
	 * @param mixed $indexedBy Column name to use for index array
	 * @return Base[] all rows as Base objects
	 */
	public function getAllAs(Base $bindingObject, $indexedBy = NULL) {

		$binding = get_class($bindingObject);

		try {
			/**
			 * @var Base[] $result
			 */
			$result = $this->fetchAll(\PDO::FETCH_CLASS | \PDO::FETCH_PROPS_LATE, $binding, array(array()));
		} catch (\PDOException $ex) {
			throw new DBException(L::error_query_fetch($this->queryString), $this->queryString, $ex);
		} catch (\Exception $ex) {
			throw new SaltException(L::error_query_fetch($this->queryString), $ex->getCode(), $ex);
		}

		if ($indexedBy === NULL) {
			foreach($result as $object) {
				$object->afterLoad();
			}
		} else {
			$list = array();
			foreach($result as $object) {
				$object->afterLoad();
				$key = $object->$indexedBy;
				$list[$key] = $object;
			}
			$result = $list;
		}

		return $result;
	}
}
