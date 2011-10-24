<?php

class GadgetMapper
{
  protected $list = null;


  public $id, $type, $name, $sort_order, $GadgetConfig;

  public function __construct($data)
  {
    $delimitor = '__';

    foreach ($data as $key => $value)
    {
        $field = substr($key, strpos($key, $delimitor) + strlen($delimitor));
        $this->$field = $value;
    }
  }

  protected function getGadgetConfigList()
  {
    if (null === $this->list)
    {
      $this->list = Doctrine::getTable('Gadget')->getGadgetConfigListByType($this->type);
    }
    return $this->list;
  }

  public function getComponentModule()
  {
    $list = $this->getGadgetConfigList();
    if (empty($list[$this->name]))
    {
      return false;
    }

    return $list[$this->name]['component'][0];
  }

  public function getComponentAction()
  {
    $list = $this->getGadgetConfigList();
    if (empty($list[$this->name]))
    {
      return false;
    }

    return $list[$this->name]['component'][1];
  }

  public function isEnabled()
  {
    $list = $this->getGadgetConfigList();
    if (empty($list[$this->name]))
    {
      return false;
    }

    $controller = sfContext::getInstance()->getController();
    if (!$controller->componentExists($this->getComponentModule(), $this->getComponentAction()))
    {
      return false;
    }

    $member = sfContext::getInstance()->getUser()->getMember();
    $isEnabled = $this->isAllowed($member, 'view');

    return $isEnabled;
  }

  public function getConfig($name)
  {
    $result = null;
    $list = $this->getGadgetConfigList();

    $config = Doctrine::getTable('GadgetConfig')->retrieveByGadgetIdAndName($this->id, $name);
    if ($config)
    {
      $result = $config->getValue();
    }
    elseif (isset($list[$this->name]['config'][$name]['Default']))
    {
      $result = $list[$this->name]['config'][$name]['Default'];
    }

    return $result;
  }

  public function generateRoleId(Member $member)
  {
    if ($member instanceof opAnonymousMember)
    {
      return 'anonymous';
    }

    return 'everyone';
  }

  public function getId()
  {
    return $this->id;
  }

  public function isAllowed()
  {
    return true;
  }
}
