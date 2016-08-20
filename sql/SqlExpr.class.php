<?php
/**
 * SqlBindField class
 *
 * @author     Richaud Julien "Fladnag"
 * @package    salt\sql
 */
namespace salt;

use \Exception;

/**
 * Construct a complex SQL expression
 */
class SqlExpr extends SqlBindField {

	/**
	 * type of SqlExpr : a function */
	const FUNC=0;
	/**
	 * type of SqlExpr : a value */
	const VALUE=1;
	/**
	 * type of SqlExpr : a field */
	const FIELD=2;

	/**
	 * template replaced by the main source of SqlExpr */
	const TEMPLATE_MAIN="\1:main\2";
	/**
	 * template replaced by each parameters in order */
	const TEMPLATE_PARAM="\3?\4";

	/**
	 * @var int type of the return of SqlExpr : FieldType::* */
	private $type = NULL;
	/**
	 * @var string format of the parameter of the SqlExpr, when it's a DATE
	 */
	private $dateFormat = NULL;
	/**
	 * @var boolean TRUE if the date SqlExpr have to be converted in TIMESTAMP, FALSE for MySQL DATE. Not used if the SqlExpr is not a date
	 */
	private $timestamp = FALSE;
	/**
	 * @var string[] the template to apply
	 * @content array of (template, param1, param2, ...)
	 */
	private $template = array();
	/**
	 * @var Field field to use when SqlExpr is in a SET clause
	 */
	private $setter = NULL;
	/**
	 * @var mixed source of SqlExpr, can be a string, int, array, etc...
	 */
	private $data = NULL;

	/**
	 * @var int type of SqlExpr : FUNC|VALUE|FIELD */
	private $objectType = NULL;

	/**
	 * @var string memoized value of toSql() function
	 */
	private $sqlText = NULL;

	/**
	 * create a new SqlExr. Use static function for that
	 * @param int $objectType type of SqlExpr : FUNC|VALUE|FIELD
	 * @param mixed $data source of SqlExpr, can be a scalar value or an array
	 */
	protected function __construct($objectType, $data) {
		$this->objectType = $objectType;
		$this->data = $data;
	}

	/**
	 * Create a new SqlExpr for an SQL function
	 * @param string $name name of the function
	 * @param SqlExpr ... $args list of function arguments.
	 * @return SqlExpr
	 */
	public static function func($name, SqlExpr $args = NULL) {
		return new SqlExpr(self::FUNC, func_get_args());
	}

	/**
	 * Create a new SqlExpr for a value
	 * @param mixed $value value to transform in SqlExpr. Can be NULL
	 * @return SqlExpr
	 */
	public static function value($value) {
		return new SqlExpr(self::VALUE, $value);
	}

	/**
	 * Create a new SqlExpr for a field
	 *
	 * Do not use this method, but Query::getField($fieldName) instead
	 * @param string $alias alias of the table of the field
	 * @param Field $field field
	 * @return SqlExpr
	 */
	public static function field($alias, Field $field) {
		$sql = new SqlExpr(self::FIELD, array($alias, $field));
		$sql->type = $field->type;
		$sql->dateFormat = $field->format;
		return $sql;
	}

	/**
	 * Set the SqlExpr type to NUMBER
	 * @return SqlExpr current object
	 */
	public function asNumber() {
		$this->type = FieldType::NUMBER;
		return $this;
	}

	/**
	 * Set the SqlExpr type to TEXT
	 * @return SqlExpr current object
	 */
	public function asText() {
		$this->type = FieldType::TEXT;
		return $this;
	}

	/**
	 * Set the SqlExpr type to BOOLEAN
	 * @return SqlExpr current object
	 */
	public function asBoolean() {
		$this->type = FieldType::BOOLEAN;
		return $this;
	}

	/**
	 * Set the SqlExpr type to DATE
	 * @param string $format format of the source, used for convert to date.
	 * @return SqlExpr current object
	 * @throws Exception
	 */
	public function asDate($format = NULL) {
		$this->timestamp = FALSE;
		$this->type = FieldType::DATE;
		if ($format !== NULL) {
			$this->dateFormat = $format;
		}
		if ($this->dateFormat === NULL) {
			throw new Exception('You have to set a format for a date expression');
		}
		return $this;
	}

	/**
	 * Set the SqlExpr type to DATE, as TIMESTAMP format
	 * @param string $format format of the source, used for convert to date.
	 * @return SqlExpr current object
	 * @throws Exception
	 */
	public function asTimestamp($format = NULL) {
		$this->timestamp = TRUE;
		$this->type = FieldType::DATE;
		if ($format !== NULL) {
			$this->dateFormat = $format;
		}
		if ($this->dateFormat === NULL) {
			throw new Exception('You have to set a format for a timestamp expression');
		}
		return $this;
	}

	/**
	 * Set the SqlExpr type to the field type
	 * @param Field $field the field to use for configure SqlExpr
	 * @return SqlExpr current object
	 */
	public function asSetter(Field $field) {
		$this->setter = $field;

		if ($this->type === NULL) {
			$this->type = $field->type;
		}

		// a setter on a date with unknown format has a timestamp value
		if ($field->type === FieldType::DATE) {
			$this->type = FieldType::DATE;

			if ($this->dateFormat === NULL) {
				$this->dateFormat = SqlDateFormat::RAW_TIMESTAMP;
				$this->asTimestamp();
			}
		}

		return $this;
	}

	/**
	 * Add DISTINCT before SqlExpr
	 * @return SqlExpr current object
	 */
	public function distinct() {
		return $this->template('DISTINCT '.self::TEMPLATE_MAIN);
	}

	/**
	 * Add NOT before SqlExpr
	 * @return SqlExpr current object
	 */
	public function not() {
		return $this->template('NOT '.self::TEMPLATE_MAIN);
	}

	/**
	 * Use a template to format SqlExpr
	 * @param string $template template with TEMPLATE_MAIN and TEMPLATE_PARAM if necessary
	 * @param SqlExpr[] $args list of parameters
	 * @return SqlExpr current object
	 */
	public function template($template, $args = array()) {
		$this->template = array($template, $args);
		return $this;
	}

	/**
	 * Increment or decrement a field
	 * @param int $value value to increment (if positive) or decrement (if negative)
	 * @return SqlExpr current object
	 */
	public function plus($value = 1) {
		if ($value >= 0) {
			$this->template(self::TEMPLATE_MAIN.' + '.self::TEMPLATE_PARAM, SqlExpr::value($value));
		} else {
			$this->template(self::TEMPLATE_MAIN.' - '.self::TEMPLATE_PARAM, SqlExpr::value(abs($value)));
		}
		return $this;
	}

	/**
	 * Retrieve all used table alias in SqlExpr
	 * @return string[] all table alias
	 */
	public function getAllUsedTableAlias() {
		$result = array();
		if ($this->objectType === self::FIELD) {
			$alias = first($this->data);
			$result[$alias]=$alias;
		} else if ($this->objectType === self::FUNC) {
			$args = $this->data;
			array_shift($args);
			foreach($args as $p) {
				$result = array_merge($result, $p->getAllUsedTableAlias());
			}
		}
		return $result;
	}

	/**
	 * Retrieve the field name of a FIELD type SqlExpr
	 * @return string the field name if SqlExpr is a FIELD expr, NULL otherwise
	 */
	public function getFieldName() {
		if ($this->objectType === self::FIELD) {
			return last($this->data)->name;
		}
		return NULL;
	}

	/**
	 * Get the type of the SqlExpr
	 * @return int FieldType the type of the SqlExpr
	 */
	public function getType() {
		return $this->type;
	}

	/**
	 * {@inheritDoc}
	 * @see \salt\SqlBindField::getBinds()
	 */
	public function getBinds() {
		$this->toSQL(); // force binds addition
		return parent::getBinds();
	}

	/**
	 * Convert a SQL date to another format
	 * @param string $origin origin date format
	 * @param string $destination destination date format
	 * @return string template for conversion, with ? as placeholder
	 */
	private function getTemplateForConvertSqlDate($origin, $destination) {

		// The conversion is simplified :
		// - we don't have to use STR_TO_DATE for convert DATE or SHORT_DATE in FullDate
		// - we don't have to user STR_TO_DATE(SHORT_DATE) for convert a SHORT_DATE in DATE
		// We can have shorter and quicker SQL queries with theses conditions but the algorithm will be more complex.

		$template = '?';
		// if same format : do nothing
		if ($origin !== $destination) {
			// if origin format is not a full date, we convert if to date
			if (!SqlDateFormat::isFullDate($origin) && !SqlDateFormat::isRawTimestamp($origin)) {
				$template='STR_TO_DATE('.$template.', \''.SqlDateFormat::resolve($origin).'\')';
				// after this point, origin format is a full date
			}
			if (SqlDateFormat::isRawTimestamp($origin)) {
				if (SqlDateFormat::isFullDate($destination)) {
					$template = 'FROM_UNIXTIME('.$template.')';
				} else {
					$template = 'FROM_UNIXTIME('.$template.', \''.SqlDateFormat::resolve($destination).'\')';
				}
			} else {
				// if we have a full date in origin format
				if (SqlDateFormat::isRawTimestamp($destination)) {
					$template = 'UNIX_TIMESTAMP('.$template.')';
				} else {
					$template = 'DATE_FORMAT('.$template.', \''.SqlDateFormat::resolve($destination).'\')';
				}
			}
		}
		return $template;
	}

	/**
	 * Change in objet after calling this method are ignored
	 * @return string the SQL text for SqlExpr
	 */
	public function toSQL() {
		if ($this->sqlText !== NULL) {
			// without memoization, binds are added twice or more
			return $this->sqlText;
		}

		$s='';
		$sArray = NULL;

		switch($this->objectType) {
			case self::VALUE:

				if (($this->setter !== NULL)
				&& ($this->data === NULL)
				&& (!$this->setter->nullable)) {
					throw new Exception('Cannot set a NULL value to a non-nullable field : '.$this->setter->name);
				}

				if ($this->data === NULL) {
					$s='NULL';
					$this->type = NULL;
				} else {
					if ($this->type === NULL) {
						if (is_array($this->data) && (count($this->data) > 0)) {
							$v = first($this->data);
						} else {
							$v = $this->data;
						}
						if (is_bool($v)) {
							$this->type = FieldType::BOOLEAN;
						} else if (is_numeric($v)) {
							$this->type = FieldType::NUMBER;
						} else {
							$this->type = FieldType::TEXT;
						}
					}
					if (is_array($this->data)) {
						$sArray = array();
						foreach($this->data as $v) {
							$sArray[] = $this->addBind($v, $this->type);
						}
						$s=NULL;
					} else {
						$s.=$this->addBind($this->data, $this->type);
					}
				}
			break;
			case self::FUNC:
				$args = $this->data;
				$funcName = array_shift($args);
				$params = array();
				foreach($args as $p) {
					$params[] = $p->toSQL();
					$this->binds = array_merge($this->binds, $p->getBinds());
				}
				$s.=$funcName.'('.implode(', ', $params).')';
			break;
			case self::FIELD:
				/** @var Field $field */
				list($alias, $field) = $this->data;
				$s.=$alias.'.'.$field->name;
				if ($this->type === NULL) {
					$this->type = $field->type;
					$this->dateFormat = $field->format;
				}
			break;
		}

		// Conversion of DATE types
		$template = NULL;
		if (($this->setter !== NULL) // by construction, if we have a DATE setter, origin format is also a DATE
		&& ($this->setter->type === FieldType::DATE)) {
			$template = $this->getTemplateForConvertSqlDate($this->dateFormat, $this->setter->format);

		} else if ($this->type === FieldType::DATE) {
			// if we are not in a setter but type is DATE, we have to transform :
			// - in a TIMESTAMP
			if ($this->timestamp) {
				$template = $this->getTemplateForConvertSqlDate($this->dateFormat, SqlDateFormat::RAW_TIMESTAMP);
			// - or in a DATE
			} else {
				$template = $this->getTemplateForConvertSqlDate($this->dateFormat, SqlDateFormat::DATETIME);
			}
		}

		if ($template !== NULL) {
			if ($sArray !== NULL) {
				array_walk($sArray, function($v, $key, $template) { return str_replace('?', $v, $template); }, $template);
			} else {
				$s=str_replace('?', $s, $template);
			}
		}

		// Templates
		if (count($this->template)>0) {
			list($template, $args) = $this->template;
			if (!is_array($args)) {
				$args = array($args);
			}
			$template = explode(self::TEMPLATE_PARAM, $template);
			$params=array();
			/** @var SqlExpr $arg */
			foreach($args as $arg) {
				$params[] = $arg->toSQL();
				$this->binds = array_merge($this->binds, $arg->getBinds());
			}
			if (count($template)-1 !== count($params)) {
				throw new Exception('Template '.implode(self::TEMPLATE_PARAM, $template).' contains '.(count($template)-1).
														' placeholders but with '.(count($params)).' values');
			}
			$sql = array_shift($template);
			foreach($template as $k => $piece) {
				$sql.=$params[$k];
				$sql.=$piece;
			}
			if ($sArray !== NULL) {
				array_walk($sArray, function($v, $key, $sqlTemplate) { return str_replace(self::TEMPLATE_MAIN, $v, $sql); }, implode(' ', $sql));
			} else {
				$s=str_replace(self::TEMPLATE_MAIN, $s, $sql);
			}
		}

		if ($sArray !== NULL) {
			$s = '('.implode(', ', $sArray).')';
		}
		$this->sqlText = $s;
		return $this->sqlText;
	}
}