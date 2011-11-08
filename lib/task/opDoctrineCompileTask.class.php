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
    $isAbstract = true;

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

      $lines = array_slice(file($r->getFileName()), $start, ($end - $start));

      // removes ::setTableDefinition()
      if (!$isAbstract && 'Base'.$modelName === $class)
      {
        $rm = new ReflectionMethod($class, 'setTableDefinition');
        if ($rm && $rm->getFileName() === $r->getFileName())
        {
          $methodStart = $rm->getStartLine() - $start;
          $methodEnd = $rm->getEndLine() - $start;

          $head = array_slice($lines, 0, $methodStart - 1);
          $tail = array_slice($lines, $methodEnd);

          $lines = array_merge($head, $tail);
        }
      }

      // append properties like [record]::setTableDefinition()
      if (!$isAbstract && $modelName.'Table' === $class)
      {
        $table = Doctrine::getTable($modelName);
        $table->initDefinition();
        $lines[2] =  $this->buildTablePropertyString($table).$lines[2];
      }

      $results[] = sprintf('if (!class_exists(\'%s\', false)) {', $class);
      $results = array_merge($results, $lines);
      $results[] = '}';
    }

    $content = sfToolkit::stripComments('<?php '.implode(PHP_EOL, $results));

    return $content;
  }

  public function buildTablePropertyString($table)
  {
    $options = array(
      'name' => '',
      'tableName' => '',
      'sequenceName' => '',
      'inheritanceMap' => '',
      'enumMap' => '',
      'type' => '',
      'charset' => '',
      'collate' => '',
      'treeImpl' => '',
      'treeOptions' => '',
      'indexes' => '',
      'parents' => '',
      'joinedParents' => '',
      'queryParts' => '',
      'versioning' => '',
      'subclasses' => '',
      'orderBy' => '',
    );

    // opDoctrineRecord::hasColumn
    $fieldNames = array();
    $columnNames = array();
    foreach ($table->getColumnNames() as $columnName)
    {
      $fieldName = $table->getFieldName($columnName);
      $fieldNames[$columnName] = $fieldName;
      $columnNames[$fieldName] = $columnName;
    }
    $columns = $table->getColumns();
    $identifiers = (array)$table->getIdentifier();
    $hasDefaultValues = $table->hasDefaultValues();

    foreach ($options as $key => $value)
    {
      $options[$key] = $table->getOption($key);
    }

    $string = 'protected $_columnNames = '.var_export($columnNames, true).';'.PHP_EOL
            . 'protected $_fieldNames = '.var_export($fieldNames, true).';'.PHP_EOL
            . 'protected $_columns = '.var_export($columns, true).';'.PHP_EOL
            . 'protected $_identifier = '.var_export($identifiers, true).';'.PHP_EOL
            . 'protected $hasDefaultValues = '.var_export($hasDefaultValues, true).';'.PHP_EOL
            . 'protected $_options = '.var_export($options, true).';'.PHP_EOL
    ;

    return $string;
  }
}
