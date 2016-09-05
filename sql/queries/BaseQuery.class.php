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
	 * @var Base internal instance of object the query is build for */
	protected $obj;

	/**
	 * Construct a new query
	 * @param Base $obj the object is used for retrieve metadata.
	 */
	public function __construct(Base $obj) {
		$this->obj = $obj;
	}

	/**
	 * Retrieve the COUNT query
	 *
	 * Have to be overrided by child classes if needed
	 * @return CountQuery the SQL query for count query
	 * @throws SaltException if called on BaseQuery instance
	 */
	public function toCountQuery() {
		throw new SaltException('Not implemented');
	}

	/**
	 * Get the type of the field
	 * @param string $field the fieldName
	 * @return int FiedType type of the field
	 */
	private function getFieldType($field) {
		return $this->obj->getField($field)->type;
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
		if ($this->noAlias) {
			return $fieldNameOrValue;
		}
		return $this->alias.'.'.$fieldNameOrValue;
	}

}