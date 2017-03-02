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
- new Query(Base::meta()) became Base::query()
- new UpdateQuery(Base::meta()) became Base::updateQuery()
- new DeleteQuery(Base::meta()) became Base::deleteQuery()
- Base::meta() became Base::MODEL() for metadata access
- Base::meta() became Base::singleton() for other rare usages
- metadata() implementation have to call MODEL() methods
- $query->getField('fieldName') became $query->fieldName
- $object->getField('fieldName') became $object::MODEL()->fieldName for all fields declared in metadata(). $object->getField('fieldName') can still be used for extra fields
- $object->getFieldsMetadata() became $object::MODEL()->getFields()
- SqlExpr::func(sqlFunc, args) became SqlExpr::_sqlFunc(args)
- SqlExpr::func('', args) became SqlExpr::tuple(args)



