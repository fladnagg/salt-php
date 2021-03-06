<?php
/**
 * SqlBindField class
 *
 * @author     Richaud Julien "Fladnag"
 * @package    salt\sql
 */
namespace salt;

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
	 * type of SqlExpr : a static text */
	const TEXT=3;
	/**
	 * type of SqlExpr : a sub query */
	const QUERY=4;

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
	 * @var mixed[][] the template to apply
	 * @content array of array of (template, param1, param2, ...)
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
	 * create a new SqlExr. Use static function for that
	 * @param int $objectType type of SqlExpr : FUNC|VALUE|FIELD
	 * @param mixed $data source of SqlExpr, can be a scalar value or an array
	 */
	protected function __construct($objectType, $data) {
		$this->objectType = $objectType;
		$this->data = $data;
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
	 * Create a new SqlExpr for a static text
	 *
	 * Warning : $text <b>HAVE TO BE escaped for sql usage, we recommand to DO NO USE user input in it.</b><br/>
	 * - Boolean is converted to 0/1<br/>
	 * - Number and text are used unchanged<br/>
	 * @param mixed $text text to transform in SqlExpr.
	 * @return \salt\SqlExpr
	 */
	public static function text($text) {
		return new SqlExpr(self::TEXT, $text);
	}

	/**
	 * Join multiple parameters with a separator
	 * @param string $separator text to use for join parameters
	 * @param SqlExpr|mixed ... $args list of parameters. Parameters that are not SqlExpr are converted with SqlExpr::value()
	 * @return SqlExpr current object
	 */
	public static function implode($separator, $args) {
		$args = func_get_args();
		array_shift($args);

		$values = array_fill(0, count($args), self::TEMPLATE_PARAM);
		$template = implode($separator, $values);

		$params = self::arrayToSqlExpr($args);

		$result = SqlExpr::text('');
		$result->template[] = array($template, $params);
		return $result;
	}

	/**
	 * Add parenthesis around current SqlExpr
	 * @return SqlExpr current objet
	 */
	public function parenthesis() {
		return $this->template('('.self::TEMPLATE_MAIN.')');
	}

	/**
	 * Construct a tuple with parameters : (arg1, arg2, ...)
	 * @param mixed|SqlExpr $args ...
	 * @return SqlExpr current object
	 */
	public static function tuple($args) {
		$args = func_get_args();
		$args = self::arrayToSqlExpr($args);
		array_unshift($args, '');
		$expr = new SqlExpr(self::FUNC, $args);
		return $expr->parenthesis();
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
	 * Create a new SqlExpr for a query
	 *
	 * @param BaseQuery $query a query that return a compatible value with the usage of the SqlExpr.
	 * @return SqlExpr
	 */
	public static function query(BaseQuery $query) {
		return new SqlExpr(self::QUERY, $query);
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
	 * @throws SaltException
	 */
	public function asDate($format = NULL) {
		$this->timestamp = FALSE;
		$this->type = FieldType::DATE;
		if ($format !== NULL) {
			$this->dateFormat = $format;
		}
		if ($this->dateFormat === NULL) {
			throw new SaltException(L::error_sql_date_without_format);
		}
		return $this;
	}

	/**
	 * Set the SqlExpr type to DATE, as TIMESTAMP format
	 * @param string $format format of the source, used for convert to date.
	 * @return SqlExpr current object
	 * @throws SaltException
	 */
	public function asTimestamp($format = NULL) {
		$this->timestamp = TRUE;
		$this->type = FieldType::DATE;
		if ($format !== NULL) {
			$this->dateFormat = $format;
		}
		if ($this->dateFormat === NULL) {
			throw new SaltException(L::error_sql_timestamp_without_format);
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
	 * @param SqlExpr|mixed ... $args list of parameters. Parameters that are not SqlExpr are converted with SqlExpr::value()
	 * @return SqlExpr current object
	 */
	public function template($template, $args = NULL) {
		$args = func_get_args();
		array_shift($args);

		$params = self::arrayToSqlExpr($args);

		$this->template[] = array($template, $params);
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
	 * Add something before the SqlExpr
	 * @param mixed|SqlExpr $arg converted with SqlExpr::value() if needed
	 * @return SqlExpr current object
	 */
	public function before($arg) {
		return $this->template(self::TEMPLATE_PARAM.self::TEMPLATE_MAIN, $arg);
	}

	/**
	 * Add something after the SqlExpr
	 * @param mixed|SqlExpr $arg converted with SqlExpr::value() if needed
	 * @return SqlExpr current object
	 */
	public function after($arg) {
		return $this->template(self::TEMPLATE_MAIN.self::TEMPLATE_PARAM, $arg);
	}

	/**
	 * Retrieve all used table alias in SqlExpr
	 * @return string[] all table alias
	 */
	public function getAllUsedTableAlias() {
		$result = array();
		if ($this->objectType === self::FIELD) {
			$alias = first($this->data);
			if ($alias !== NULL) {
				$result[$alias]=$alias;
			}
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
	 * Convert an array of mixed elements in SqlExpr.
	 * If element is not already an SqlExpr, it will be converted with SqlExpr::value
	 *
	 * @param mixed[] $args all elements
	 * @return SqlExpr[] list of SqlExpr
	 */
	private static function arrayToSqlExpr($args) {
		foreach($args as $k => $arg) {
			if (!($arg instanceof SqlExpr)) {
				$args[$k] = SqlExpr::value($arg);
			}
		}
		return $args;
	}

	/**
	 * Create a new SqlExpr for an SQL function
	 * @param string $func name of the SQL function with an underscore prefix for avoid collision with PHP keywords or other defined functions
	 * @param mixed|SqlExpr ... $args list of function arguments. All arg that is not a SqlExpr is converted with SqlExpr::value($arg)
	 * @return SqlExpr
	 */
	public static function __callstatic($func, $args) {
		if (strpos($func, '_') === 0) {

			$func = substr($func, 1);

			$args = self::arrayToSqlExpr($args);
			array_unshift($args, $func);

			return new SqlExpr(self::FUNC, $args);
		}
		throw new \BadMethodCallException(L::error_sql_bad_function($func));
	}

	/**
	 * Change in objet after calling this method are ignored
	 * @return string the SQL text for SqlExpr
	 */
	protected function buildSQL() {
		$s='';
		$sArray = NULL;

		switch($this->objectType) {
			case self::VALUE:

				if (($this->setter !== NULL)
				&& ($this->data === NULL)
				&& (!$this->setter->nullable)) {
					throw new SaltException(L::error_sql_null_value($this->setter->name));
				}

				if ($this->data === NULL) {
					$s='NULL';
					$this->type = NULL;
				} else {
					if ($this->type === NULL) {
						$this->type = FieldType::guessType($this->data);
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
				/** @var SqlExpr $p */
				foreach($args as $p) {
					if ($this->isPrivateBinds()) {
						$p->privateBinds();
					}
					$params[] = $p->toSQL();
					$this->linkBindsOf($p);
				}
				$s.=$funcName.'('.implode(', ', $params).')';
			break;
			case self::FIELD:
				/** @var Field $field */
				list($alias, $field) = $this->data;
				if ($alias !== NULL) {
					$s.=$alias.'.';
				}
				$s.=$field->name;
				if ($this->type === NULL) {
					$this->type = $field->type;
					$this->dateFormat = $field->format;
				}
			break;
			case self::TEXT:
				if ($this->data === NULL) {
					$s.='NULL';
				} else if (!is_scalar($this->data)) {
					throw new SaltException(L::error_sql_text_complex_value);
				} else if (is_bool($this->data)) {
					$s.=($this->data)?1:0;
				} else {
					$s.=$this->data;
				}
			break;
			case self::QUERY:
				$this->linkBindsOf($this->data);
				$s.='('.$this->data->toSQL().')';
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
		foreach($this->template as $t) {
			list($template, $args) = $t;
			$template = explode(self::TEMPLATE_PARAM, $template);
			$params=array();
			/** @var SqlExpr $arg */
			foreach($args as $arg) {
				$a = $arg->toSQL();
				$params[] = $arg->toSQL();
				$this->linkBindsOf($arg);
			}
			if (count($template)-1 !== count($params)) {
				throw new SaltException(L::error_sql_template_placeholder_mismatch(implode(self::TEMPLATE_PARAM, $template), count($template)-1, count($params)));
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
		return $s;
	}
}