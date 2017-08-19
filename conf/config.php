<?php
/**
 * Load default configuration
 *
 * @author     Richaud Julien "Fladnag"
 * @package    salt\conf
 */
namespace salt;

/**
 * Default locale for ALL I18n instances
 *
 * We cannot allow user to overwrite it. So the only way to change it is here, and it changed it for all applications linked to this SALT installation.<br/>
 * If we allow overwrite in each application, and if two applications use different value, classes generated for SALT framework will became unconsistent :<br/>
 * the I18N_DEFAULT_LOCALE set the parent class of all generated classes, so we cannot have a class A with parent B for an application, and class B with parent A for another.
  */
define('salt\I18N_DEFAULT_LOCALE', 'en');

$_defaultValues = array(
	'CHARSET'						=> 'UTF-8',
	'DEFAULT_DATE_DISPLAY_FORMAT' 	=> 'd/m/Y',
	'BENCH_PRECISION'				=> 5,
	'I18N_LOCALE'					=> 'en',
	'I18N_MODE'						=> I18n::MODE_REGENERATE_ON_THE_FLY,
	'I18N_GENERATE'					=> FALSE,
	'I18N_CHECK'					=> FALSE,
);
foreach($_defaultValues as $k => $v) {
	if (!defined(__NAMESPACE__.'\\'.$k)) {
		/**
		 * @ignore
		 */
		define(__NAMESPACE__.'\\'.$k, $v);
	}
}


if (false) { // for doc...
	/** Default charset */
	define('salt\CHARSET', 'UTF-8');
	/** Default PHP date format for display a date field */
	define('salt\DEFAULT_DATE_DISPLAY_FORMAT', 'd/m/Y');
	/** Default precision of Benchmark timers */
	define('salt\BENCH_PRECISION', 5);
	/** Locale for SALT I18n instance */
	define('salt\I18N_LOCALE', 'en');
	/** Mode for SALT I18n instance class generation */
	define('salt\I18N_MODE', I18n::MODE_REGENERATE_ON_THE_FLY);
	/** Launch I18n class regeneration */
	define('salt\I18N_GENERATE', FALSE);
	/** Launch I18n locales check */
	define('salt\I18N_CHECK', FALSE);
}
