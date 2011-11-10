<?php

class opDoctrineSimpleRecord extends ArrayObject
{
  protected $modelName;

  public function __construct($input = array(), $modelName = null)
  {
    $this->modelName = $modelName;

    parent::__construct($input, self::STD_PROP_LIST | self::ARRAY_AS_PROPS);
  }

  public function getTable()
  {
    return Doctrine_Core::getTable($modelName);
  }
}
