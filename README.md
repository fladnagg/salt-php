# SALT-php
SALT (Simple And LighT) framework in PHP

## Features
* XHTML strict compatible
* User input format helper
* Database input display/edit helper
* HTML form helper
* Multiple database connexions
* Database object mapping (MySQL, InnoDB)
* Transaction (InnoDB)
* Complex queries
* Paging

## What SALT is not and never be/contains
* A template engine
* A complete file organization and architecture for building a PHP application/website
* Database relational objects links

### Documentation 
[https://salt-php.org](https://salt-php.org) in french


### Migration from 0.8.0 (<2017/03/02)
A lot of API changes : 
- `new Query(Base::meta())` became `Base::query()`
- `new UpdateQuery(Base::meta())` became `Base::updateQuery()`
- `new DeleteQuery(Base::meta())` became `Base::deleteQuery()`
- `Base::meta()` became `Base::MODEL()` for metadata access
- `Base::meta()` became `Base::singleton()` for other rare usages
- `metadata()` implementation have to call `MODEL()` methods
- `$query->getField('fieldName')` became `$query->fieldName`
- `$object->getField('fieldName')` became `$object::MODEL()->fieldName` for all fields declared in metadata(). `$object->getField('fieldName')` can still be used for extra fields
- `$object->getFieldsMetadata()` became `$object::MODEL()->getFields()`
- `SqlExpr::func(sqlFunc, args)` became `SqlExpr::_sqlFunc(args)`
- `SqlExpr::func('', args)` became `SqlExpr::tuple(args)`

### Migration from 1.0.0 (< 2017/01/02)
Major changes :
* new SQL API (Query API stay unchanged and is not deprecated)
* replaced ViewHelper API by DAOConverter (ViewHelper became deprecated)
* Improved checkbox in FormHelper

The following change are required :
* `Base::initAfterCreateTable()` signature change : `Base::initAfterCreateTable(DBHelper $db)`
* `Base::registerHelper` is removed()
* Rename each `*ViewHelper` class by `<ClassName>DAOConverter` class : the class name is normalized. You cannot change it.
* ViewHelper constants (FORMAT_KEY, RAW) are moved to FormHelper
* in each ViewHelper converted in DAOConverter :
    - `extends from DAOConverter` instead of `BaseViewHelper`
    - column() method signature change :
    `public function column(Field $field, $format=NULL)` 
    became 
    `public function column(Base $object, Field $field, $value, $format, $params)`
* Replace `$object->field = $value` by `$object->FORM->field = $value` when $value came from an HTML form (date as string, boolean as other value than true/false, etc...)
* `FormHelper::field()` do NOT convert value anymore. If you use it directly, value have to be formatted for a Field usage (dates have to be timestamp, booleans have to be boolean)
* `Object::COLUMN(field, format)` became `Object::COLUMN(format)->field`
* For input of type checkbox, 2 possibilities :
    - keep using old checkbox implementation (call `FormHelper::useImprovedCheckbox(FALSE);`)
        * do not change retrieve method : `$checked = $Input->G->ISSET->name;`
    - use new checkbox implementation (by default)
        * change retrieve method : `$checked = $Input->G->RAW->name == 1;`
        * for set in object, you can do : `$dao->FORM->booleanField = $Input->G->RAW->name;`

