<?php

class SnsConfigMapper extends opDoctrineSimpleRecord
{
  protected static $snsConfigSettings = array();

  public function getConfig()
  {
    $name = $this->name;
    if ($name && isset(self::$snsConfigSettings[$name]))
    {
      return self::$snsConfigSettings[$name];
    }

    return false;
  }

  public function getValue()
  {
    $value = $this->value;

    if ($this->isMultipleSelect())
    {
      $value = unserialize($value);
    }

    return $value;
  }

  protected function isMultipleSelect()
  {
    $config = $this->getConfig();

    return ('checkbox' === $config['FormType']);
  }
}
