<?php

class opOutputEscaperArrayDecorator extends sfOutputEscaperArrayDecorator
{
  public function current()
  {
    $value = current($this->value);
    if (is_object($value) && (
      $value instanceof sfForm ||
      $value instanceof sfFormField ||
      $value instanceof sfFormFieldSchema ||
      $value instanceof opConfig ||
      $value instanceof SnsTermTable ||
      $value instanceof sfModelGeneratorHelper
    ))
    {
      return $value;
    }

    return sfOutputEscaper::escape($this->escapingMethod, $value);
  }

  public function offsetGet($offset)
  {
    $value = $this->value[$offset];
    if (is_object($value) && (
      $value instanceof sfForm ||
      $value instanceof sfFormField ||
      $value instanceof sfFormFieldSchema ||
      $value instanceof opConfig ||
      $value instanceof SnsTermTable ||
      $value instanceof sfModelGeneratorHelper
    ))
    {
      return $value;
    }

    return sfOutputEscaper::escape($this->escapingMethod, $value);
  }
}
