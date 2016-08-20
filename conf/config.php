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
}
