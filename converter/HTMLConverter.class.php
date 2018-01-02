<?php
/**
 * HTMLConverter class
 *
 * @author     Richaud Julien "Fladnag"
 * @package    salt\converter
 */
namespace salt;

/**
 * Escape value for HTML usage
 */
class HTMLConverter extends AbstractConverter {

	/**
	 * {@inheritDoc}
	 * @param mixed $value The value to convert
	 * @return mixed the converted value
	 * @see \salt\AbstractConverter::convert()
	 */
	public function convert($value) {
		return htmlentities($value, \ENT_QUOTES, CHARSET);
	}
}