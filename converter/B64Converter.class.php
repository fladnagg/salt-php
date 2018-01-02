<?php
/**
 * B64Converter class
 *
 * @author     Richaud Julien "Fladnag"
 * @package    salt\converter
 */
namespace salt;

/**
 * Convert value to base64
 */
class B64Converter extends AbstractConverter {

	/**
	 * {@inheritDoc}
	 * @param mixed $value The value to convert
	 * @return mixed the converted value
	 * @see \salt\AbstractConverter::convert()
	 */
	public function convert($value) {
		return base64_encode($value);
	}
}