<?php

/**
 * This file is part of the sfImageHelper plugin.
 * (c) 2010 Kousuke Ebihara <ebihara@php.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * sfImageGeneratorIM
 *
 * @package    sfImageHandlerPlugin
 * @subpackage image
 * @author     Kousuke Ebihara <ebihara@php.net>
 */
class sfImageGeneratorIM extends sfImageGeneratorImageTransform
{
  protected function creaateTransform()
  {
    return Image_Transform::factory('IM');
  }

  protected function disableInterlace()
  {
    $this->transform->command['interlace'] = '-interlace none';
  }
}
