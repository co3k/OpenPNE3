<?php

class opLazyUnserializeRoute
{
  protected $serialized, $unserialized, $parameters;

  public function __construct($serialized)
  {
    $this->serialized = $serialized;
  }

  public function setDefaultParameters($parameters)
  {
    $this->parameters = $parameters;
  }

  public function matchesParameters($params, $context = array())
  {
    if (!isset($params['action']) || !isset($params['module']))
    {
        return $this->unserialize()->matchesParameters($params, $context);
    }

    if ('C:7:"sfRoute"' === substr($this->serialized, 0, 13))
    {
      $pos = strrpos($this->serialized, '{s:6:"module";s:');
      if (false === $pos)
      {
        return $this->unserialize()->matchesParameters($params, $context);
      }

      $serializedParams = substr($this->serialized, $pos);
      if (!preg_match('/{s:6:"module";s:[0-9]+:"([^"]+)";s:6:"action";s:[0-9]+:"([^"]+)";}/', $serializedParams, $matches))
      {
        return $this->unserialize()->matchesParameters($params, $context);
      }

      if ($this->isRegexPattern($matches[1]) || $this->isRegexPattern($matches[2]))
      {
        return $this->unserialize()->matchesParameters($params, $context);
      }

      if ($params['module'] === $matches[1] && $params['action'] === $matches[2])
      {
        return $this->unserialize()->matchesParameters($params, $context);
      }
    }

    return false;
  }

  public function isRegexPattern($string)
  {
    return ($string !== preg_quote($string));
  }

  public function __call($method, $params = array())
  {
    return call_user_func_array(array($this->unserialize(), $method), $params);
  }

  public function unserialize()
  {
    if (!$this->unserialized)
    {
      $this->unserialized = unserialize($this->serialized);
      if ($this->parameters)
      {
        $this->unserialized->setDefaultParameters($this->parameters);
        unset($this->parameters);
      }

      unset($this->serialized);
    }

    return $this->unserialized;
  }
}
