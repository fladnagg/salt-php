<?php 
/**
 * Dual class
 *
 * @author     Richaud Julien "Fladnag"
 * @package    salt\dao
 */
namespace salt;

/**
 * DAO for dual SQL table
 */
class Dual extends Base {

	/**
	 * {@inheritDoc}
	 * @see \salt\Base::metadata()
	 */
	protected function metadata() {
		parent::registerTableName('dual');
		return array();
	}
}