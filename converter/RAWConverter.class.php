<?php
/**
 * RAWConverter class
 *
 * @author     Richaud Julien "Fladnag"
 * @package    salt\converter
 */
namespace salt;

/**
 * Return value without convert it
 */
class RAWConverter extends AbstractConverter {

	/**
	 * {@inheritDoc}
	 * @param mixed $value The value to convert
	 * @return mixed the converted value
	 * @see \salt\AbstractConverter::convert()
	 */
	public function convert($value) {
		return $value;
	}
}