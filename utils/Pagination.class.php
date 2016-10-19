<?php 
/**
 * Pagination class
 *
 * @author     Richaud Julien "Fladnag"
 * @package    salt\utils
 */
namespace salt;

/**
 * Handling paging and SQL offset/limit
 */
class Pagination {
	/**
	 * default limit/page size */
	const DEFAULT_LIMIT=100;

	/**
	 * @var int total count of elements */
	private $count = -1;
	/**
	 * @var int current offset */
	private $offset = 0;
	/**
	 * @var int current limit/page size */
	private $limit;
	/**
	 * @var boolean true if object is locked : cannot be used for paging, only for SQL offset/limit */
	private $locked = false;

	/**
	 * Create a new Pagination object
	 * @param int $offset current offset
	 * @param int $limit limit / page size
	 * @param boolean $locked true if object is used for SQL offset/limit only and not for paging
	 */
	public function __construct($offset, $limit = Pagination::DEFAULT_LIMIT, $locked = false) {
		$this->limit = $limit;
		$this->setOffset($offset);
		$this->locked = $locked;
	}

	/**
	 * Check if pagination is locked
	 * @return boolean true if this object is used for SQL offset/limit only and not for paging
	 */
	public function isLocked() {
		return $this->locked;
	}

	/**
	 * Set the current offset
	 * @param int $offset current offset
	 */
	public function setOffset($offset) {
		if ($offset == NULL) {
			$offset = 0;
		}
		$this->offset = max(0, $offset);
	}

	/**
	 * Return TRUE if setCount() is not called with value greater than or equal 0
	 * @return boolean TRUE if the Pagination object is not used
	 */
	public function isEmpty() {
		return ($this->count === -1);
	}
	
	/**
	 * Return the total size of elements
	 * @return int total size of elements
	 */
	public function getCount() {
		return max(0, $this->count);
	}

	/**
	 * Set the total size of elements
	 * @param int $count total size of elements
	 */
	public function setCount($count) {
		$this->count = $count;
// 		if ($this->offset > $count) {
// 			$this->offset = 0;
// 		}
	}

	/**
	 * Get current offset
	 * @return int current offset
	 */
	public function getOffset() {
		return $this->offset;
	}
	/**
	 * Get current limit/page size
	 * @return int current limit
	 */
	public function getLimit() {
		return $this->limit;
	}
	/**
	 * Get total number of pages
	 * @return int number of pages
	 */
	public function getMaxPages() {
		return max(0, floor(($this->count-1) / $this->limit))+1;
	}
	/**
	 * Get current page number
	 * @return int current page number
	 */
	public function getPage() {
		return floor($this->offset / $this->limit)+1;
	}
	/**
	 * Get offset from a page number
	 * @param int $page
	 * @return int the offset for this page
	 */
	public function getOffsetFromPage($page) {
		return $this->limit * ($page-1);
	}
}