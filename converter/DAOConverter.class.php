<?php
/**
 * DAOConverter class
 *
 * @author     Richaud Julien "Fladnag"
 * @package    salt\converter
 */
namespace salt;

/**
 * Convert DAO value for HTML display
 */
class DAOConverter extends AbstractConverter {

	const METHOD_COLUMN = 'column';
	const METHOD_EDIT = 'edit';
	const METHOD_SHOW = 'show';
	const METHOD_TEXT = 'text';

	const METHOD_SET = 'setterInput';
	const METHOD_SQL = 'sql';

	/**
	 * Convert field to column name
	 *
	 * @param Base $object The singleton object
	 * @param Field $field the field to display
	 * @param mixed $value the default value
	 * @param string $format format to use for change the output
	 * @param mixed[] $params others parameters passed to convert function
	 * @return string HTML escaped text for describe $field in $format
	 */
	public function column(Base $object, Field $field, $value, $format, $params) {
		$Input = In::getInstance();
		return $Input->HTML($field->text);
	}

	/**
	 * Convert field value to text
	 *
	 * @param Base $object object that contains the value
	 * @param Field $field the field
	 * @param mixed $value the value to display
	 * @param string $format format to use
	 * @param mixed[] $params others parameters passed to convert function
	 * @return string a non-HTML escaped value
	 */
	public function text(Base $object, Field $field, $value, $format, $params) {
		if ($format === FormHelper::RAW) {
			return $value;
		}

		if (isset($field->values) && isset($field->values[$value])) {
			$value = $field->values[$value];
		}

		if ($field->type === FieldType::DATE) {

			if ($format === NULL) {
				$format = $params[FormHelper::FORMAT_KEY];
			}
			if (($format === NULL) && array_key_exists(FormHelper::FORMAT_KEY, $field->displayOptions)) { // can be NULL, array_key_exists required
				$format = $field->displayOptions[FormHelper::FORMAT_KEY];
			}
			if ($format === NULL) {
				$format = $field->displayFormat;
			}

			if ($format !== NULL) {
				$value = date($format, $value);
			}
		} else if ($field->type === FieldType::BOOLEAN) {
			$value = ($value)?1:0;
		}
		return $value;
	}

	/**
	 * Convert value for HTML display
	 *
	 * @param Base $object object that contains the value
	 * @param Field $field the field
	 * @param mixed $value the value to display
	 * @param string $format format to use
	 * @param mixed[] $params others parameters passed to convert function
	 * @return string an HTML escaped value
	 */
	public function show(Base $object, Field $field, $value, $format, $params) {
		$Input = In::getInstance();
		if ($format !== FormHelper::RAW) {
			$value = $this->text($object, $field, $value, $format, $params);
		}
		return $Input->HTML($value);
	}

	/**
	 * Convert a field to an HTML input control
	 *
	 * @param Base $object object that contains the value
	 * @param Field $field the field
	 * @param mixed $value the value to edit
	 * @param string $format format to use
	 * @param mixed[] $params others parameters passed to convert function
	 * @return string a full HTML form tag (input, select, etc...) for editing the value
	 */
	public function edit(Base $object, Field $field, $value, $format, $params) {
		if ($params === NULL) {
			$params = array();
		}

		if ($format !== NULL) {
			$params[FormHelper::FORMAT_KEY] = $format;
		}

		return FormHelper::field($field, NULL, $value, array(), $params, $this);
	}

	/**
	 * {@inheritDoc}
	 * @param mixed $value The value to convert
	 * @return mixed the converted value
	 * @see \salt\AbstractConverter::convert()
	 */
	public final function convert($value) {
		return $this->delegateTo($value, array(self::METHOD_COLUMN, self::METHOD_EDIT, self::METHOD_SHOW, self::METHOD_SQL, self::METHOD_TEXT));
	}

	/**
	 * Convert a value using another object converter
	 * @param Base $otherObject An instance of the other object (retrieve it with ::singleton())
	 * @param mixed $value The value to convert
	 * @param string $method Optional, override the method to use for convert, one of DAOConverter::METHOD_*
	 * @return mixed the converted value
	 */
	public final function convertAs(Base $otherObject, $value, $method = NULL) {
		$ctx = $this->getContext();

		$format = reset($ctx);
		$currentMethod = key($ctx);
		if ($method === NULL) {
			$method = $currentMethod;
		}
		if ($currentMethod !== $method) {
			unset($ctx[$currentMethod]);
			$ctx = array_merge(array($method => $format), $ctx);
		}
		$converter = DAOConverter::getInstance($otherObject, $ctx, $this->getField());
		return $converter->convert($value);
	}

	/**
	 * {@inheritDoc}
	 * @param mixed $value The value to convert for set in object
	 * @return mixed the converted value
	 * @see \salt\AbstractConverter::convertForSetter()
	 */
	public final function convertForSetter($value) {
		// $dao->FORM->field (EDIT) can redirect to ->VIEW (SHOW or TEXT), so we cannot check the method we came from
		// and we replace it by SET
		$format = current($this->getContext());
		$this->_init(array(self::METHOD_SET => $format), $this->getObject(), $this->getField());
		return $this->delegateTo($value, array(self::METHOD_SET));
	}

	/**
	 * Call the appropriate function for convert the value
	 *
	 * @param mixed $value value to convert
	 * @param string[] $validMethods valid methods
	 * @return mixed the converted value
	 */
	private final function delegateTo($value, $validMethods) {
		$params = NULL;
		$method = key($this->getContext());
		$format = current($this->getContext());
		if (!in_array($method, $validMethods)) {
			throw SaltException(L::error_not_implemented);
		}
		if (is_array($format)) {
			$params = $format;
			if (isset($params[FormHelper::FORMAT_KEY])) {
				$format = $params[FormHelper::FORMAT_KEY];
			} else {
				$format = NULL;
			}
		}
		/**
		 * @var Base $object
		 */
		$object = $this->getObject();
		// create field if not exists, because object is singleton on COLUMN method, so extra fields are not defined
		$field = $object->getField($this->getField(), ($method === self::METHOD_COLUMN));
		return $this->$method($object, $field, $value, $format, $params);
	}

	/**
	 * Convert a value for using it in SQL query.
	 * This method DO NOT make any SQL escape. With PDO or similar, we don't need escape
	 *
	 * @param Base $object object that contains the value
	 * @param Field $field field
	 * @param mixed $value the value to convert
	 * @param string $format the format of the value
	 * @param mixed[] $params others parameters passed to convert function
	 * @return mixed the value for using in SQL query
	 */
	public function sql(Base $object, Field $field, $value, $format, $params) {
		if ($value !== NULL) {
			switch($field->type) {
				case FieldType::BOOLEAN:
					$value = $value?1:0;
				break;
				case FieldType::DATE:
					if ($format === NULL) {
						$format = $field->format;
					}
					$phpFormat = SqlDateFormat::resolvePHP($format);
					$value = date($phpFormat, $value);
				break;
			}
		}
		return $value;
	}

	/**
	 * Convert an Input value before setting in object : Base->FORM->field = value;
	 *
	 * @param Base $object object that contains the value
	 * @param Field $field field
	 * @param mixed $value the value to convert
	 * @param string $format the format of the value
	 * @param mixed[] $params others parameters passed to convert function
	 * @return mixed the converted value
	 */
	public function setterInput(Base $object, Field $field, $value, $format, $params) {
		if ($format === NULL) {
			$format = $field->displayFormat;
		}
		$value = $this->convertNullValues($field, $value, $format);
		return $this->convertFromExternal($field, $value, $format);
	}

	/**
	 * Convert a value before setting in object : Base->field = value;
	 *
	 * @param Field $field field
	 * @param mixed $value the value to convert
	 * @return mixed the converted value
	 */
	public function setter(Field $field, $value) {
		return $value;
	}


	/**
	 * Convert a DB value before setting in object, called at object load
	 *
	 * @param Field $field field
	 * @param mixed $value the value to convert
	 * @param string $format the format to use
	 * @return mixed the converted value
	 */
	public function setterDB(Field $field, $value, $format) {
		if ($format === NULL) {
			$format = $field->format;
		}
		return $this->convertFromExternal($field, $value, $format);
	}

	/**
	 * Handle NULL values, that does not exists in HTML.
	 *
	 * Any nullable field with an empty string value will be NULL.
	 * If we want a real empty string, we have to use Field::EMPTY_STRING instead
	 *
	 * @param Field $field the field
	 * @param mixed $value the value
	 * @param string $format the format from convert function
	 * @return NULL|string the converted value
	 */
	protected function convertNullValues(Field $field, $value, $format) {
		if (($field->nullable && ($value === '')) || ($value === NULL)) {
			return NULL;
		}
		if ($value === Field::EMPTY_STRING) {
			$value = '';
		}
		return $value;
	}

	/**
	 * Convert a value from external source (input form or database) for storage in object.
	 *
	 * A value for a boolean field is converted to boolean. A value for a date field is converted to timestamp.
	 * @param string $field Field name
	 * @param mixed $value value to convert
	 * @param string $format date format
	 * @return mixed the converted value
	 */
	protected function convertFromExternal($field, $value, $format) {
		if ($value === NULL) {
			return $value;
		}
		switch($field->type) {
			case FieldType::NUMBER :
				if (!is_numeric($value)) {
					throw new SaltException(L::error_model_field_not_number($value));
				}
				$value = intval($value);
			break;
			case FieldType::BOOLEAN : $value = (($value == 1) || ($value === 'on'));
			break;
			case FieldType::DATE :
				$date = \DateTime::createFromFormat('!'.SqlDateFormat::resolvePHP($format), $value);

				if ($date !== FALSE) {
					// Timezone issue ? timestamp can be negative because createFromFormat use default timezone
					// and initial date (1970) with negative timezone will produce a negative timestamp.
					// Setting a timezone AFTER is not a solution : the date is modified for 1969
					// Setting a timezone with 3rd parameter of createFromFormat require PHP 5.3.9, and will increase all requirements.
					$value = max(0, $date->getTimestamp());
				} else {
					throw new SaltException(L::error_model_field_date_invalid_format($value, $format));
				}
			break;
		}
		return $value;
	}

	/**
	 * {@inheritDoc}
	 * @param string $field field name
	 * @return mixed|NULL the field value on current object or NULL if there is no current object or if method is COLUMN
	 * @see \salt\AbstractConverter::getValue()
	 */
	public function getValue($field) {
		// column do NOT need value because object is singleton, so value is default value
		if (key($this->getContext()) === self::METHOD_COLUMN) {
			return NULL;
		}
		return parent::getValue($field);
	}
}