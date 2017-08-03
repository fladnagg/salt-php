<?php
/**
 * Load default configuration
 *
 * @author     Richaud Julien "Fladnag"
 * @package    salt\conf
 */
namespace salt;

$_defaultValues = array(
	'CHARSET'						=> 'UTF-8',
	'DEFAULT_DATE_DISPLAY_FORMAT' 	=> 'd/m/Y',
	'BENCH_PRECISION'				=> 5,
	'I18N_DEFAULT_LOCALE'			=> 'en',
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
	/** Default locale for ALL I18n instances */
	define('salt\I18N_DEFAULT_LOCALE', 'en');
	/** Locale for SALT I18n instance */
	define('salt\I18N_LOCALE', 'en');
	/** Mode for SALT I18n instance class generation */
	define('salt\I18N_MODE', I18n::MODE_REGENERATE_ON_THE_FLY);
	/** Launch I18n class regeneration */
	define('salt\I18N_GENERATE', FALSE);
	/** Launch I18n locales check */
	define('salt\I18N_CHECK', FALSE);
}
