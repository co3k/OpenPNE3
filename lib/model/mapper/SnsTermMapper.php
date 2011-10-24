<?php

class SnsTermMapper
{
  protected $process = array(
    'withArticle' => false,
    'pluralize' => false,
    'fronting' => false,
    'titleize' => false,
  );

  public $lang;
  public $id;
  public $name;
  public $application;
  public $value;

  public $Translation = array();

  public function __construct($data)
  {
    $delimitor = '__';

    foreach ($data as $key => $value)
    {
        $field = substr($key, strpos($key, $delimitor) + strlen($delimitor));
        $this->$field = $value;
    }

    $this->Translation[$this->lang] = array(
        'value' => $value,
    );
  }

  public function doFronting($string)
  {
    if ('en' === $this->lang)
    {
      $string = strtoupper($string[0]).substr($string, 1);
    }

    return $string;
  }

  public function doTitleize($string)
  {
    if ('en' === $this->lang)
    {
      $words = array_map('ucfirst', explode(' ' ,$string));
      $string = implode(' ', $words);
    }

    return $string;
  }

  public function doPluralize($string)
  {
    if ('en' === $this->lang)
    {
      $string = opInflector::pluralize($string);
    }

    return $string;
  }

  public function doWithArticle($string)
  {
    if ('en' === $this->lang)
    {
      $string = opInflector::getArticle($string).' '.$string;
    }

    return $string;
  }

  public function __toString()
  {
    $value = $this->Translation[$this->lang]['value'];

    foreach ($this->process as $k => $v)
    {
      if ($v)
      {
        $method = 'do'.ucfirst($k);
        $value = $this->$method($value);
      }

      $this->process[$k] = false;
    }

    return htmlspecialchars($value, ENT_QUOTES, sfConfig::get('sf_charset'));
  }

  public function __call($name, $args)
  {
    if (isset($this->process[$name]))
    {
      $this->process[$name] = true;

      return $this;
    }

    throw new Exception();
  }
}
