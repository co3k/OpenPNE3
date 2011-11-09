<?php

class opDoctrineSimpleRecord extends Doctrine_Record_Abstract
{
  protected $data = array();

  public function __construct($table = null, $isNewEntry = false)
  {
    if (isset($table) && $table instanceof Doctrine_Table) {
      $this->_table = $table;
      $exists = (!$isNewEntry);
    } else {
      $class = get_class($this);
      $this->_table = Doctrine_Core::getTable($class);
      $exists = false;
    }
  }

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

  public function preSerialize(Doctrine_Event $event)
  {
  }

  public function postSerialize(Doctrine_Event $event)
  {
  }

  public function preUnserialize(Doctrine_Event $event)
  {
  }

  public function postUnserialize(Doctrine_Event $event)
  {
  }

  public function preDqlSelect(Doctrine_Event $event)
  {
  }

  public function preSave(Doctrine_Event $event)
  {
  }

  public function postSave(Doctrine_Event $event)
  {
  }

  public function preDqlDelete(Doctrine_Event $event)
  {
  }

  public function preDelete(Doctrine_Event $event)
  {
  }

  public function postDelete(Doctrine_Event $event)
  {
  }

  public function preDqlUpdate(Doctrine_Event $event)
  {
  }

  public function preUpdate(Doctrine_Event $event)
  {
  }

  public function postUpdate(Doctrine_Event $event)
  {
  }

  public function preInsert(Doctrine_Event $event)
  {
  }

  public function postInsert(Doctrine_Event $event)
  {
  }

  public function preHydrate(Doctrine_Event $event)
  {
  }

  public function postHydrate(Doctrine_Event $event)
  {
  }
}
