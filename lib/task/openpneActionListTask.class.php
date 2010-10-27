<?php

/**
 * This file is part of the OpenPNE package.
 * (c) OpenPNE Project (http://www.openpne.jp/)
 *
 * For the full copyright and license information, please view the LICENSE
 * file and the NOTICE file that were distributed with this source code.
 */

/**
 * openpneActionListTask
 *
 * @package    OpenPNE
 * @subpackage task
 * @author     Kousuke Ebihara <ebihara@php.net>
 */
class openpneActionListTask extends sfBaseTask
{
  protected function configure()
  {
    $this->addArguments(array(
      new sfCommandArgument('application', sfCommandArgument::REQUIRED, 'The application name'),
    ));

    $this->addOptions(array(
      new sfCommandOption('env', null, sfCommandOption::PARAMETER_REQUIRED, 'The environment', 'dev'),
    ));

    $this->namespace        = 'openpne';
    $this->name             = 'action-list';
    $this->briefDescription = 'action list wo haku';
    $this->detailedDescription = <<<EOF
ganbare
EOF;
  }

  protected function execute($arguments = array(), $options = array())
  {
    $actionList = array();

    $app = $arguments['application'];
    $moduleBaseDirs = array();
    foreach ($this->configuration->getPluginPaths() as $path)
    {
      $moduleBaseDirs[] = $path.'/modules';
      $moduleBaseDirs[] = $path.'/apps/'.$app.'/modules';
    }
    $moduleBaseDirs[] = $this->configuration->getSymfonyLibDir().'/controller';
    $moduleBaseDirs[] = sfConfig::get('sf_app_module_dir');

    $moduleDirs = sfFinder::type('dir')->maxdepth(0)->in($moduleBaseDirs);
    $actionDirs = array();
    foreach ($moduleDirs as $moduleDir)
    {
      $actionDirs[] = $moduleDir.'/actions';
    }

    $actions = sfFinder::type('file')->name(array('actions.class.php', '*Action.class.php'))->maxdepth(0)->in($actionDirs);
    foreach ($actions as $action)
    {
      $className = preg_replace('/[^a-zA-Z1-9]/', '_', $action);
      $classString = file_get_contents($action);

      eval('?>'.preg_replace('/class [^ ]+/', 'class '.$className, $classString, 1));

      if (preg_match('#.*modules/([^/]+)/.*?$#', $action, $matches))
      {
        $module = $matches[1];
      }
      else
      {
        $module = 'default';
      }

      $r = new ReflectionClass($className);
      if (!$r->isSubclassOf('sfActions'))  // single action
      {
        $actionList[] = array(
          $action,
          $module.'/'.substr(basename($action), 0, -strlen('Action.class.php')),
        );
      }
      else
      {
        $methods = $r->getMethods(ReflectionMethod::IS_PUBLIC);
        foreach ($methods as $key => $method)
        {
          if ('execute' !== $method->name && 0 === strpos($method->name, 'execute'))
          {
            $actionList[] = array(
              $action,
              $module.'/'.lcfirst(substr($method->name, strlen('execute'))),
            );
          }
        }
      }
    }

    foreach ($actionList as $item)
    {
      if (preg_match('#.*plugins/([^/]+Plugin)/.*?$#', $item[0], $matches))
      {
        $plugin = $matches[1];
      }
      else
      {
        $plugin = 'core';
      }


      echo $item[1]."\t".$plugin.PHP_EOL;
    }
  }
}
