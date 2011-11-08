<?php

/**
 * This file is part of the OpenPNE package.
 * (c) OpenPNE Project (http://www.openpne.jp/)
 *
 * For the full copyright and license information, please view the LICENSE
 * file and the NOTICE file that were distributed with this source code.
 */

/**
 * opDoctrineCompileTask
 *
 * @package    OpenPNE
 * @subpackage task
 * @author     Kousuke Ebihara <ebihara@php.net>
 */
class opDoctrineCompileTask extends sfDoctrineBaseTask
{
  protected function configure()
  {
    $this->addOptions(array(
      new sfCommandOption('application', null, sfCommandOption::PARAMETER_OPTIONAL, 'The application name', true),
      new sfCommandOption('env', null, sfCommandOption::PARAMETER_REQUIRED, 'The environment', 'prod'),
    ));

    $this->namespace        = 'openpne';
    $this->name             = 'doctrine-compile';
    $this->briefDescription = 'Compile class files for doctrine model classes (table class and record class)';
    $this->detailedDescription = <<<EOF
The [openpne:doctrine-compile|INFO] task compiles class files for doctrine model classes (table class and record class).
Call it with:

  [./symfony openpne:doctrine-compile|INFO]
EOF;
  }

  protected function execute($arguments = array(), $options = array())
  {
    $databaseManager = new sfDatabaseManager($this->configuration);
    $config = $this->getCliConfig();

    $dir = sfConfig::get('sf_lib_dir').'/model/compiled/';
    $this->getFilesystem()->mkdirs($dir);

    $schema = $this->prepareSchemaFile($config['yaml_schema_path']);
    $definitions = sfYaml::load($schema);
    foreach ($definitions as $modelName => $definition)
    {
      $content = $this->getCompiledModelFile($modelName, isset($definition['package_custom_path']) ? $definition['package_custom_path'] : null);
      file_put_contents($dir.$modelName.'.compiled.php', $content);
    }
  }

  protected function getCompiledModelFile($modelName, $basePath = null)
  {
    $coreBasePath = sfConfig::get('sf_lib_dir').'/model/doctrine';
    if (!$basePath)
    {
      $basePath = $coreBasePath;
    }
    $isPluginModel = ($basePath !== $coreBasePath);

    if ($isPluginModel)
    {
      $classes = array(
        'Base'.$modelName, 'Plugin'.$modelName, $modelName,
        'Plugin'.$modelName.'Table', $modelName.'Table',
      );
    }
    else
    {
      $classes = array(
        'Base'.$modelName, $modelName, $modelName.'Table',
      );
    }

    $results = array();

    foreach ($classes as $class)
    {
      $r = new ReflectionClass($class);
      if (!$r)
      {
        continue;
      }

      $start = $r->getStartLine() - 1;
      $end = $r->getEndLine();

      $results[] = sprintf('if (!class_exists(\'%s\', false)) {', $class);
      $results = array_merge($results, array_slice(file($r->getFileName()), $start, ($end - $start)));
      $results[] = '}';
    }

    $content = sfToolkit::stripComments('<?php '.implode(PHP_EOL, $results));

    return $content;
  }
}
