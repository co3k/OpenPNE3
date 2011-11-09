<?php

class opDoctrineSimpleRecord extends Doctrine_Record_Abstract
{
  protected $data = array();

  // copied from opDoctrineRecord
  public function setTableName($tableName)
  {
    if (sfConfig::get('op_table_prefix'))
    {
      $tableName = sfConfig::get('op_table_prefix').$tableName;
    }

    parent::setTableName($tableName);
  }

  public function get($offset)
  {
    if (!$this->contains($offset))
    {
      return null;
    }

    return $this->data[$offset];
  }

  public function set($offset, $value)
  {
    $this->data[$offset] = $value;
  }

  public function contains($offset)
  {
    return isset($this->data[$offset]);
  }

  public function remove($offset)
  {
    unset($this->data[$offset]);
  }
}
