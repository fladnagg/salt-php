<?php
/**
 * Field class
 *
 * @author     Richaud Julien "Fladnag"
 * @package    salt\dao
 */
namespace salt;

use \DateTime;

/**
 * Represent a field of a DAO object / Database column
 */
class Field {

	/** Can be used to set a field to an empty string (''). All empty string are converted to NULL */
	const EMPTY_STRING = "\1EMPTY_STRING\2";

	/**
	 * @var string[] list of Base special field names */
	private static $BASE_FIELDS=array('FORM', 'VIEW', 'COLUMN', 'SQL');
	/**
	 * @var string[] list of all reserved field names */
	private static $RESERVED_FIELDS= NULL;

	/**
	 * @var string column name */
	public $name;
	/**
	 * @var string description, used by DAOConverter for display the name of the field  */
	public $text;
	/**
	 * @var mixed[] List of possible values for the field
	 * @content value => text */
	public $values=array();
	/**
	 * @var mixed default value of the field. */
	public $defaultValue = NULL;
	/**
	 * @var string SqlDateFormat constant or other value : store format for DB, used for DATE type fields */
	public $format=NULL;
	/**
	 * @var string default date format display for PHP date() function
	 * @see \date() */
	public $displayFormat = NULL;
	/**
	 * @var int FieldType constant : type of the field */
	public $type;

	/**
	 * @var boolean TRUE if field can be NULL */
	public $nullable = FALSE;

	/**
	 * @var string if $name is a reserved word, the $useName can be used instead. */
	public $useName = NULL; // FIXME implement Field::$useName

	/**
	 * @var string SQL type for field creation if table does not exists. */
	public $sqlType = NULL;

	/**
	 * @var mixed[] attribute=>value for FORM display of the field
	 */
	public $displayOptions = array();

	/**
	 * Create a new Field. Use static functions new...() for that
	 * @param string $name column name
	 * @param string $text literal description of the column.
	 * @param int $type FieldType type of the field
	 * @param boolean $nullable (optional, FALSE) TRUE if the field can be NULL : set an empty string for set a NULL. Explicit set EMPTY_STRING for set an empty string
	 * @param mixed $defaultValue (optional, NULL) default value for new created object
	 * @param mixed[] $values (optional, array()) List of all possible values for the field
	 * @param string $sqlFormat (optional, NULL) SqlDateFormat or Mysql date format for date type field
	 * @param string $displayFormat (optional, DEFAULT_DATE_DISPLAY_FORMAT) PHP date() format for display date type field
	 * @param string $useName (optional, NULL) in case $name is a reserved word, $useName will be used instead for DAO (obviously $name is still used in query SQL text)
	 */
	private function __construct($name, $text, $type, $nullable = FALSE, $defaultValue = NULL, array $values = array(), $sqlFormat = NULL, $displayFormat = DEFAULT_DATE_DISPLAY_FORMAT, $useName = NULL) {

		if (self::$RESERVED_FIELDS === NULL) {
			$rcBase = new \ReflectionClass('salt\Base');
			$props = array();
			foreach($rcBase->getProperties() as $prop) {
				$props[]=strtoupper($prop->name);
			}
			$props = array_merge($props, self::$BASE_FIELDS);
			self::$RESERVED_FIELDS = $props;
		}

		$this->name = $name;
		$this->text = $text;
		$this->type = $type;
		$this->nullable = $nullable;

		$this->defaultValue = $defaultValue;
		$this->values = ($values===NULL)?array():$values;
		$this->format = $sqlFormat;
		$this->displayFormat = (($displayFormat===NULL) && ($this->type === FieldType::DATE))?DEFAULT_DATE_DISPLAY_FORMAT:$displayFormat;
		$this->useName = $useName;

		$this->checkMetadata();
	}

	/**
	 * Create a new Field from the current Field with some change. Every parameter can be NULL for keeping previous value
	 * @param string $name the new name
	 * @param string $text the new text
	 * @param string $nullable the new value for nullable behavior
	 * @param string $defaultValue the new default value
	 * @param string $values the new values list
	 * @return Field a new instance of Field with parameters
	 */
	public function newClone($name = NULL, $text = NULL, $nullable = NULL, $defaultValue = NULL, $values = NULL) {
		$field = clone $this;

		if ($name !== NULL) $field->name = $name;
		if ($text !== NULL) $field->text = $text;
		if ($nullable !== NULL) $field->nullable = $nullable;
		if ($defaultValue !== NULL) $field->defaultValue = $defaultValue;
		if ($values !== NULL) $field->values = $values;

		$field->checkMetadata();

		return $field;
	}

	/**
	 * Check internal metadata of a field after creation
	 * @throws SaltException if metadata are not valid
	 */
	private function checkMetadata() {
		if ($this->type === FieldType::DATE) {
			if ($this->format === NULL) {
				throw new SaltException(L::error_model_date_without_store_format);
			}
		} else {
			if ($this->format !== NULL) {
				throw new SaltException(L::error_model_format_but_not_date);
			}
		}

		if ((strpos(strtoupper($this->name), strtoupper(RESERVED_PREFIX)) === 0)
		|| in_array(strtoupper($this->name), self::$RESERVED_FIELDS)) {
			if ($this->useName === NULL) {
				throw new SaltException(L::error_model_field_reserved_word($this->name));
			}
		} else if ($this->useName !== NULL) {
			throw new SaltException(L::error_model_field_useless_usename($this->name));
		}

		if (($this->useName != NULL)
		&& ((strpos(strtoupper($this->useName), strtoupper(RESERVED_PREFIX)) === 0)
		|| in_array(strtoupper($this->name), self::$RESERVED_FIELDS))) {
			throw new SaltException(L::error_model_field_usename_reserved_word($this->name));
		}

		if (count($this->values) > 0) {
			foreach($this->values as $k => $v) {
				$this->validate($k);
			}
		}
		if ($this->defaultValue !== NULL) {
			$this->validate($this->defaultValue);
		}
	}

	/**
	 * Create a Field that contains a number
	 * @param string $name column name
	 * @param string $text literal description of the column. Displayed in column header
	 * @param boolean $nullable (optional, FALSE) TRUE if the field can be NULL : set an empty string for set a NULL. Explicit set EMPTY_STRING for set an empty string
	 * @param int $defaultValue (optional, NULL) default value for new created object
	 * @param mixed[] $values (optional, array()) List of all possible values for the field : number => mixed
	 * @param string $useName (optional, NULL) in case $name is a reserved word, $useName will be used instead for DAO (obviously $name is still used in query SQL text)
	 */
	public static function newNumber($name, $text, $nullable = FALSE, $defaultValue = NULL, array $values = array(), $useName = NULL) {
		return new Field($name, $text, FieldType::NUMBER, $nullable, $defaultValue, $values, NULL, NULL, $useName);
	}

	/**
	 * Create a Field that contains a text
	 * @param string $name column name
	 * @param string $text literal description of the column.
	 * @param boolean $nullable (optional, FALSE) TRUE if the field can be NULL : set an empty string for set a NULL. Explicit set EMPTY_STRING for set an empty string
	 * @param mixed $defaultValue (optional, NULL) default value for new created object
	 * @param mixed[] $values (optional, array()) List of all possible values for the field
	 * @param string $useName (optional, NULL) in case $name is a reserved word, $useName will be used instead for DAO (obviously $name is still used in query SQL text)
	 */
	public static function newText($name, $text, $nullable = FALSE, $defaultValue = NULL, array $values = array(), $useName = NULL) {
		return new Field($name, $text, FieldType::TEXT, $nullable, $defaultValue, $values, NULL, NULL, $useName);
	}

	/**
	 * Create a Field that contains a boolean
	 * @param string $name column name
	 * @param string $text literal description of the column.
	 * @param boolean $nullable (optional, FALSE) TRUE if the field can be NULL : set an empty string for set a NULL. Explicit set EMPTY_STRING for set an empty string
	 * @param mixed $defaultValue (optional, NULL) default value for new created object
	 * @param string $useName (optional, NULL) in case $name is a reserved word, $useName will be used instead for DAO (obviously $name is still used in query SQL text)
	 */
	public static function newBoolean($name, $text, $nullable = FALSE, $defaultValue = NULL, $useName = NULL) {
		return new Field($name, $text, FieldType::BOOLEAN, $nullable, $defaultValue, array(), NULL, NULL, $useName);
	}

	/**
	 * Create a Field that contains a date
	 * @param string $name column name
	 * @param string $text literal description of the column.
	 * @param string $sqlFormat (optional, NULL) SqlDateFormat or Mysql date format for date type field
	 * @param string $displayFormat (optional, DEFAULT_DATE_DISPLAY_FORMAT) PHP date() format for display date type field
	 * @param boolean $nullable (optional, FALSE) TRUE if the field can be NULL : set an empty string for set a NULL. Explicit set EMPTY_STRING for set an empty string
	 * @param mixed $defaultValue (optional, NULL) default value for new created object
	 * @param string $useName (optional, NULL) in case $name is a reserved word, $useName will be used instead for DAO (obviously $name is still used in query SQL text)
	 */
	public static function newDate($name, $text, $sqlFormat, $displayFormat = DEFAULT_DATE_DISPLAY_FORMAT, $nullable = FALSE, $defaultValue = NULL, $useName = NULL) {
		return new Field($name, $text, FieldType::DATE, $nullable, $defaultValue, array(), $sqlFormat, $displayFormat, $useName);
	}

	/**
	 * Set a SQL text for creating table with exact type
	 * @param string $sqlType SQL type with lenght and other options : primary, unique, auto_increment, etc...
	 * @return Field this object
	 */
	public function sqlType($sqlType) {
		$this->sqlType = $sqlType;
		return $this;
	}

	/**
	 * Set default options for displaying the object with Base::FORM
	 * @param mixed[] $options parameters to use for displaying the field with FORM. key=>value
	 * @return Field this object
	 */
	public function displayOptions(array $options) {
		$this->displayOptions = $options;
		return $this;
	}

	/**
	 * Check the value is valid for this field.
	 * @param mixed $value
	 * @throws SaltException if $value is not valid.
	 */
	public function validate($value) {

		if ($this->nullable) {
			if ($value === NULL) {
				return TRUE;
			}
			// a NULL key is converted in empty string in PHP
			if (($value === '') && (count($this->values) > 0)) {
				return TRUE;
			}
		} else {
			if ($value === NULL) {
				throw new SaltException(L::error_model_field_cannot_be_null($this->name));
			}
		}

		if ((count($this->values) > 0) && !isset($this->values[$value])) {
			throw new SaltException(L::error_model_field_value_not_expected($value, $this->name, implode(', ', array_keys($this->values))));
		}


		switch($this->type) {
			case FieldType::NUMBER :
				if (!is_numeric($value)) {
					throw new SaltException(L::error_model_field_not_number($value));
				}
			break;
			case FieldType::BOOLEAN :
				if (!is_bool($value)) {
					throw new SaltException(L::error_model_field_not_boolean($value));
				}
			break;
			case FieldType::DATE :
				try {
					$date = new DateTime('@'.$value);
					if ($date->getTimestamp() == $value) {
						break;
					}
				} catch(\Exception $ex) {
					// do nothing
				}
				throw new SaltException(L::error_model_field_not_timestamp($value));
			break;
		}
	}

	/**
	 * Check field is a date type
	 * @return boolean TRUE if the field is a date
	 */
	public function isDate() {
		return $this->type === FieldType::DATE;
	}
}