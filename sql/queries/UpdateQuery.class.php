<?php
/**
 * UpdateQuery class
 *
 * @author     Richaud Julien "Fladnag"
 * @package    salt\sql\queries
 */
namespace salt;

/**
 * Query for UPDATE
 */
class UpdateQuery extends Query {

	/**
	 * @var mixed[] list of fields => values to update */
	private $sets = array();
	/**
	 * @var mixed[] list of fieldName => array of binds.
	 * @content Register all binds used for each field. If a field is changed twice, previous binds are canceled
	 */
	private $bindsBySetField = array();

	/**
	 * @var boolean TRUE for allow WHERE clause to update multiple objects
	 */
	protected $allowMultiple = FALSE;
	/**
	 * @var boolean TRUE for allow and empty WHERE clause
	 */
	protected $allowEmptyWhere = FALSE;

	/**
	 * @var string memoized SQL text of UPDATE query.
	 */
	protected $sqlText = NULL;

	/**
	 * Create a new UPDATE query
	 *
	 * If $obj parameter was retrieve by a SELECT query, you can reuse this query in $fromQuery for update the same object.
	 * @param Base $obj the object to update
	 * @param Query $fromQuery NULL or a query for initializing WHERE, JOINS and ORDER clauses.
	 */
	public function __construct(Base $obj, Query $fromQuery = NULL) {
		parent::__construct($obj);

		if ($fromQuery !== NULL) {
			$this->alias = $fromQuery->alias;
			$this->binds = $fromQuery->binds;
			$this->joins = $fromQuery->joins;
			$this->wheres = $fromQuery->wheres;
			$this->orders = $fromQuery->orders;
		}

		if (!$obj->isReadonly()) {
			foreach($obj->getModifiedFields() as $fieldName => $value) {
				$this->set($fieldName, SqlExpr::value($value));
			}
		}
	}

	/**
	 * Allow the query to have an "open" WHERE clause, without the clause on object id added automatically
	 * @param boolean $value Optional : TRUE. Use FALSE for forbidden again multiple update after an allow
	 */
	public function allowMultipleChange($value = TRUE) {
		$this->allowMultiple = $value;
	}

	/**
	 * Allow the query to have an empty WHERE clause, which will update all objects in table.
	 * @param boolean $value Optional : TRUE. Use FALSE for forbidden again empty where after an allow
	 */
	public function allowEmptyWhere($value = TRUE) {
		$this->allowEmptyWhere = $value;
	}

	/**
	 * Check if UPDATE query update only one object
	 * @return boolean TRUE if the update is not multiple and modify only 1 object
	 */
	public function isSimpleQuery() {
		return (!$this->allowMultiple && (count($this->wheres) === 1));
	}

	/**
	 * Force a SET clause like fieldName = fieldName + X
	 * @param string $fieldName fieldName to increment
	 * @param int $value default 1
	 */
	public function increment($fieldName, $value = 1) {
		$this->set($fieldName, SqlExpr::field($this->alias, $this->obj->getField($fieldName))->plus($value));
	}

	/**
	 * Force a SET clause like fieldName = fieldName - X
	 * @param string $fieldName fieldName to decrement
	 * @param int $value default 1
	 */
	public function decrement($fieldName, $value = 1) {
		$this->set($fieldName, SqlExpr::field($this->alias, $this->obj->getField($fieldName))->plus(- $value));
	}

	/**
	 * Force a SET clause like fieldName = sql expression
	 * @param string $fieldName fieldName to set
	 * @param mixed|SqlExpr $expr the value
	 */
	public function set($fieldName, $expr) {
		$field = $this->obj->getField($fieldName);

		if (!($expr instanceof SqlExpr)) {
			$expr = SqlExpr::value($expr);
		}
		$expr->asSetter($field);

		if (isset($this->bindsBySetField[$fieldName])) {
			if (!isset($this->bindsSource['SET'])) {
				$this->bindsSource['SET'] = array();
			}
			foreach($this->bindsBySetField[$fieldName] as $b) {
				unset($this->bindsSource['SET'][$b]);
				unset($this->binds[$b]);
			}
		}

		$this->sets[$fieldName] = $this->resolveFieldName('SET', $expr);

		$binds = $expr->getBinds();
		$this->bindsBySetField[$fieldName] = array_keys($binds);
	}

	/**
	 * {@inheritDoc}
	 * @param Pagination $pagination not used here
	 * @see \salt\Query::toSQL()
	 */
	public function toSQL(Pagination $pagination = NULL) {
		if ($this->sqlText !== NULL) {
			// without memoization, object id bind is added at every call and the 2+ execution failed.
			// we have to delay add of object id bind at the end for allow the call to allowMultipleUpdate() before
			return $this->sqlText;
		}

		$sql='UPDATE '.$this->resolveTable();
		$sql.=$this->buildJoinClause();

		$allSets=array();
		foreach($this->sets as $field => $value) {
			$allSets[]=$field.' = '.$value;
		}
		$sql.=' SET '.implode(', ', $allSets);

		if (!$this->allowMultiple && !$this->obj->isReadonly()) {
			$this->whereAndObject($this->obj);
		}
		if ((count($this->wheres) === 0) && !$this->allowEmptyWhere) {
			throw new SaltException('You don\'t have a WHERE clause on UPDATE. Please call allowEmptyWhere() if you really want to do this');
		}
		$sql.=$this->buildWhereClause();
		$sql.=$this->buildOrderClause();

		$this->sqlText = $sql;
		return $this->sqlText;
	}
}
