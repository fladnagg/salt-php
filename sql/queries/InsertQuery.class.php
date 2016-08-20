<?php
/**
 * InsertQuery class
 *
 * @author     Richaud Julien "Fladnag"
 * @package    salt\sql\queries
 */
namespace salt;

/**
 * Query for INSERT
 */
class InsertQuery extends BaseQuery {

	/**
	 * @var Base[] list of objects to insert
	 */
	private $objects;

	/**
	 * @var string[][] list of values to insert
	 * @content array of array of field=>bind
	 */
	private $sets = array();

	/** @var string[] array of modified field names */
	private $fields = array();

	/**
	 * Construct a new INSERT query
	 * @param Base[]|Base $objects object or array of object (of same type) to insert
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
		$this->fields = array_keys($obj->getModifiedFields());

		/**
		 * @var $o Base */
		foreach($objects as $o) {
			$cl = get_class($o);
			if ($cl !== $class) {
				throw new SaltException('Cannot insert differents objects type at the same time. '.
						'The first object is a ['.$class.']. Found another object in list of type ['.$cl.']');
			}
			if (!$o->isNew()) {
				throw new SaltException('Cannot insert object which are not in NEW state');
			}
			$fields = array_keys($o->getModifiedFields());
			if ((count($this->fields) != count($fields))
			|| (count(array_diff($this->fields, $fields)) > 0)) {
				throw new SaltException('Cannot insert objects which have not the same fields modified. '.
						'The first object change fields ['.implode(',', $this->fields).'], but another '.
						'object change fields ['.implode(',', $fields).']');
			}

			$sets = array();
			foreach($this->fields as $f) {
				$expr = SqlExpr::value($o->$f)->asSetter($this->obj->getField($f));
				$sets[$f] = $this->resolveFieldName('INSERT', $expr);
				$this->binds = array_merge($this->binds, $expr->getBinds());
			}
			$this->sets[] = $sets;
		}

		$this->objects = $objects;
	}

	/**
	 * Retrieve the number of objects passed in query constructor
	 * @return int the number of expected objects to insert
	 */
	public function getInsertObjectCount() {
		return count($this->objects);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Pagination $pagination not used here
	 * @see BaseQuery::toSQL()
	 */
	public function toSQL(Pagination $pagination = NULL) {

		$sql='INSERT INTO '.$this->obj->getTableName();

		$sql.=' ('.implode(', ', $this->fields).')';

		$allValues = array();
		/**
		 * @var $obj Base */
		foreach($this->sets as $sets) {
			$values = array();
			foreach($this->fields as $f) {
				$values[] = $sets[$f];
			}
			$allValues[]='('.implode(', ', $values).')';
		}
		$sql.=' VALUES '.implode(', ', $allValues);

		return $sql;
	}
}
