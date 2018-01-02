<?php
/**
 * In class
 *
 * @author     Richaud Julien "Fladnag"
 * @package    salt\utils
 */
namespace salt;

use \Exception;

/**
* Input and Output variables - Require PHP 5.3.0
*
* <pre>
* <u>Features</u> :
* <blockquote>
*	- Input sources (types) : GET, POST, COOKIE, FILES, SERVER, REQUEST, ENV, SESSION, local variables
*	- Output format : HTML, URL, RAW, Base64, ISSET, SET
*	- Format a input variable for an output format
*	- Cached variables for performance
*	- Exception or not if variable don't exist (configuration)
*	- Charset can be specified
*	- Types and format can be extended
*	- Change values in superglobal arrays
* </blockquote>
*
* <br/><u>Usage</u> :
* <blockquote>
*	$Input = In::getInstance();
*	echo $Input->G->RAW->id; // display $_GET['id'] without change
*	echo $Input->P->HTML->foo; // display $_POST['foo'] for a HTML usage (htmlentities)
*	echo $Input->C->B64->login; // display $_COOKIE['login'] in base64 encoding
*	echo $Input->F->RAW->fileName; // display $FILES['fileName']; without change
*	echo $Input->G->ISSET->id; // display TRUE if $_GET['id'] exists, FALSE otherwise
*	echo $Input->G->SET->id = 'foo'; // change value of $_GET['id'] et reset caches in instance
*	echo $Input->HTML($localVariable); // display $localVariable for a HTML usage (htmlentities)
* </blockquote>
*
* <br/><u>Extend type or format</u> :
* <blockquote>
*	- Create a child class which extend In
*	- Register type / format in register() method like predefined type/format in In
*	- Implement convert() method for new formats in child class
* </blockquote>
*
* <br/><u>Configuration</u> :
* <blockquote>
*	In::setThrowException(FALSE); // Return NULL instead of throwing exception if a variable don't exists.
*	In::setCharset('...'); // Set a charset for htmlentities
* </blockquote>
*
* <br/><u>Warning to the behavior with In cache</u> :
* <blockquote>
*	$Input = In::getInstance();
*	echo $Input->G->RAW->id; // display $_GET['id']
*	$_GET['id'] = 'new value';
*	echo $Input->G->RAW->id; // display the OLD value of $_GET['id'] because id is now in cache !
*	echo $Input->G->HTML->id; // display the NEW value of $_GET['id'] because id has been never asked for HTML format !
*	$_GET['machin'] = 'valeur';
*	echo $Input->G->RAW->machin; // display $_GET['machin'] because value has neven been asked, so it's not in cache.
*	If we use $Input->G->SET->id = 'new value'; instead, this behavior vanish and all works as expected.
* </blockquote>
*
* <br/><u>History</u>
* <blockquote>
* 	2.0 (25/11/2017)<blockquote>
* 		The class became linked to SALT framework :
*		Use of SALT Converters, better performance
*		Use of SALT I18n translation
*		Remove SQL format
*	</blockquote>
* 	1.8 (03/03/2017)<blockquote>
*		Remove array format feature : Allow this class to process array and scalar values on different ways is error prone.
*			We can use RAW for retrieve unformated array values, and a loop for formatting each values.
*	</blockquote>
*	1.7 (15/08/2016)<blockquote>
*		Translate in english
*	</blockquote>
*	1.6 (31/07/2016) <blockquote>
*		Complete rewrite / simplification
*		Removing magic_quotes support (deprecated in 5.3, removed in 5.4)
*		Exception thrown for bad usage. setThrowException(FALSE) disable exceptions only for undefined variables.
*		Add REQUEST, ENV, SESSION types
*		Add SET format
*	</blockquote>
*	1.5 (10/07/2016) <blockquote>
*		Add SERVER type
*		Change URL implementation for using rawurlencode instead of urlencode
*	</blockquote>
*	1.4 (12/03/2011) <blockquote>
*		Complete rewrite. Removing internal classes.
*		New feature : register new type & format
*		New format : URL & Base64
*	</blockquote>
*	1.3 (15/02/2009) <blockquote>
*		Bug fix : magic_quotes_gpc
*	</blockquote>
*	1.2 <blockquote>
*		New feature : Charset for htmlentities
*		HTML format : use of ENT_QUOTES
*	</blockquote>
*	1.1 (29/12/2007) <blockquote>
*		New feature : format local variables
*	</blockquote>
* </blockquote>
* </pre>
*
* @author	Richaud Julien "Fladnag"
* @version	1.8
*
* @method static string HTML(string $value) HTML value formatted with htmlentities(), ENT_QUOTES, and charset
* @method static string URL(string $value) value formatted with rawurlencode()
* @method static string B64(string $value) value formatted with base64_encode()
* @method static mixed RAW(mixed $value) value without change
*/
class In {

	/** In type : instance retrieved with getInstance() */
	const _SALT_INSTANCE_BASE = -1;
	/** In type : instance retrieved with ->TYPE, linked to a superglobal array */
	const _SALT_INSTANCE_TYPE = 0;
	/** In type : instance retrieved with ->TYPE->FORMAT, linked to a superglobal array AND a format */
	const _SALT_INSTANCE_FORMAT = 1;

	/** Exception code for undefined variable */
	const _SALT_EX_UNDEFINED_VARIABLE=1;
	/** Exception code for undefined format */
	const _SALT_EX_UNDEFINED_FORMAT=2;
	/** Exception code for undefined type */
	const _SALT_EX_UNDEFINED_TYPE=3;
	/** Exception code for trying to set a value without SET format */
	const _SALT_EX_BAD_SETTER = 4;

	/** @var int instance type */
	private $_saltType = self::_SALT_INSTANCE_BASE;
	/** @var In parent instance */
	private $_saltParent = NULL;
	/** @var string format or type name, depends on instance type */
	private $_saltName = NULL;
	/** @var boolean differentiate internal call of exteral __set call */
	private $_saltCacheSetter = FALSE;

	/** @var string Charset to use in format functions if needed */
	protected static $_saltCharset = NULL;
	/** @var boolean TRUE if we have to throw an exception if variable undefined */
	protected static $_saltThrowException = TRUE;
	/** @var mixed[][] Type and format registered as TYPE=>array(name => mixed) */
	protected static $_saltRegistry = array(self::_SALT_INSTANCE_TYPE => array(), self::_SALT_INSTANCE_FORMAT => array());
	/** @var In[] In singleton list : 1 singleton for each child class as className=>Instance */
	protected static $_saltInstances = array();

	/***************** PRIVATE FUNCTIONS ***************/

	/**
	 * New instance of In
	 *
	 * If $parent is provided, the return object depends on parent type.<br/>
	 * If $parent is an _SALT_INSTANCE_BASE instance, will construct an _SALT_INSTANCE_TYPE instance<br/>
	 * If $parent is an _SALT_INSTANCE_TYPE instance, will construct an _SALT_INSTANCE_FORMAT instance<br/>
	 *
	 * @param In $parent Parent instance if we are in a delegation chain
	 * @param string $name Name of type or format
	 */
	private function __construct(In $parent = NULL, $name = NULL) {
		$this->_saltParent = $parent;
		if ($parent !== NULL) {
			switch($parent->_saltType) {
				case self::_SALT_INSTANCE_BASE : $this->_saltType = self::_SALT_INSTANCE_TYPE; break;
				case self::_SALT_INSTANCE_TYPE : $this->_saltType = self::_SALT_INSTANCE_FORMAT; break;
			}
			if (!isset(self::$_saltRegistry[$this->_saltType][$name])) {
				if ($this->_saltType === self::_SALT_INSTANCE_TYPE) {
					static::throwException(L::error_in_unknown_type($name), static::_SALT_EX_UNDEFINED_TYPE, TRUE);
				} else {
					static::throwException(L::error_in_unknown_format($name), static::_SALT_EX_UNDEFINED_FORMAT, TRUE);
				}
			}
		}
		$this->_saltName = $name;
	}

	/**
	 * Reset caches for variable on current instance (of type _SALT_INSTANCE_TYPE)
	 * @param string $var Name of variable to remove from caches
	 */
	private function invalidCache($var) {
		foreach(static::$_saltRegistry[self::_SALT_INSTANCE_FORMAT] as $format => $instance) {
			// property_exists required because cache can contain NULL, so isset can return FALSE
			if (isset($this->$format)) { // && property_exists($this->$format, $var)) {
				// make an unset on an undefined property is faster than check this existence with property_exists
				// if the property exists : we spare property_exists execution time
				// if the property don't exists : on spare (property_exists - unset) execution time
				unset($this->$format->$var);
			}
		}
	}

	/**
	 * Change a dynamic property of the instance
	 * @param string $var Name of property
	 * @param mixed $value New value
	 */
	private function setCache($var, $value) {
		$this->_saltCacheSetter = TRUE;
		$this->$var = $value;
	}

	/**
	* Throw an exception
	* @param string $message Exception message
	* @param int $type code, see self::_SALT_EX_*
	* @param boolean $force (optional, FALSE) TRUE if we have to throw an internal exception and ignore configuration
	* @throws \Exception self::_SALT_EX_*
	*/
	private static function throwException($message, $type, $force = FALSE) {
		if (self::haveToThrowException() || $force) {
			throw new \Exception($message, $type);
		}
	}

	/********** PROTECTED FUNCTIONS **********/

	/**
	 * Register types and formats
	 *
	 * The method to override to register new type/format<br/>
	 * Don't forget to call parent::register()
	 */
	protected function register() {
		static::registerType('G', '_GET');
		static::registerType('P', '_POST');
		static::registertype('F', '_FILES');
		static::registerType('C', '_COOKIE');
		static::registerType('S', '_SERVER');
		static::registerType('R', '_REQUEST');
		static::registerType('E', '_ENV');
		static::registerType('SS', '_SESSION');

		static::registerFormat('HTML', 	HTMLConverter::getInstance());
		static::registerFormat('URL', 	URLConverter::getInstance());
		static::registerFormat('RAW', 	RAWConverter::getInstance());
		static::registerFormat('B64', 	B64Converter::getInstance());


		static::registerFormat('ISSET', ''); 	// internal implementation, but required for register the format
		static::registerFormat('SET', ''); 		// internal implementation, but required for register the format
	}

	/**
	 * Register a new type
	 * @param string $name Name of type
	 * @param string $source Name of superglobal array, without $ (for example "_GET" for "$_GET")
	 */
	protected static function registerType($name, $source) {
		static::$_saltRegistry[self::_SALT_INSTANCE_TYPE][$name] = $source;
	}

	/**
	 * Register a new format
	 * @param string $name Name of format
	 * @param Converter $converter Converter class
	 */
	protected static function registerFormat($name, $converter) {
		static::$_saltRegistry[self::_SALT_INSTANCE_FORMAT][$name] = $converter;
	}

	/********** PUBLIC FUNCTIONS **********/

	/**
	 * Set a dynamic property in instance
	 *
	 * If the call is from self::setCache(), it's an internal call and we set the value without check.<br/>
	 * If the call is from ->$type->SET-> , it's an external call and we set the value in $type array and we doing a cache reset<br/>
	 * If the call is from another format ->$type->$format-> we throw an exception.
	 *
	 * @param string $var Property name to set
	 * @param mixed $value value
	 * @throws \Exception self::_SALT_EX_BAD_SETTER
	 */
	public function __set($var, $value) {
		//echo 'SET '.(is_scalar($var)?$var:gettype($var)).' TO '.(is_scalar($value)?$value:gettype($value)).'<br/>';
		if ($this->_saltCacheSetter) {
			$this->$var = $value;
		} else if (($this->_saltType === self::_SALT_INSTANCE_FORMAT) && ($this->_saltName === 'SET')) {
			$array = static::$_saltRegistry[self::_SALT_INSTANCE_TYPE][$this->_saltParent->_saltName];
			global ${$array};
			${$array}[$var] = $value;

			$this->_saltParent->invalidCache($var);
		} else {
			static::throwException(L::error_in_bad_setter_format, self::_SALT_EX_BAD_SETTER, TRUE);
		}
		$this->_saltCacheSetter = FALSE;
	}

	/**
	 * Return an instance property. If the property don't exists, it's will be dynamically created then returned<br/>
	 * So, the next access to this property will not call this method.
	 *
	 * @param string $var Property name
	 * @return mixed value
	 * @throws \Exception static::_SALT_EX_UNDEFINED_VARIABLE if property don't exists, or NULL if exceptions disabled
	 */
	public function __get($var) {
		//echo 'GET '.(is_scalar($var)?$var:gettype($var)).'<br/>';
		if (!isset($this->$var)) {
			if ($this->_saltType === self::_SALT_INSTANCE_FORMAT) {
				$array = static::$_saltRegistry[self::_SALT_INSTANCE_TYPE][$this->_saltParent->_saltName];
				global ${$array};

				$isExists = isset(${$array}[$var]);
				if ($this->_saltName === 'ISSET') {
					$this->setCache($var, $isExists);
				} else {
					if (!$isExists) {
						static::throwException(L::error_in_unknown_property($var, $this->_saltParent->_saltName), static::_SALT_EX_UNDEFINED_VARIABLE);
						//$this->setCache($var, NULL);
						// on ne stocke pas en cache une variable inexistante
						return NULL;
					} else {
						$value = ${$array}[$var];
						if (isset(static::$_saltRegistry[self::_SALT_INSTANCE_FORMAT][$this->_saltName])) {
							$cl = static::$_saltRegistry[self::_SALT_INSTANCE_FORMAT][$this->_saltName];
							$this->setCache($var, $cl->convert($value));
						} else {
							static::throwException(L::error_in_unknown_format($this->_saltName), static::_SALT_EX_UNDEFINED_FORMAT, TRUE);
						}
					}
				}
			} else {
				$this->setCache($var, new static($this, $var));
			}
		}
		return $this->$var;
	}

	/**
	 * Throw an exception for calling an undefined format
	 *
	 * @param string $method the method/format
	 * @param mixed[] $args
	 * @throws \Exception static::_SALT_EX_UNDEFINED_FORMAT
	 */
	public function __call($method, $args) {
		if (!isset(static::$_saltRegistry[self::_SALT_INSTANCE_FORMAT][$method])) {
			static::throwException(L::error_in_unknown_format($method), static::_SALT_EX_UNDEFINED_FORMAT, TRUE);
		}
		return static::$_saltRegistry[self::_SALT_INSTANCE_FORMAT][$method]->convert($args[0]);
	}

	/**
	 * Retrieve an In singleton
	 * @return static instance of class (1 singleton by child class)
	 */
	public static function getInstance() {
		$key = get_called_class();
		if (!isset(static::$_saltInstances[$key])) {
			static::$_saltInstances[$key] = new static();
			static::$_saltInstances[$key]->register();
		}
		return static::$_saltInstances[$key];
	}

	/**
	* Set the charset for format functions (like HTML with htmlentities)
	*
	* @param string $charset Valid charset
	* @see http://php.net/manual/fr/function.htmlentities.php
	*/
	public static function setCharset($charset) {
		self::$_saltCharset = $charset;
	}

	/**
	* Return charset
	*
	* @return string charset
	*/
	public static function getCharset() {
		return self::$_saltCharset;
	}

	/**
	* Configure In instances for throw exception if a variable is undefined
	* @param boolean $throwException FALSE to not thrown exception if variable undefined.
	*/
	public static function setThrowException($throwException = TRUE) {
		self::$_saltThrowException = (boolean)$throwException;
	}

	/**
	 * Check if we throw an exception if variable undefined
	 * @return boolean return TRUE if we have to thrown an exception if variable undefined.
	 */
	public static function haveToThrowException() {
		return self::$_saltThrowException;
	}
}
