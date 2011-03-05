<?php

/**
 * This file is part of the OpenPNE package.
 * (c) OpenPNE Project (http://www.openpne.jp/)
 *
 * For the full copyright and license information, please view the LICENSE
 * file and the NOTICE file that were distributed with this source code.
 */

/**
 * opLazyUnserializeRoutes
 *
 * @package    OpenPNE
 * @subpackage routing
 * @author     Rimpei Ogawa <ogawa@tejimaya.com>
 */
class opLazyUnserializeRoutes extends ArrayIterator
{
  public function getPrefixes()
  {
    $prefixes = array();

    $this->rewind();
    while ($this->valid())
    {
      $current = parent::current();
      $prefix = is_array($current) ? $current[0] : '';

      $prefixes[$this->key()] = $prefix;

      $this->next();
    }
    $this->rewind();

    return $prefixes;
  }

  public function offsetGet($offset)
  {
    if (!$this->offsetExists($offset))
    {
      return null;
    }

    $value = parent::offsetGet($offset);

    if (is_array($value))
    {
      $value = unserialize($value[1]);

      $value->setDefaultParameters(sfContext::getInstance()->getRouting()->getDefaultParameters());

      $this->offsetSet($offset, $value);
    }

    return $value;
  }

  public function current()
  {
    return $this->offsetGet($this->key());
  }
}
