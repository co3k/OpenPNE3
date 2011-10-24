<?php

class opDoctrineMapperHydrator extends Doctrine_Hydrator_Abstract
{
    public function hydrateResultSet($stmt)
    {
        $class = $this->getRootComponent()->getOption('name').'Mapper';

        $rs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rs as $r) {
            $results[] = new $class($r);
        }

        return $results;
    }
}
