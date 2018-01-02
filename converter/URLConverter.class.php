<?php
/**
 * URLConverter class
 *
 * @author     Richaud Julien "Fladnag"
 * @package    salt\converter
 */
namespace salt;

/**
 * Escape value for URL
 */
class URLConverter extends AbstractConverter {
	/**
	 * {@inheritDoc}
	 * @param mixed $value The value to convert
	 * @return mixed the converted value
	 * @see \salt\AbstractConverter::convert()
	 */
	public function convert($value) {
		return rawurlencode($value);
	}
}