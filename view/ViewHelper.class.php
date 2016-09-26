<?php 
/**
 * ViewHelper interface
 *
 * @author     Richaud Julien "Fladnag"
 * @package    salt\view
 */
namespace salt;

/**
 * interface of all ViewHelpers.
 */
interface ViewHelper {

	/**
	 * Raw format */
	const RAW='raw';
	const FORMAT_KEY='format';

	/**
	 * Return a text for a column
	 * 
	 * @param Field $field the field to display
	 * @param string $format format to use for change the output
	 * @return string HTML escaped text for describe $field in $format
	 */
	public function column(Field $field, $format=NULL);

	/**
	 * Return a value to display without HTML escaping
	 * 
	 * @param Base $object object that contains the value
	 * @param Field $field the field
	 * @param mixed $value the value to display
	 * @param string $format format to use
	 * @param mixed[] $params parameter passed to Base->FORM or Base->VIEW method
	 * @return string a non-HTML escaped value
	 */
	public function text(Base $object, Field $field, $value, $format, $params);

	/**
	 * Return a value to display with HTML escaping
	 * 
	 * @param Base $object object that contains the value
	 * @param Field $field the field
	 * @param mixed $value the value to display
	 * @param string $format format to use
	 * @param mixed[] $params parameter passed to Base->FORM or Base->VIEW method
	 * @return string a HTML escaped value
	 */
	public function show(Base $object, Field $field, $value, $format, $params);

	/**
	 * Return a full form tag for edit the value
	 * 
	 * @param Base $object object that contains the value
	 * @param Field $field the field
	 * @param mixed $value the value to edit
	 * @param string $format format to use
	 * @param mixed[] $params parameter passed to Base->FORM or Base->VIEW method
	 * @return string a full HTML form tag (input, select, etc...) for editing the value
	 */
	public function edit(Base $object, Field $field, $value, $format, $params);

	/**
	 * Get the Base object who called the ViewHelper
	 * @return Base the objet to work on
	 */
	public function getObject();
}