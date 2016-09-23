<?php 
/**
 * FieldType class
 *
 * @author     Richaud Julien "Fladnag"
 * @package    salt\dao
 */
namespace salt;

/**
 * List of Field type
 */
class FieldType {
	/** Field is a boolean : int with value 0 for FALSE, 1 for TRUE */
	const BOOLEAN=10;
	/** Field is a text */
	const TEXT=20;
	/** integer Field is a number */
	const NUMBER=30;
	/** Field is a DATE, DATETIME, TIMESTAMP or int or text field with format YYYYMMDD or YYMMDD, or any valid format 
	 * 
	 * A valid format is a format that can be converted to a timestamp, so with year, month and day*/
	const DATE=40;
	
	
	/**
	 * Guess type of a value from value content
	 * @param mixed $value The value to guess
	 * @return int FieldType 
	 */
	public static function guessType($value) {
		
		if (is_array($value) && (count($value) > 0)) {
			$value = first($value);
		}

		if ($value === NULL) {
			return NULL;
		} else if (is_bool($value)) {
			return FieldType::BOOLEAN;
		} else if (is_numeric($value) && !is_string($value)) {
			return FieldType::NUMBER;
		} else {
			return FieldType::TEXT;
		}
	}
	
}