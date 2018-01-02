<?php
/**
 * Base class
 *
 * @author     Richaud Julien "Fladnag"
 * @package    salt\dao
 */
namespace salt;

use \Exception;

/**
 * Base class for all business DAO objects
 */
abstract class Base extends Identifiable {
	/** State of a not initialized objet */
	const _SALT_STATE_NONE=0;
	/** State of a new created objet */
	const _SALT_STATE_NEW=10;
	/** State of an object created by PDO but not populated yet. After populate, the object will be at NEW state */
	const _SALT_STATE_NEW_LOADING=11;
	/** State of an object created by PDO but not populated yet */
	const _SALT_STATE_LOADING=12;
	/** State of an object created by PDO but not populated yet, with dynamic columns initialization (we don't know columns at object creation) */
	const _SALT_STATE_DYNAMIC_LOADING = 13;
	/** State of an object created and populated by PDO fetch */
	const _SALT_STATE_LOADED=20;
	/** State of a modified object after being loaded by PDO */
	const _SALT_STATE_MODIFIED=30;
	/** State of a deleted object */
	const _SALT_STATE_DELETED=40;
	/** A special state for objet singleton returned by singleton() function. Throw an exception if a setter is called */
	const _SALT_STATE_READONLY=50;

	/**
	 * @var Base object by child class name. Used in ::singleton()
	 */
	private static $_saltSingletons = array();

	/**
	 * @var Model models of dao class.
	 */
	private static $_saltModels = NULL;
	/**
	 * @var mixed[] Modified values for object loaded from database
	 * @content array of fieldName => value */
	private $_saltValues = array();

	/**
	 * @var mixed[] All extra fields which are returned by a query and are not declared in metadata
	 * @content array of extraFieldName => value */
	private $_saltExtraFields=array();

	/**
	 * @var Field[] Extra fields metadata
	 * @content array of extraFieldName => Field */
	private $_saltExtraFieldsMetadata=array();

	/**
	 * @var int Current object state : self::_SALT_STATE_*
	 */
	private $_saltState = self::_SALT_STATE_NONE;

	/**
	 * @var mixed[] Contains values initially loaded from database or for new object (not in database)
	 * @content array of fieldName => value */
	private $_saltLoadValues = array();

	/**
	 * @var boolean TRUE if object loaded from constructed Query API, which format date as timestamp
	 */
	private $_saltFromQueryAPI = FALSE;

	/**
	 * Register metadata of object
	 *
	 * Sub classes have to implement this method for declaring all metadata by calling ::MODEL()->register...
	 * @see Model::registerTableName()
	 * @see Model::registerId()
	 * @see Model::registerFields()
	 * @see Field
	 */
	abstract protected function metadata();

	/**
	 * doing some stuff after creating table
	 * @param DBHelper $db database where table is created
	 * @return static[] objects to create in table
	 */
	public function initAfterCreateTable(DBHelper $db) {
		// do nothing
		return NULL;
	}

	/**
	 * Create a new DAO object
	 * @param string[] $loadedFields fields loaded by a query for this object. NULL if object created manually (default)
	 * @param string[] $extraFields fields to add to instance of new objects. Useless for classic usage.
	 * @param boolean $loadAsNew (Optional, FALSE) if TRUE, the afterLoad() method set the state to NEW instead of LOADED
	 * @param boolean $fromQueryAPI (Optional, FALSE) TRUE means all date are converted to timestamp in SQL, FALSE means all date are NOT converted
	 */
	public function __construct(array $loadedFields = NULL, array $extraFields = NULL, $loadAsNew = FALSE, $fromQueryAPI = FALSE) {
		parent::__construct();
		$this->_saltFromQueryAPI = $fromQueryAPI;
		$extras = $this->initValues($loadedFields, $extraFields, $loadAsNew);
	}

	/**
	 * Retrieve the Model object containing metadata about the object
	 * @param boolean $withLoad (Optionnal, TRUE) if TRUE, initialize the model if not exists. Never use in normal usage, only for internal usage
	 * @return Model the Model object
	 */
	public static function MODEL($withLoad = TRUE) {
		$child = get_called_class();
		if (!isset(self::$_saltModels[$child])) {
			self::$_saltModels[$child] = new Model($child);
			if ($withLoad) {
				// create an object will be call initValues, that call metadata() and initialize MODEL
				new $child();
			}
		}
		return self::$_saltModels[$child];
	}

	/**
	 * Retrieve a singleton instance
	 * @return static a singleton instance of the object
	 */
	public static function singleton() {
		$child = get_called_class();
		if (!isset(self::$_saltSingletons[$child])) {
			self::$_saltSingletons[$child] = new $child();
			self::$_saltSingletons[$child]->readonly();
		}
		return self::$_saltSingletons[$child];
	}

	/**
	 * Return a SELECT query on object
	 * @param string $withField TRUE for retrieve all fields, FALSE otherwise
	 * @return Query the empty query object
	 */
	public static function query($withField = FALSE) {
		return new Query(self::singleton(), $withField);
	}

	/**
	 * Return a generic UPDATE query on object
	 * @param Query $fromQuery the query to use for update
	 * @return UpdateQuery the update query
	 */
	public static function updateQuery($fromQuery = NULL) {
		return new UpdateQuery(self::singleton(), $fromQuery);
	}

	/**
	 * Return a generic DELETE query on object
	 * @return DeleteQuery the delete query
	 */
	public static function deleteQuery() {
		return new DeleteQuery(self::singleton());
	}

	/**
	 * Create and return a new object of the same type
	 * @param string[] $extraFields array of fieldName to add to the instance
	 * @return static
	 */
	public function getNew($extraFields = NULL) {
		$cl = get_class($this);
		return new $cl(NULL, $extraFields);
	}

	/**
	 * Add an extra field on instance after object creation
	 * @param string $extraField field name
	 * @throws SaltException if field name is a regular field
	 */
	public function addExtraField($extraField) {
		$child = get_called_class();
		if (self::MODEL()->exists($extraField)) {
			throw new SaltException(L::error_model_field_already_exists($extraField));
		}
		$this->_saltExtraFields[$extraField] = NULL;
		$this->_saltExtraFieldsMetadata[$extraField] = Field::newText($extraField, $extraField, TRUE);
	}

	/**
	 * Return the tablename registered with ::registerTableName()
	 * @param boolean $withEscape (Optional, TRUE) FALSE for do not escape table name with backquotes
	 * @return string the table name
	 */
	public function getTableName($withEscape = TRUE) {
		$table = self::MODEL()->getTableName();
		if ((strtolower($table) !== 'dual') && (strpos($table, '.') === FALSE) && $withEscape) { // special value
			$table = SqlBindField::escapeName($table);
		}
		return $table;
	}

	/**
	 * Get the value of the ID field. The ID field is registered with ::registerId()
	 * @return mixed value of id field
	 */
	public function getId() {
		return $this->{$this->getIdField()};
	}

	/**
	 * Get the name of the ID field registered with ::registerId()
	 * @return string field name */
	public function getIdField() {
		return self::MODEL()->getIdFieldName();
	}

	/**
	 * Retrieve an object by ID on a database
	 * @param DBHelper $db The database to search object
	 * @param mixed $id a value of the id field
	 * @return static|NULL the first object with this id. All fields are loaded. Return NULL if no object found
	 */
	public static function getById(DBHelper $db, $id) {
		$q = static::query(TRUE);
		$q->whereAnd(static::MODEL()->getIdFieldName(), '=', $id);
		$r = $db->execQuery($q);
		return first($r->data);
	}

	/**
	 * Retrieve a list of object on a database
	 * @param DBHelper $DB database to search objects
	 * @param mixed[] $ids list of value to search
	 * @return static[] associative array : id => object
	 */
	public static function getByIds(DBHelper $DB, array $ids) {
		$q = static::query(TRUE);
		$idField = static::MODEL()->getIdFieldName();
		$q->whereAnd($idField , 'IN', $ids);
		$q->disableIfEmpty($ids);
		$r = $DB->execQuery($q);
		$result = array();
		foreach($r->data as $obj) {
			$result[$obj->$idField] = $obj;
		}
		return $result;
	}

	/**
	 * Initialize the class by calling metadata() (once by class) and setting default values (once by instance) if needed
	 * @param string[] $loadedFields fields loaded by a query for this object
	 * @param string[] $extraFields fields to add to new instance only
	 * @param boolean $loadAsNew if TRUE, the afterLoad() method set the state to NEW instead of LOADED
	 */
	private function initValues(array $loadedFields = NULL, array $extraFields = NULL, $loadAsNew = FALSE) {
		$model = self::MODEL(FALSE);

		if (!$model->initialized()) {
			$this->metadata(); // populate Model
			$model->setInitialized();
		}

		if ($this->_saltState === self::_SALT_STATE_NONE) {
			if (($loadedFields === NULL) || $loadAsNew) {
				if ($loadAsNew) {
					$this->_saltState = self::_SALT_STATE_NEW_LOADING;
					$extraFields = $loadedFields;
				} else {
					$this->_saltState = self::_SALT_STATE_NEW;
				}

				// for manually created new object, load all default values
				foreach($model->getFields() as $fieldName => $field) {
					$this->_saltLoadValues[$fieldName]=$field->defaultValue;
					if ($field->defaultValue !== NULL) {
						$this->_saltValues[$fieldName]=$field->defaultValue; // for create in INSERT statement
					}
				}

				if ($extraFields !== NULL) {
					foreach($extraFields as $field) {
						if (!$model->exists($field)) {
							$this->_saltExtraFields[$field] = NULL;
							$this->_saltExtraFieldsMetadata[$field] = Field::newText($field, $field, TRUE);
						}
					}
				}
			} elseif (count($loadedFields) === 0) {
				$this->_saltState = self::_SALT_STATE_DYNAMIC_LOADING;

			} else {
				$this->_saltState = self::_SALT_STATE_LOADING; // for query loaded object, define all loaded fields
				foreach($loadedFields as $field) {
					if ($model->exists($field)) {
						$this->_saltLoadValues[$field] = NULL; // will be loaded by setter later
					} else {
						$this->_saltExtraFields[$field] = NULL;
						$this->_saltExtraFieldsMetadata[$field] = Field::newText($field, $field, TRUE);
					}
				}
			} // else $loadedFields === NULL
		} // if _SALT_STATE_NONE
	}

	/**
	 * Retrieve a Field by field name
	 * @param string $fieldName field name registered with metadata() or extra field constructor parameter
	 * @param boolean $createIfNotExists if TRUE, create extra field if not exists, throw SaltException otherwise
	 * @return Field */
	public function getField($fieldName, $createIfNotExists = FALSE) {
		if (!$this->checkFieldExists($fieldName, FALSE, $createIfNotExists)) {
			return Field::newText($fieldName, $fieldName, TRUE);
		}

		if (isset($this->_saltExtraFieldsMetadata[$fieldName])) {
			return $this->_saltExtraFieldsMetadata[$fieldName];
		}

		return self::MODEL()->$fieldName;
	}

	/**
	 * Check a field exist
	 * @param string $fieldName
	 * @param boolean $forValue also check field is loaded and value can be retrieve
	 * @param boolean $doNotThrowException return FALSE if field does not exists instead of throwing exception
	 * @return boolean TRUE if field exists
	 * @throws SaltException if field don't exists or is not loaded (if $forValue)
	 */
	private function checkFieldExists($fieldName, $forValue = FALSE, $doNotThrowException = FALSE) {
		$this->initValues();

		$child = get_class($this);

		// value can be null. array_key_exists instead of isset
		if (array_key_exists($fieldName, $this->_saltExtraFields)) {
			return;
		}
		if (!self::MODEL()->exists($fieldName)) {
			if ($doNotThrowException) {
				return FALSE;
			}
			throw new SaltException(L::error_model_field_unknown($fieldName, get_class($this)));
		}
		// value can be null : array_key_exists instead of isset
		if ($forValue && !array_key_exists($fieldName, $this->_saltLoadValues)) {
			if ($doNotThrowException) {
				return FALSE;
			}
			throw new SaltException(L::error_model_field_not_loaded($fieldName, get_class($this)));
		}
		return TRUE;
	}

	/**
	 * Return the value of field or extra field of object.
	 * Can also return an _InternalFieldAccess if $fieldName is FORM or VIEW
	 * @param string $fieldName
	 * @return mixed the value of the field
	 */
	public function __get($fieldName) {
		if (in_array($fieldName, array('FORM', 'VIEW', 'COLUMN', 'SQL'))) {
			return $this->$fieldName();
		}
		$this->checkFieldExists($fieldName, TRUE);
		// values can be null : array_key_exists instead of isset
		if (array_key_exists($fieldName, $this->_saltExtraFields)) {
			return $this->_saltExtraFields[$fieldName];
		}
		if (array_key_exists($fieldName, $this->_saltValues)) {
			return $this->_saltValues[$fieldName];
		}
		return $this->_saltLoadValues[$fieldName];
	}

	/**
	 * Change the internal state after object has been loaded by PDO fetch
	 * @internal Called after PDO populate for setting correct state.
	 */
	public function afterLoad() {
		$this->checkState(array(self::_SALT_STATE_LOADING, self::_SALT_STATE_DYNAMIC_LOADING, self::_SALT_STATE_NEW_LOADING));
		$this->_saltState = ($this->_saltState === self::_SALT_STATE_NEW_LOADING)?self::_SALT_STATE_NEW:self::_SALT_STATE_LOADED;
	}

	/**
	 * Check object state is in allowed states
	 * @param int|int[] $state expected states for the object
	 * @throws SaltException if the object is not in one of the expected states
	 */
	public function checkState($state) {
		if (!is_array($state)) {
			$state = array($state);
		}
		if (!in_array($this->_saltState, $state, TRUE)) {
			throw new SaltException(L::error_model_state($this->_saltState, implode(', ',$state)));
		}
	}

	/**
	 * Check object state is not in forbidden states
	 * @param int|int[] $state forbidden states for the object
	 * @param string $message error message for exception
	 * @throws SaltException if the object is in one of the forbidden states
	 */
	public function checkNotState($state, $message = NULL) {
		if (!is_array($state)) {
			$state = array($state);
		}
		if (in_array($this->_saltState, $state, TRUE)) {
			if ($message === NULL) {
				$message = L::error_model_state_forbidden($this->_saltState);
			}
			throw new SaltException($message);
		}
	}

	/**
	 * Return all modified field since object creation
	 * @return mixed[] fieldName => value
	 */
	public function getModifiedFields() {
		return $this->_saltValues;
	}


	/**
	 * Set a field value
	 * @param string $fieldName the field to change
	 * @param mixed $value the value
	 */
	public function __set($fieldName, $value) {
		$this->checkNotState(self::_SALT_STATE_READONLY, L::error_model_change_readonly);
		$this->checkNotState(self::_SALT_STATE_DELETED, L::error_model_change_deleted);

		if ($this->_saltState === self::_SALT_STATE_DYNAMIC_LOADING) {
			if (!self::MODEL()->exists($fieldName)) {
				$this->addExtraField($fieldName);
			}
		}

		$this->checkFieldExists($fieldName, TRUE, ($this->_saltState === self::_SALT_STATE_DYNAMIC_LOADING));

		$field = $this->getField($fieldName);

		$conv = DAOConverter::getInstance($this, NULL);
		if (in_array($this->_saltState, array(self::_SALT_STATE_LOADING, self::_SALT_STATE_DYNAMIC_LOADING, self::_SALT_STATE_NEW_LOADING))) {
			$format = NULL;
			if ($this->_saltState === self::_SALT_STATE_NEW_LOADING) {
				$format = $field->format;
			} elseif ($this->_saltFromQueryAPI) {
				$format = SqlDateFormat::RAW_TIMESTAMP;
			}
			$value = $conv->setterDB($field, $value, $format);
		} else {
			$value = $conv->setter($field, $value);
		}

		$field->validate($value);

		// value can be null : array_key_exists instead of isset
		if (array_key_exists($fieldName, $this->_saltExtraFields)) {
			$this->_saltExtraFields[$fieldName] = $value;
		} else if (in_array($this->_saltState, array(self::_SALT_STATE_LOADING, self::_SALT_STATE_DYNAMIC_LOADING))) {
			// first load
			$this->_saltLoadValues[$fieldName] = $value;
		} else {
			if (($value !== $this->_saltLoadValues[$fieldName]) || $this->isNew()) {
				$this->_saltValues[$fieldName] = $value;
			// value can be null : array_key_exists instead of isset
			} else if (array_key_exists($fieldName, $this->_saltValues)) {
				unset($this->_saltValues[$fieldName]);
			}
		}
		if ($this->_saltState === self::_SALT_STATE_LOADED) {
			$this->_saltState = self::_SALT_STATE_MODIFIED;
		}
	}

	/**
	 * Check object is a readonly object
	 * @return boolean TRUE if the object is readonly (Base::singleton() is a readonly object)
	 */
	public function isReadonly() {
		return ($this->_saltState === self::_SALT_STATE_READONLY);
	}

	/**
	 * Check object has been modified
	 * @return boolean TRUE if the object have been modified
	 */
	public function isModified() {
		return ($this->_saltState === self::_SALT_STATE_MODIFIED)
				&& (count($this->getModifiedFields())>0);
	}

	/**
	 * Check object is new : so, not loaded from a database
	 * @return boolean TRUE if the object have been created with a new ...()
	 */
	public function isNew() {
		return ($this->_saltState === self::_SALT_STATE_NEW || $this->_saltState === self::_SALT_STATE_NEW_LOADING);
	}

	/**
	 * Check object is loaded from a database
	 * @return boolean TRUE if the object have been loaded for a database by PDO
	 */
	public function isLoaded() {
		return $this->_saltState === self::_SALT_STATE_LOADED;
	}

	/**
	 * Delete the current object. This method is called when object is used in a DeleteQuery constructor
	 * @internal
	 */
	public function delete() {
		$this->checkState(array(self::_SALT_STATE_LOADED, self::_SALT_STATE_DELETED));

		$this->_saltState = self::_SALT_STATE_DELETED;
	}

	/**
	 * Check if object is deleted
	 * @return boolean TRUE if the object have been deleted
	 */
	public function isDeleted() {
		return $this->_saltState === self::_SALT_STATE_DELETED;
	}

	/**
	 * Make the object readonly. All changes will throw an exception.
	 */
	public function readonly() {
		$this->checkNotState(self::_SALT_STATE_MODIFIED, L::error_model_readonly_on_modified);

		$this->_saltState = self::_SALT_STATE_READONLY;
	}

	/**
	 * Return an accessor for retrieve the text of a field
	 * @param mixed $format (Optional, NULL) the format to use
	 * @return \salt\DAOConverter Converter for display the column of field
	 */
	public static function COLUMN($format=NULL) {
		return DAOConverter::getInstance(get_called_class(), array(DAOConverter::METHOD_COLUMN => $format));
	}

	/**
	 * Return an accessor for retrieve a field value
	 * @param mixed $format (Optional, NULL) format to use
	 * @return \salt\DAOConverter Converter for display the value of field
	 */
	public function VIEW($format = NULL) {
		$method = ViewControl::isText()?DAOConverter::METHOD_TEXT:DAOConverter::METHOD_SHOW;
		return DAOConverter::getInstance($this, array($method => $format));
	}

	/**
	 * Return an accessor for retrieve an input of a field
	 * @param mixed $format (Optional, NULL) format to use
	 * @return \salt\DAOConverter Converter for display the input of a field
	 */
	public function FORM($format = NULL) {
		if (!ViewControl::isEdit()) {
			return $this->VIEW($format);
		}
		return DAOConverter::getInstance($this, array(DAOConverter::METHOD_EDIT => $format));
	}

	/**
	 * Return an accessor for retrieve the SQL text of a field
	 * @param mixed $format (Optional, NULL) format to use
	 * @return \salt\DAOConverter Converter for retrieve SQL field value
	 */
	public function SQL($format = NULL) {
		return DAOConverter::getInstance($this, array(DAOConverter::METHOD_SQL => $format));
	}
}
