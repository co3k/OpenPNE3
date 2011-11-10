<?php

class opDoctrineMapperHydrator extends Doctrine_Hydrator_ArrayDriver
{
    protected function prepareHydration()
    {
      reset($this->_queryComponents);
      $this->_rootAlias = key($this->_queryComponents);;
    }

    protected function generateIdTemplate()
    {
      $idTemplate = array();

      foreach ($this->_queryComponents as $dqlAlias => $data)
      {
        $idTemplate[$dqlAlias] = '';
      }

      return $idTemplate;
    }

    protected function createInstanceFromRowData($rowData)
    {
      $table = $this->_queryComponents[$this->_rootAlias]['table'];
      $class = $table->getOption('name').'Mapper';

      $data = array();

      foreach ($rowData as $alias => $values)
      {
        if ($alias === $this->_rootAlias)
        {
          $data = array_merge($data, $values);

          continue;
        }

        $map = $this->_queryComponents[$alias];
        $relation = $map['relation'];
        $relationAlias = $relation->getAlias();

        $data[$relationAlias] = array();
        if ($relation->isOneToOne())
        {
          $data[$relationAlias] = $values;
        }
        else
        {
          $field = $this->_getCustomIndexField($alias);
          if ($field)
          {
            $data[$relationAlias][$values[$field]] = $values;
          }
          else
          {
            $data[$relationAlias][] = $values;
          }
        }
      }

      $instance = new $class($data, $table->getOption('name'));

      return $instance;
    }

    // this method is based Doctrine_Hydrator_Graph::hydrateResultSet()
    public function hydrateResultSet($stmt)
    {
        $this->prepareHydration();

        $results = array();
        $cache = array();
        $idTemplate = $this->generateIdTemplate();

        while ($data = $stmt->fetch(Doctrine_Core::FETCH_ASSOC))
        {
          $table = $this->_queryComponents[$this->_rootAlias]['table'];
          if ($table->getConnection()->getAttribute(Doctrine_Core::ATTR_PORTABILITY) & Doctrine_Core::PORTABILITY_RTRIM)
          {
            array_map('rtrim', $data);
          }

          $id = $idTemplate; // initialize the id-memory
          $nonemptyComponents = array();
          $rowData = $this->_gatherRowData($data, $cache, $id, $nonemptyComponents);

          $results[] = $this->createInstanceFromRowData($rowData);
        }

        return $results;
    }
}
