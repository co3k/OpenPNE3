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
      new sfCommandOption('env', null, sfCommandOption::PARAMETER_REQUIRED, 'The environment', 'dev'),
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
    $this->getFilesystem()->remove(sfFinder::type('file')->in($dir));
    $this->getFilesystem()->mkdirs($dir);

    $schema = $this->prepareSchemaFile($config['yaml_schema_path']);
    $definitions = sfYaml::load($schema);
    foreach ($definitions as $modelName => $definition)
    {
      $content = $this->getCompiledModelFile($modelName, $definition, isset($definition['package_custom_path']) ? $definition['package_custom_path'] : null);
      file_put_contents($dir.$modelName.'.compiled.php', $content);
    }
  }

  protected function getCompiledModelFile($modelName, $definition, $basePath = null)
  {
    $coreBasePath = sfConfig::get('sf_lib_dir').'/model/doctrine';
    if (!$basePath)
    {
      $basePath = $coreBasePath;
    }
    $isPluginModel = ($basePath !== $coreBasePath);
    $isAbstract = !empty($definition['abstract']);

    if ($isPluginModel)
    {
      $classes = array(
        /*'Base'.$modelName, 'Plugin'.$modelName, $modelName, */
        'Plugin'.$modelName.'Table', $modelName.'Table',
      );
    }
    else
    {
      $classes = array(
        /*'Base'.$modelName, $modelName,*/ $modelName.'Table',
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

      $lines = array_slice(file($r->getFileName()), $start, ($end - $start));

      // append constructors for performance
      if (!$isAbstract && $modelName.'Table' === $class)
      {
        $table = Doctrine::getTable($modelName);
        if (!$table->getOption('joinedParents'))
        {
          $lines[2] = $this->getAvailableCallbacksPropertyString($modelName).PHP_EOL
            .$this->getCompiledFlagString().PHP_EOL
            .$this->buildTableDefinitionString($modelName).$lines[2];
        }
      }

      $results[] = sprintf('if (!class_exists(\'%s\', false)) {', $class);
      $results = array_merge($results, $lines);
      $results[] = '}';
    }

    $content = sfToolkit::stripComments('<?php '.implode(PHP_EOL, $results));
    if (!$isAbstract && $modelName.'Table' === $class)
    {
      $content = str_replace('extends Doctrine_Table', 'extends opDoctrineBaseCompiledTable', $content);
    }

    return $content;
  }

  public function getCompiledFlagString()
  {
    return 'public $isCompiled = true;';
  }

  public function buildTableDefinitionString($model)
  {
    $definitionCode = $this->extractMethodString(new ReflectionMethod($model, 'setTableDefinition'));
    $setUpCode = $this->extractMethodString(new ReflectionMethod($model, 'setUp'));

    $definitionCode = str_replace('$this->', '$this->definition->', $definitionCode);
    $setUpCode = str_replace('$this->', '$this->definition->', $setUpCode);

    $string = $definitionCode.PHP_EOL.$setUpCode;

    return $string;
  }

  public function extractMethodString($reflectionMethod)
  {
    $start = $reflectionMethod->getStartLine() - 1;
    $end = $reflectionMethod->getEndLine();

    $lines = array_slice(file($reflectionMethod->getFileName()), $start, ($end - $start));

    return implode('', $lines);
  }

  public function getListOfDqlCallbacks($model)
  {
    $callbacks = array(
      'preDqlDelete' => false,
      'preDqlUpdate' => false,
      'preDqlSelect' => false,
    );

    foreach ($callbacks as $key => $value)
    {
      $r = new ReflectionMethod($model, $key);
      if ('Doctrine_Record' !== $r->class)
      {
        $callbacks[$key] = true;
      }
    }

    return $callbacks;
  }

  public function getAvailableCallbacksPropertyString($model)
  {
    return 'protected $availableDqlCallbacks = '.var_export($this->getListOfDqlCallbacks($model), true).';';
  }
}
