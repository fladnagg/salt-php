<?php
/**
 * Benchmark class
 *
 * @author     Richaud Julien "Fladnag"
 * @package    salt\utils
 */
namespace salt;

/**
 * Register and return times, count and data elements
 */
class Benchmark {

	/**
	 * @var float[] all active timers : name => float (microtime) */
	private static $inProgress=array();
	/**
	 * @var float[] all stopped timers : name => float (microtime) */
	private static $times=array();
	/**
	 * @var int[] all counters values : name => int */
	private static $counters=array();
	/**
	 * @var mixed[] all data values : name => array(mixed) */
	private static $datas=array();

	/**
	 * Start or restart a timer
	 * @param string $name name of the timer */
	public static function start($name) {
		if (!isset(self::$times[$name])) {
			self::resetTime($name);
		}
		if (!isset(self::$inProgress[$name])) {
			self::$inProgress[$name]=microtime(TRUE);
		}
	}

	/**
	 * Stop a timer
	 * @param string $name name of the timer
	 * @return float time of the stopped timer
	 */
	public static function stop($name) {
		if (isset(self::$inProgress[$name])) {
			self::$times[$name]+=max(0.00, microtime(TRUE) - self::$inProgress[$name]);
			unset(self::$inProgress[$name]);
		}
		return self::$times[$name];
	}

	/**
	 * End a timer and return his value
	 *
	 * The timer is destroyed after the call. If we call start() on it again, it will restart to 0
	 *
	 * @param string $name name of the timer
	 * @return float time of the ended timer
	 */
	public static function end($name) {
		$result = self::stop($name);
		unset(self::$times[$name]);
		return $result;
	}

	/**
	 * Add a time to a timer
	 * @param string $name name of the timer
	 * @param float $time time to add
	 */
	public static function addTime($name, $time) {
		if (!isset(self::$times[$name])) {
			self::resetTime($name);
		}
		self::$times[$name]+=$time;
	}

	/**
	 * Reset a timer
	 * @param string $name name of the timer
	 */
	public static function resetTime($name) {
		self::$times[$name]=0;
	}
	/**
	 * Reset a counter
	 * @param string $name name of the counter
	 */
	public static function resetCount($name) {
		self::$counters[$name]=0;
	}
	/**
	 * Increment a counter
	 * @param string $name name of the counter
	 * @param int $number number to add, default 1
	 * @return int the counter value
	 */
	public static function increment($name, $number=1) {
		if (!isset(self::$counters[$name])) {
			self::resetCount($name);
		}
		self::$counters[$name]+=$number;
		return self::$counters[$name];
	}
	/**
	 * Decrement a counter
	 * @param string $name name of the counter
	 * @param int $number number to substract, default 1
	 * @return int the counter value
	 */
	public static function decrement($name, $number=1) {
		if (!isset(self::$counters[$name])) {
			self::resetCount($name);
		}
		self::$counters[$name]-=$number;
		return self::$counters[$name];
	}
	/**
	 * Add a data
	 * @param string $name the name of the data
	 * @param mixed $value data to append
	 */
	public static function addData($name, $value) {
		if (!isset(self::$datas[$name])) {
			self::$datas[$name]=array();
		}
		self::$datas[$name][]=$value;
	}

	/**
	 * Get a timer value
	 * @param string $name name of a timer
	 * @return float timer value at the last stop
	 */
	public static function getTime($name) {
		return self::$times[$name];
	}

	/**
	 * Get a counter value
	 * @param string $name name of a counter
	 * @return int counter value
	 */
	public static function getCounter($name) {
		return self::$counters[$name];
	}

	/**
	 * Check if a data exists
	 * @param string $name name of a data
	 * @return boolean true if some data exists for name.
	 */
	public static function hasData($name) {
		return isset(self::$datas[$name]);
	}

	/**
	 * Get a data value
	 * @param string $name name of a data to retrieve
	 * @return NULL|mixed[] : NULL if name does not exist. array of registered data otherwise.
	 */
	public static function getData($name) {
		if (!self::hasData($name)) {
			return NULL;
		}
		return self::$datas[$name];
	}

	/**
	 * Get all timers values
	 * @return float[] all timers at their last stop() : name => float
	 */
	public static function getAllTimes() {
		return self::$times;
	}

	/**
	 * Get all counters values
	 * @return int[] all counters : name => int
	 */
	public static function getAllCounters() {
		return self::$counters;
	}

	/**
	 * Get all datas values
	 * @return mixed[][] all datas : name => array (mixed)
	 */
	public static function getAllDatas() {
		return self::$datas;
	}
}