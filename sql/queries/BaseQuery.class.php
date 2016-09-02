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
	 * @var string[][] binds by source (SELECT, WHERE, GROUPBY, LIMIT, etc...)
	 * @content array of source => array(binds)
	 */
	protected $bindsSource = array();

	/**
	 * Construct a new query
	 * @param Base $obj the object is used for retrieve metadata.
	 */
	public function __construct(Base $obj) {
		$this->obj = $obj;
	}

	/**
	 * Retrieve the SQL text of the query
	 * @param Pagination $pagination the object for handle paging
	 * @return string the SQL text of the query with bind placeholders
	 */
	abstract public function toSQL(Pagination $pagination = NULL);

	/**
	 * Retrieve the SQL text for COUNT query
	 *
	 * Have to be overrided by child classes if needed
	 * @return string the SQL text for count query
	 * @throws SaltException if called
	 */
	public function toCountSQL() {
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
	 * @param string $source caller of resolve : SELECT, WHERE, etc...
	 * @param mixed|SqlExpr $fieldNameOrValue a FieldName (string) or SqlExpr or potential value (int, string, array) if 3rd argument is provided
	 * @param mixed|SqlExpr $fieldOfValue a Field related to the value. Can be a SqlExpr or a string. Can be NULL if 2nd argument is a string fieldName
	 * @return string|string[] absolute field name (alias.fieldname) or string with bind values or array of bind values if 2nd argument is an array of values
	 */
	protected function resolveFieldName($source, $fieldNameOrValue, $fieldOfValue = NULL) {
		if ($fieldNameOrValue instanceof SqlExpr) {
			if (count($fieldNameOrValue->getBinds()) > 0) {
				$this->mergeBinds($fieldNameOrValue, $source);
			}
			return $fieldNameOrValue->toSQL();
		}

		// handle values
		if ($fieldOfValue != NULL) {
			$value = $fieldNameOrValue;
			$type = ($fieldOfValue instanceof SqlExpr)?$fieldOfValue->getType():$this->getFieldType($fieldOfValue);
			if (is_array($value)) {
				$allBinds = array();
				foreach($value as $v) {
					$allBinds[] = $this->addBind($v, $type);
				}
				$this->addBindsSource($source, $allBinds);
				return $allBinds;
			} else {
				$bind = $this->addBind($value, $type);
				$this->addBindsSource($source, $bind);
				return $bind;
			}
		}
		if ($this->noAlias) {
			return $fieldNameOrValue;
		}
		return $this->alias.'.'.$fieldNameOrValue;
	}

	/**
	 * Link one or more binds to a source
	 * @param string $source caller of bind (SELECT, WHERE, GROUPBY, etc...)
	 * @param string|string[] $binds bind name or list of binds name
	 */
	protected function addBindsSource($source, $binds) {
		if (!is_array($binds)) {
			$binds = array($binds);
		}
		if (!isset($this->bindsSource[$source])) {
			$this->bindsSource[$source] = array();
		}
		$this->bindsSource[$source] = array_merge($this->bindsSource[$source], $binds);
	}

	/**
	 * Retrieve binds linked to one or more sources
	 * @param string ... $sources function take unlimited source bind parameters
	 * @return string[][] binds registered with theses sources
	 */
	protected function getBindsBySource($sources) {
		$sources = func_get_args();

		$binds=array();
		foreach($sources as $source) {
			if (isset($this->bindsSource[$source])) {
				// we keep only keys that exists in source
				$bindKeys = array_flip($this->bindsSource[$source]);
				$binds = array_merge($binds, array_intersect_key($this->binds, $bindKeys));
			}
		}
		return $binds;
	}

	/**
	 * Merge binds of another query with current object
	 * @param SqlBindField $other the binds to add in current object
	 * @param string $source Optional, restrict merge to this source type only
	 */
	protected function mergeBinds(SqlBindField $other, $source = NULL) {
		if (!($other instanceof BaseQuery)) {
			$otherBinds = $other->getBinds();
			$otherBindsSource = array($source => array_keys($otherBinds));
		} else if ($source !== NULL) {
			$otherBinds = $other->getBindsBySource($source);
			$otherBindsSource = $other->bindsSource;
		} else {
			$otherBinds = $other->getBinds();
			$otherBindsSource = $other->bindsSource;
		}

		$this->binds = array_merge($this->binds, $otherBinds);

		foreach($otherBindsSource as $otherSource => $otherBinds) {
			if (($source === NULL) || ($source === $otherSource)) {
				$this->addBindsSource($otherSource, $otherBinds);
			}
		}
	}

	/**
	 * Set or replace a bind linked to a specific source
	 *
	 * Only work with 1 bind by source name
	 * @param string $source source of bind
	 * @param mixed $value a value for bind
	 * @param int $type FieldType
	 * @return string the bind added or replaced
	 */
	protected function setOrRemplaceBind($source, $value, $type) {
		$bind = NULL;
		if (isset($this->bindsSource[$source])) {
			if (count($this->bindsSource[$source]) > 1) {
				throw new SaltException('Cannot replace multiple binds source');
			}
			$bind = first($this->bindsSource[$source]);
			$this->binds[$bind]['value'] = $value;
		} else {
			$bind = $this->addBind($value, $type);
			$this->addBindsSource($source, array($bind));
		}
		return $bind;
	}

}