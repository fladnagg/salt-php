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
Salt::addClassFolder(PATH, __NAMESPACE__, '.class.php', 'vendor');

// add all vendor classes in default namespace
// Salt::addClassFolder(PATH.'vendor', NULL, '.class.php');

// register Salt autoload
spl_autoload_register(__NAMESPACE__.'\Salt::loadClass');

include_once __DIR__.DIRECTORY_SEPARATOR.'version.php';

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
		$caller = self::firstCallerFile();

		return self::computeRelativePath($_SERVER['SCRIPT_FILENAME'], $caller['file'], $destIgnoreLevel);
	}

	/**
	 * Retrieve relative path from request URI to method caller
	 * @param int $destIgnoreLevel (Optionnel, 0) Ignore some folders at the end of the method caller
	 * @return string Relative path between requested URI and method caller (usefull for URI rewrite)
	 */
	public static function webRelativePath($destIgnoreLevel = 0) {

		// retrieve web root path
		$relative = self::relativePath($destIgnoreLevel);

		$caller = implode('/', explode('/', $_SERVER['SCRIPT_NAME'], -1)); // remove file part
		$root = self::computePath($caller, $relative);

		// compute relative path between requested URI and web root
		$rel = self::computeRelativePath($_SERVER['REQUEST_URI'], $root);

		return $rel;
	}

	/**
	 * Return caller file
	 * @param int $ignoreCallers number of first callers to ignore
	 * @return string[] associative array of a backtrace with keys : file, line
	 * @see \debug_backtrace()
	 */
	private static function firstCallerFile($ignoreCallers = 0) {
		$data = debug_backtrace();
		$data = array_slice($data, $ignoreCallers);
		foreach($data as $row) {
			if (isset($row['file']) && $row['file'] !== __FILE__) {
				return $row;
			}
		}
		return NULL;
	}

	/**
	 * Load SALT configuration
	 */
	public static function config() {
		require_once(PATH.'conf/config.php');
		In::setCharset(CHARSET);
		self::initI18n();
	}

	/**
	 * Initialize I18n instance
	 */
	private static function initI18n() {
		static $i18nInitialized = FALSE;

		if (!$i18nInitialized) {
			$i18nInitialized = TRUE;
			$i18n = I18n::getInstance('SALT', PATH, I18N_MODE);
			if (I18N_GENERATE)  {
				$i18n->generate(TRUE);
				echo '<br/>Exit application - please remove salt\I18N_GENERATE constant'; flush();
				exit(0);
			}
			if (I18N_CHECK) {
				$i18n->check(TRUE);
				echo '<br/>Exit application - please remove salt\I18N_CHECK constant'; flush();
				exit(0);
			}
			$i18n->init(I18N_LOCALE)->alias('salt\L');
		}
	}

	/**
	 * Add a folder to the class autoload mecanism.
	 * @param string $folder root folder that contain classes
	 * @param string $namespace (Optional, NULL) Namespace to use for register classes
	 * @param string $pattern (Optional, .class.php) Suffix of files that contains classes
	 * @param string[] $excludes (Optional, NULL) List of folders or files to exclude. Can be a part of a folder/file
	 */
	public static function addClassFolder($folder, $namespace = NULL, $pattern = '.class.php', $excludes = NULL) {
		Benchmark::start('salt.findClasses');
		$resolvedFolder = realpath($folder);
		if ($resolvedFolder === FALSE) {
			// not translated : I18n can be not loaded yet
			throw new SaltException('Directory ['.$folder.'] does not exists');
		}
		self::$ALL_CLASSES += self::findAllClasses($resolvedFolder, $namespace, $pattern, $excludes = NULL);
		Benchmark::stop('salt.findClasses');
	}

	/**
	 * Deep walk in a folder for find files that contains classes
	 * @param string $folder Folder to inspect
	 * @param string $namespace (Optional, NULL) Namespace to use
	 * @param string $pattern (Optional, .class.php) Suffix to use for filter files
	 * @param string[] $excludes (Optional, NULL) List of folders or files to exclude. Can be a part of a folder/file
	 * @return string[] List or found files as ( Namespace\ClassName => FilePath )
	 */
	private static function findAllClasses($folder, $namespace = NULL, $pattern = '.class.php', $excludes = NULL) {
		$classes=array();

		if ($excludes === NULL) {
			$excludes = array();
		}
		if (!is_array($excludes)) {
			$excludes = array($excludes);
		}
		$excludes = array_map('strtolower', $excludes);

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
				$process = TRUE;
				foreach($excludes as $path) {
					if (strpos(strtolower($k), $path) !== FALSE) {
						$process = FALSE;
						break;
					}
				}
				if ($process) {
					$classes[$namespace.substr($v->getFilename(), 0, -$patternSize)]=$k;
				}
			}
		}
		return $classes;
	}

	/**
	 * Load a class
	 * @param string $className Class name with namespace to load
	 * @return boolean TRUE if class found, FALSE otherwise
	 */
	public static function loadClass($className) {
		Benchmark::increment('salt.classes');
		if (isset(self::$ALL_CLASSES[$className])) {
			Benchmark::start('salt.loadClasses');
			include_once(self::$ALL_CLASSES[$className]);
			Benchmark::stop('salt.loadClasses');
			return TRUE;
		}
		// Give a chance to another classloader to find the class
		//throw new SaltException('Unable to find the class '.$className.' required by '.$caller);
		return FALSE;
	}

	/**
	 * Compute a folder from a path and a relative path
	 * @param string $origin a folder, with or without trailing directory $separator
	 * @param string $relative relative folder, with / separator
	 * @param string $separator folder separator for $origin, default to /
	 * @return string the resolved path, always end with $separator
	 */
	public static function computePath($origin, $relative, $separator = '/') {
		$relative = explode('/', $relative);

		if (substr($origin, -strlen($separator)) === $separator) {
			$origin = substr($origin, 0, -strlen($separator));
		}
		$origin = explode($separator, $origin);
		foreach($relative as $path) {
			if (($path === '.') || ($path === '')) {
				// do nothing
			} else if ($path === '..') {
				array_pop($origin);
			} else {
				$origin[]=$path;
			}
		}
		$result = implode($separator, $origin);

		if ($result==='') {
			$result='.';
		}
		if (substr($result, -strlen($separator)) !== $separator) {
			$result.=$separator;
		}
		return $result;
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
	 * @throws SaltException If the origin and destination paths are not both relative or absolute path
	 * @return string Relative path for go to $dest from $from. Return always ends with /
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

			if ($from[0] === '.') {
				$from[0] = '';
			}
			if ($dest[0] === '.') {
				$dest[0] = '';
			}

			if (($from[0] === '') ^ ($dest[0] === '')) { // xor ^^ // smiley ;o)
				// not translated : I18n can be not loaded yet
				throw new SaltException('Cannot compute relative path from a relative and an absolute path. Please provide two absolute or relative paths.');
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

		if (strlen($relative) === 0) {
			$relative='.';
		}

		// add last separator
		if (substr($relative, -1) !== '/') {
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

	/**
	 * Return a list of registered classes in provided path
	 * @param string $path the path to find classes
	 * @return string[] classes as (className => path)
	 */
	public static function getClassesByPath($path) {
		$result = array();

		foreach(self::$ALL_CLASSES as $className => $p) {
			if (strpos($p, $path) === 0) {
				$result[$className] = $p;
			}
		}
		return $result;
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
