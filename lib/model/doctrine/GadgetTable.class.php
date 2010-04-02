<?php

/**
 * This file is part of the OpenPNE package.
 * (c) OpenPNE Project (http://www.openpne.jp/)
 *
 * For the full copyright and license information, please view the LICENSE
 * file and the NOTICE file that were distributed with this source code.
 */

class GadgetTable extends opAccessControlDoctrineTable
{
  protected $results;

  static protected function getTypes($typesName)
  { 
    $types = array();
    $configs = sfConfig::get('op_gadget_config', array());
    $layoutConfigs = sfConfig::get('op_gadget_layout_config', array());

    if (!isset($configs[$typesName]))
    { 
      throw new Doctrine_Exception('Invalid types name');
    } 
    if (isset($configs[$typesName]['layout']['choices']))
    { 
      foreach ($configs[$typesName]['layout']['choices'] as $choice)
      { 
        $types += $layoutConfigs[$choice];
      } 
    } 
    $types += $layoutConfigs[$configs[$typesName]['layout']['default']];
    $types = array_unique($types);

    if ($typesName !== 'gadget')
    {
      foreach ($types as &$type)
      {
        $type = $typesName.ucfirst($type);
      }
    }

    return $types;
  }

  public function retrieveGadgetsByTypesName($typesName)
  {
    $types = $this->getTypes($typesName);

    foreach($types as $type)
    {
      $results[$type] = $this->retrieveByType($type);
    }

    return $results;
  }

  public function retrieveByType($type)
  {
    $results = $this->getResults();

    return (isset($results[$type])) ? $results[$type] : null;
  }

  public function getGadgetsIds($type)
  {
    $_result = $this->createQuery()
      ->select('id')
      ->where('type = ?', $type)
      ->orderBy('sort_order')
      ->execute();
    
    $result = array();

    foreach ($_result as $value)
    {
      $result[] = $value->getId();
    }

    return $result;
  }

  protected function getResults()
  {
    if (empty($this->results))
    {
      $this->results = array();
      $objects = $this->createQuery()->orderBy('sort_order')->execute();
      foreach ($objects as $object)
      {
        $this->results[$object->type][] = $object;
      }
    }
    return $this->results;
  }
  
  public function getGadgetConfigListByType($type)
  {
    $configs = sfConfig::get('op_gadget_config');
    foreach ($configs as $key => $config)
    {
      if (in_array($type, self::getTypes($key)))
      {
        $configName = 'op_'.sfInflector::underscore($key);
        if ('gadget' !== $key)
        {
          $configName .= '_gadget';
        }
        $configName .= '_list';

        return sfConfig::get($configName, array());
      }
    }

    return array();
  }

  public function appendRoles(Zend_Acl $acl)
  {
    return $acl
      ->addRole(new Zend_Acl_Role('anonymous'))
      ->addRole(new Zend_Acl_Role('everyone'), 'anonymous');
  }

  public function appendRules(Zend_Acl $acl, $resource = null)
  {
    $acl->allow('everyone', $resource, 'view');

    if (4 == $resource->getConfig('viewable_privilege'))
    {
      $acl->allow('anonymous', $resource, 'view');
    }

    return $acl;
  }
}
