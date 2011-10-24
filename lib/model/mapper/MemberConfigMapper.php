<?php

class MemberConfigMapper
{

  protected  $id, $member_id, $name, $value, $value_datetime, $name_value_hash, $Member;

  public function __construct($data)
  {
    $delimitor = '__';

    foreach ($data as $key => $value)
    {
        $field = substr($key, strpos($key, $delimitor) + strlen($delimitor));
        $this->$field = $value;
    }
  }

  public function getValue()
  {
    if ($this->value_datetime)
    {
      return $this->value_datetime;
    }

    return $this->value;
  }

  public function getFormType()
  {
    $setting = $this->getSetting();
    if (isset($setting['FormType']))
    {
      return $setting['FormType'];
    }

    return 'input';
  }

  private function createHash()
  {
    return md5(uniqid(mt_rand(), true));
  }

  public function getSetting()
  {
    $config = sfConfig::get('openpne_member_config');

    $name = $this->name;
    if (!$name)
    {
      return array();
    }

    if (empty($config[$this->name]))
    {
      return array();
    }

    return $config[$this->name];
  }

  public function generateRoleId(Member $member)
  {
    if ($this->Member->id === $member->id)
    {
      return 'self';
    }

    return 'everyone';
  }

  public function getId()
  {
    return $this->id;
  }

  public function getName()
  {
    return $this->name;
  }

  public function setValueDatetime($value)
  {
    $this->value_datetime = $value;
  }

  public function setValue($value)
  {
    $this->value = $value;
  }

  public function save()
  {
  }
}
