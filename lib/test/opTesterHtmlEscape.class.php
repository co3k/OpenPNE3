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

  const TEST_DATA_TEMPLATE = '<&"\'>%model%.%column% ESCAPING HTML TEST DATA';

  public function prepare()
  {
  }

  public function initialize()
  {
    $this->context = $this->browser->getContext();
    $this->response = $this->browser->getResponse();

    $this->context->getConfiguration()->loadHelpers(array('Escaping', 'opUtil'));
  }

  static public function getRawTestData($model, $column)
  {
    return strtr(self::TEST_DATA_TEMPLATE, array(
      '%model%'  => $model,
      '%column%' => $column,
    ));
  }

  static public function getEscapedTestData($model, $column)
  {
    return sfOutputEscaper::escape(ESC_SPECIALCHARS, $this->getRawTestData($model, $column));
  }

  protected function countTestData($model, $column, $isEscaped, $truncateOption = array())
  {
    if ($isEscaped)
    {
      $string = $this->getEscapedTestData($model, $column);
    }
    else
    {
      $string = $this->getRawTestData($model, $column);
    }

    if ($truncateOption)
    {
      if (!is_array($truncateOption))
      {
        $truncateOption = array();
      }

      $width = isset($truncateOption['width']) ? $truncateOption['width'] : 80;
      $etc = isset($truncateOption['etc']) ? $truncateOption['etc'] : '';
      $rows = isset($truncateOption['rows']) ? $truncateOption['rows'] : 1;

      $string = op_truncate($string, $width, $etc, $rows);
    }

    return substr_count($this->response->getContent(), $string);
  }

  public function countEscapedData($expected, $model, $column, $truncateOption = array())
  {
    $this->tester->is($this->countTestData($model, $column, true, $truncateOption), $expected, sprintf('%d data of "%s"."%s" are escaped.', $expected, $model, $column));

    return $this->getObjectToReturn();
  }

  public function countRawData($expected, $model, $column, $truncateOption = array())
  {
    $this->tester->is($this->countTestData($model, $column, false, $truncateOption), $expected, sprintf('%d data of "%s"."%s" are raw.', $expected, $model, $column));

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
