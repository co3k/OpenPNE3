<?php

class sfMessageSource_OpenPNECached extends sfMessageSource_File
{
  protected $dataExt = '.xml.php';

  public function getSource($variant)
  {
    $result = $this->source.'/'.$variant;

    return $result;
  }

  public function getCatalogueList($catalogue)
  {
    $variants = explode('_', $this->culture);
    $base = $this->source.DIRECTORY_SEPARATOR.$catalogue.'.';

    return array(
      $base.$variants[0].$this->dataExt, $base.$this->culture.$this->dataExt,
    );
  }

  public function load($catalogue = 'messages')
  {
    $variants = $this->getCatalogueList($catalogue);

    $isLoaded = false;
    foreach ($variants as $variant)
    {
      if (isset($this->messages[$variant]))
      {
        return true;
      }

      if (is_file($variant))
      {
        $this->messages[$variant] = include($variant);
        $isLoaded = true;
        break;
      }
    }

    return $isLoaded;
  }

  public function save($catalogue = 'messages')
  {
    return true;
  }

  public function delete($message, $catalogue='messages')
  {
    return true;
  }

  public function update($text, $target, $comments, $catalogue = 'messages')
  {
    return true;
  }
}
