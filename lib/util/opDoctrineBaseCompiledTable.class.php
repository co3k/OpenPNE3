<?php

abstract class opDoctrineBaseCompiledTable extends Doctrine_Table
{
  protected $isCompiled = false;

  public $definition;

  public function __construct($name, Doctrine_Connection $conn, $initDefinition = false)
  {
    if (!$this->isCompiled)
    {
      return parent::__construct($name, $conn, $initDefinition);
    }

    $this->definition = new opDoctrineDefinitionRecord($this);

    parent::__construct($name, $conn, false);

    if ($initDefinition)
    {
        $this->initDefinition();
        $this->initIdentifier();
        $this->setUp();
        if ($this->isTree())
        {
          $this->getTree()->setUp();
        }
    }

    unset($this->definition);
  }

  public function setUp()
  {
  }

  public function setTableDefinition()
  {
  }

  public function initDefinition()
  {
    $this->setTableDefinition();

    $this->_options['declaringClass'] = $name = $this->_options['name'];

    // set the table definition for the given tree implementation
    if ($this->isTree())
    {
      $this->getTree()->setTableDefinition();
    }
    $this->columnCount = count($this->_columns);

    if (!isset($this->_options['tableName']))
    {
      $this->setTableName(Doctrine_Inflector::tableize($class->getName()));
    }
  }
}
