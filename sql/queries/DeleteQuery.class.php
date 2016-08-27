<?php
/**
 * DeleteQuery class
 *
 * @author     Richaud Julien "Fladnag"
 * @package    salt\sql\queries
 */
namespace salt;

/**
 * Query for DELETE
 */
class DeleteQuery extends UpdateQuery {

	/** @var Base[] List of deleted objects */
	private $objects;

	/**
	 * Create a query for delete one or more object
	 * @param Base[]|Base $objects object or array of object (of same type) to delete
	 */
	public function __construct($objects) {
		$obj = NULL;
		if (is_array($objects)) {
			$obj = first($objects);
		} else {
			$obj = $objects;
			$objects = array($obj);
		}

		parent::__construct($obj);

		$class = get_class($obj);
		/**
		 * @var Base $o */
		foreach($objects as $o) {
			$cl = get_class($o);
			if ($cl !== $class) {
				throw new SaltException('Cannot delete differents objects type at the same time. The first object is a ['.$class.']. Found another object in list of type ['.$cl.']');
			}
		}

		$this->objects = $objects;

		$this->noAlias = TRUE;
	}

	/**
	 * Retrieve the number of objects passed in query constructor
	 * @return int number of objects expected to be deleted
	 */
	public function getDeletedObjectCount() {
		return count($this->objects);
	}

	/**
	 * {@inheritDoc}
	 * @param Pagination $pagination not used here
	 * @see \salt\UpdateQuery::toSQL()
	 */
	public function toSQL(Pagination $pagination = NULL) {
		if ($this->sqlText !== NULL) {
			// without memoization, a 2nd call will bind object ID twice.
			// we cannot bind object id before because user can call allowMultipleUpdate()
			return $this->sqlText;
		}

		$sql='DELETE FROM '.$this->resolveTable();

		if (!$this->allowMultiple) {
			$deletedObjects = array();
			foreach($this->objects as $obj) {
				if (!$obj->isReadonly()) {
					$obj->delete();
					$deletedObjects[] = $obj;
				}
			}

			$this->whereAndObject($deletedObjects);
		}
		if ((count($this->wheres) === 0) && !$this->allowEmptyWhere) {
			throw new SaltException('You don\'t have a WHERE clause on DELETE. Please call allowEmptyWhere() if you really want to do this');
		}

		$sql.=$this->wheresToSQL();
		$sql.=$this->orderToSQL();

		$this->sqlText = $sql;
		return $sql;
	}
}
