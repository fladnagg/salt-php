<?php 
/**
 * SqlDateFormat class
 *
 * @author     Richaud Julien "Fladnag"
 * @package    salt\dao
 */
namespace salt;

/**
 * Predefined SQL date store format
 */
class SqlDateFormat {

	/** field is stored in an integer or text field, as a timestamp. */
	const RAW_TIMESTAMP = '';

	/** field is a TIMESTAMP */
	const TIMESTAMP = 'TIMESTAMP';
	/** field is a DATETIME */
	const DATETIME = 'DATETIME';
	/** field is a DATE */
	const DATE = 'DATE';
	/** field is stored in an integer or text field with format %Y%m%d */
	const SHORT_DATE = 'SHORT_DATE';

	/**
	 * @var string[] format list for all predefined date formats
	 */
	private static $SQL_FORMAT=array(
		self::TIMESTAMP => '%Y-%m-%d %H:%i:%s',
		self::DATETIME => '%Y-%m-%d %H:%i:%s',
		self::DATE => '%Y-%m-%d',
		self::SHORT_DATE => '%Y%m%d',
	);

	/**
	 * Check if a format is compatible with the DATETIME MySQL type
	 * @param string $format predefined format
	 * @return boolean TRUE if the format is compatible with a full mysql date
	 */
	public static function isFullDate($format) {
		return in_array($format, array(
			self::DATETIME,
			self::TIMESTAMP,
		));
	}

	/**
	 * Check if a format is RAW_TIMESTAMP
	 * @param string $format predefined format
	 * @return boolean TRUE if the format is stored as a timestamp
	 */
	public static function isRawTimestamp($format) {
		return $format === self::RAW_TIMESTAMP;
	}

	/**
	 * Convert a format (predefined or not) in real SQL format 
	 * @param string $format Format identifier or SQL format
	 * @return string real SQL format
	 */
	public static function resolve($format) {
		if (isset(self::$SQL_FORMAT[$format])) {
			return self::$SQL_FORMAT[$format];
		}
		return $format;
	}

}