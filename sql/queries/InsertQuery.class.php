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
	private $_salt_objects = array();

	/**
	 * @var string[][] list of values to insert
	 * @content array of array of field=>bind
	 */
	private $_salt_sets = array();

	/** @var string[] array of modified field names */
	private $_salt_fields = array();
	
	/** @var Query query to use for populate insert */
	private $_salt_query = NULL;

	/**
	 * Construct a new INSERT query
	 * @param Base[]|Base $objects object or array of object (of same type) to insert
	 * @param Query $select SELECT query to use for insert. 
	 */
	public function __construct($objects, Query $select = NULL) {
		$obj = NULL;

		if (is_array($objects)) {
			$obj = first($objects);
		} else {
			$obj = $objects;
			$objects = array($obj);
		}

		parent::__construct($obj);

		$class = get_class($obj);
		
		if ($select !== NULL) {
			$this->_salt_fields = $select->getSelectFields();
				
			foreach($this->_salt_fields as $field) {
				if (!$obj->MODEL()->exists($field)) {
					throw new SaltException('Select query return the column ['.$field.'] which not exists in ['.get_class($obj).']');
				}
			}
			$this->_salt_query = $select;
		} else {
			
			$this->_salt_fields = array_keys($obj->getModifiedFields());
	
			/**
			 * @var Base $o */
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
				if ((count($this->_salt_fields) != count($fields))
				|| (count(array_diff($this->_salt_fields, $fields)) > 0)) {
					throw new SaltException('Cannot insert objects which have not the same fields modified. '.
							'The first object change fields ['.implode(',', $this->_salt_fields).'], but another '.
							'object change fields ['.implode(',', $fields).']');
				}
	
				$sets = array();
				foreach($this->_salt_fields as $f) {
					$expr = SqlExpr::value($o->$f)->asSetter($this->_salt_obj->MODEL()->$f);
					$sets[$f] = $this->resolveFieldName(ClauseType::INSERT, $expr);
					//$this->binds = array_merge($this->binds, $expr->getBinds()); // already done by resolveFieldName
				}
				$this->_salt_sets[] = $sets;
			}
	
			$this->_salt_objects = $objects;
		}
	}

	/**
	 * Retrieve the number of objects passed in query constructor
	 * @return int the number of expected objects to insert
	 */
	public function getInsertObjectCount() {
		return count($this->_salt_objects);
	}

	/**
	 * {@inheritDoc}
	 * @see \salt\SqlBindField::buildSQL()
	 */
	protected function buildSQL() {
		$sql='INSERT INTO '.$this->_salt_obj->MODEL()->getTableName();

		$sql.=' (`'.implode('`, `', $this->_salt_fields).'`)';

		if ($this->_salt_query !== NULL) {
			$sql.=' '.$this->_salt_query->toSQL();
			$this->linkBindsOf($this->_salt_query, ClauseType::INSERT);
		} else {
			$allValues = array();
			/**
			 * @var Base $obj */
			foreach($this->_salt_sets as $sets) {
				$values = array();
				foreach($this->_salt_fields as $f) {
					$values[] = $sets[$f];
				}
				$allValues[]='('.implode(', ', $values).')';
			}
			$sql.=' VALUES '.implode(', ', $allValues);
		}

		return $sql;
	}
}
