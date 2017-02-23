<?php 
/**
 * SqlBindField class
 *
 * @author     Richaud Julien "Fladnag"
 * @package    salt\sql
 */
namespace salt;

/**
 * Handling Fields and Binds for Query and SqlExpr
 */
abstract class SqlBindField {

	/**
	 * @var int bind unique number */
	private static $_salt_bindNumber=0;

	/**
	 * @var int bind pagination unique number */
	private static $_salt_bindPaginationNumber=0;
	
	/**
	 * @var mixed[] list of binds
	 * @content <pre>array of bindName => array(
	 * 					'value' => mixed // value of bind
	 * 					'type' => int FieldType // type of field
	 * 					'private' => boolean // if value is private data (like passwords)
	 * 			)</pre> */
	private $_salt_binds = array();

	/**
	 * @var string SQL text that use the binds
	 */
	private $_salt_text = NULL;
	
	/**
	 * @var string[][] list of local binds by source
	 * @content <pre>array of (ClauseType => array of bindName)</pre>
	 */
	private $_salt_sources = array();
	/**
	 * @var SqlBindField[][][] list of linked binds
	 * @content <pre>array of (ClauseType (source) => array of ClauseType(dest) => array of SqlBindField)</pre>
	 */
	private $_salt_others = array();
	
	/***
	 * @var boolean TRUE if binds values are private (do not display in debug queries)
	 */
	private $_salt_privateBinds = FALSE;
	
	/**
	 * Add a bind
	 * @param mixed $value value of bind
	 * @param int $type (FieldType) type of the field
	 * @param string $source (Optional) ClauseType or other text : clause where the bind will be used, if we know it
	 * @return string the bind name
	 */
	protected function addBind($value, $type, $source = ClauseType::ALL) {
		$this->checkNotResolved();
		
		if (is_array($value)) { // handle tuples
			$binds = array();
			foreach($value as $v) {
				$binds[] = $this->addBind($v, $type, $source);
			}
			return '('.implode(',', $binds).')';
		}

		$bind = ':v'.(self::$_salt_bindNumber++);

		if ($type === NULL) {
			$type = FieldType::guessType($value);
		}

		$this->_salt_binds[$bind]=array(
				'value' => $value,
				'type' => $type,
				'private' => $this->_salt_privateBinds,
		);

		if ($source === NULL) {
			$source = ClauseType::ALL;
		}
		
		if (!isset($this->_salt_sources[$source])) {
			$this->_salt_sources[$source] = array();
		}
		$this->_salt_sources[$source][] = $bind;
		
		return $bind;
	}

	/**
	 * Set all binds values as private. They will be hidden in debug queries
	 * @params boolean $privateBinds (Optional, TRUE) hide binds values in debug queries
	 * @return SqlBindField the current object
	 */
	public function privateBinds($privateBinds = TRUE) {
		$this->_salt_privateBinds = $privateBinds;
		return $this;
	}
	
	/**
	 * Check binds are private
	 * @return boolean TRUE if privateBinds has been called 
	 */
	public function isPrivateBinds() {
		return $this->_salt_privateBinds;
	}
	
	/**
	 * Retrieve binds for pagination (LIMIT clause)
	 * @param Pagination $pagination the Pagination object
	 * @return mixed[][] binds for the Pagination : array of bindName => array of ('value' => ..., 'type' => ...)
	 */
	public static function getPaginationBinds(Pagination $pagination) {
		$offset = ':L'.(self::$_salt_bindPaginationNumber++);
		$limit = ':L'.(self::$_salt_bindPaginationNumber++);
		$binds = array(
			$offset => array(
				'value' => $pagination->getOffset(),
				'type' => FieldType::NUMBER,
				'private' => FALSE,
			),
			$limit => array(
				'value' => $pagination->getLimit(),
				'type' => FieldType::NUMBER,
				'private' => FALSE,
			),
		);
		return $binds;
	}
	
	/**
	 * Return all binds
	 * @param string $source (Optional) ClauseType or specific text for restrict returned binds to specified source
	 * @return mixed[][] list of binds : array of array('value' => ..., 'type' => ...)
	 */
	public function getBinds($source = ClauseType::ALL) {

		$result = array();
		
		if ($source === NULL) {
			$source = ClauseType::ALL;
		}
		
		if ($source === ClauseType::ALL) { // not really needed, but avoid the useless for in that case
			$result = $this->_salt_binds;
		} else {
			// all local binds
			foreach($this->_salt_sources as $src => $binds) {
				if (($source === ClauseType::ALL) || ($source === $src)) {
					$bindKeys = array_flip($binds);
					$result = array_merge($result, array_intersect_key($this->_salt_binds, $bindKeys));
				}
			}
		}
		
		// all linked binds
		foreach($this->_salt_others as $src => $other) {
			if (($source === ClauseType::ALL) || ($source === $src)) {
				foreach($other as $dest => $otherBinds) {
					foreach($otherBinds as $otherBind) {
						$result = array_merge($result, $otherBind->getBinds($dest));
					} // each other
				} // each other by DEST
			} // have to include
		} // each other by SOURCE
		
		return $result;
	}

	/**
	 * Remove binds (local and linked) of the specified source.
	 * @param string $source ClauseType of specific text
	 */
	protected function removeBinds($source) {
		
		$this->checkNotResolved();

		if (isset($this->_salt_sources[$source])) {
			foreach($this->_salt_sources[$source] as $binds) {
				$bindKeys = array_flip($binds);
				$this->_salt_binds = array_diff_key($this->_salt_binds, $bindKeys);
			}
			unset($this->_salt_sources[$source]);
		}
		
		if (isset($this->_salt_others[$source])) {
			unset($this->_salt_others[$source]);
		}
	}
	
	private function checkNotResolved() {
		if ($this->_salt_text !== NULL) {
			throw new SaltException('Cannot change SQL query after resolving');
		}
	}
	
	/**
	 * Link another SqlBindField to this bindFields.
	 * @param SqlBindField $other the other SqlBindField to link
	 * @param string $source clause usage of the SqlBindField if we known it
	 * @param string $otherSource restrict link to this source in $other
	 * @throws SaltException if this SqlBindField is already resolved
	 */
	protected function linkBindsOf(SqlBindField $other, $source = ClauseType::ALL, $otherSource = ClauseType::ALL) {

		$this->checkNotResolved();
		
		if ($source === NULL) {
			$source = ClauseType::ALL;
		}
		if (!isset($this->_salt_others[$source])) {
			$this->_salt_others[$source]=array();
		}
		if ($otherSource === NULL) {
			$otherSource = ClauseType::ALL;
		}
		if (!isset($this->_salt_others[$source][$otherSource])) {
			$this->_salt_others[$source][$otherSource]=array();
		}		

		$this->_salt_others[$source][$otherSource][] = $other;
	}

	/**
	 * Escape a name for SQL use with backquote
	 * @param string $name a SQL name : database, table, field, alias, etc... 
	 * @return string SQL escaped text with backquote.
	 */
	public static function escapeName($name) {
		return '`'.$name.'`';
	}
	
	/**
	 * Build the SQL text that using the binds
	 * @return string the SQL text
	 */
	abstract protected function buildSQL();
	
	/**
	 * Retrieve the SQL text
	 * @return string the memoized SQL text
	 */
	public final function toSQL() {
		if ($this->_salt_text === NULL) {
			$this->_salt_text = $this->buildSQL();
		}
		return $this->_salt_text;
	}
}
