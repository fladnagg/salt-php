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
	 */
	private function __construct(PDO $pdo) {
		$this->base = $pdo;
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
	 * @throws SaltException if database already defined
	 */
	public static function registerDefault($name, $host, $port, $db, $user, $pass, $charset = CHARSET) {
		if (self::$default !== NULL) {
			throw new SaltException('Default DB already defined');
		}
		self::$default = $name;
		self::register($name, $host, $port, $db, $user, $pass, $charset);
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
	 */
	public static function register($name, $host, $port, $db, $user, $pass, $charset = CHARSET) {
		self::$allDatas[$name] = new DBConnexion($host, $port, $db, $user, $pass, $charset);
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
				throw new SaltException('Unknown database '.$type.'. Please register it before using.');
			}
			try {
				$helper = new DBHelper(self::$allDatas[$type]->connect());
			} catch (\PDOException $ex) {
				throw new SaltException('Unable to connect to database '.$type, $ex->getCode(), $ex);
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
		$count = $st->fetchColumn(0);
		return intval($count);
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
		if (($pagination != NULL) && !$pagination->isLocked() && !$query->isEmptyResults()) {
			$count = $this->execCountQuery($query);
		}
		$r = $query->initResults($pagination, $count);

		if (!$query->isEmptyResults() && ($count !== 0)) {

			$st = $this->exec($query, false, $pagination);

			$fields = $query->getSelectFields();
			$binding = $query->getBindingClass();

			if ($bindingObject !== NULL) {
				$binding = get_class($bindingObject);
				//$fields = NULL;
			}

			try {
				$r->data = $st->fetchAll(PDO::FETCH_CLASS | PDO::FETCH_PROPS_LATE, $binding, array($fields, NULL, ($bindingObject !== NULL)));
			} catch (\PDOException $ex) {
				throw new DBException('Error in populate object during fetch from the query ', $query->toSQL($pagination), $ex);
			} catch (\Exception $ex) {
				throw new SaltException('Error in populate object during fetch from the query '.$query->toSQL($pagination), $ex->getCode(), $ex);
			}

			foreach($r->data as $object) {
				$object->afterLoad();
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
		Benchmark::increment('salt.queries');

		if ($count) {
			$query = $query->toCountQuery();
		}
		
		$sql = $query->toSQL();
		$binds = $query->getBinds();

		if (!$count && ($pagination != NULL)) {
			$paginationBinds = SqlBindField::getPaginationBinds($pagination);
			list($offset, $limit) = array_keys($paginationBinds);

			$sql.=' LIMIT '.$offset.','.$limit;
			$binds = array_merge($binds, $paginationBinds);
		}

		try {

			Benchmark::start('salt.prepare');
			$st = $this->base->prepare($sql);
			foreach($binds as $param => $data) {
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
	 * Execute a query from a SQL text
	 * @param string $sql sql text
	 * @param array $binds array of placeholder (key => value). If we want to set the type for bind a value, we can suffix the key by @
	 * 			followed by a PDO_PARAM_* constant<br/>For example : Par exemple, array(':param@'.PDO::PARAM_INT => 3)
	 * @return \PDOStatement \PDOStatement after query execution
	 */
	public function execSQL($sql, array $binds = array()) {
		Benchmark::increment('salt.queries');

		Benchmark::start('salt.prepare');
		$st = $this->base->prepare($sql);
		foreach($binds as $k => $v) {
			$type = NULL;
			@list($param, $type) = explode('@', $k, 2);
			if ($type !== NULL) {
				$type = intval($type);
			}
			$st->bindValue($param, $v, $type);
		}
		$time = Benchmark::end('salt.prepare');
		Benchmark::addTime('salt.queries-prepare', $time);

		try {
			Benchmark::start('salt.query');
			$st->execute();
			$time = Benchmark::end('salt.query');
			Benchmark::addTime('salt.queries-exec', $time);

			// parameters format is unknown for manual made queries
			//$this->addDebugData($sql, array(), round($time, BENCH_PRECISION));
		} catch (\PDOException $ex) {
			//$this->addDebugData($sql, array(), NULL);
			throw new DBException($ex->getMessage(), $sql, $ex);
		} catch (\Exception $ex) {
			//$this->addDebugData($sql, array(), NULL);
			throw new SaltException($ex->getMessage(), $ex->getCode(), $ex);
		}

		return $st;
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
		$sqlValues = preg_replace_callback('(:[vL][0-9]+)', function($bind) use(&$binds) {
			if (isset($binds[$bind[0]])) {
				$b = $binds[$bind[0]];
				$v = $b['value'];
				if ($b['type'] === FieldType::TEXT) $v = '\''.$v.'\'';
				if ($b['type'] === FieldType::BOOLEAN) $v = ($v)?1:0;
				if ($v === NULL) $v='NULL';
			} else {
				$v = '/*MISSING*/';
			}
			return $v;
		}, $sql);

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

		$expected = $query->getInsertObjectCount();

		$changedRows = $st->rowCount();
		if ($expected !== $changedRows) {
			throw new RowCountException('Query have inserted '.$changedRows.' rows instead of expected '.$expected.'.',
					$query->toSQL(), $changedRows, $expected);
		}
		return $this->base->lastInsertId();
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

		if ($query->isSimpleQuery() && ($expected < 0)) {
			$expected = $query->getDeletedObjectCount();
		}

		if ($expected <= 0) {
			$expected = NULL;
		}

		$changedRows = $st->rowCount();
		if (($expected !== NULL) && ($expected !== $changedRows)) {
			throw new RowCountException('Query have deleted '.$changedRows.' rows instead of expected '.$expected.'.',
					$query->toSQL(), $changedRows, $expected);
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

		if ($query->isSimpleQuery() && ($expected < 0)) {
			$expected = 1;
		}

		if ($expected <= 0) {
			$expected = NULL;
		}

		$changedRows = $st->rowCount();
		if (($expected !== NULL) && ($expected !== $changedRows)) {
			throw new RowCountException('Query have modified '.$changedRows.' rows instead of expected '.$expected.'.',
					$query->toSQL(), $changedRows, $expected);
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
			throw new SaltException('Cannot create a table during a transaction.');
		}
		$this->exec($query);
	}

	/**
	 * Start a transaction with PDO
	 * @throws SaltException if PDO->beginTransaction() failed
	 */
	public function beginTransaction() {
		if ($this->txLevel === 0) {
			$this->txRollback = false;
			if ($this->base->beginTransaction() === FALSE) {
				throw new SaltException('Cannot begin a new Transaction.');
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
			throw new SaltException('No transaction in progress.');
		}
		if ($this->txLevel === 1) {
			if ($this->txRollback) { // if nested transaction rollback, rollback too
				$this->rollback();
				return;
			}
			if ($this->base->commit() === FALSE) {
				throw new SaltException('Error during commit.');
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
			throw new SaltException('No transaction in progress.');
		}
		if ($this->txLevel === 1) {
			if ($this->base->rollBack() === FALSE) {
				throw new SaltException('Error during rollback.');
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
				throw new SaltException('A transaction is in progress for database ['.$name.']. Please handle it better before leaving page.');
			}
		}
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
	/**
	 * Create a DBConnexion
	 * @param string $host Host name
	 * @param string $port Port number
	 * @param string $db database name
	 * @param string $user user name
	 * @param string $pass password
	 * @param string $charset charset of database
	 */
	public function __construct($host, $port, $db, $user, $pass, $charset) {
		$this->host = $host;
		$this->port = $port;
		$this->db = $db;
		$this->user = $user;
		$this->pass = $pass;
		$this->charset = $charset;
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
			$pdo = new PDO('mysql:host='.$this->host.';port='.$this->port.';dbname='.$this->db.';charset='.str_replace('-', '', $this->charset), // UTF8 instead of 'UTF-8'
					$this->user, $password);
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
}
