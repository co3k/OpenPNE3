<?php

/**
 * This file is part of the OpenPNE package.
 * (c) OpenPNE Project (http://www.openpne.jp/)
 *
 * For the full copyright and license information, please view the LICENSE
 * file and the NOTICE file that were distributed with this source code.
 */

/**
 * Tests a dynamic value in response might be escaped / unescaped
 *
 * @package    OpenPNE
 * @subpackage test
 * @author     Kousuke Ebihara <ebihara@php.net>
 */
class opTesterHtmlEscape extends sfTester
{
  protected
    $response,
    $context;

  const TEST_DATA_TEMPLATE = '<&"\'>ESCAPING HTML TEST DATA FOR %model%.%column%';
  const PREG_DELIMITER = '/';

  public function prepare()
  {
  }

  public function initialize()
  {
    $this->context = $this->browser->getContext();
    $this->response = $this->browser->getResponse();

    $this->context->getConfiguration()->loadHelpers(array('Escaping', 'opUtil'));
  }

  protected function getRawTestData($model, $column)
  {
    return strtr(self::TEST_DATA_TEMPLATE, array(
      '%model%'  => $model,
      '%column%' => $column,
    ));
  }

  protected function getEscapedTestData($model, $column)
  {
    return sfOutputEscaper::escape(ESC_SPECIALCHARS, $this->getRawTestData($model, $column));
  }

  protected function countEscapedData($model, $column)
  {
    return substr_count($this->response->getContent(), $this->getEscapedTestData($model, $column));
  }

  protected function countRawData($model, $column)
  {
    return substr_count($this->response->getContent(), $this->getRawTestData($model, $column));
  }

  public function isAllEscapedData($model, $column)
  {
    $isEscaped = !$this->countRawData($model, $column) && $this->countEscapedData($model, $column);

    if ($isEscaped)
    {
      $this->tester->pass(sprintf('all of value of "%s"."%s" are escaped.', $model, $column));
    }
    else
    {
      $this->tester->fail(sprintf('there is / are some raw value(s) of "%s"."%s".', $model, $column));
    }

    return $this->getObjectToReturn();
  }

  public function isAllRawData($model, $column)
  {
    $isEscaped = $this->countRawData($model, $column) && !$this->countEscapedData($model, $column);

    if ($isRaw)
    {
      $this->tester->pass(sprintf('all of value of "%s"."%s" are raw.', $model, $column));
    }
    else
    {
      $this->tester->fail(sprintf('there is / are some escaped value(s) of "%s"."%s".', $model, $column));
    }

    return $this->getObjectToReturn();
  }
}
