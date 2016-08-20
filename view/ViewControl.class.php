<?php 
/**
 * ViewControl class
 *
 * @author     Richaud Julien "Fladnag"
 * @package    salt\view
 */
namespace salt;

/**
 * Choose how to display the current page
 */
class ViewControl {

	/**
	 * EDIT state : FORM return input HTML tags
	 */
	const EDIT = 'edit';
	/**
	 * SHOW state : every FORM act as a VIEW
	 */
	const SHOW = 'show';
	/**
	 * TEXT state : every FORM & VIEW return a not HTML escaped text
	 */
	const TEXT = 'text';

	/**
	 * @var string current type of the view
	 */
	private static $type = self::SHOW;

	/**
	 * Check if a type is SHOW
	 * @param string $type ViewControl type to check, or NULL for current
	 * @return boolean TRUE if parameter (or current if parameter NULL) is SHOW
	 */
	public static function isShow($type = NULL) {
		if ($type === NULL) $type = self::$type;
		return $type === self::SHOW;
	}
	
	/**
	 * Check if a type is EDIT
	 * @param string $type ViewControl type to check, or NULL for current
	 * @return boolean TRUE if parameter (or current if parameter NULL) is EDIT
	 */
	public static function isEdit($type = NULL) {
		if ($type === NULL) $type = self::$type;
		return $type === self::EDIT;
	}
	
	/**
	 * Check if a type is TEXT
	 * @param string $type ViewControl type to check, or NULL for current
	 * @return boolean TRUE if parameter (or current if parameter NULL) is TEXT
	 */
	public static function isText($type = NULL) {
		if ($type === NULL) $type = self::$type;
		return $type === self::TEXT;
	}

	/**
	 * Set the view to SHOW : all element will be displayed for reading only. FORM element are converted to VIEW
	 */
	public static function show() {
		self::$type = self::SHOW;
	}

	/**
	 * Set the view to EDIT : all element declared as FORM will be displayed as form.
	 */
	public static function edit() {
		self::$type = self::EDIT;
	}

	/**
	 * Set the view to TEXT : FORM and VIEW will return <b>non-escaped HTML value</b>
	 */
	public static function text() {
		self::$type = self::TEXT;
	}
}