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
*	- Output format : SQL, HTML, URL, RAW, Base64, ISSET, SET
*	- Format a input variable for an output format
*	- Array variables (each element are formated)
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
*	echo $Input->C->SQL->login; // display $_COOKIE['login'] for a SQL usage (mysql_real_escape_string)
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
*	- Call parent::register(); in register()
*	- Create STATIC methods for new formats in child class
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
*	1.7 (15/08/2016) <blockquote>
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
* @author	Richaud Julien "Fladnag"
* @version	1.7
*/
class In {

	/** In type : instance retrieved with getInstance() */
	const INSTANCE_BASE = -1;
	/** In type : instance retrieved with ->TYPE, linked to a superglobal array */
	const INSTANCE_TYPE = 0;
	/** In type : instance retrieved with ->TYPE->FORMAT, linked to a superglobal array AND a format */
	const INSTANCE_FORMAT = 1;

	/** Exception code for undefined variable */
	const EX_UNDEFINED_VARIABLE=1;
	/** Exception code for undefined format */
	const EX_UNDEFINED_FORMAT=2;
	/** Exception code for undefined type */
	const EX_UNDEFINED_TYPE=3;
	/** Exception code for trying to set a value without SET format */
	const EX_BAD_SETTER = 4;
	/** Exception code for trying to access to another instance with a FORMAT parent */
	const EX_BAD_PARENT = 5;

	/** @var int instance type */
	private $_saltType = self::INSTANCE_BASE;
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
	protected static $_saltRegistry = array(self::INSTANCE_TYPE => array(), self::INSTANCE_FORMAT => array());
	/** @var In[] In singleton list : 1 singleton for each child class as className=>Instance */
	protected static $_saltInstances = array();

	/***************** PRIVATE FUNCTIONS ***************/

	/**
	 * New instance of In
	 *
	 * If $parent is provided, the return object depends on parent type.<br/>
	 * If $parent is an INSTANCE_BASE instance, will construct an INSTANCE_TYPE instance<br/>
	 * If $parent is an INSTANCE_TYPE instance, will construct an INSTANCE_FORMAT instance<br/>
	 *
	 * @param In $parent Parent instance if we are in a delegation chain
	 * @param string $name Name of type or format
	 */
	private function __construct(In $parent = NULL, $name = NULL) {
		$this->_saltParent = $parent;
		if ($parent !== NULL) {
			switch($parent->_saltType) {
				case self::INSTANCE_BASE : $this->_saltType = self::INSTANCE_TYPE; break;
				case self::INSTANCE_TYPE : $this->_saltType = self::INSTANCE_FORMAT; break;
				case self::INSTANCE_FORMAT :
					static::throwException('Cannot create an instance from a FORMAT parent', static::EX_BAD_PARENT, TRUE);
				break;
			}
			if (!isset(self::$_saltRegistry[$this->_saltType][$name])) {
				if ($this->_saltType === self::INSTANCE_TYPE) {
					static::throwException("The type [$name] is not registered.", static::EX_UNDEFINED_TYPE, TRUE);
				} else {
					static::throwException("The format [$name] is not registered.", static::EX_UNDEFINED_FORMAT, TRUE);
				}
			}
		}
		$this->_saltName = $name;
	}

	/**
	 * Reset caches for variable on current instance (of type INSTANCE_TYPE)
	 * @param string $var Name of variable to remove from caches
	 */
	private function invalidCache($var) {
		foreach(static::$_saltRegistry[self::INSTANCE_FORMAT] as $format => $instance) {
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
	* @param int $type code, see self::EX_*
	* @param boolean $force (optional, FALSE) TRUE if we have to throw an internal exception and ignore configuration
	* @throws Exception self::EX_*
	*/
	private static function throwException($message, $type, $force = FALSE) {
		if (self::haveToThrowException() || $force) {
			throw new Exception($message, $type);
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

		static::registerFormat('HTML', array(get_class($this),'HTML'));
		static::registerFormat('URL', array(get_class($this),'URL'));
		static::registerFormat('SQL', array(get_class($this),'SQL'));
		static::registerFormat('RAW', array(get_class($this),'RAW'));
		static::registerFormat('B64', array(get_class($this),'B64'));
		static::registerFormat('ISSET', ''); 	// internal implementation, but required for register the format
		static::registerFormat('SET', ''); 		// internal implementation, but required for register the format
	}

	/**
	 * Register a new type
	 * @param string $name Name of type
	 * @param string $source Name of superglobal array, without $ (for example "_GET" for "$_GET")
	 */
	protected static function registerType($name, $source) {
		static::$_saltRegistry[self::INSTANCE_TYPE][$name] = $source;
	}

	/**
	 * Register a new format
	 * @param string $name Name of format
	 * @param string|string[] $function Function name. If it's a class function, use an array(ClassName,StaticMethodName)
	 */
	protected static function registerFormat($name, $function) {
		static::$_saltRegistry[self::INSTANCE_FORMAT][$name] = $function;
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
	 * @throws Exception self::EX_BAD_SETTER
	 */
	public function __set($var, $value) {
		//echo 'SET '.(is_scalar($var)?$var:gettype($var)).' TO '.(is_scalar($value)?$value:gettype($value)).'<br/>';
		if ($this->_saltCacheSetter) {
			$this->$var = $value;
		} else if (($this->_saltType === self::INSTANCE_FORMAT) && ($this->_saltName === 'SET')) {
			$array = static::$_saltRegistry[self::INSTANCE_TYPE][$this->_saltParent->_saltName];
			global ${$array};

			${$array}[$var] = $value;

			$this->_saltParent->invalidCache($var);
		} else {
			static::throwException('Cannot change a value like that. You have to use SET format', self::EX_BAD_SETTER, TRUE);
		}
		$this->_saltCacheSetter = FALSE;
	}

	/**
	 * Return an instance property. If the property don't exists, it's will be dynamically created then returned<br/>
	 * So, the next access to this property will not call this method.
	 *
	 * @param string $var Property name
	 * @return mixed value
	 * @throws Exception static::EX_UNDEFINED_VARIABLE if property don't exists, or NULL if exceptions disabled
	 */
	public function __get($var) {
		//echo 'GET '.(is_scalar($var)?$var:gettype($var)).'<br/>';
		if (!isset($this->$var)) {
			if ($this->_saltType === self::INSTANCE_FORMAT) {
				$array = static::$_saltRegistry[self::INSTANCE_TYPE][$this->_saltParent->_saltName];
				global ${$array};

				$isExists = isset(${$array}[$var]);
				if ($this->_saltName === 'ISSET') {
					$this->setCache($var, $isExists);
				} else {
					if (!$isExists) {
						static::throwException("The property [$var] is not defined for [".$this->_saltParent->_saltName.']', static::EX_UNDEFINED_VARIABLE);
						//$this->setCache($var, NULL);
						// on ne stocke pas en cache une variable inexistante
						return NULL;
					} else {
						$value = ${$array}[$var];
						if (is_scalar($value)) {
							$this->setCache($var, forward_static_call(static::$_saltRegistry[self::INSTANCE_FORMAT][$this->_saltName], $value));
						} else if (is_array($value)) {
							$this->setCache($var, array_map(static::$_saltRegistry[self::INSTANCE_FORMAT][$this->_saltName], $value));
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
	 * @param string $format the method/format
	 * @param mixed[] $args
	 * @throws Exception static::EX_UNDEFINED_FORMAT
	 */
	public function __call($format, $args) {
		static::throwException("The format [$format] is undefined.", static::EX_UNDEFINED_FORMAT, TRUE);
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

	/**
	 * HTML format definition
	 * @param string $var value to convert
	 * @return string HTML value formatted with htmlentities(), ENT_QUOTES, and charset
	 */
	public static function HTML($var) {
		return htmlentities($var, ENT_QUOTES, self::getCharset());
	}

	/**
	 * Base64 format definition
	 * @param string $var value to convert
	 * @return string value formatted with base64_encode()
	 */
	public static function B64($var) {
		return base64_encode($var);
	}

	/**
	 * URL format definition
	 * @param string $var value to convert
	 * @return string value formatted with rawurlencode()
	 */
	public static function URL($var) {
		return rawurlencode($var);
	}

	/**
	 * SQL format definition
	 *
	 * We recommand to use binds and prepared queries instead of this format
	 * @param string $var value to convert
	 * @return string value formatted with mysql_real_escape_string (need an active connexion to a MySQL database)
	 */
	public static function SQL($var) {
		return mysql_real_escape_string($var);
	}

	/**
	 * RAW format definition
	 * @param mixed $var value to convert
	 * @return mixed value without change
	 */
	public static function RAW($var) {
		return $var;
	}
}

/**** Tests Part ****
header('Content-Type : text/html; charset=utf-8;');
echo '<pre>';

mysql_connect('localhost', 'root', '');

$a='a\'a"a/a\a<b>a</b>a$a';
$_GET['id']=$a;

$Tests=array(
	'$In=salt\In::getInstance();',
	'salt\In::setCharset("utf-8");',
	'salt\In::setThrowException(FALSE);',
	'$_GET[id] pour HTML' => array('$In->G->HTML->id', 'a&#039;a&quot;a/a\a&lt;b&gt;a&lt;/b&gt;a$a'),
	'$_GET[id] pour SQL' => array('$In->G->SQL->id', 'a\\\'a\\"a/a\\\a<b>a</b>a$a'),
	'$_GET[id] pour URL' => array('$In->G->URL->id', 'a%27a%22a%2Fa%5Ca%3Cb%3Ea%3C%2Fb%3Ea%24a'),
	'$_GET[id] pour Base64' => array('$In->G->B64->id', 'YSdhImEvYVxhPGI+YTwvYj5hJGE='),
	'Local var pour HTML' => array('$In->HTML(\''.str_replace("'", "\\'", $a).'\')', 'a&#039;a&quot;a/a\a&lt;b&gt;a&lt;/b&gt;a$a'),
	'$_GET[non_exist] pour RAW' => array('$In->G->RAW->non_exist', NULL),
	'salt\In::setThrowException(TRUE);',
	'$_GET[non_exist] pour RAW avec Exception' => array('$In->G->RAW->non_exist', In::EX_UNDEFINED_VARIABLE),
	'Isset FALSE'=>array('$In->G->ISSET->nexistepas', FALSE),
	'Isset TRUE'=>array('$In->G->ISSET->id', TRUE),
	'Unknown type with Exception' => array('$In->AA->HTML(1)', In::EX_UNDEFINED_TYPE),
	'Unknown format with Exception' => array('$In->TRUC(1)', In::EX_UNDEFINED_FORMAT),
	'class In2 extends salt\In {
		public function register() {
			parent::register();
			static::registerType("RR", "_T");
			static::registerFormat("HTML2", array(get_class($this), "HTML2"));
		}

		public static function HTML2($arg) {
			return "&lt;".$arg."&gt;";
		}
	}',
	'global $_T;',
	'$_T=array("AA"=>"aa", "BB"=>"bb");',
	'$In2=In2::getInstance();',
	'Child class : RR type and HTML2 format'=>array('$In2->RR->HTML2->AA', '&lt;aa&gt;'),
	'Child class : RR type and HTML format'=>array('$In2->RR->HTML->BB', 'bb'),
	'Child class : format on local variable'=>array('$In2->HTML2("machin")', '&lt;machin&gt;'),
	'$_POST["id"]="truc";',
	'Test Cache 1' => array('$In->P->RAW->id', 'truc'),
	'$_POST["id"]="machin";',
	'Test Cache 2' => array('$In->P->RAW->id', 'truc'),
	'Test Cache 3' => array('$In->P->HTML->id', 'machin'),
	'$_GET["tab"]=array("<u>", "<a>");',
	'Child class : array with HTML' => array('$In2->G->HTML->tab', array('&lt;u&gt;', '&lt;a&gt;')),
	'Child class : array with HTML2' => array('$In2->G->HTML2->tab', array('&lt;<u>&gt;', '&lt;<a>&gt;')),
);

$mem=array(memory_get_peak_usage(true), memory_get_usage(true));

function runTests($pTests) {
	$aNumTest=1;
	$nbOK=0;
	$nbKO=0;
	foreach($pTests as $aTestName=>$aTest) {
		if (is_numeric($aTestName)) {
			eval($aTest);
		} else {
			echo 'Test n°'.($aNumTest++).': '.$aTestName;
			//echo $In->$value[0][0]->$value[0][1]->$value[0][2];
			$isExceptionExpected=(is_numeric($aTest[1]));
			try {
				//var_dump($aTest[0]);
				eval('$aResult = '.$aTest[0].';');
				if (!$isExceptionExpected) {
					if (assert($aResult === $aTest[1])) {
						echo ' : OK';
						$nbOK++;
					} else {
						echo ' : KO : '.htmlspecialchars($aTest[1]).'] expected. ['.htmlspecialchars($aResult).'] obtained';
						$nbKO++;
					}
				} else {
					echo ' : KO : Exception '.htmlspecialchars($aTest[1]).' expected';
					$nbKO++;
				}
			} catch (Exception $ex) {
				if ($isExceptionExpected) {
					if (assert($ex->getCode() === $aTest[1])) {
						echo ' : OK';
						$nbOK++;
					} else {
						echo ' : KO : '.htmlspecialchars($aTest[1]).'] expected. ['.htmlspecialchars($ex->getCode()).'] obtained';
						$nbKO++;
					}
				} else {
					echo ' : KO : '.$ex->getMessage().'<br/>['.htmlspecialchars($aTest[1]).'] expected. ['.htmlspecialchars($aResult).'] obtained';
					$nbKO++;
				}
			}
			echo '<br/>';
		}
	}
	echo '<b># OK : '.$nbOK.'<br/>';
	echo '# KO : '.$nbKO.'</b><br/>';
	echo 'Dump for memory :<br/>';
	ob_start();
		var_dump($In);
		var_dump($In2);
	$out=ob_get_contents();
	ob_end_clean();
	echo htmlentities($out);
}

runTests($Tests);

echo 'Memory max peek usage : '.(memory_get_peak_usage(true)-$mem[0]).' octets<br/>';
echo 'Memory usage : '.(memory_get_usage(true)-$mem[1]).' octets<br/>';
******/
