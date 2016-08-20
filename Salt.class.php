<?php
/**
 * Salt class
 *
 * @author     Richaud Julien "Fladnag"
 * @package    salt
 */
namespace salt;

require_once('utils/Benchmark.class.php');
Benchmark::start('salt.init');

// ===================================================
/**
 * Absolute path to SALT framework
 */
define('salt\PATH', Salt::saltPath());
/**
 * Reserved prefix for everything (field, constant, methods) in child class of SALT classes
 */
define('salt\RESERVED_PREFIX', '_salt');

// add all Salt classes to autoload
Salt::addClassFolder(PATH, __NAMESPACE__);
// register Salt autoload
spl_autoload_register(__NAMESPACE__.'\Salt::loadClass');

/**
 * SALT main class
 */
class Salt {
	/**
	 * @var string[] all classes that can be loaded by our autoload : namespace\class => /full/class/path
	 */
	private static $ALL_CLASSES = array();

	/**
	 * Retrieve relative path from executed PHP script to method caller
	 * @param int $destIgnoreLevel (Optionnel, 0) Ignore some folders at the end of the method caller
	 * @return string Relative path between executed PHP script and method caller
	 */
	public static function relativePath($destIgnoreLevel = 0) {
		$caller = first(debug_backtrace());

		return self::computeRelativePath($_SERVER['SCRIPT_FILENAME'], $caller['file'], $destIgnoreLevel);
	}

	/**
	 * Retrieve relative path from request URI to method caller
	 * @param int $destIgnoreLevel (Optionnel, 0) Ignore some folders at the end of the method caller
	 * @return string Relative path between requested URI and method caller (usefull for URI rewrite)
	 */
	public static function webRelativePath($destIgnoreLevel = 0) {

		$request = implode('/', explode('/',$_SERVER['REQUEST_URI'], -1));
		$exec = implode('/', explode('/', $_SERVER['SCRIPT_NAME'], -1));

		$caller = first(debug_backtrace());

		$root = self::computeRelativePath($_SERVER['SCRIPT_FILENAME'], $caller['file'], $destIgnoreLevel);

		if ($request === $exec) { // no redirection
			return $root;
		}

		return self::computeRelativePath($_SERVER['REQUEST_URI'], $_SERVER['SCRIPT_NAME'], substr_count($root, '/'));
	}

	/**
	 * Load SALT configuration
	 */
	public static function config() {
		require_once(PATH.'conf/config.php');
		In::setCharset(CHARSET);
	}

	/**
	 * Add a folder to the class autoload mecanism.
	 * @param string $folder root folder that contain classes
	 * @param string $namespace (Optional, NULL) Namespace to use for register classes
	 * @param string $pattern (Optional, .class.php) Suffix of files that contains classes
	 */
	public static function addClassFolder($folder, $namespace = NULL, $pattern = '.class.php') {

		Benchmark::start('salt.findClasses');
		self::$ALL_CLASSES += self::findAllClasses(realpath($folder), $namespace, $pattern);
		Benchmark::stop('salt.findClasses');
	}

	/**
	 * Deep walk in a folder for find files that contains classes
	 * @param string $folder Folder to inspect
	 * @param string $namespace (Optional, NULL) Namespace to use
	 * @param string $pattern (Optional, .class.php) Suffix to use for filter files
	 * @return string[] List or found files as ( Namespace\ClassName => FilePath )
	 */
	private static function findAllClasses($folder, $namespace = NULL, $pattern = '.class.php') {
		$classes=array();

		if ($namespace !== NULL) {
			$namespace = $namespace.'\\';
		} else {
			$namespace = '';
		}

		$directory = new \RecursiveDirectoryIterator($folder, \FilesystemIterator::KEY_AS_PATHNAME);

		$iterator = new \RecursiveIteratorIterator($directory);
		// Don't work. Maybe not exists in our PHP version ?
		//$regex = new RegexIterator($iterator, '/+*\.class\.php/', RecursiveRegexIterator::GET_MATCH);
		$patternSize=strlen($pattern);
		foreach($iterator as $k => $v) {
			if (substr($k, -$patternSize)===$pattern) {
				$classes[$namespace.substr($v->getFilename(), 0, -$patternSize)]=$k;
			}
		}
		return $classes;
	}

	/**
	 * Load a class
	 * @param string $className Class name with namespace to load
	 * @throws SaltException if the class don't exists in folders added with self::addClassFolder()
	 */
	public static function loadClass($className) {
		Benchmark::increment('salt.classes');
		//$className = last(explode('\\', $className));
		if (isset(self::$ALL_CLASSES[$className])) {
			Benchmark::start('salt.loadClasses');
			include_once(self::$ALL_CLASSES[$className]);
			Benchmark::stop('salt.loadClasses');
		} else {
			$caller = debug_backtrace();
			if (count($caller) > 0) {
				$caller = $caller[1]['file'].':'.$caller[1]['line'];
			} else {
				$caller = 'unknown';
			}
			throw new SaltException('Unable to find the class '.$className.' required by '.$caller);
		}
	}

	/**
	 * Return the relative path between two folders.
	 *
	 * Parameters can be provided with a file or ends with / (or \)<br/>
	 * All characters after the last / (or \) is ignored
	 *
	 * @param string $from Origin path
	 * @param string $dest Destination path
	 * @param int $destIgnoreLevel (Optional, 0) Ignore some folders at the end of the destination path
	 * @throws \Exception If the origin and destination paths are not both relative or absolute path
	 * @return string Relative path for go to $dest from $from. Return always ends with /, except if empty
	 */
	public static function computeRelativePath($from, $dest, $destIgnoreLevel = 0) {

		$from = first(explode('?', $from, 2));
		$dest = first(explode('?', $dest, 2));

		$from = explode('/', str_replace('\\', '/', $from), -1);
		$dest = explode('/', str_replace('\\', '/', $dest), -1);

		if ($destIgnoreLevel > 0) {
			$dest = array_slice($dest, 0, -$destIgnoreLevel);
		}

		if ((count($from) > 0) && (count($dest) > 0)) {
			if (($from[0] === '') ^ ($dest[0] === '')) { // xor ^^ // smiley ;o)
				throw new \Exception('Cannot compute relative path from a relative and an absolute path. Please provide two absolute or relative paths.');
			}
		}

		$commonPath=count($dest);
		foreach($dest as $i => $eachPath) {
			if ((count($from) == $i) || ($from[$i] !== $eachPath)) {
				$commonPath = $i;
				break;
			}
		}

		// up to common
		$relative = str_repeat('../', count($from)-$commonPath);
		// down to uncommon
		$relative.=implode('/', array_slice($dest, $commonPath));

		// add last separator
		if (strlen($relative)>0 && (substr($relative, -1, 1) !== '/')) {
			$relative.='/';
		}
		return $relative;
	}

	/**
	 * Return absolute SALT path
	 * @return string absolute SALT path
	 */
	public static function saltPath() {
		$base = implode(DIRECTORY_SEPARATOR, explode(DIRECTORY_SEPARATOR, realpath(__FILE__), -1));
		$base.=DIRECTORY_SEPARATOR;
		return $base;
	}
}

/**
 * Get first element of an array
 *
 * - PHP have the reset() method which do pretty the same thing, but we have to use a variable reference as parameter so we cannot
 * 	use an expression without a WARNING.<br/>
 * - "reset" is also not a great name for just retrieve the first element of an array.<br/>
 * - the FALSE return if array is empty is also weird. NULL by default is more logical.
 *
 * @param mixed[] $array the array
 * @param mixed $valueIfEmpty (Optional, NULL) The value to return if array is empty
 * @return mixed the first element of array or $valueIfEmpty if array empty
 */
function first(array $array, $valueIfEmpty = NULL) {
	$r = reset($array);
	return ($r === FALSE)?$valueIfEmpty:$r;
}

/**
 * Get last element of an array
 *
 * - PHP have the array_pop() method which do pretty the same thing, but we have to use a variable reference as parameter so we cannot
 * 	use an expression without a WARNING.<br/>
 * - "array_pop" also change the original array.
 *
 * @param mixed[] $array the array
 * @param mixed $valueIfEmpty (Optional, NULL) The value to return if array is empty
 * @return mixed last element of array or $valueIfEmpty if array empty
 */
function last(array $array, $valueIfEmpty = NULL) {
	$r = array_pop($array);
	return ($r === NULL)?$valueIfEmpty:$r;
}

Benchmark::end('salt.init');
