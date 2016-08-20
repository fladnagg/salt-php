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
}