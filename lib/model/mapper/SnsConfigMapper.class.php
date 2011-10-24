<?php

class SnsConfigMapper
{
  protected static $snsConfigSettings = array();

  public $name, $value;

  public function __construct($data)
  {
    $delimitor = '__';

    foreach ($data as $key => $value)
    {
        $field = substr($key, strpos($key, $delimitor) + strlen($delimitor));
        $this->$field = $value;
    }

    if (!self::$snsConfigSettings) {
        self::$snsConfigSettings = sfConfig::get('openpne_sns_config');
    }
  }

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
