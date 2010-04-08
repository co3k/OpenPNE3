<?php

/**
 * This file is part of the OpenPNE package.
 * (c) OpenPNE Project (http://www.openpne.jp/)
 *
 * For the full copyright and license information, please view the LICENSE
 * file and the NOTICE file that were distributed with this source code.
 */

if (!defined('E_DEPRECATED'))
{
  define('E_DEPRECATED', 8192);
}

/**
 * opProjectConfiguration
 *
 * @package    OpenPNE
 * @subpackage config
 * @author     Kousuke Ebihara <ebihara@tejimaya.com>
 */
class opProjectConfiguration extends sfProjectConfiguration
{
  public function generateFixedMethodToDoctrineRecord(sfEvent $event)
  {
    if ($event->getSubject() instanceof sfDoctrineBuildModelTask)
    {
      if (!sfConfig::get('ebi_no_magic', false))
      {
        return;
      }

      $defnitionTemplate = "\n    public function %s(%s)\n    {\n"
                         ."        return \$this->_%s('%s'%s);\n"
                         ."    }\n";

      $config = $event->getSubject()->getCliConfig();
      $builderOptions = $this->getPluginConfiguration('sfDoctrinePlugin')->getModelBuilderOptions();

      $models = sfFinder::type('file')->name('Base*.php')->in($config['models_path']);
      foreach ($models as $model)
      {
        $code = file_get_contents($model);
        $newDefinitions = '';

        $matches = array();
        if (preg_match_all('/@property (\w+) \$(\w+)/', $code, $matches, PREG_SET_ORDER))
        {
          foreach ($matches as $match)
          {
            $type = $match[1];
            $property = $match[2];
            $getter = 'get'.sfInflector::camelize($property);
            $setter = 'set'.sfInflector::camelize($property);

            // method is already exists
            if (false !== strpos($code, 'public function '.$getter.'(')
              || false !== strpos($code, 'public function '.$setter.'(')
            )
            {
              continue;
            }

            $isColumn = ord($type[0]) >= 97 && ord($type[0]) <= 122; // a to z
            if ($isColumn)
            {
              // it is not related-column
              if (false === strpos($code, '\'local\' => \''.$property.'\''))
              {
                $setterArgument = ', $value';
                $getterArgument = '';
                if ('timestamp' === $type)
                {
                  $setterArgument .= ', true, true';
                  $getterArgument .= ', true, true';
                }
                else
                {
                  $setterArgument .= ', true, false';
                  $getterArgument .= ', true, false';
                }

                $newDefinitions .= sprintf($defnitionTemplate, $setter, '$value', 'set', $property, $setterArgument);
                $newDefinitions .= sprintf($defnitionTemplate, $getter, '', 'get', $property, $getterArgument);
              }
            }
          }
        }

        if ($newDefinitions)
        {
          $pos = strrpos($code, '}');
          $tail = substr($code, $pos);
          $code = substr($code, 0, $pos);
          $code .= $newDefinitions.$tail;
        }

        file_put_contents($model, $code);
      }
    }
  }


  static public function listenToPreCommandEvent(sfEvent $event)
  {
    require_once dirname(__FILE__).'/../behavior/opActivateBehavior.class.php';
    opActivateBehavior::disable();
  }

  public function setup()
  {
    $this->enableAllPluginsExcept(array('sfPropelPlugin'));
    $this->setIncludePath();

    $this->setOpenPNEConfiguration();

    sfConfig::set('doctrine_model_builder_options', array(
      'baseClassName' => 'opDoctrineRecord',
    ));

    $this->dispatcher->connect('command.pre_command', array(__CLASS__, 'listenToPreCommandEvent'));
    $this->dispatcher->connect('command.post_command', array($this, 'generateFixedMethodToDoctrineRecord'));

    $this->setupProjectOpenPNE();
  }

  protected function configureSessionStorage($name, $options = array())
  {
    $sessionName = 'OpenPNE_'.sfConfig::get('sf_app', 'default');
    $params = array('session_name' => $sessionName);

    if ('memcache' === $name)
    {
      sfConfig::set('sf_factory_storage', 'opMemcacheSessionStorage');
      sfConfig::set('sf_factory_storage_parameters', array_merge((array)$options, $params));
    }
    elseif ('database' === $name)
    {
      sfConfig::set('sf_factory_storage', 'opPDODatabaseSessionStorage');
      sfConfig::set('sf_factory_storage_parameters', array_merge(array(
        'db_table'    => 'session',
        'database'    => 'doctrine',
        'db_id_col'   => 'id',
        'db_data_col' => 'data',
        'db_time_col' => 'time',
      ), (array)$options, $params));
    }
    elseif ('file' !== $name)
    {
      sfConfig::set('sf_factory_storage', $name);
      sfConfig::set('sf_factory_storage_parameters', array_merge((array)$options, $params));
    }
  }

  public function setIncludePath()
  {
    sfToolkit::addIncludePath(array(
      dirname(__FILE__).'/../vendor/PEAR/',
      dirname(__FILE__).'/../vendor/OAuth/',
      dirname(__FILE__).'/../vendor/simplepie/',
    ));
  }

  public function configureDoctrine($manager)
  {
    $manager->setAttribute(Doctrine::ATTR_AUTOLOAD_TABLE_CLASSES, true);
    $manager->setAttribute(Doctrine::ATTR_RECURSIVE_MERGE_FIXTURES, true);
    $manager->setAttribute(Doctrine::ATTR_QUERY_CLASS, 'opDoctrineQuery');

    if (extension_loaded('apc'))
    {
      $cacheDriver = new Doctrine_Cache_Apc();
      $manager->setAttribute(Doctrine::ATTR_QUERY_CACHE, $cacheDriver);
    }

    $this->setupProjectOpenPNEDoctrine($manager);
  }

  protected function setOpenPNEConfiguration()
  {
    $opConfigCachePath = sfConfig::get('sf_cache_dir').DIRECTORY_SEPARATOR.'OpenPNE.yml.php';
    if (is_readable($opConfigCachePath))
    {
      $config = (array)include($opConfigCachePath);
    }
    else
    {
      $path = OPENPNE3_CONFIG_DIR.'/OpenPNE.yml';
      $config = sfYaml::load($path.'.sample');
      if (is_readable($path))
      {
        $config = array_merge($config, sfYaml::load($path));
      }

      file_put_contents($opConfigCachePath, '<?php return '.var_export($config, true).';');
    }

    $this->configureSessionStorage($config['session_storage']['name'], (array)$config['session_storage']['options']);
    unset($config['session_storage']);

    foreach ($config as $key => $value)
    {
      sfConfig::set('op_'.$key, $value);
    }
  }
}
