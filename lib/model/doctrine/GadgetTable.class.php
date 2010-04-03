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
  protected
    $results,
    $gadgets = array(),
    $gadgetConfigList = array();

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
    if (isset($this->gadgets[$typesName]))
    {
      return $this->gadgets[$typesName];
    }

    $file = sfconfig::get('sf_app_cache_dir').'/config/'.sfInflector::underscore($typesName)."_gadgets.php";
    if (is_readable($file))
    {
      $results = include($file);
      $this->gadgets[$typesName] = $results;
      return $results;
    }

    $types = $this->getTypes($typesName);

    foreach($types as $type)
    {
      $results[$type] = $this->retrieveByType($type);
    }

    file_put_contents($file, "<?php return unserialize('".serialize($results)."');");
    $this->gadgets[$typesName] = $results;

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
    if (isset($this->gadgetConfigList[$type]))
    {
      return $this->gadgetConfigList[$type];
    }

    $file = sfconfig::get('sf_app_cache_dir').'/config/'.sfinflector::underscore($type)."_gadgets_config_list.php";
    if (is_readable($file))
    {
      $resultConfig = include($file);
      $this->gadgetConfigList[$type] = $resultConfig;
      return $resultConfig;
    }

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

        $resultConfig = sfConfig::get($configName, array());
        file_put_contents($file, "<?php return unserialize('".serialize($resultConfig)."');");
        $this->gadgetConfigList[$type] = $resultConfig;
        return $resultConfig;
      }
    }

    $this->gadgetConfigList[$type] = array();
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
