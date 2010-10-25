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

  protected function countTestData($model, $column, $isEscaped)
  {
    if ($isEscaped)
    {
      $string = $this->getEscapedTestData($model, $column);
    }
    else
    {
      $string = $this->getRawTestData($model, $column);
    }

    return substr_count($this->response->getContent(), $string);
  }

  public function countEscapedData($expected, $model, $column)
  {
    $this->tester->is($this->countTestData($model, $column, true), $expected, sprintf('%d data of "%s"."%s" are escaped.', $expected, $model, $column));

    return $this->getObjectToReturn();
  }

  public function countRawData($expected, $model, $column)
  {
    $this->tester->is($this->countTestData($model, $column, false), $expected, sprintf('%d data of "%s"."%s" are raw.', $expected, $model, $column));

    return $this->getObjectToReturn();
  }

  public function isAllEscapedData($model, $column)
  {
    $isEscaped = !$this->countTestData($model, $column, false) && $this->countTestData($model, $column, true);

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
    $isRaw = $this->countTestData($model, $column, false) && !$this->countTestData($model, $column, true);

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
