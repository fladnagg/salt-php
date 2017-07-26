<?php
/**
 * CountQuery class
 *
 * @author     Richaud Julien "Fladnag"
 * @package    salt\sql\queries
 */
namespace salt;

/**
 * Internal query for execution only
 */
class CountQuery extends BaseQuery {

	/**
	 * @var string SQL text of the count query */
	private $_salt__text;
	/**
	 * @var mixed[][] binds of the query : array of bindName => array of ('value' => ..., 'type' => ...)
	 */
	private $_salt__binds;

	/**
	 * Create a new CountQuery
	 * @param string $sqlText SQL text of the count query
	 * @param mixed[][] $binds binds of the query : array of bindName => array of ('value' => ..., 'type' => ...)
	 */
	public function __construct($sqlText, $binds) {
		$this->_salt__text = $sqlText;
		$this->_salt__binds = $binds;
	}

	/**
	 * {@inheritDoc}
	 * @see \salt\SqlBindField::buildSQL()
	 */
	protected function buildSQL() {
		return $this->_salt__text;
	}

	/**
	 * {@inheritDoc}
	 * @param string $source not used here
	 * @see \salt\SqlBindField::getBinds()
	 */
	public function getBinds($source = NULL) {
		return $this->_salt__binds;
	}
}