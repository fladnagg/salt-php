<?php
/**
 * Converter interface
 *
 * @author     Richaud Julien "Fladnag"
 * @package    salt\converter
 */
namespace salt;

/**
 * Convert object values
 *
 * Do NOT use if PHP is multi threaded
 */
interface Converter {

	/**
	 * Convert a value
	 * @param mixed $value The value to convert
	 * @return mixed the converted value
	 */
	public function convert($value);

	/**
	 * Convert a value
	 * @param mixed $value The value to convert for set in object
	 * @return mixed the converted value
	 */
	public function convertForSetter($value);

}

