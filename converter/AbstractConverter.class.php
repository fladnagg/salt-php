<?php
/**
 * AbstractConverter class
 *
 * @author     Richaud Julien "Fladnag"
 * @package    salt\converter
 */
namespace salt;

/**
 * Convert object values
 *
 * Do NOT use if PHP is multi threaded
 */
abstract class AbstractConverter implements Converter {

	/**
	 * Make a new Converter
	 */
	private function __construct() {
		$_salt_parameters = array_fill_keys(array('field', 'object', 'context'), NULL);
	}

	/**
	 * @var Converter[] Converters instances, indexed by class name
	 */
	private static $_salt_instances = array();

	/**
	 * @var mixed[] context for accessor call. Generaly an array with format entry */
	private $_salt_context = NULL;
	/**
	 * @var object object for accessor call. */
	private $_salt_object = NULL;
	/**
	 * @var string field name */
	private $_salt_field = NULL;

	/**
	 * Initialize a converter accessor call
	 * @param mixed[] $context an array with format entry
	 * @param object $object the object
	 * @param string $field the field name
	 */
	protected function _init($context = NULL, $object = NULL, $field = NULL) {
		$this->_salt_context = $context;
		$this->_salt_object = $object;
		$this->_salt_field = $field;
	}

	/**
	 * Retrieve field name
	 * @return string the field name
	 */
	public function getField() {
		return $this->_salt_field;
	}

	/**
	 * Retrieve object for accessor
	 * @return object|string $object Instance of class name of the object
	 */
	public function getObject() {
		return $this->_salt_object;
	}

	/**
	 * Retrieve context for accessor
	 * @return mixed $context The context
	 */
	public function getContext() {
		return $this->_salt_context;
	}

	/**
	 * Retrieve a Converter instance
	 * @param object|string $object Object or class name for static call
	 * @param mixed $context context to pass at convert method
	 * @param string $fieldName set field name : use it when delegate calls between DAOConverters only.
	 * @return static the converter instance
	 */
	public static function getInstance($object = NULL, $context = NULL, $fieldName = NULL) {
		static $existingDaoConverters = array();

		$daoConverterClass = get_called_class();
		if ($object !== NULL) {
			$cl = NULL;
			if (is_object($object)) {
				$cl = get_class($object);
			} else if (class_exists($object, FALSE)) {
				$cl = $object;
				$object = $cl::singleton();
			}

			if (!array_key_exists($cl, $existingDaoConverters)) {

				$k2 = explode('\\', $daoConverterClass);
				$suffix = $k2[count($k2)-1]; // $key without namespace

				// the converter class can be defined on any parent class
				$allClasses = array_values(class_parents($cl, FALSE));
				array_unshift($allClasses, $cl);
				$ownConverter = NULL;
				$i = -1;
				while(++$i < count($allClasses)) {
					$class = $allClasses[$i];

					if (array_key_exists($class, $existingDaoConverters)) {
						$ownConverter = $existingDaoConverters[$class];
						break;
					}
					if (class_exists($class.$suffix)) {
						$ownConverter = $class.$suffix;
						// fill cache for found entry
						$existingDaoConverters[$class] = $ownConverter;
						break;
					}
				}
				// fill cache for all previous entries
				while($i-- > 0) {
					$existingDaoConverters[$allClasses[$i]] = $ownConverter;
				}
			}

			if ($existingDaoConverters[$cl] !== NULL) {
				$daoConverterClass = $existingDaoConverters[$cl];
			}
		}

		if (!isset(self::$_salt_instances[$daoConverterClass])) {
			self::$_salt_instances[$daoConverterClass] = new $daoConverterClass();
		}
		self::$_salt_instances[$daoConverterClass]->_init($context, $object, $fieldName);
		return self::$_salt_instances[$daoConverterClass];
	}

	/**
	 * Retrieve value of a field on the current object
	 * @param string $field field name
	 * @return mixed|NULL the field value on current object or NULL if there is no current object
	 */
	public function getValue($field) {
		$value = NULL;
		if (is_object($this->_salt_object)) {
			$value = $this->_salt_object->$field;
		}
		return $value;
	}

	/**
	 * {@inheritDoc}
	 * @param mixed $value The value to convert
	 * @return mixed the converted value
	 * @see \salt\Converter::convert()
	 */
	public function convert($value) {
		throw new SaltException(L::error_not_implemented);
	}

	/**
	 * {@inheritDoc}
	 * @param mixed $value The value to convert for set in object
	 * @return mixed the converted value
	 * @see \salt\Converter::convertForSetter()
	 */
	public function convertForSetter($value) {
		throw new SaltException(L::error_not_implemented);
	}

	/**
	 * Retrieve converted value
	 * @param string $field Field name
	 * @return mixed converter value
	 */
	public function __get($field) {
		$value = $this->getValue($field);
		$this->_salt_field = $field;
		$result = $this->convert($value);

		return $result;
	}

	/**
	 * Set a field with converted value
	 * @param string $field Field name
	 * @param mixed $value Value
	 */
	public function __set($field, $value) {
		$this->_salt_field = $field;
		$newValue = $this->convertForSetter($value);

		$this->_salt_object->$field = $newValue;
	}
}

