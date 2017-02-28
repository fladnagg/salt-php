<?php
/**
 * Query class
 *
 * @author     Richaud Julien "Fladnag"
 * @package    salt\sql\queries
 */
namespace salt;

/**
 * SELECT Query
 */
class Query extends BaseQuery {
	/**
	 * @var int table alias unique number */
	protected static $_salt_tableAliasNumber=0;
	/**
	 * @var string alias for the table */
	protected $_salt_alias = NULL;
	/**
	 * @var mixed[] list of fields to retrieve (SELECT)
	 * @content array of array(SqlExpr, alias)
	 */
	protected $_salt_fields = array();
	/**
	 * @var string[] list of where clause
	 * @content AND / OR are parts of $where elements */
	protected $_salt_wheres = array();
	/**
	 * @var mixed[] list of join clause
	 * @content <pre>array of string => array( // key is table alias for join
	 * 			'type' => string // join type 'INNER|OUTER|LEFT INNER|...'
	 * 			'table' => string // table name or inner select table of the joined object
	 * 			'on' => array of string // where clauses, like $wheres
	 * 		)</pre> */
	protected $_salt_joins = array();
	/**
	 * @var string[] : list of order by clause
	 * @content field ASC|DESC */
	protected $_salt_orders = array();
	/**
	 * @var string[] : list of field name for group by clause */
	protected $_salt_groups = array();

	/**
	 * @var boolean TRUE if table name does not have to use alias (DELETE query) */
	protected $_salt_noAlias = FALSE;
	
	/**
	 * Create a new SELECT query
	 * @param Base $obj object for query creation
	 * @param boolean $withField TRUE for load all the fields
	 */
	public function __construct(Base $obj, $withField=FALSE) {
		parent::__construct($obj);
		
		if ($obj instanceof Dual) {
			$this->_salt_noAlias = TRUE;
		} else {
			$this->_salt_alias = 't'.self::$_salt_tableAliasNumber++;
		}

		if ($withField) {
			foreach($obj->MODEL()->getFields() as $field) {
				$this->selectField($field->name);
			}
		}
	}

	
	/**
	 * Get a sub query of this query
	 * @return Query a query on the same table with the same alias for using in whereAndQuery or whereOrQuery
	 */
	public function getSubQuery() {
		$subQuery = $this->_salt_obj->query();
		$subQuery->_salt_alias = $this->_salt_alias;
		return $subQuery;
	}

	/**
	 * Get the class of the object used to construct to query
	 * @return string the class of object the query work on
	 */
	public function getBindingClass() {
		return get_class($this->_salt_obj);
	}

	/**
	 * Add a list of fields in the SELECT clause
	 * @param string[] $fields list of fields name to add in select clause
	 */
	public function selectFields(array $fields) {
		foreach($fields as $f) {
			$this->selectField($f);
		}
	}

	/**
	 * Add a field int the SELECT clause
	 * @param string|SqlExpr $field a field name to add in select clause. A SqlExpr can also be used if it's a SqlExpr of type FIELD
	 * @param string $as alias if needed
	 */
	public function selectField($field, $as = NULL) {

		if ($field instanceof SqlExpr) {
			$sqlExpr = $field;
			$field = $sqlExpr->getFieldName();
			if ($field === NULL) {
				throw new SaltException('Only SqlExpr of type Field can be used here');
			}
		} else if (!is_string($field)) {
			throw new SaltException('A field name is expected');
		} else {
			$sqlExpr = $this->$field;
		}

		if ($sqlExpr->getType() === FieldType::DATE) {
			$sqlExpr->asTimestamp();
		}

		return $this->select($sqlExpr, ($as === NULL)?$field:$as);
	}

	/**
	 * Add a SqlExpr in the SELECT clause
	 * @param SqlExpr $expr expression to add in select clause, of any type
	 * @param string $as alias for the expression
	 * @throws SaltException
	 */
	public function select(SqlExpr $expr, $as = NULL) {

		$aliases = $expr->getAllUsedTableAlias();
		foreach($aliases as $a) {
			if (($this->_salt_alias !== $a) && !isset($this->_salt_joins[$a])) {
				throw new SaltException('No query with alias ['.$a.'] in join clauses. We cannot add the field '.$expr->toSQL().' in select clause');
			}
		}

		if ($as === NULL) {
			$as = $expr->getFieldName();
		}

		if ($as === NULL) {
			throw new SaltException('Please provide an alias for field '.$expr->toSQL());
		}

		$this->_salt_fields[]=array($expr, $as);

		return $expr;
	}

	/**
	 * Add a field in order clause, with ASC
	 * @param string|SqlExpr $fieldOrExpr the field to order by ASC
	 */
	public function orderAsc($fieldOrExpr) {
		$this->_salt_orders[]=$this->resolveFieldName(ClauseType::ORDER, $fieldOrExpr).' ASC';
	}

	/**
	 * Add a field in order clause, with DESC
	 * @param string|SqlExpr $fieldOrExpr the field to order by DESC
	 */
	 public function orderDesc($fieldOrExpr) {
		$this->_salt_orders[]=$this->resolveFieldName(ClauseType::ORDER, $fieldOrExpr).' DESC';
	}

	/**
	 * Add a where clause AND EXISTS (SELECT 1 $otherQuery)
	 * @param Query $otherQuery
	 * @param boolean $exists if FALSE : AND NOT EXISTS (SELECT 1 $otherQuery)
	 */
	public function whereAndExists(Query $otherQuery, $exists = TRUE) {
		$this->addWhereExists('AND', $otherQuery, $exists);
	}

	/**
	 * Add a where clause OR EXISTS (SELECT 1 $otherQuery)
	 * @param Query $otherQuery
	 * @param boolean $exists if FALSE : OR NOT EXISTS (SELECT 1 $otherQuery)
	 */
	public function whereOrExists(Query $otherQuery, $exists = TRUE) {
		$this->addWhereExists('OR', $otherQuery, $exists);
	}

	/**
	 * Add a where clause EXISTS (SELECT 1 $otherQuery)
	 * @param string $type AND|OR
	 * @param Query $otherQuery
	 * @param boolean $exists if FALSE : NOT EXISTS (SELECT 1 $otherQuery)
	 */
	private function addWhereExists($type, Query $otherQuery, $exists = TRUE) {
		$this->addWhereClause($type, ($exists?'':'NOT ').'EXISTS (SELECT 1 '.$otherQuery->toBaseSQL().')');

		$this->linkBindsOf($otherQuery, ClauseType::WHERE, ClauseType::JOIN);
		$this->linkBindsOf($otherQuery, ClauseType::WHERE, ClauseType::WHERE);
		$this->linkBindsOf($otherQuery, ClauseType::WHERE, ClauseType::GROUP);
	}

	/**
	 * Add a WHERE clause on every object IDs (registerId)
	 * @param Base|Base[] $objects Base object or array of Base objects. Objects have to be of the same type than the query
	 */
	public function whereAndObject($objects) {
		$this->addWhereObject('AND', $objects);
	}

	/**
	 * Add a WHERE clause on every object IDs (registerId)
	 * @param Base|Base[] $objects Base object or array of Base objects. Objects have to be of the same type than the query
	 */
	public function whereOrObject($objects) {
		$this->addWhereObject('OR', $objects);
	}

	/**
	 * Add a WHERE clause on every object IDs (registerId)
	 * @param string $type AND|OR
	 * @param Base|Base[] $objects Base object or array of Base objects. Objects have to be of the same type than the query
	 */
	private function addWhereObject($type, $objects) {
		if (!is_array($objects)) {
			$objects = array($objects);
		}

		$idField = $this->_salt_obj->MODEL()->getIdFieldName();
		$class = get_class($this->_salt_obj);

		$allIds = array();
		foreach($objects as $obj) {
			if (!$obj instanceof $class) {
				throw new SaltException('Cannot use an object of type '.get_class($obj).' in a query on '.$class.' objects');
			}
			$allIds[] = $obj->$idField;
		}

		if (count($allIds) === 1)  {
			$this->addWhere($type, $idField, '=', first($allIds));
		} else {
			$this->addWhere($type, $idField, 'IN', $allIds);
		}
	}

	/**
	 * Add a sub query where clause : AND ( $subQuery where clauses )
	 * @param Query $subQuery have to be a query retrieved by getSubQuery()
	 */
	public function whereAndQuery(Query $subQuery) {
		$this->addWhereQuery('AND', $subQuery);
	}

	/**
	 * Add a sub query where clause : OR ( $subQuery where clauses )
	 * @param Query $subQuery have to be a query retrieved by getSubQuery()
	 */
	public function whereOrQuery(Query $subQuery) {
		$this->addWhereQuery('OR', $subQuery);
	}

	/**
	 * Add a sub query where clause : OR ( $subQuery where clauses )
	 * @param string $type OR|AND
	 * @param Query $subQuery have to be a query retrieved by getSubQuery()
	 */
	private function addWhereQuery($type, Query $subQuery) {
		if ($subQuery->_salt_alias == $this->_salt_alias) {
			$this->addWhereClause($type, '('.implode(' ',$subQuery->_salt_wheres).')');
			$this->linkBindsOf($subQuery, ClauseType::WHERE, ClauseType::WHERE);
		} else {
			throw new SaltException('Cannot use a subquery on a different table of the main query');
		}
	}

	/**
	 * Add a where clause from SQL text
	 * @param string $type type of where clause : AND|OR
	 * @param string $whereClause where clause
	 */
	private function addWhereClause($type, $whereClause) {
		if (is_array($whereClause)) {
			$whereClause=implode(' ', $whereClause);
		}
		if (count($this->_salt_wheres)>0) {
			$this->_salt_wheres[]=$type.' '.$whereClause;
		} else {
			$this->_salt_wheres[]=$whereClause;
		}
	}

	/**
	 * Get a selected expression added in query by an alias for reuse it
	 *
	 * Example : <pre>$query->select(SqlExpr::_count('*'), 'nb');
	 * $query->orderDesc($query->getSelect('nb'));</pre>
	 * @param string $alias name of an alias
	 * @return SqlExpr the expression for this alias
	 */
	public function getSelect($alias) {
		foreach($this->_salt_fields as $select) {
			list($expr, $aliasName) = $select;
			if ($alias === $aliasName) {
				return SqlExpr::text(self::escapeName($alias));
			}
		}
		throw new SaltException('Cannot find the alias ['.$alias.'] in the query');
	}

	/**
	 * Add a where clause with OR
	 * @param string|SqlExpr $fieldOrExpr fieldName or SqlExpr
	 * @param string $operator operator to use : '=', 'LIKE', 'IN', etc...
	 * @param mixed|SqlExp $valueOrExpr value or SqlExpr
	 */
	public function whereOr($fieldOrExpr, $operator, $valueOrExpr) {
		$this->addWhere('OR', $fieldOrExpr, $operator, $valueOrExpr);
	}

	/**
	 * Add a where clause with AND
	 * @param string|SqlExpr $fieldOrExpr fieldName or SqlExpr
	 * @param string $operator operator to use : '=', 'LIKE', 'IN', etc...
	 * @param mixed|SqlExp $valueOrExpr value or SqlExpr
	 */
	public function whereAnd($fieldOrExpr, $operator, $valueOrExpr) {
		$this->addWhere('AND', $fieldOrExpr, $operator, $valueOrExpr);
	}

	/**
	 * Add a where clause
	 * @param string $addType AND|OR
	 * @param string|SqlExpr $fieldOrExpr fieldName or SqlExpr
	 * @param string $operator operator to use : '=', 'LIKE', 'IN', etc...
	 * @param string|SqlExpr $valueOrExpr value or SqlExpr
	 */
	private function addWhere($addType, $fieldOrExpr, $operator, $valueOrExpr) {
		$absoluteField = $this->resolveFieldName(ClauseType::WHERE, $fieldOrExpr);

		// a value can be a field of another query
		$valueOrExpr = $this->resolveFieldName(ClauseType::WHERE, $valueOrExpr, $fieldOrExpr);
		if (is_array($valueOrExpr)) {
			$valueOrExpr = '('.implode(', ', $valueOrExpr).')';
		}
		$this->addWhereClause($addType, $absoluteField.' '.$operator.' '.$valueOrExpr);
	}

	/**
	 * Add a field in GROUP BY clause
	 * @param string|SqlExpr $fieldOrExpr field name to group by
	 */
	public function groupBy($fieldOrExpr) {
		$absoluteField = $this->resolveFieldName(ClauseType::GROUP, $fieldOrExpr);
		$this->_salt_groups[]=$absoluteField;
	}

	/**
	 * Add a join with a select : INNER JOIN (SELECT ... ) tX ON ...
	 * @param Query $other the other query to join with
	 * @param string|SqlExpr $fieldOrExpr field of ON clause
	 * @param string $operator operator of ON clause
	 * @param mixed|SqlExpr $valueOrExpr value of ON clause
	 * @param string $type type of join: 'INNER', 'OUTER', etc...
	 * @throws SaltException if this join already exists
	 */
	public function joinSelect(Query $other, $fieldOrExpr, $operator, $valueOrExpr, $type = 'INNER') {
		$this->addJoin($other, $fieldOrExpr, $operator, $valueOrExpr, $type, '( '.$other->toSQL().' )', FALSE);
	}

	/**
	 * Add a classic join : INNER JOIN table tX ON ...
	 * @param Query $other the other query to join with
	 * @param string|SqlExpr $fieldOrExpr field of ON clause
	 * @param string $operator operator of ON clause
	 * @param mixed|SqlExpr $valueOrExpr value of ON clause
	 * @param string $type type of join: 'INNER', 'OUTER', etc...
	 * @throws SaltException if this join already exists
	 */
	public function join(Query $other, $fieldOrExpr, $operator, $valueOrExpr, $type = 'INNER') {
		$this->addJoin($other, $fieldOrExpr, $operator, $valueOrExpr, $type, $other->_salt_obj->MODEL()->getTableName());
	}

	/**
	 * Add a join clause
	 * @param Query $other the other query to join with
	 * @param string|SqlExpr $fieldOrExpr field of ON clause
	 * @param string $operator operator of ON clause
	 * @param mixed|SqlExpr $valueOrExpr value of ON clause
	 * @param string $type type of join: 'INNER', 'OUTER', etc...
	 * @param string $table object to join with : table name of subquery
	 * @param boolean $withOtherDatas TRUE if we have to add fields, where, group by, order clause to main query
	 * @throws SaltException if this join already exists
	 */
	private function addJoin(Query $other, $fieldOrExpr, $operator, $valueOrExpr, $type = 'INNER', $table, $withOtherDatas=TRUE) {
		if (isset($this->_salt_joins[$other->_salt_alias])) {
			throw new SaltException('A join with the same alias ['.$other->_salt_alias.'] already exists.');
		}

		$this->_salt_joins[$other->_salt_alias]=array(
			'type'=>strtoupper($type),
			'table'=>$table,
			'on'=>array(),
		);

		$absoluteField = $this->resolveFieldName(ClauseType::JOIN, $fieldOrExpr);

		$valueOrExpr = $this->resolveFieldName(ClauseType::JOIN, $valueOrExpr, $fieldOrExpr);
		if (is_array($valueOrExpr)) {
			$valueOrExpr = '('.implode(', ', $valueOrExpr).')';
		}
		$clause = $absoluteField.' '.$operator.' '.$valueOrExpr;

		$this->addJoinOnClause($other, 'AND', $clause);

		if ($withOtherDatas) {
			// Merge of others parameters
			if (count($other->_salt_fields) > 0) {
				$this->_salt_fields=array_merge($this->_salt_fields, $other->_salt_fields);
				$this->linkBindsOf($other, ClauseType::JOIN, ClauseType::SELECT);
			}
			if (count($other->_salt_wheres) > 0) {
				//$this->addWhereClause('AND', $other->wheres);
				$this->addJoinOnClause($other, 'AND', $other->_salt_wheres);
				$this->linkBindsOf($other, ClauseType::JOIN, ClauseType::WHERE);
			}
			if (count($other->_salt_groups) > 0) {
				$this->_salt_groups=array_merge($this->_salt_groups, $other->_salt_groups);
				$this->linkBindsOf($other, ClauseType::JOIN, ClauseType::GROUP);
			}
			if (count($other->_salt_orders) > 0) {
				$this->_salt_orders=array_merge($this->_salt_orders, $other->_salt_orders);
				$this->linkBindsOf($other, ClauseType::JOIN, ClauseType::ORDER);
			}
		} else {
			// join (Select ...) : link ALL binds, because we include full query text
			$this->linkBindsOf($other, ClauseType::JOIN);
		}
	}

	/**
	 * add an ON clause to an existing join
	 * @param Query $other the other query for retrieve the join clause
	 * @param string|SqlExpr $fieldOrExpr field of ON clause
	 * @param string $operator operator of ON clause
	 * @param mixed|SqlExpr $valueOrExpr value of ON clause
	 * @throws SaltException if join don't exists
	 */
	public function joinOnAnd(Query $other, $fieldOrExpr, $operator, $valueOrExpr) {
		$this->addJoinOn('AND', $other, $fieldOrExpr, $operator, $valueOrExpr);
	}

	/**
	 * add an ON clause to an existing join
	 * @param Query $other the other query for retrieve the join clause
	 * @param string|SqlExpr $fieldOrExpr field of ON clause
	 * @param string $operator operator of ON clause
	 * @param mixed|SqlExpr $valueOrExpr value of ON clause
	 * @throws SaltException if join don't exists
	 */
	public function joinOnOr(Query $other, $fieldOrExpr, $operator, $valueOrExpr) {
		$this->addJoinOn('OR', $other, $fieldOrExpr, $operator, $valueOrExpr);
	}

	/**
	 * add an query to an ON clause of an existing join
	 * @param Query $other the other query for retrieve the join clause
	 * @param Query $whereQuery the query to add in ON clause
	 */
	public function joinOnAndQuery(Query $other, Query $whereQuery) {
		$this->addJoinOnQuery('AND', $other, $whereQuery);
	}

	/**
	 * add an query to an ON clause of an existing join
	 * @param Query $other the other query for retrieve the join clause
	 * @param Query $whereQuery the query to add in ON clause
	 */
	public function joinOnOrQuery(Query $other, Query $whereQuery) {
		$this->addJoinOnQuery('OR', $other, $whereQuery);
	}

	/**
	 * add an query to an ON clause of an existing join
	 * @param string $type AND|OR
	 * @param Query $other the other query for retrieve the join clause
	 * @param Query $whereQuery the query to add in ON clause
	 */
	private function addJoinOnQuery($type, Query $other, Query $whereQuery) {
		//$this->joins[$other->alias]['on'][]=' '.$type.' ('.implode(' ', $whereQuery->wheres).')';
		$this->addJoinOnClause($other, $type, '('.implode(' ',$whereQuery->_salt_wheres).')');
		$this->linkBindsOf($other, ClauseType::JOIN, ClauseType::WHERE);
	}

	/**
	 * Add a ON clause from SQL text
	 * @param Query $other the other query for retrieve the join clause
	 * @param string $type type of ON clause : AND|OR
	 * @param string $onClause ON clause
	 */
	private function addJoinOnClause($other, $type, $onClause) {
		if (is_array($onClause)) {
			$onClause=implode(' ', $onClause);
		}
		if (count($this->_salt_joins[$other->_salt_alias]['on'])>0) {
			$this->_salt_joins[$other->_salt_alias]['on'][]=$type.' '.$onClause;
		} else {
			$this->_salt_joins[$other->_salt_alias]['on'][]=$onClause;
		}
	}
	
	/**
	 * add an ON clause to an existing join
	 * @param string $type AND|OR
	 * @param Query $other the other query for retrieve the join clause
	 * @param string|SqlExpr $fieldOrExpr field of ON clause
	 * @param string $operator operator of ON clause
	 * @param mixed|SqlExpr $valueOrExpr value of ON clause
	 * @throws SaltException if join don't exists
	 */
	private function addJoinOn($type, Query $other, $fieldOrExpr, $operator, $valueOrExpr) {
		if (!array_key_exists($other->_salt_alias, $this->_salt_joins)) {
			throw new SaltException('No join found for alias '.$other->_salt_alias.'.');
		}

		$absoluteField = $this->resolveFieldName(ClauseType::JOIN, $fieldOrExpr);

		$valueOrExpr = $this->resolveFieldName(ClauseType::JOIN, $valueOrExpr, $fieldOrExpr);
		if (is_array($valueOrExpr)) {
			$valueOrExpr = '('.implode(', ', $valueOrExpr).')';
		}
		$clause = $absoluteField.' '.$operator.' '.$valueOrExpr;

		$this->addJoinOnClause($other, $type, $clause);
// 		$this->joins[$other->alias]['on'][]=' '.$type.' '.$clause;
	}

	/**
	 * Return the list of field or alias returned by SELECT clause
	 * @return string[] array of all fields or aliases selected by the query
	 */
	public function getSelectFields() {
		$f = array();
		foreach($this->_salt_fields as $field) {
			$f[]=last($field);
		}
		return $f;
	}

	/**
	 * Initialize DBResult. Called during query execution.
	 * @param Pagination $pagination pagination object to update
	 * @param string $count total number of elements. Set on pagination
	 * @return DBResult with selected columns populated
	 */
	public function initResults(Pagination $pagination = NULL, $count = NULL) {
		$r = new DBResult();

		if (($pagination != NULL) && ($count !== NULL)) {
			$pagination->setCount($count);
		}
		$r->columns = $this->getSelectFields();

		return $r;
	}

	/**
	 * get the table name for the query
	 * @return string table name, with or without alias (depends on noAlias)
	 */
	protected function resolveTable() {
		if ($this->_salt_noAlias) {
			return $this->_salt_obj->MODEL()->getTableName();
		}
		return $this->_salt_obj->MODEL()->getTableName().' '.$this->_salt_alias;
	}

	/**
	 * get the SQL text for FROM, JOIN, WHERE, GROUP BY parts
	 * @return string SQL text query with common parts only
	 */
	protected function toBaseSQL() {
		$sql=' FROM '.$this->resolveTable();

		$sql.=$this->buildJoinClause();
		$sql.=$this->buildWhereClause();

		$sql.=$this->buildGroupClause();

		return $sql;
	}

	/**
	 * get the SQL text for ORDER BY part
	 * @return string SQL text for ORDER BY part
	 */
	protected function buildOrderClause() {
		$sql='';
		if (count($this->_salt_orders)>0) {
			$sql.=' ORDER BY '.implode(', ', $this->_salt_orders);
		}
		return $sql;
	}

	/**
	 * get the SQL text for GROUP BY part
	 * @return string SQL text for GROUP BY part
	 */
	protected function buildGroupClause() {
		$sql = '';
		if (count($this->_salt_groups) > 0) {
			$sql.=' GROUP BY '.implode(', ', $this->_salt_groups);
		}
		return $sql;
	}

	/**
	 * get the SQL text for JOIN part
	 * @return string SQL text query with JOIN part
	 */
	protected function buildJoinClause() {
		$sql='';
		if (count($this->_salt_joins)>0) {
			foreach($this->_salt_joins as $alias => $join) {
				$sql.=' '.$join['type'].' JOIN '.$join['table'].' '.$alias.' ON '.implode(' ', $join['on']);
			}
		}
		return $sql;
	}

	/**
	 * get the SQL text for WHERE part
	 * @return string SQL text query with WHERE part
	 */
	protected function buildWhereClause() {
		$sql='';
		if (count($this->_salt_wheres)>0) {
			$sql.=' WHERE '.implode(' ', $this->_salt_wheres);
		}
		return $sql;

	}

	/**
	 * get the COUNT query
	 * @return CountQuery the query for count objects of this query
	 */
	public function toCountQuery() {
		$sql='SELECT COUNT(*) as nb';

		$selectClause = $this->buildSelectClause();
		$hasDistinct = (strpos(strtolower($selectClause), 'distinct') !== FALSE);

		$excludeBinds = array();
		if (($hasDistinct) || (count($this->_salt_groups) > 0)) {
			// if select clause have a distinct... we have to count the complete subquery...
 			// with groups, count() also need to be executed on a sub select query...
 			// @see http://stackoverflow.com/questions/364825/getting-the-number-of-rows-with-a-group-by-query
			$sql.=' FROM ( SELECT '.$selectClause.$this->toBaseSQL().') c';
		} else {
			$excludeBinds = $this->getBinds(ClauseType::SELECT);
			// more simple query without select clause
			$sql.=$this->toBaseSQL();
		}

		$binds = $this->getBinds();
		$binds = array_diff_key($binds, $excludeBinds);

		return new CountQuery($sql, $binds);
	}

	/**
	 * get the SQL text for the SELECT clause
	 * @return string SQL text of all retrieved fields
	 */
	protected function buildSelectClause() {
		$sql='';
		$fields=array();
		foreach($this->_salt_fields as $data) {
			/** @var SqlExpr $expr */
			list($expr, $alias) = $data;

			$fields[]=$expr->toSQL().' as '.self::escapeName($alias);
			$this->linkBindsOf($expr, ClauseType::SELECT);
		}
		$sql.=implode(', ', $fields);
		return $sql;
	}

	/**
	 * {@inheritDoc}
	 * @see \salt\SqlBindField::buildSQL()
	 */
	protected function buildSQL() {
		$sql='SELECT ';
		
		$sql.=$this->buildSelectClause();
		$sql.=$this->toBaseSQL();
		$sql.=$this->buildOrderClause();
		
		return $sql;
	}
	
	/**
	 * Retrieve a field as an SqlExpr for reuse it in another query / SqlExpr
	 * @param string $fieldName name of the field
	 * @return SqlExpr the field as an SqlExpr
	 */
	public function __get($fieldName) {
		// do NOT register field on object for avoid next access because the SqlExpr return object
		// is modifiable outside but this method always have to return a clean SqlExpr
		if ($this->_salt_noAlias) {
			return SqlExpr::field(NULL, $this->_salt_obj->MODEL()->$fieldName);
		} else {
			return SqlExpr::field($this->_salt_alias, $this->_salt_obj->MODEL()->$fieldName);
		}
	}
}
