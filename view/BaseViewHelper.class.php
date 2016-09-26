<?php
/**
 * BaseViewHelper class
 *
 * @author     Richaud Julien "Fladnag"
 * @package    salt\view
 */
namespace salt;

/**
 * Parent class for *ViewHelper
 *
 * ViewHelpers are handled by classes and not by instances.
 */
class BaseViewHelper extends Identifiable implements ViewHelper {

	/**
	 * @var ViewHelper[] liste of instances : className => ViewHelper */
	private static $instances=array();

	/**
	 * @var string class of object this view helper work on */
	private $class;
	/**
	 * @var Base internal instance of object of class $class */
	private $object;

	/**
	 * Create a new ViewHelper
	 * @param string $class Class name of a Base child class */
	public function __construct($class) {
		parent::__construct();
		$this->class = $class;
	}

	/**
	 * Register a ViewHelper for a class
	 * @param string $class Class name of a Base child class
	 * @param string $helper Class name of a ViewHelper class
	 */
	public static function setInstance($class, $helper) {
		self::$instances[$class] = new $helper($class);
	}

	/**
	 * Retrieve a ViewHelper
	 * @param string $class Class name of a Base child class
	 * @return ViewHelper registered ViewHelper for this class, or a BaseViewHelper instead
	 */
	public static function getInstance($class) {
		if (!array_key_exists($class, self::$instances)) {
			self::$instances[$class] = new BaseViewHelper($class);
		}
		return self::$instances[$class];
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Field $field the field to display
	 * @param string $format format to use for change the output
	 * @return string HTML escaped text for describe $field in $format
	 * @see \salt\ViewHelper::column()
	 */
	public function column(Field $field, $format=NULL) {
		$Input = In::getInstance();
		return $Input->HTML($field->text);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Base $object object that contains the value
	 * @param Field $field the field
	 * @param mixed $value the value to display
	 * @param string $format format to use
	 * @param mixed[] $params parameter passed to Base->FORM or Base->VIEW method
	 * @return string a non-HTML escaped value
	 * @see \salt\ViewHelper::text()
	 */
	public function text(Base $object, Field $field, $value, $format, $params) {
		if ($format === ViewHelper::RAW) {
			return $value;
		}
		if (isset($field->values) && isset($field->values[$value])) {
			$value = $field->values[$value];
		}

		if ($field->type === FieldType::DATE) {

			if ($format === NULL) {
				$format = $params[ViewHelper::FORMAT_KEY];
			}
			if (($format === NULL) && array_key_exists(ViewHelper::FORMAT_KEY, $field->displayOptions)) { // can be NULL, array_key_exists required
				$format = $field->displayOptions[ViewHelper::FORMAT_KEY];
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
	 * {@inheritDoc}
	 *
	 * @param Base $object object that contains the value
	 * @param Field $field the field
	 * @param mixed $value the value to display
	 * @param string $format format to use
	 * @param mixed[] $params parameter passed to Base->FORM or Base->VIEW method
	 * @return string a HTML escaped value
	 * @see \salt\ViewHelper::show()
	 */
	public function show(Base $object, Field $field, $value, $format, $params) {
		$Input = In::getInstance();
		if ($format !== ViewHelper::RAW) {
			$value = $this->text($object, $field, $value, $format, $params);
		}
		return $Input->HTML($value);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return Base the objet to work on
	 * @see \salt\ViewHelper::getObject()
	 */
	public function getObject() {
		if ($this->object === NULL) {
			$class = $this->class;
			$this->object = $class::meta();
		}
		return $this->object;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Base $object object that contains the value
	 * @param Field $field the field
	 * @param mixed $value the value to edit
	 * @param string $format format to use
	 * @param mixed[] $params parameter passed to Base->FORM or Base->VIEW method
	 * @return string a full HTML form tag (input, select, etc...) for editing the value
	 * @see \salt\ViewHelper::edit()
	 */
	public function edit(Base $object, Field $field, $value, $format, $params) {
		if ($params === NULL) {
			$params = array();
		}

		if ($format !== NULL) {
			$params[ViewHelper::FORMAT_KEY] = $format;
		}

		//$params['value'] = $this->show($object, $field, $value, $format, $params);

		$this->object = $object; // update with the real object for retrieve it in field() method if needed

		return FormHelper::field($field, NULL, $value, array(), $params, $this);
	}

}