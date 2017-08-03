<?php
/**
 * InformationSchemaTables class
 *
 * @author     Richaud Julien "Fladnag"
 * @package    salt\dao
 */
namespace salt;

/**
 * DAO for information_schema.tables of mysql instances
 *
 * @property string $table_name
 * @property string $table_schema
 */
class InformationSchemaTables extends Base {

	/**
	 * {@inheritDoc}
	 * @see \salt\Base::metadata()
	 */
	protected function metadata() {

		self::MODEL()
			->registerId('table_name')
			->registerTableName('information_schema.tables')
			->registerFields(
				Field::newText(	'table_name', 		'Nom table'),
				Field::newText(	'table_schema', 	'Schema')
			);
	}

	/**
	 * Find missing tables name
	 * @param DBHelper $db database to use
	 * @param string[] $tableNames list of table name to check
	 * @return string[] list of missing tables
	 */
	public static function missingTables(DBHelper $db, array $tableNames) {

		$q = InformationSchemaTables::query();
		$q->selectField('table_name');
		$q->whereAnd('table_name', 'IN', $tableNames);
		$q->whereAnd('table_schema', '=', SqlExpr::_database());

		$r = $db->execQuery($q);

		$existingTables = array();
		foreach($r->data as $infos) {
			$existingTables[] = $infos->table_name;
		}

		$tableNames = array_diff($tableNames, $existingTables);

		return $tableNames;
	}
}