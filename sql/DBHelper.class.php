<?php
/**
 * DBException class
 *
 * @author     Richaud Julien "Fladnag"
 * @package    salt\sql
 */
namespace salt;

use \Exception;
use \PDO;
use \PDOException;

/**
 * Helper for execute queries
 */
class DBHelper {

	/**
	 * @var \PDO instance of DB connexion
	 */
	private $base = NULL;

	/**
	 * @var string type of instance
	 */
	private $type = NULL;

	/**
	 * @var int transaction level. 0 is effective level
	 */
	private $txLevel = 0;

	/**
	 * @var DBHelper[] List of all connected DB : name=>DBHelper
	 */
	private static $allInstances = array();

	/**
	 * @var DBConnexion[] List of all registed DB name=>DBConnexion
	 */
	private static $allDatas = array();
	/**
	 * @var string name of the default DBHelper
	 */
	private static $default = NULL;
	/**
	 * @var boolean TRUE if a rollback is called during transaction processing
	 */
	private $txRollback = false;

	/**
	 * Create a new DBHelper
	 * @param \PDO $pdo the PDO instance to use in this DBHelper instance
	 * @param string $type type of the instance
	 */
	private function __construct(PDO $pdo, $type) {
		$this->base = $pdo;
		$this->type = $type;
	}
/*
  try {
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_OBJ);
    $db->setAttribute(PDO::MYSQL_ATTR_INIT_COMMAND,'SET NAMES UTF8');

  } catch (PDOException $e) {
    throw new PDOException("Error  : " .$e->getMessage());
  }
*/

	/**
	 * Register a default database
	 * @param string $name the id of the database. Used for retrieve the instance with DBHelper::getInstance()
	 * @param string $host host name
	 * @param string $port port
	 * @param string $db database name
	 * @param string $user user name
	 * @param string $pass password of user
	 * @param string $charset charset of database
	 * @param array $options PDO options array
	 * @throws SaltException if database already defined
	 * @see http://dev.mysql.com/doc/refman/5.6/en/charset-charsets.html for mysql charset list
	 */
	public static function registerDefault($name, $host, $port, $db, $user, $pass, $charset, array $options = array()) {
		if (self::$default !== NULL) {
			throw new SaltException(L::error_db_default_already_registered);
		}
		self::$default = $name;
		self::register($name, $host, $port, $db, $user, $pass, $charset, $options);
	}

	/**
	 * Register a database
	 * @param string $name the id of the database. Used for retrieve the instance with DBHelper::getInstance(name)
	 * @param string $host host name
	 * @param string $port port
	 * @param string $db database name
	 * @param string $user user name
	 * @param string $pass password of user
	 * @param string $charset charset of database
	 * @param array $options PDO options array
	 * @see http://dev.mysql.com/doc/refman/5.6/en/charset-charsets.html for mysql charset list
	 */
	public static function register($name, $host, $port, $db, $user, $pass, $charset, array $options = array()) {
		self::$allDatas[$name] = new DBConnexion($host, $port, $db, $user, $pass, $charset, $options);
	}

	/**
	 * Check a password for a registered database
	 * @param string $name id of a registered database
	 * @param string $pass password to check
	 * @return boolean TRUE if successfull connect to database with this password
	 */
	public static function checkPassword($name, $pass) {
		$dbCnx = self::$allDatas[$name];
		return $dbCnx->checkConnect($pass);
	}

	/**
	 * Retrieve an instance of DBHelper
	 * @param string $type id of a previously registered database, or NULL for default registered database
	 * @return DBHelper the database resource
	 * @throws SaltException if $type is unknown or connexion failed
	 */
	public static function getInstance($type = NULL) {
		if ($type === NULL)  {
			$type = self::$default;
		}
		if (!isset(self::$allInstances[$type])) {
			$helper = null;
			if (!isset(self::$allDatas[$type])) {
				throw new SaltException(L::error_db_unknown($type));
			}
			try {
				$helper = new DBHelper(self::$allDatas[$type]->connect(), $type);
			} catch (\PDOException $ex) {
				throw new SaltException(L::error_db_connect($type), $ex->getCode(), $ex);
			}
			self::$allInstances[$type] = $helper;
		}
		return self::$allInstances[$type];

	}

	/**
	 * Execute a SELECT count(*) query
	 * @param Query $query the query to execute
	 * @return int number of rows
	 */
	public function execCountQuery(Query $query) {
		$st = $this->exec($query, TRUE);
		if ($st !== NULL) {
			$count = $st->fetchColumn(0);
			return intval($count);
		}
		return NULL;
	}

	/**
	 * Execute a SELECT query
	 *
	 * If $pagination is provided and not locked, a count query is also executed
	 * @param Query $query the query
	 * @param Pagination $pagination Pagination object.
	 * @param Base $bindingObject (Optional) bind to another object type. All returned objects are in NEW state instead of LOADED
	 * @return DBResult result of the query
	 */
	public function execQuery(Query $query, Pagination $pagination = NULL, Base $bindingObject = NULL) {

		$count = NULL;
		if (($pagination != NULL) && !$pagination->isLocked()) {
			$count = $this->execCountQuery($query);
		}
		$r = $query->initResults($pagination, $count);

		if ($count !== 0) {
			$st = $this->exec($query, false, $pagination);

			if ($st !== NULL) {
				$fields = $query->getSelectFields();
				$binding = $query->getBindingClass();

				if ($bindingObject !== NULL) {
					$binding = get_class($bindingObject);
					//$fields = NULL;
				}

				try {
					$r->data = $st->fetchAll(PDO::FETCH_CLASS | PDO::FETCH_PROPS_LATE, $binding, array($fields, NULL, ($bindingObject !== NULL)));
				} catch (\PDOException $ex) {
					throw new DBException(L::error_query_fetch($query->toSQL($pagination)), $query->toSQL($pagination), $ex);
				} catch (\Exception $ex) {
					throw new SaltException(L::error_query_fetch($query->toSQL($pagination)), $ex->getCode(), $ex);
				}

				foreach($r->data as $object) {
					$object->afterLoad();
				}
			}
		}

		return $r;
	}

	/**
	 * Construct and execute a query
	 * @param Query $query the query
	 * @param boolean $count true if count query have to be executed
	 * @param Pagination $pagination pagination if required
	 * @return \PDOStatement the PDOStatement after execution
	 * @throws DBException if prepare or execute query failed with a PDOException
	 * @throws SaltException if something else failed
	 */
	private function exec(BaseQuery $query, $count = FALSE, Pagination $pagination = NULL) {
		if (!$query->isEnabled())  {
			return NULL;
		}

		Benchmark::increment('salt.queries');

		if ($count) {
			$query = $query->toCountQuery();
		}

		$sql = $query->toSQL();
		$binds = $query->getBinds();

		if (!$count && ($pagination != NULL)) {
			$paginationBinds = SqlBindField::getPaginationBinds($pagination);
			list($offset, $limit) = array_keys($paginationBinds);

			$sql.=' LIMIT :'.$offset.', :'.$limit;
			$binds = array_merge($binds, $paginationBinds);
		}

		try {

			Benchmark::start('salt.prepare');
			$st = $this->base->prepare($sql);
			foreach($binds as $param => $data) {
				$param = ':'.$param;
				if ($data['value'] === NULL) {
					$st->bindValue($param, $data['value'], PDO::PARAM_NULL);
				} else {
					switch($data['type']) {
						case FieldType::NUMBER: $st->bindValue($param, intval($data['value']), PDO::PARAM_INT);
						break;
						case FieldType::BOOLEAN : $st->bindValue($param, $data['value'], PDO::PARAM_BOOL);
						break;
						case FieldType::DATE : $st->bindValue($param, $data['value'], PDO::PARAM_INT);
						break;
						default: $st->bindValue($param, $data['value']);
					}
				}
			}
			$time = Benchmark::end('salt.prepare');
			Benchmark::addTime('salt.queries-prepare', $time);

		} catch (\PDOException $ex) {
			throw new DBException($ex->getMessage(), $sql, $ex);

		} catch (\Exception $ex) {
			throw new SaltException($ex->getMessage(), $ex->getCode(), $ex);
		}

		try {
			Benchmark::start('salt.query');
			$st->execute();
			$time = Benchmark::end('salt.query');
			Benchmark::addTime('salt.queries-exec', $time);

			$this->addDebugData($sql, $binds, round($time, BENCH_PRECISION));

		} catch (\PDOException $ex) {
			$this->addDebugData($sql, $binds, NULL);
			throw new DBException($ex->getMessage(), $sql, $ex);

		} catch (\Exception $ex) {
			$this->addDebugData($sql, $binds, NULL);
			throw new SaltException($ex->getMessage(), $ex->getCode(), $ex);
		}

		return $st;
	}

	/**
	 * Flatten binds. If some binds are array values, linearize them
	 * 
	 * @param string $q SQL query string
	 * @param mixed[] $binds binds, in multiple format, as input/output parameter
	 * @return string modified SQL query string
	 */
	private function flattenBinds($sql, &$binds) {
		// TODO : handle values in array('value' => , 'type' => ) format
		$newParams = array();
		foreach($binds as $k => $v) {
			if (is_array($v) && !in_array('value', array_keys($v), TRUE)) {
				$extraKeys = array();
				$callAgainOn = array();
				foreach($v as $kk => $vv) {
					$extraKeys[$k.'_'.$kk] = $vv;
					if (is_array($vv) && !in_array('value', array_keys($vv), TRUE)) {
						$callAgainOn[$k.'_'.$kk] = $vv;
					}
				}
				$sql = preg_replace('#:'.$k.'([^:_a-zA-Z0-9]|$)#', '(:'.implode(', :', array_keys($extraKeys)).')$1', $sql);

				if (count($callAgainOn) > 0) {
					// keys will be recomputed... so we remove them
					$extraKeys = array_diff_key($extraKeys, $callAgainOn);
					// linearize sub array by this call
					$sql = $this->flattenBinds($sql, $callAgainOn);
					// add real keys
					$extraKeys = array_merge($extraKeys, $callAgainOn);
				}
				
				$newParams = array_merge($newParams, $extraKeys);
			} else if (is_array($v) || (preg_match('#:'.$k.'([^:_a-zA-Z0-9]|$)#', $sql) === 1)) {
				$newParams[$k] = $v;
			}
		}

		$binds = $newParams;

		return $sql;
	}
	
	/**
	 * Execute a query from a SQL text
	 * @param string $sql sql text
	 * @param array $binds array of placeholder (key => value). If we want to set the type for bind a value, we can suffix the key by @
	 * 			followed by a PDO_PARAM_* constant<br/>For example : Par exemple, array(':param@'.PDO::PARAM_INT => 3)<br/>
	 * 			value can also be an array with two keys for compatibily with classic queries : array('value' => value, 'type' => FieldType)
	 * @return \PDOStatement PDOStatement after query execution
	 */
	public function execSQL($sql, array $binds = array()) {
		Benchmark::increment('salt.queries');

		Benchmark::start('salt.prepare');
		$debugBinds = array();
		
		$sql = $this->flattenBinds($sql, $binds);

		$st = $this->base->prepare($sql);
		
		foreach($binds as $k => $v) {
			$type = NULL;
			if (is_array($v) && isset($v['value']) && isset($v['type'])) {
				switch($v['type']) {
					case FieldType::NUMBER: $type = PDO::PARAM_INT;
					break;
					case FieldType::BOOLEAN : $type = PDO::PARAM_BOOL;
					break;
					case FieldType::DATE : $type = PDO::PARAM_INT;
					break;
				}
				$param = $k;
				$v = $v['value'];
			} else if (strpos($k, '@') !== FALSE) {
				@list($param, $type) = explode('@', $k, 2);
				if ($type !== NULL) {
					$type = intval($type);
				}
			} else {
				$param = $k;
				if ($v === NULL) {
					$type = PDO::PARAM_NULL;
				} else if (is_numeric($v)) {
					$type = PDO::PARAM_INT;
				} else {
					$type = PDO::PARAM_STR;
				}
			}
			$debugBinds[$param] = array('value' => $v, 'type' => ($type === PDO::PARAM_INT)?FieldType::NUMBER:FieldType::TEXT, 'private' => FALSE);
			$st->bindValue(':'.$param, $v, $type);
		}
		$time = Benchmark::end('salt.prepare');
		Benchmark::addTime('salt.queries-prepare', $time);

		try {
			Benchmark::start('salt.query');
			$st->execute();
			$time = Benchmark::end('salt.query');
			Benchmark::addTime('salt.queries-exec', $time);

			$this->addDebugData(str_replace("\n", ' ', $sql), $debugBinds, round($time, BENCH_PRECISION));
		} catch (\PDOException $ex) {
			$this->addDebugData(str_replace("\n", ' ', $sql), $debugBinds, NULL);
			throw new DBException($ex->getMessage(), $sql, $ex);
		} catch (\Exception $ex) {
			$this->addDebugData(str_replace("\n", ' ', $sql), $debugBinds, NULL);
			throw new SaltException($ex->getMessage(), $ex->getCode(), $ex);
		}

		return $st;
	}

	/**
	 * Return the last inserted ID
	 * 
	 * @see http://php.net/manual/en/pdo.lastinsertid.php
	 * @param string $name sequence name or NULL
	 * @return string the last insert ID
	 */
	public function getLastId($name = NULL) {
		return $this->base->lastInsertId($name);
	}
	
	/**
	 * Return the 3 elements array of PDO::errorInfo() if an error occurred, or FALSE if all is right
	 * 
	 * @return array|boolean
	 */
	public function getLastError() {
		$code = $this->base->errorCode();
		if (!in_array($code, array('', '00000'))) {
			return $this->base->errorInfo();
		}
		return FALSE;
	}
	
	/**
	 * Add some information about query in Benchmark data
	 * @param string $sql SQL text query (can be count or not count query)
	 * @param mixed[] $binds placeholders (key => value)
	 * @param float|NULL $temps execution time or NULL if query failed
	 */
	private function addDebugData($sql, array $binds, $temps) {
		Benchmark::start('salt.queries-debugInfo');
		// the goal is NOT to execute the query here, but only to build it for a debug display if needed : binds values are NOT escaped !
		if (count($binds) > 0) {
			$keys = implode('|', array_keys($binds));
			$sqlValues = preg_replace_callback('#(?::('.$keys.'))([^:a-zA-Z0-9]|$)#', function($match) use(&$binds) {
				if (isset($binds[$match[1]])) {
					$b = $binds[$match[1]];
					if ($b['private']) {
						$v = '/*HIDDEN*/';
					} else {
						$v = $b['value'];
						if ($b['type'] === FieldType::TEXT) $v = '\''.$v.'\'';
						if ($b['type'] === FieldType::BOOLEAN) $v = ($v)?1:0;
						if ($v === NULL) $v='NULL';
					}
				} else {
					$v = '/*MISSING*/';
				}
				return $v.$match[2];
			}, $sql);
		} else {
			$sqlValues = $sql;
		}

		Benchmark::addData('salt.queries', array('Query' => $sql, 'Time' => ($temps === NULL)?'ERROR':$temps));
		Benchmark::addData('salt.queriesValues', $sqlValues);
		Benchmark::stop('salt.queries-debugInfo');
	}

	/**
	 * Execute an INSERT query
	 * @param InsertQuery $query the query to execute
	 * @return string \PDOStatement::lastInsertId()
	 * @throws RowCountException if query don't insert the expected number of objects
	 */
	public function execInsert(InsertQuery $query) {
		$st = $this->exec($query);

		if ($st !== NULL) {
			$expected = $query->getInsertObjectCount();

			$changedRows = $st->rowCount();
			if (($expected > 0) && ($expected !== $changedRows)) {
				throw new RowCountException(L::error_query_expected_insert($changedRows, $expected),
						$query->toSQL(), $changedRows, $expected);
			}
			return $this->base->lastInsertId();
		}
		return NULL;
	}

	/**
	 * Execute a DELETE query
	 * @param DeleteQuery $query the query to execute
	 * @param int $expected number of expected delete, NULL for unknown
	 * @return int the number of rows deleted
	 * @throws RowCountException if delete don't change the expected number of rows
	 */
	public function execDelete(DeleteQuery $query, $expected = -1) {
		$st = $this->exec($query);

		$changedRows = 0;
		if ($st !== NULL) {
			if ($query->isSimpleQuery() && ($expected < 0)) {
				$expected = $query->getDeletedObjectCount();
			}

			if ($expected <= 0) {
				$expected = NULL;
			}

			$changedRows = $st->rowCount();
			if (($expected !== NULL) && ($expected !== $changedRows)) {
				throw new RowCountException(L::error_query_expected_delete($changedRows, $expected),
						$query->toSQL(), $changedRows, $expected);
			}
		}

		return $changedRows;
	}

	/**
	 * Execute an UPDATE query
	 * @param UpdateQuery $query
	 * @param int $expected number of expected modified rows. NULL if unknown
	 * @return int number of modified rows.
	 */
	public function execUpdate(UpdateQuery $query, $expected = -1) {
		$st = $this->exec($query);

		$changedRows = 0;
		if ($st !== NULL) {
			if ($query->isSimpleQuery() && ($expected < 0)) {
				$expected = 1;
			}

			if ($expected <= 0) {
				$expected = NULL;
			}

			$changedRows = $st->rowCount();
			if (($expected !== NULL) && ($expected !== $changedRows)) {
				throw new RowCountException(L::error_query_expected_update($changedRows, $expected),
						$query->toSQL(), $changedRows, $expected);
			}
		}
		return $changedRows;
	}

	/**
	 * Execute a CREATE TABLE query
	 * @param CreateTableQuery $query the CreateTable query
	 * @throws SaltException if called during a transaction
	 */
	public function execCreate(CreateTableQuery $query) {
		if ($this->txLevel > 0) {
			throw new SaltException(L::error_tx_create_table);
		}
		$this->exec($query);
	}

	/**
	 * Check transaction is active
	 * @return boolean TRUE if we are in a transaction
	 */
	public function inTransaction() {
		return ($this->txLevel > 0);
	}

	/**
	 * Start a transaction with PDO
	 * @throws SaltException if PDO->beginTransaction() failed
	 */
	public function beginTransaction() {
		if ($this->txLevel === 0) {
			$this->txRollback = false;
			if ($this->base->beginTransaction() === FALSE) {
				throw new SaltException(L::error_tx_begin);
			}
		}
		$this->txLevel++;
	}

	/**
	 * Commit a transaction. Only first level transaction are effective.<br/>
	 * If any nested transaction call rollback(), the commit will rollback
	 *
	 * @throws SaltException
	 */
	public function commit() {
		if ($this->txLevel <= 0) {
			throw new SaltException(L::error_tx_no_transaction);
		}
		if ($this->txLevel === 1) {
			if ($this->txRollback) { // if nested transaction rollback, rollback too
				$this->rollback();
				return;
			}
			if ($this->base->commit() === FALSE) {
				throw new SaltException(L::error_tx_commit);
			}
		// There is a bug with MYSQL. Commit can fail if mysql server became unreachable : https://bugs.php.net/bug.php?id=66528
		// No real workaround... so we just pray for not happening (the script go on, but mysql statements are rollback)
// 		$errorInfo = $db->errorInfo();
// 		if ($errorInfo[0] !== NULL) {
// 			throw new SaltException('MYSQL Server error during commit.');
// 		}
		}
		$this->txLevel--;
	}

	/**
	 * Rollback in first level transaction
	 * @throws SaltException
	 */
	public function rollback() {
		if ($this->txLevel <= 0) {
			throw new SaltException(L::error_tx_no_transaction);
		}
		if ($this->txLevel === 1) {
			if ($this->base->rollBack() === FALSE) {
				throw new SaltException(L::error_tx_rollback);
			}
		}
		$this->txLevel--;
		$this->txRollback = true;
	}

	/**
	 * Check every transaction are ended
	 * @throws SaltException if one transaction is in progress
	 */
	public static function checkAllTransactionsEnded() {
		foreach(self::$allInstances as $name => $instance) {
			if ($instance->txLevel > 0) {
				throw new SaltException(L::error_tx_pending($name));
			}
		}
	}

	/**
	 * Return database name
	 * @param string $type id of a previously registered database, or NULL for default registered database
	 * @return string database name
	 * @throws SaltException if $type is unknown
	 */
	public static function getDatabase($type = NULL) {
		if ($type === NULL) {
			$type = self::$default;
		}
		if (!isset(self::$allDatas[$type])) {
			throw new SaltException(L::error_db_unknown($type));
		}

		return self::$allDatas[$type]->getDatabase();
	}
} // DBHelper


/**
 * Registered database
 * @internal
 */
class DBConnexion {
	/** @var string Host name */
	private $host;
	/** @var string Port number */
	private $port;
	/** @var string database name */
	private $db;
	/** @var string user name */
	private $user;
	/** @var string password. Will be cleared after connexion */
	private $pass;
	/** @var string charset of database */
	private $charset;
	/** @var array PDO options array */
	private $options;
	/**
	 * Create a DBConnexion
	 * @param string $host Host name
	 * @param string $port Port number
	 * @param string $db database name
	 * @param string $user user name
	 * @param string $pass password
	 * @param string $charset charset of database
	 * @param array $options PDO options array
	 */
	public function __construct($host, $port, $db, $user, $pass, $charset, $options) {
		$this->host = $host;
		$this->port = $port;
		$this->db = $db;
		$this->user = $user;
		$this->pass = $pass;
		$this->charset = $charset;
		$this->options = $options;
	}

	/**
	 * Connect to a database
	 * @param string $password the password to use for connect (NULL for using the registered password)
	 * @return \PDO PDO instance
	 * @throws SaltException if connexion failed
	 */
	public function connect($password = NULL) {
		Benchmark::increment('salt.bdConnect');
		Benchmark::start('salt.bdConnect');

		$pdo = NULL;

		try {
			// if the database cannot be reached, PHP throw an unexplicit warning we want to ignore, and an explicit exception we want to handle.
			$oldErrorReporting = error_reporting(0);

			if ($password === NULL) {
				$password = $this->pass;
			}
			$options = $this->options;
			if (version_compare(PHP_VERSION, '5.3.6') < 0) {
				if (isset($options[PDO::MYSQL_ATTR_INIT_COMMAND])) {
					$options[PDO::MYSQL_ATTR_INIT_COMMAND].=';';
				} else {
					$options[PDO::MYSQL_ATTR_INIT_COMMAND]='';
				}
				$options[PDO::MYSQL_ATTR_INIT_COMMAND].='SET NAMES '.$this->charset;
			}
			$pdo = new PDO('mysql:host='.$this->host.';port='.$this->port.';dbname='.$this->db.';charset='.$this->charset,
					$this->user, $password, $options);
			$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		} catch (\Exception $ex) {
			error_reporting($oldErrorReporting);
			throw new SaltException($ex->getMessage(), $ex->getCode(), $ex);
		}
		error_reporting($oldErrorReporting);

		Benchmark::stop('salt.bdConnect');

		$this->pass = NULL; // don't keep password more than needed

		return $pdo;
	}

	/**
	 * Check a password for connect to database
	 * @param string $password Password
	 * @return boolean TRUE if password can be used for connect
	 */
	public function checkConnect($password) {
		try {
			$this->connect($password);
		} catch (\Exception $ex) {
			return FALSE;
		}
		return TRUE;
	}

	/**
	 * Retrieve database name
	 * @return string database name
	 */
	public function getDatabase() {
		return $this->db;
	}
}
