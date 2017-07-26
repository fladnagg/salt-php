<?php
/**
 * BaseQuery class
 *
 * @author     Richaud Julien "Fladnag"
 * @package    salt\sql\queries
 */
namespace salt;

/**
 * Base class for all queries
 */
abstract class BaseQuery extends SqlBindField {

	/**
	 * @var boolean FALSE for never execute the query */
	private $_salt_enabled = TRUE;

	/**
	 * @var Base the object the query is build for */
	protected $_salt_obj;

	/**
	 * Construct a new query
	 * @param Base $obj the object used for retrieve metadata.
	 */
	public function __construct(Base $obj) {
		$this->_salt_obj = $obj;
	}

	/**
	 * Retrieve the COUNT query
	 *
	 * Have to be overrided by child classes if needed
	 * @return CountQuery the SQL query for count query
	 * @throws SaltException if called on BaseQuery instance
	 */
	public function toCountQuery() {
		throw new SaltException(L::error_not_implemented);
	}

	/**
	 * Get the type of the field
	 * @param string $field the fieldName
	 * @return int FiedType type of the field
	 */
	private function getFieldType($field) {
		return $this->_salt_obj->MODEL()->$field->type;
	}

	/**
	 * Used for replace something that can be a value by a bind or a SQL text
	 * @param string $source caller of resolve : ClauseType or specific text
	 * @param mixed|SqlExpr $fieldNameOrValue a FieldName (string) or SqlExpr or potential value (int, string, array) if 3rd argument is provided
	 * @param mixed|SqlExpr $fieldOfValue a Field related to the value. Can be a SqlExpr or a string. Can be NULL if 2nd argument is a string fieldName
	 * @return string|string[] absolute field name (alias.fieldname) or string with bind values or array of bind values if 2nd argument is an array of values
	 */
	protected function resolveFieldName($source, $fieldNameOrValue, $fieldOfValue = NULL) {
		if ($fieldNameOrValue instanceof SqlBindField) {
			$this->linkBindsOf($fieldNameOrValue, $source);
			return $fieldNameOrValue->toSQL();
		}

		// handle values
		if ($fieldOfValue != NULL) {
			$value = $fieldNameOrValue;
			$type = ($fieldOfValue instanceof SqlExpr)?$fieldOfValue->getType():$this->getFieldType($fieldOfValue);
			if (is_array($value)) {
				$allBinds = array();
				foreach($value as $v) {
					$allBinds[] = $this->addBind($v, $type, $source);
				}
				return $allBinds;
			} else {
				$bind = $this->addBind($value, $type, $source);
				return $bind;
			}
		}
		if ($this->_salt_noAlias) {
			return self::escapeName($fieldNameOrValue);
		}
		return $this->_salt_alias.'.'.$fieldNameOrValue; // FIXME : date in timestamp format is not converted to DATE format in WHERE clause
// 		return SqlExpr::field($this->_salt_alias, $this->_salt_obj->getField($fieldNameOrValue))->toSQL();
	}

	/**
	 * If parameter is empty, the query will not be executed
	 *
	 * This can be used if the query contains an IN where condition with an empty array : Executing the query result in an exception,
	 * but we can use this function for return an empty result without exception : <pre>
	 *  $q->whereAnd('ids', 'IN', $values); // will produce a bad where clause : "ids IN ()" if $values is empty
	 * 	$q->disabledIfEmpty($values);
	 * 	$db->execQuery($q); // valid, will not execute query, so not throw exception
	 * </pre>
	 * @param mixed $list
	 */
	public function disableIfEmpty($list) {
		if (!is_array($list) || (count($list) === 0)) {
			$this->_salt_enabled = FALSE;
		}
	}

	/**
	 * Check if we have to execute the query
	 * @return boolean TRUE if the query can be executed, FALSE otherwise
	 */
	public function isEnabled() {
		return $this->_salt_enabled;
	}
}