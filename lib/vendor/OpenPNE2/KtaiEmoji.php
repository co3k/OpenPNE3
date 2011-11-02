<?php
/**
 * @copyright 2005-2008 OpenPNE Project
 * @license   http://www.php.net/license/3_01.txt PHP License 3.01
 */

class OpenPNE_KtaiEmoji
{
    //変換テーブル
    var $relation_list;

    function getFromRelationList($o_carrier, $c_carrier, $o_id)
    {
        if (!$this->relation_list) {
            $this->relation_list = include 'OpenPNE2/KtaiEmoji/RelationList.php';
        }

        return $this->relation_list[$o_carrier][$c_carrier][$o_id];
    }

    function &getInstance()
    {
        static $singleton;
        if (empty($singleton)) {
            $singleton = new OpenPNE_KtaiEmoji();
        }
        return $singleton;
    }

    /**
     * 与えられた絵文字からその絵文字の絵文字コードを取得する
     */
    function get_emoji_code4emoji($emoji, $carrier = '')
    {
        $emoji_code = '';
        switch ($carrier) {
        case 'i':
            $converter = OpenPNE_KtaiEmoji_Docomo::getInstance();
            $emoji_code = $converter->get_emoji_code4emoji($emoji);
            break;
        case 's':
            $converter = OpenPNE_KtaiEmoji_Softbank::getInstance();
            $emoji_code = $converter->get_emoji_code4emoji($emoji);
            break;
        case 'e':
            $converter = OpenPNE_KtaiEmoji_Au::getInstance();
            $emoji_code = $converter->get_emoji_code4emoji($emoji);
            break;
        default:
            //キャリアが指定されていない場合は全てのキャリアでチェックを行う
            $converter = OpenPNE_KtaiEmoji_Docomo::getInstance();
            $emoji_code = $converter->get_emoji_code4emoji($emoji);
            if (!$emoji_code) {
                $converter = OpenPNE_KtaiEmoji_Softbank::getInstance();
                $emoji_code = $converter->get_emoji_code4emoji($emoji);
            }
            if (!$emoji_code) {
                $converter = OpenPNE_KtaiEmoji_Au::getInstance();
                $emoji_code = $converter->get_emoji_code4emoji($emoji);
            }
            break;
        }

        return $emoji_code;
    }

    /**
     * 絵文字コードを指定キャリアの絵文字もしくは代替文字列に変換する
     */
    function convert_emoji($o_code, $c_carrier = null)
    {
        $o_carrier = $o_code[0];
        $o_id = substr($o_code, 2);

        if (is_null($c_carrier) || ($o_carrier == $c_carrier)) {  // キャリアの変更がないか、キャリアが指定されていない場合はそのまま変換処理
            $c_code = $o_id;
            switch ($c_carrier) {
            case 'i':
                $converter = OpenPNE_KtaiEmoji_Docomo::getInstance();
                break;
            case 's':
                $converter = OpenPNE_KtaiEmoji_Softbank::getInstance();
                break;
            case 'e':
                $converter = OpenPNE_KtaiEmoji_Au::getInstance();
                break;
            default:
                // PC向けau/SoftBank→DoCoMo絵文字変換
                if ((!defined('OPENPNE_EMOJI_DOCOMO_FOR_PC') || OPENPNE_EMOJI_DOCOMO_FOR_PC) && $o_carrier !== 'i') {
                    return self::convertEmoji($this->getFromRelationList($o_carrier, 'i', $o_id));
                }

                $c_code = $o_code;  // 画像出力の際にキャリア情報が必要になるため、絵文字IDではなく絵文字コードを用いる
                $converter = OpenPNE_KtaiEmoji_Img::getInstance();
                break;
            }
            return $converter->get_emoji4emoji_code_id($c_code);
        } else {  // キャリアの変更がある場合、ここでの変換処理はおこなわず、対応する文字列に置換した上で再度変換処理
            return self::convertEmoji($this->getFromRelationList($o_carrier, $c_carrier, $o_id));
        }
    }

  public static function convertEmoji($str)
  {
    $pattern = '/\[([ies]:[0-9]{1,3})\]/';
    return preg_replace_callback($pattern, array('OpenPNE_KtaiEmoji', 'convertEmojiCallback'), $str);
  }

  protected static function convertEmojiCallback($matches)
  {
    $request = sfContext::getInstance()->getRequest();
    $org_code = $matches[1];

    $carrier = null;
    if ($request->getMobile()->isDoCoMo() || $request->getMobile()->isWillcom())
    {
      $carrier = 'i';
    }
    elseif ($request->getMobile()->isSoftBank())
    {
      $carrier = 's';
    }
    elseif ($request->getMobile()->isEZweb())
    {
      $carrier = 'e';
    }

    $ktaiEmoji = self::getInstance();
    $emojiString = $ktaiEmoji->convert_emoji($org_code, $carrier);
    if ($emojiString)
    {
      return $emojiString;
    }
    else
    {
      return '〓';
    }
  }

  public static function convertDoCoMoEmojiToOpenPNEFormat($bin)
  {
    $iemoji = '\xF8[\x9F-\xFC]|\xF9[\x40-\xFC]';
    if (preg_match('/'.$iemoji.'/', $bin))
    {
      $unicode = mb_convert_encoding($bin, 'UCS2', 'SJIS-win');
      $emoji_code = OpenPNE_KtaiEmoji::getInstance();
      $code = $emoji_code->get_emoji_code4emoji(sprintf('&#x%02X%02X;', ord($unicode[0]), ord($unicode[1])), 'i');
      return '['.$code.']';
    }
    return '';
  }

  public static function convertEZwebEmojiToOpenPNEFormat($bin)
  {
    $sjis = (ord($bin[0]) << 8) + ord($bin[1]);
    if ($sjis >= 0xF340 && $sjis <= 0xF493)
    {
      if ($sjis <= 0xF352)
      {
        $unicode = $sjis - 3443;
      }
      elseif ($sjis <= 0xF37E)
      {
        $unicode = $sjis - 2259;
      }
      elseif ($sjis <= 0xF3CE)
      {
        $unicode = $sjis - 2260;
      }
      elseif ($sjis <= 0xF3FC)
      {
        $unicode = $sjis - 2241;
      }
      elseif ($sjis <= 0xF47E)
      {
        $unicode = $sjis - 2308;
      }
      else
      {
        $unicode = $sjis - 2309;
      }
    }
    elseif ($sjis >= 0xF640 && $sjis <= 0xF7FC)
    {
      if ($sjis <= 0xF67E)
      {
        $unicode = $sjis - 4568;
      }
      elseif ($sjis <= 0xF6FC)
      {
        $unicode = $sjis - 4569;
      }
      elseif ($sjis <= 0xF77E)
      {
        $unicode = $sjis - 4636;
      }
      elseif ($sjis <= 0xF7D1)
      {
        $unicode = $sjis - 4637;
      }
      elseif ($sjis <= 0xF7E4)
      {
        $unicode = $sjis - 3287;
      }
      else
      {
        $unicode = $sjis - 4656;
      }
    }
    else
    {
      return '';
    }
    $emoji_code = OpenPNE_KtaiEmoji::getInstance();
    $code = $emoji_code->get_emoji_code4emoji(sprintf('&#x%04X;', $unicode), 'e');
    return '['.$code.']';
  }

  public static function convertSoftBankEmojiToOpenPNEFormat($bin)
  {
    $sjis1 = ord($bin[0]);
    $sjis2 = ord($bin[1]);
    $codepoint = 0;
    switch ($sjis1)
    {
      case 0xF9:
        if ($sjis2 >= 0x41 && $sjis2 <= 0x7E)
        {
          $codepoint = 0xE000 + $sjis2 - 0x40;
        }
        elseif ($sjis2 >= 0x80 && $sjis2 <= 0x9B)
        {
          $codepoint = 0xE000 + $sjis2 - 0x41;
        }
        elseif ($sjis2 >= 0xA1 && $sjis2 <= 0xED)
        {
          $codepoint = 0xE300 + $sjis2 - 0xA0;
        }
        break;
      case 0xF7:
        if ($sjis2 >= 0x41 && $sjis2 <= 0x7E)
        {
          $codepoint = 0xE100 + $sjis2 - 0x40;
        }
        elseif ($sjis2 >= 0x80 && $sjis2 <= 0x9B)
        {
          $codepoint = 0xE100 + $sjis2 - 0x41;
        }
        elseif ($sjis2 >= 0xA1 && $sjis2 <= 0xF3)
        {
          $codepoint = 0xE200 + $sjis2 - 0xA0;
        }
        break;
      case 0xFB:
        if ($sjis2 >= 0x41 && $sjis2 <= 0x7E)
        {
          $codepoint = 0xE400 + $sjis2 - 0x40;
        }
        elseif ($sjis2 >= 0x80 && $sjis2 <= 0x8D)
        {
          $codepoint = 0xE400 + $sjis2 - 0x41;
        }
        elseif ($sjis2 >= 0xA1 && $sjis2 <= 0xD7)
        {
          $codepoint = 0xE500 + $sjis2 - 0xA0;
        }
        break;
      default:
        return '';
    }
    $emoji_code = OpenPNE_KtaiEmoji::getInstance();
    $code = $emoji_code->get_emoji_code4emoji(sprintf('&#x%04X;', $codepoint), 's');
    return '['.$code.']';
  }
}

?>
