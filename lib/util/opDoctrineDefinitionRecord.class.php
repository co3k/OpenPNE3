<?php

class opDoctrineDefinitionRecord extends Doctrine_Record_Abstract
{
  public function __construct($table)
  {
    $this->_table = $table;
  }

  public function setTableName($tableName)
  {
    // copied from opDoctrineRecord
    if (sfConfig::get('op_table_prefix'))
    {
      $tableName = sfConfig::get('op_table_prefix').$tableName;
    }

    parent::setTableName($tableName);
  }

  public function setSubclasses($map)
  {
    $name = $this->getTable()->getOption('name');
    if (isset($map[$name]))
    {
      // fake parent method
      $map[get_class($this)] = $map[$name];
    }

    parent::setSubclasses($map);
  }
}
