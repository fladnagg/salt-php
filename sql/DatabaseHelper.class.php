<?php
/**
 * DatabaseHelper class
 *
 * @author     Richaud Julien "Fladnag"
 * @package    salt\sql
 */
namespace salt;

/**
 * Helper for Database operations
 */
class DatabaseHelper {

	/**
	 * Check if tables exists
	 * @param DBHelper $db database to use
	 * @param string[] $classNames classname of object to check existence
	 * @return Base[] Base objects if their DB table is missing
	 */
	public static function missingTables(DBHelper $db, array $classNames) {
		$allTables = array();
		foreach($classNames as $class) {
			$obj = $class::singleton();
			$allTables[$obj->getTableName(FALSE)] = $obj;
		}

		$tableNames = InformationSchemaTables::missingTables($db, array_keys($allTables));

		$missingObjects = array();
		foreach($tableNames as $table) {
			$missingObjects[] = $allTables[$table];
		}
		return $missingObjects;
	}

	/**
	 * Create and initialize tables with DAO metadata
	 * @param DBHelper $db database to use
	 * @param Base[] $objects array of Base objects for create their tables
	 */
	public static function createTablesFromObjects(DBHelper $db, array $objects) {
		foreach($objects as $obj) {
			$query = new CreateTableQuery($obj);
			$db->execCreate($query);
		}
		// insert after all create
		/** @var Base $obj */
		foreach($objects as $obj) {
			$newObjects = $obj->initAfterCreateTable($db);
			if ($newObjects !== NULL) {
				$insert = new InsertQuery($newObjects);
				$db->execInsert($insert);
			}
		}
		return TRUE;
	}
}