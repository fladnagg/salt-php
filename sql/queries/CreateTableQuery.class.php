<?php
/**
 * CreateTableQuery class
 *
 * @author     Richaud Julien "Fladnag"
 * @package    salt\sql\queries
 */
namespace salt;

/**
 * Query for CREATE TABLE
 */
class CreateTableQuery extends Query {

	/**
	 * Construct a new Query
	 * @param Base $obj the object from creating the table
	 */
	public function __construct(Base $obj) {
		parent::__construct($obj);
	}

	/**
	 * {@inheritDoc}
	 *
	 * All tables are created as InnoDB
	 * @see salt\SqlBindField::buildSQL()
	 */
	protected function buildSQL() {
		$fields = array();
		/**
		 * @var Field $field */
		foreach($this->obj->getFieldsMetadata() as $field) {
			$f=$field->name;
			$f.=' '.self::getSqlTypeForField($field);
			if (!$field->nullable) {
				$f.=' NOT NULL';
			}
			if ($field->defaultValue !== NULL) {
				$bind = $this->addBind($field->defaultValue, $field->type);
				$f.=' DEFAULT '.$bind;
			}
			$fields[]=$f;
		}

		$sql = 'CREATE TABLE '.$this->obj->getTableName().' ( ';
		$sql.= implode(', ', $fields);
		$sql.=' ) ENGINE=InnoDB';

		return $sql;
	}

	/**
	 * Convert a field to a SQL type
	 * @param Field $field the field to convert
	 * @return string the SQL text of the type of the field
	 */
	private static function getSqlTypeForField(Field $field) {
		if ($field->sqlType !== NULL) {
			return $field->sqlType;
		}
		switch($field->type) {
			case FieldType::BOOLEAN	: return 'TINYINT(1)';
			break;
			case FieldType::DATE	:
				switch($field->format) {
					case SqlDateFormat::RAW_TIMESTAMP : return 'INT(10)';
					case SqlDateFormat::TIMESTAMP : return 'TIMESTAMP';
					case SqlDateFormat::DATETIME : return 'DATETIME';
					case SqlDateFormat::SHORT_DATE : return 'DATETIME';
					default : return 'TEXT';
				}
				break;
			case FieldType::NUMBER	:	return 'INT';

			break;
			case FieldType::TEXT :	return 'TEXT';
			break;
		}
	}
}
