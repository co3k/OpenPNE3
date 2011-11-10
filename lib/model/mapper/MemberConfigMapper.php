<?php

class MemberConfigMapper extends opDoctrineSimpleRecord
{
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
