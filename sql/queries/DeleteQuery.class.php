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
	private $_salt_objects;

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

		$notReadonlyObjects = array();

		$class = get_class($obj);
		/**
		 * @var Base $o */
		foreach($objects as $o) {
			$cl = get_class($o);
			if ($cl !== $class) {
				throw new SaltException('Cannot delete differents objects type at the same time. The first object is a ['.$class.']. Found another object in list of type ['.$cl.']');
			}
			if (!$o->isReadonly()) {
				$notReadonlyObjects[] = $o;
			}
		}

		$this->_salt_objects = $notReadonlyObjects;

		$this->_salt_noAlias = TRUE;
	}

	/**
	 * Retrieve the number of objects passed in query constructor
	 * @return int number of objects expected to be deleted
	 */
	public function getDeletedObjectCount() {
		return count($this->_salt_objects);
	}

	/**
	 * {@inheritDoc}
	 * @see salt\SqlBindField::buildSQL() 
	 */
	protected function buildSQL() {
		$sql='DELETE FROM '.$this->resolveTable();

		if (!$this->_salt_allowMultiple) {
			$deletedObjects = array();
			foreach($this->_salt_objects as $obj) {
				$obj->delete();
				$deletedObjects[] = $obj;
			}
			if (count($deletedObjects) > 0) {
				$this->whereAndObject($deletedObjects);
			}
		}
		if ((count($this->_salt_wheres) === 0) && !$this->_salt_allowEmptyWhere) {
			throw new SaltException('You don\'t have a WHERE clause on DELETE. Please call allowEmptyWhere() if you really want to do this');
		}

		$sql.=$this->buildWhereClause();
		$sql.=$this->buildOrderClause();

		return $sql;
	}
}
