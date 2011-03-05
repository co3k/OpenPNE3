<?php

/**
 * This file is part of the OpenPNE package.
 * (c) OpenPNE Project (http://www.openpne.jp/)
 *
 * For the full copyright and license information, please view the LICENSE
 * file and the NOTICE file that were distributed with this source code.
 */

/**
 * opPatternRouting
 *
 * @package    OpenPNE
 * @subpackage routing
 * @author     Rimpei Ogawa <ogawa@tejimaya.com>
 */
class opPatternRouting extends sfPatternRouting
{
  public function loadConfiguration()
  {
    parent::loadConfiguration();

    $this->routes = new opLazyUnserializeRoutes($this->routes);
  }

  protected function ensureDefaultParametersAreSet()
  {
    // do nothing
  }

  public function prependRoute($name, $route)
  {
    $this->routes = (array)$this->routes;

    parent::prependRoute($name, $route);

    $this->routes = new opLazyUnserializeRoutes($this->routes);
  }

  public function insertRouteBefore($pivot, $name, $route)
  {
    $this->routes = (array)$this->routes;

    parent::insertRouteBefore($pivot, $name, $route);

    $this->routes = new opLazyUnserializeRoutes($this->routes);
  }

  protected function getRouteThatMatchesUrl($url)
  {
    if (!$this->routes instanceof opLazyUnserializeRoutes)
    {
      return parent::getRouteThatMatchesUrl($url);
    }

    $prefixes = $this->routes->getPrefixes();

    foreach ($prefixes as $name => $prefix)
    {
      // @see sfRoute::matchesUrl()
      if ('' !== $prefix && 0 !== strpos($url, $prefix))
      {
        continue;
      }

      // unserialize route
      $route = $this->routes[$name];

      if (false === $parameters = $route->matchesUrl($url, $this->options['context']))
      {
        continue;
      }

      return array('name' => $name, 'pattern' => $route->getPattern(), 'parameters' => $parameters);
    }

    return false;
  }
}
