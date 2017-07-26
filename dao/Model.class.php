<?php
/**
 * Model class
 *
 * @author     Richaud Julien "Fladnag"
 * @package    salt\dao
 */
namespace salt;


/**
 * Model class for all business DAO objects
 */
class Model {

	/**
	 * @var Field[] all field of the object, indexed by field name
	 */
	private $_salt_fields = array();
	/**
	 * @var string table name of the dao object
	 */
	private $_salt_tableName = NULL;
	/**
	 * @var string name of the main id field
	 */
	private $_salt_idFieldName = NULL;

	/**
	 * @var string the real class name of the object
	 */
	private $_salt_class = NULL;

	/**
	 * @var boolean TRUE if initialized
	 */
	private $_salt_initialized = FALSE;

	/**
	 * Create a Model object
	 * @param string $objectClassName class name of the model object
	 */
	public function __construct($objectClassName) {
		$this->_salt_class = $objectClassName;
	}

	/**
	 * Register the fieldName will be returned by Base::getId()
	 * @param string $fieldName a field name registered in metadata()
	 * @return Model this object
	 *
	 * @see Base::getId() */
	public function registerId($fieldName) {
		$this->checkChangeAfterInit();
		$this->_salt_idFieldName = $fieldName;
		return $this;
	}

	/**
	 * Register the table name of the object in database
	 * @param string $table the table name that will be used in query generation
	 * @return Model this object */
	public function registerTableName($table) {
		$this->checkChangeAfterInit();
		$this->_salt_tableName = $table;
		return $this;
	}

	/**
	 * Register fields
	 * @param Field $args ... list of fields
	 * @return Model this object
	 */
	public function registerFields(Field $args) {
		$this->checkChangeAfterInit();
		$fields = func_get_args();

		foreach($fields as $field) {
			$this->_salt_fields[$field->name] = $field;
		}

		return $this;
	}

	/**
	 * Check if the model is initialized
	 * @return boolean TRUE if model is initialized, FALSE otherwise
	 */
	public function initialized() {
		return $this->_salt_initialized;
	}

	/**
	 * Set the model to initialized state.
	 * No modification can be done to the model after that.
	 */
	public function setInitialized() {
		$this->checkChangeAfterInit();
		$this->_salt_initialized = TRUE;
	}

	/**
	 * Throw exception if called after model was initialized
	 * @throws SaltException if called after setInitialized()
	 */
	private function checkChangeAfterInit() {
		if ($this->initialized()) {
			throw new SaltException(L::error_model_change_after_initialized);
		}
	}

	/**
	 * Return a field metadata
	 * @param string $field field name
	 * @return Field the field metadata
	 */
	public function __get($field) {
		return $this->_salt_fields[$field];
	}

	/**
	 * Return all fields
	 * @return Field[] fields indexed by field name
	 */
	public function getFields() {
		return $this->_salt_fields;
	}

	/**
	 * Return the table name registered with registerTableName()
	 * @return string the table name
	 */
	public function getTableName() {
		return $this->_salt_tableName;
	}

	/**
	 * Return the name of the main ID field registered with registerId()
	 * @return string the name of the ID field
	 */
	public function getIdFieldName() {
		return $this->_salt_idFieldName;
	}

	/**
	 * Return the class name of the object linked to the model
	 * @return string a child class name of Base
	 */
	public function getClass() {
		return $this->_salt_class;
	}

	/**
	 * Check if a field exists
	 * @param string $field field name
	 * @return boolean TRUE if field is registered, FALSE otherwise
	 */
	public function exists($field) {
		return isset($this->_salt_fields[$field]);
	}
}
