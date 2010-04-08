<?php

/**
 * This file is part of the OpenPNE package.
 * (c) OpenPNE Project (http://www.openpne.jp/)
 *
 * For the full copyright and license information, please view the LICENSE
 * file and the NOTICE file that were distributed with this source code.
 */

/**
 * opViewParameterHolder stores all variables that will be available to the template.
 *
 * @package    OpenPNE
 * @subpackage view
 * @author     Kousuke Ebihara <ebihara@php.net>
 */
class opViewParameterHolder extends sfViewParameterHolder
{
  public function toArray()
  {
    $event = $this->dispatcher->filter(new sfEvent($this, 'template.filter_parameters'), $this->getAll());
    $parameters = $event->getReturnValue();
    $attributes = array();

    if ($this->isEscaped())
    {
      if (is_array($parameters))
      {
        $attributes['sf_data'] = new opOutputEscaperArrayDecorator($this->getEscapingMethod(), $parameters);
      }
      else
      {
        $attributes['sf_data'] = sfOutputEscaper::escape($this->getEscapingMethod(), $parameters);
      }
      foreach ($attributes['sf_data'] as $key => $value)
      {
        $attributes[$key] = $value;
      }
    }
    else if (in_array($this->getEscaping(), array('off', false), true))
    {
      $attributes = $parameters;
      $attributes['sf_data'] = sfOutputEscaper::escape(ESC_RAW, $parameters);
    }
    else
    {
      throw new InvalidArgumentException(sprintf('Unknown strategy "%s".', $this->getEscaping()));
    }

    return $attributes;
  }
}
