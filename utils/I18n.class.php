<?php
/**
 * I18n class
 *
 * @author     Richaud Julien "Fladnag"
 * @package    salt\utils
 */
namespace salt;

/*
 * This class is inspired by https://github.com/Philipp15b/php-i18n<br/>
 * The original project is great, but not usable "as is" :<br/>
 * - The CC Licence is too "open source restrictive" : SALT have a MIT licence for allowing project with another licence to use it.<br/>
 * 		The CC Licence force other project to also use the same CC licence<br/>
 * - chmod 777 on php class file is too much, and can produce security issues<br/>
 * - It include spyc lib (for yaml parsing, MIT licence) : https://github.com/mustangostang/spyc, but not the last version<br/>
 * - Yaml arrays are not well supported due to storage format (array in constant are not allowed before PHP 5.6)<br/>
 * - special characters in keys ('-' for example) are not handled, and produce parse error<br/>
 * - the class is declared in global namespace<br/>
 */

/**
 * Handle message translation
 *
 * 1) Initialize application<br/>
 * $i18n = I18n::getInstance(NAME, APPLICATION PATH);<br/>
 * retrieve next instances with $i18n = I18n::getInstance(NAME);
 *
 * 2) Retrieve locales<br/>
 * $en = $i18n->init(LOCALE)->get();<br/>
 * or: $i18n->init(LOCALE)->alias('L');
 *
 * 3) Retrieve text<br/>
 * $en::KEY<br/>
 * or: L::KEY
 *
 * For better performance :<br/>
 * - When a language file is modified, call $i18n->generate(); (do NOT call at each page !)<br/>
 * - Initialize application with I18n::getInstance(NAME, APPLICATION PATH, I18n::MODE_USE_GENERATED)<br/>
 */
class I18n {
	/** default language */
	const DEFAULT_LOCALE = 'en';
	/** localization file extension */
	const EXTENSION = 'yml';

	/** default path for write generated class files */
	const DEFAULT_CACHE_PATH = 'cache';
	/** default path for read yaml files */
	const DEFAULT_LANG_PATH = 'lang';

	/** location of Spyc main file in SALT */
	const SPYC_PATH = 'vendor/spyc-0.5.1/Spyc.php';

	/** With this mode, the initLocale() method will check locale file last modification and regenerate locale class if needed. We call filemtime() and file_exists() */
	const MODE_REGENERATE_ON_THE_FLY = 1;
	/** With this mode, the initLocale() method will include class file generated by a previous call of generate() method, or failed if the class does not exists. */
	const MODE_USE_GENERATED = 2;

	/** Debug only, set to TRUE for enclose every string with # **/
	const DEBUG = FALSE;

	/**
	 * @var I18n[] Instances indexed by application name */
	private static $_saltInstances = array();

	/**
	 * @var string absolute path of generated class files */
	private $_saltCachePath = NULL;
	/**
	 * @var string absolute path of yaml files */
	private $_saltLangPath = NULL;
	/**
	 * @var string application name */
	private $_saltName = NULL;
	/**
	 * @var string current locale */
	private $_saltLocale = self::DEFAULT_LOCALE;

	/**
	 * @var int how to retrieve locales classes. see self::MODE_* */
	private $_saltGenerationMode = self::MODE_REGENERATE_ON_THE_FLY;

	/**
	 * @var string[] list of initialized locales as : locale => className or FALSE if not exists */
	private $_saltInitializedLocales = array();

	/**
	 * Create a new instance for an application
	 *
	 * @param string $name Application name
	 * @param string $path absolute path of the application
	 * @param int $mode set it to a self::MODE_* value for change how locales classes are loaded
	 */
	private function __construct($name, $path, $mode) {
		if ($path !== NULL) {

			if (substr($path, -1) !== DIRECTORY_SEPARATOR) {
				$path.=DIRECTORY_SEPARATOR;
			}

			$this->setCachePath($path.self::DEFAULT_CACHE_PATH);
			$this->setLangPath($path.self::DEFAULT_LANG_PATH);
		}

		$this->_saltGenerationMode = $mode;
		$this->_saltName = $name;
	}

	/**
	 * Create or retrieve an I18n instance.
	 *
	 * @param string $name name of the I18n instance to retrieve
	 * @param string $rootPath absolute path of an application. Can be used to quickly initialize cache and lang paths.
	 * @param int $mode set it to a self::MODE_* value for change how locales classes are loaded
	 * @return static
	 */
	public static function getInstance($name, $rootPath = NULL, $mode = self::MODE_REGENERATE_ON_THE_FLY) {
		if (!isset(self::$_saltInstances[$name])) {
			self::$_saltInstances[$name] = new static($name, $rootPath, $mode);
		} else if ($rootPath !== NULL) {
			throw new SaltException('I18n is already initialized for application ['.$name.'].');
		}
		return self::$_saltInstances[$name];
	}

	/**
	 * Check the generation mode
	 * @param int $mode a generation mode
	 * @return boolean TRUE if current generation mode is $mode
	 */
	public function isGenerationMode($mode) {
		return $this->_saltGenerationMode === $mode;
	}


	/**
	 * Throw an exception if any locale is already initialized
	 *
	 * @throws SaltException if method init() was called
	 */
	private function checkNoneLocaleInitialized() {
		if (count($this->_saltInitializedLocales) > 0) {
			throw new SaltException('Cannot call this method after init() call.');
		}
	}

	/**
	 * Set the path to seach yaml files
	 *
	 * @param string $path absolute path that contains yaml files
	 * @return \salt\I18n current instance
	 */
	public function setLangPath($path) {
		$this->checkNoneLocaleInitialized();
		if (substr($path, -1) !== DIRECTORY_SEPARATOR) {
			$path.=DIRECTORY_SEPARATOR;
		}
		$this->_saltLangPath = $path;
		return $this;
	}

	/**
	 * Set the path to save generated class files
	 *
	 * @param string $path absolute path that will contains PHP classes
	 * @return \salt\I18n current instance
	 */
	public function setCachePath($path) {
		$this->checkNoneLocaleInitialized();
		if (substr($path, -1) !== DIRECTORY_SEPARATOR) {
			$path.=DIRECTORY_SEPARATOR;
		}
		$this->_saltCachePath = $path;
		return $this;
	}

	/**
	 * Set an alias for a locale class
	 *
	 * @param string $alias name of the class (with namespace) to use
	 * @param string $locale locale to alias, if not provided, use the locale of the last init() call
	 */
	public function alias($alias, $locale = NULL) {
		if ($locale === NULL) {
			$locale = $this->_saltLocale;
		}
		$class = $this->get($locale);

		if (!class_alias($class, $alias)) {
			throw new SaltException('Cannot alias class ['.$class.'] to ['.$alias.'].');
		}
	}

	/**
	 * Normalize locale to [_a-z]*
	 *
	 * @param string $locale raw locale like en-US
	 * @return string normalized locale like en_us
	 */
	private function normalizeLocale($locale) {
		return preg_replace('#[^a-z]#', '_', strtolower($locale));
	}

	/**
	 * Retrieve all compatibles locales
	 *
	 * @param string $locale locale like xx-yy
	 * @return string[] array of locales, for example [xx-yy, xx]
	 */
	private function compatibleLocales($locale) {
		static $results = array();

		if (empty($locale)) {
			return array();
		}

		$locale = $this->normalizeLocale($locale);
		if (!isset($results[$locale])) {
			$result = array();
			$current = '';
			do {
				$pos = strpos($locale, '_', strlen($current)+1);
				if ($pos === FALSE) {
					$pos = strlen($locale);
				}
				$current = substr($locale, 0, $pos);
				$result[] = $current;
				$results[$current] = array_reverse($result);
			} while(strlen($current) != strlen($locale));
		}

		return $results[$locale];
	}

	/**
	 * Retrieve class for an initialized locale
	 *
	 * @param string $locale locale to retrieve or NULL for use the last initialized locale
	 * @return \stdClass a class for use the specified locale
	 */
	public function get($locale = NULL) {
		if ($locale === NULL) {
			$locales = array($this->_saltLocale);
		} else {
			$locales = $this->compatibleLocales($locale);
		}
		foreach($locales as $loc) {
			if (isset($this->_saltInitializedLocales[$loc]) && ($this->_saltInitializedLocales[$loc] !== FALSE)) {
				return $this->_saltInitializedLocales[$loc];
			}
		}
		throw new SaltException('Current locale ['.$locale.'] is not initialized. Call init() before '.__METHOD__.'.');
	}

	/**
	 * Initialize the first available locale in the list
	 *
	 * @param string|string[] $locales locale to load, in preference order. Load the first available locale.<br/>
	 * 		Generic locale has not required.<br/>
	 * 		init('en_us') is equivalent to init(array('en_us', 'en'))
	 * @throws SaltException
	 * @return static current instance
	 */
	public function init($locales) {

		Benchmark::start('salt.i18n.init');

		if (!is_array($locales)) {
			$locales = array($locales);
		}

		if ((I18N_DEFAULT_LOCALE !== NULL) && (!in_array(I18N_DEFAULT_LOCALE, $locales))) {
			$locales[] = I18N_DEFAULT_LOCALE;
		}

		foreach($locales as $locale) {
			if ($this->initLocale($locale) !== NULL) {
				Benchmark::stop('salt.i18n.init');
				return $this;
			}
		}

		if ($this->_saltGenerationMode === self::MODE_USE_GENERATED) {
			throw new SaltException('Cannot find any generated class file compatible with ['.implode(',', $locales).'] in ['.$this->_saltCachePath.']. Please check '.__CLASS__.' configuration or call generate() again.');
		} else {
			throw new SaltException('Cannot find any language file compatible with ['.implode(',', $locales).'] in ['.$this->_saltLangPath.']. Please check '.__CLASS__.' configuration.');
		}
	}

	/**
	 * Retrieve root of a locale
	 *
	 * @param string $locale locale like xx_yy
	 * @return string root locale like xx
	 */
	private function rootLocale($locale) {
		$pos = strpos($locale, '_');
		return ($pos === FALSE)?$locale:substr($locale, 0, $pos);
	}

	/**
	 * Retrieve locale to create with all parents
	 *
	 * @param string $locale locale
	 * @param boolean $parent TRUE if is parent locale
	 * @return mixed[] array locale => yamlFilePath. yamlFilePath is NULL if the file don't exist
	 */
	private function retrieveClassesToCreate($locale, $parent = FALSE) {
		$allLocales = array();
		foreach($this->compatibleLocales($locale) as $compatibleLocale) {
			$source = $this->_saltLangPath.$compatibleLocale.'.'.self::EXTENSION;

			if (($this->_saltGenerationMode === self::MODE_USE_GENERATED) || file_exists($source)) {
				// first locale to create : if file exists
				$allLocales[$compatibleLocale] = $source;
			} else if (count($allLocales) === 0) {
				// first locale not exists
				break;
			} else {
				// also create all parent locales
				$allLocales[$compatibleLocale] = NULL;
			}
		}

		if ((count($allLocales) > 0) && !$parent) {
			if ((I18N_DEFAULT_LOCALE !== NULL) && ($this->rootLocale(I18N_DEFAULT_LOCALE) !== $this->rootLocale($locale))) {
				$allLocales += $this->retrieveClassesToCreate(I18N_DEFAULT_LOCALE, TRUE);
			}
		}

		return $allLocales;
	}

	/**
	 * Check every locale files.
	 *
	 * Display keys missing or not defined in default locale for each locale files
	 *
	 * DO NOT CALL this method at each page ! You have to call it only once, after a language file was modified.
	 *
	 * @param boolean $display TRUE for display check report
	 * @return mixed[] Report as :<br/>
	 * 		locale => array(<br/>
	 * 			'count' => number of entries,<br/>
	 * 			'missing' => entries missing in locale but present in default,<br/>
	 * 			'orphan' => entries in locale but missing in default<br/>
	 * 		)<br/>
	 * 		First locale is default locale
	 */
	public function check($display = FALSE) {

		require_once PATH.self::SPYC_PATH;

		$localesData = array();
		$dh = opendir($this->_saltLangPath);
		while(($f = readdir($dh)) !== FALSE) {
			$pos = strrpos($f, '.');
			if (($pos !== FALSE) && ($pos > 0)) {
				$name = substr($f, 0, $pos);
				$ext = substr($f, strlen($name)+1);
				if (strtolower($ext) === self::EXTENSION) {
					$localesData[self::normalizeLocale($name)] = \Spyc::YAMLLoad($this->_saltLangPath.DIRECTORY_SEPARATOR.$f);
				}
			}
		}

		$defaultKeys = self::array_keys_recursive($localesData[I18N_DEFAULT_LOCALE], '/');

		$report = array();
		$report[I18N_DEFAULT_LOCALE] = array('count' => count($defaultKeys), 'missing' => array(), 'orphan' => array());

		foreach($localesData as $locale => $data) {
			if ($locale !== I18N_DEFAULT_LOCALE) {
				$keys = self::array_keys_recursive($data, '/');
				$report[$locale] = array('count' => count($keys), 'missing' => array(), 'orphan' => array_diff($keys, $defaultKeys));
				if (strpos($locale, '_') === FALSE)  {
					$report[$locale]['missing'] = array_diff($defaultKeys, $keys);
				}
			}
		}

		if ($display) {
			echo '== Check locales files for ['.$this->_saltName.'] application ==<br/>';
			echo 'Default locale ['.I18N_DEFAULT_LOCALE.'] contains '.$report[I18N_DEFAULT_LOCALE]['count'].' entries<br/>';

			foreach($report as $locale => $data) {
				echo $locale.' contains '.$data['count'].' entries : ';
				$errors = array();
				if (count($data['missing']) > 0) {
					$errors[] = count($data['missing']).' missing entries : '.implode(', ', $data['missing']);
				}
				if (count($data['orphan']) > 0) {
					$errors[] = count($data['orphan']).' orphan entries not in default locale : '.implode(', ', $data['orphan']);
				}
				if (count($errors) === 0) {
					echo 'OK';
				} else {
					echo 'KO<br/>';
					echo '&nbsp;&nbsp;'.implode('<br/>&nbsp;&nbsp;', $errors);
				}
				echo '<br/>';
			}
			flush();
		}

		return $report;
	}

	/**
	 * Extract all keys from multi dimensionnal array
	 * @param mixed[] $array multi dimensionnal array
	 * @param string $separator the separator for merge sub keys
	 * @param string $prefix internal prefix for recursivity
	 * @return string[] key list. If a value is an array, all array keys will be added with a prefix : the current key and $separator.<br/>
	 * Example :<br>
	 * [1 => a, 2 => [ b => A, c => B ]] with '_' as separator will return [1, 2_b, 2_c]
	 */
	private static function array_keys_recursive($array, $separator, $prefix='') {
		$result = array();

		foreach($array as $k => $v) {
			$k = self::normalizeKey($k);
			if (is_array($v)) {
				$result = array_merge($result, self::array_keys_recursive($v, $separator, $prefix.$k.$separator));
			} else {
				$result[] = $prefix.$k;
			}
		}

		return $result;
	}

	/**
	 * Generate class files for all available locales.
	 *
	 * DO NOT CALL this method at each page ! You have to call it only once, after a language file was modified<br/>
	 * If you don't want to do this, use I18n::MODE_REGENERATE_ON_THE_FLY in getInstance() instead.
	 *
	 * @param boolean $display TRUE for display report after generate
	 * @return string[] list of initialized locales
	 */
	public function generate($display = FALSE) {

		$existingLocales = array();
		$dh = opendir($this->_saltLangPath);
		while(($f = readdir($dh)) !== FALSE) {
			$pos = strrpos($f, '.');
			if (($pos !== FALSE) && ($pos > 0)) {
				$name = substr($f, 0, $pos);
				$ext = substr($f, strlen($name)+1);
				if (strtolower($ext) === self::EXTENSION) {
					$existingLocales[] = $name;
				}
			}
		}

		$this->_saltInitializedLocales = array();
		foreach($existingLocales as $locale) {
			$this->initLocale($locale, TRUE);
		}

		if ($display) {
			echo '== Generate locales classes for ['.$this->_saltName.'] application ==<br/>';
			echo count($existingLocales).' classes generated for locales : '.implode(', ', $existingLocales).'.'; flush();
		}

		return $existingLocales;
	}

	/**
	 * Generate a .htaccess file in $dir
	 * @param string $dir directory
	 */
	private function generateHtaccess($dir) {
		$file = $dir;
		if (substr($file, -1) !== DIRECTORY_SEPARATOR) {
			$file.=DIRECTORY_SEPARATOR;
		}
		$file.='.htaccess';

		if (!file_exists($file)) {
			$content=<<<'HTACCESS'
<IfModule mod_version.c>
	<IfVersion >= 2.4>
		Require all denied
	</IfVersion>
	<IfVersion < 2.4>
		Deny from all
	</IfVersion>
</IfModule>

<IfModule !mod_version.c>
	<IfModule mod_authz_core.c>
		Require all denied
	</IfModule>
	<IfModule !mod_authz_core.c>
		Deny from all
	</IfModule>
</IfModule>
HTACCESS;
			file_put_contents($file, $content);
		}
	}

	/**
	 * Initialize a locale
	 *
	 * @param string $locale locale to initialize
	 * @param boolean $forceGenerate TRUE for generate class file for MODE_USE_GENERATED mode
	 * @return static current I18n or NULL if locale does not exists
	 */
	private function initLocale($locale, $forceGenerate = FALSE) {
		if (!isset($this->_saltInitializedLocales[$locale])) {

			if ($this->_saltCachePath === NULL) {
				throw new SaltException('The cache path is not set. Please check '.__CLASS__.' configuration.');
			}
			if ($this->_saltLangPath === NULL) {
				throw new SaltException('The lang path is not set. Please check '.__CLASS__.' configuration.');
			}

			// retrieve all locales to check/create
			$allLocales = $this->retrieveClassesToCreate($locale);

			// no locale to create : stop here
			if (count($allLocales) === 0) {
				$this->_saltInitializedLocales[$locale] = FALSE;
				return NULL;
			}

			$namespace = __NAMESPACE__.'\\i18n\\'.$this->_saltName;
			// create all classes in reverse order : from generic to specific for create parent before children
			$parent = NULL;
			foreach(array_reverse($allLocales, TRUE) as $currentLocale => $source) {

				// a locale can be already initialized by a previous init() call
				if (isset($this->_saltInitializedLocales[$currentLocale])) {
					$parent = $currentLocale;
					continue;
				}

				$prefixFilename = preg_replace('/[^-_A-Za-z0-9]/', '_', $this->_saltName);
				$extension = '.class.php';

				if (($this->_saltGenerationMode === self::MODE_REGENERATE_ON_THE_FLY) && (!$forceGenerate)) {
					$date = 0;
					if ($source !== NULL) {
						$date = date('YmdHis', filemtime($source));
					}
					$prefixFilename.= '.gen';
					$extension='.'.$date.$extension;
				}

				$prefixFilename.='.'.$currentLocale;
				$fileName = $this->_saltCachePath.$prefixFilename.$extension;

				$fullClassName = $namespace.'\\'.$currentLocale;

				if (($this->_saltGenerationMode === self::MODE_USE_GENERATED) && !$forceGenerate) {
					$result = @include_once($fileName);
					if ($result === FALSE) {
						return NULL;
					}
				} else {
					if (!file_exists($fileName)
					|| ($forceGenerate)) {

						if (class_exists($fullClassName, TRUE) && !$forceGenerate) {
							throw new SaltException('A class named ['.$fullClassName.'] already exists. '.__CLASS__.' cannot generate the same class.');
						}

						$this->generateHtaccess($this->_saltCachePath);

						$this->generateClass($source, $fileName, $namespace, $currentLocale, $parent);
						$parent = $currentLocale;

						if (($this->_saltGenerationMode === self::MODE_REGENERATE_ON_THE_FLY) && !$forceGenerate) {

							// remove old classes
							$d = opendir($this->_saltCachePath);
							while(($file = readdir($d)) !== FALSE) {
								// file start with prefix...
								if ((strpos($file, $prefixFilename) === 0)
								// but not the same date
								&& (strpos($file, $prefixFilename.'.'.$date) === FALSE)) {
									@unlink($this->_saltCachePath.$file);
								}
							}
						}
					} // if !file_exists

					if (!$forceGenerate) {
						// load the implementation
						require_once($fileName);
					}
				}

				// set locale to last found locale
				$locale = $currentLocale;
				$this->_saltInitializedLocales[$locale] = $fullClassName;
			} // foreach locale
		} // not initialized

		$this->_saltLocale = $locale;

		return $this;
	}

	/**
	 * Generate and write a class to disk
	 *
	 * @param string $source locale file
	 * @param string $destination destination file
	 * @param string $namespace namespace of the class
	 * @param string $locale locale to generate
	 * @param string $parent parent locale
	 */
	private function generateClass($source, $destination, $namespace, $locale, $parent) {

		Benchmark::increment('salt.i18n.generate');
		Benchmark::start('salt.i18n.generate');

		$dir = dirname($destination);
		if (!is_dir($dir)) {
			if (!mkdir($dir, NULL, TRUE)) {
				throw new SaltException('Cannot create the cache directory ['.$dir.']. Please create it manually or change it by configuration.');
			}
		}

		// parse & create
		$data = array();
		if ($source !== NULL) {
			require_once PATH.self::SPYC_PATH;
			$data = \Spyc::YAMLLoad($source);
		} else {
			$source = $this->_saltLangPath.$locale.'.'.self::EXTENSION;
		}

		$infos = ($this->_saltGenerationMode === self::MODE_REGENERATE_ON_THE_FLY)?'on the fly':'by I18n::generate() method';

		$classContent = self::convertToClass($source, $namespace, $locale, $parent, $data, $infos);

		if (!file_put_contents($destination, $classContent)) {
			throw new SaltException('Cannot write the cache file ['.$destination.']. Please check '.__CLASS__.' configuration.');
		}

		Benchmark::stop('salt.i18n.generate');
	}

	/**
	 * Create a PHP class source
	 *
	 * @param string $source original filename that contains raw data (yaml localization file)
	 * @param string $namespace namespace of the class
	 * @param string $className name of the class
	 * @param string $parent name of the parent class (of same namespace)
	 * @param mixed[] $data array as key=>value, value can be an array
	 * @param string $generationInfos additionnal information on generation
	 * @return string PHP code of the class
	 */
	private static function convertToClass($source, $namespace, $className, $parent, array $data, $generationInfos) {
		$fullNamespace = $namespace;
		if ($namespace !== NULL) {
			$fullNamespace = 'namespace '.$namespace.';';
		}

		$source = realpath($source);

		$date = date('Y/m/d H:i:s');

		if ($parent !== NULL) {
			$parent = 'extends '.$parent.' ';
		} else {
			$parent = '';
		}

		if ($generationInfos !== '') {
			$generationInfos = ' '.$generationInfos;
		}

		$datas = self::buildData($data);
		$content = <<<PHP
<?php
/**
 * {$className} class
 *
 * @package    {$namespace}
 */

{$fullNamespace}

/**
 * Class generated at {$date}{$generationInfos}.
 * DO NOT MODIFY MANUALLY
 * Please edit {$source} instead.
 */
class {$className} {$parent}{
{$datas}

	public static function __callStatic(\$name, \$args) {
		return vsprintf(constant("self::" . \$name), \$args);
	}
}
PHP;

		return $content;
	}

	/**
	 * Normalize a locale key
	 * @param string $key key in locale file
	 * @return string normalized key
	 */
	private static function normalizeKey($key) {
		return preg_replace('/[^_a-zA-Z0-9]/', '_', $key);
	}

	/**
	 * Build PHP code for $data
	 *
	 * @param mixed[] $data array as key=>value, value can be an array
	 * @param string $keyPrefix prefix of all keys, do not use on first call
	 * @return string PHP code with all leaf of $data as constant and all node of $data as static function that return an array
	 */
	private static function buildData(array $data, $keyPrefix = '') {
		$result = '';
		foreach($data as $k => $v) {

			if ((self::DEBUG) && !is_array($v)) {
				$v = '#'.$v.'#';
			}

			$key = $keyPrefix.self::normalizeKey($k);
			if (is_array($v)) {
				// add sub values as flatten constants
				$result.=self::buildData($v, $key.'_');

				// build a smart array where values are other constants/methods
				$var = array();
				foreach($v as $kk => $vv) {
					$element = var_export($kk, TRUE).' => self::'.$key.'_'.$kk;
					if (is_array($vv)) {
						$element.='()';
					}
					$var[] = $element;
				}
				$var = implode(",\n\t\t\t", $var);

				// add value as full array
				$result.=<<<PHP

	public static function {$key}() {
		return array(
			{$var}
		);
	}
PHP;
			} else {
				$var = var_export($v, TRUE);
				$result.=<<<PHP

	const {$key} = {$var};
PHP;
			}
		}
		return $result;
	}
}
