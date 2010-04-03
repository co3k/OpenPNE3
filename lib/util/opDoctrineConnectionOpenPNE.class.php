<?php


class opDoctrineConnectionOpenPNE extends Doctrine_Connection_Mysql
{
  public function unsetDbh()
  {
    if (!$this->transaction->getTransactionLevel())
    {
      $this->dbh = null;
      $this->isConnected = false;
    }
  }
}
