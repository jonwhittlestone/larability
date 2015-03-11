<?php namespace Jonwhittlestone\Larability;

use DOMDocument;
use Config;


/**
 * PHP Readability
 *
 * Readability PHP 版本，详见
 *      http://code.google.com/p/arc90labs-readability/
 *
 * ChangeLog:
 *      [+] Combined into Laravel 4 service provider
 *      [+] 2014-02-08 Add lead image param and improved get title function.
 *      [+] 2013-12-04 Better error handling and junk tag removal.
 *      [+] 2011-02-17 初始化版本
 *
 * @date   2015-03-06
 *
 * @author jonwhittlestone<dev@howapped.com>
 * @link   http://www.howapped.com/
 *
 * @author mingcheng<i.feelinglucky#gmail.com>
 * @link   http://www.gracecode.com/
 *
 * @author Tuxion <team#tuxion.nl>
 * @link   http://tuxion.nl/
 */


class Larability {

  // 保存判定结果的标记位名称
  const ATTR_CONTENT_SCORE = "contentScore";

  // DOM 解析类目前只支持 UTF-8 编码
  const DOM_DEFAULT_CHARSET = "utf-8";

  // 当判定失败时显示的内容
  const MESSAGE_CAN_NOT_GET = "Readability was unable to parse this page for content.";

  // DOM 解析类（PHP5 已内置）
  protected $DOM = null;

  // 需要解析的源代码
  protected $source = "";

  // 章节的父元素列表
  private $parentNodes = array();

  private $iteration;

  // 需要删除的标签
  // Note: added extra tags from https://github.com/ridcully
  private $junkTags = Array("style", "form", "iframe", "script", "button", "input", "textarea",
                              "noscript", "select", "option", "object", "applet", "basefont",
                              "bgsound", "blink", "canvas", "command", "menu", "nav", "datalist",
                              "embed", "frame", "frameset", "keygen", "label", "marquee", "link");

  // 需要删除的属性
  private $junkAttrs = Array("style", "class", "onclick", "onmouseover", "align", "border", "margin");

  public function setIteration($iteration)
  {
    $this->iteration = $iteration;
  }

  public function getIteration()
  {
    return $this->iteration;
  }

  public function read($url)
  {
    if(!$this->getUrl($url)) return false;

    $this->loadDomFromSource();

    $title = $this->getTitle();
    $ContentBox = $this->getTopBox();

    if (!$this->DOM) return false;
    $Target = $this->buildTarget($ContentBox);

    $content =  $this->getContent($Target);

    return [
            'lead_image_url' => $this->getLeadImageUrl($Target,$url),
            'word_count' => mb_strlen(strip_tags($content), Larability::DOM_DEFAULT_CHARSET),
            'title' => $title ? $title : null,
            'content' => $content
        ];

  }

  public function saveLeadImage($pageUrl)
  {

    $this->source = null;
    if(!$this->getUrl($pageUrl)) return false;

    $this->loadDomFromSource();
    $ContentBox = $this->getTopBox();

    if (!$this->DOM) return false;

    $Target = $this->buildTarget($ContentBox);

    if(!$Target) return;

    $imageUrl = $this->getLeadImageUrl($Target, $pageUrl);
    if($imageUrl == null) return;

    $parts = pathinfo($imageUrl);
    $urlParts = parse_url($imageUrl);

    // download file
    $filename = $parts['filename'].'-'.time().'.'.$parts['extension'];
    $dir = base_path().'/public'.Config::get('larability::leadImageStoragePath').'/'.date('Ymd');

    if (!file_exists($dir) && !mkdir($dir, 0777, true)) {
      return;
    }

    $path = Config::get('larability::leadImageStoragePath').'/'.date('Ymd').'/'.$filename;

    $imageUrl = (isset($urlParts['query']) ? $imageUrl.'&cb='.rand(0,9999) : $imageUrl.'?cb='.rand(0,9999));

    $ch = curl_init($imageUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $data = curl_exec($ch);
    curl_close($ch);

    if(file_put_contents(base_path().'/public'.$path, $data))
    {
        $details = getimagesize(base_path().'/public'.$path);

        return [
          'absolutePath' => base_path().'/public'.$path,
          'pathRelativeToPublic' => $path,
          'filename' => $filename,
          'size' => filesize(base_path().'/public'.$path),
          'type' =>$details['mime'],
          'width' => $details[0],
          'height' => $details[1]

        ];
    }

  }

  public function getUrl($url)
  {

    $client = new \GuzzleHttp\Client();
    try {

      $response = $client->get($url,['exceptions' => false]);
      $this->source = (string)$response->getBody();

      return true;
    }
    catch( Exception $e)
    {
            unset($this->source);
            return false;
        }

  }

  /**
   * 预处理 HTML 标签，使其能够准确被 DOM 解析类处理
   *
   * @return String
   */
  private function preparSource($string)
  {
      // 剔除多余的 HTML 编码标记，避免解析出错
      preg_match("/charset=([\w|\-]+);?/", $string, $match);
      if (isset($match[1])) {
          $string = preg_replace("/charset=([\w|\-]+);?/", "", $string, 1);
      }

      // Replace all doubled-up <BR> tags with <P> tags, and remove fonts.
      $string = preg_replace("/<br\/?>[ \r\n\s]*<br\/?>/i", "</p><p>", $string);
      $string = preg_replace("/<\/?font[^>]*>/i", "", $string);

      // @see https://github.com/feelinglucky/php-readability/issues/7
      //   - from http://stackoverflow.com/questions/7130867/remove-script-tag-from-html-content
      $string = preg_replace("#<script(.*?)>(.*?)</script>#is", "", $string);

      return trim($string);
  }

  public function loadDomFromSource($input_char = "utf-8")
  {

        // DOM 解析类只能处理 UTF-8 格式的字符
        $source = mb_convert_encoding($this->source, 'HTML-ENTITIES', $input_char);

        // 预处理 HTML 标签，剔除冗余的标签等
        $source = $this->preparSource($source);

        // 生成 DOM 解析类
        $this->DOM = new \DOMDocument('1.0', $input_char);
        try {
            //libxml_use_internal_errors(true);
            // 会有些错误信息，不过不要紧 :^)
            if (!@$this->DOM->loadHTML('<?xml encoding="'.Larability::DOM_DEFAULT_CHARSET.'">'.$source)) {
                throw new Exception("Parse HTML Error!");
            }

            foreach ($this->DOM->childNodes as $item) {
                if ($item->nodeType == XML_PI_NODE) {
                    $this->DOM->removeChild($item); // remove hack
                }
            }

            // insert proper
            $this->DOM->encoding = Larability::DOM_DEFAULT_CHARSET;


        } catch (Exception $e) {
            // ...
        }
  }

  public function buildTarget($ContentBox)
  {

      // Check if we found a suitable top-box.
      if($ContentBox === null) return;// ['status' => 'fail', 'message' => Larability::MESSAGE_CAN_NOT_GET,'url' => $this->source].

      //  DOMDocument
      $Target = new DOMDocument;
      $Target->appendChild($Target->importNode($ContentBox, true));

      // 删除不需要的标签
      foreach ($this->junkTags as $tag)
      {
          $Target = $this->removeJunkTag($Target, $tag);
      }


      foreach ($this->junkAttrs as $attr)
      {
        $Target = $this->removeJunkAttr($Target, $attr);
      }

      return $Target;

  }

  public function getContent($Target)
  {
      $content = mb_convert_encoding($Target->saveHTML(), Larability::DOM_DEFAULT_CHARSET, "HTML-ENTITIES");
      return $content;
  }

  /**
     * 获取 HTML 页面标题
     *
     * @return String
     */
    public function getTitle()
    {
        $split_point = ' - ';
        $titleNodes = $this->DOM->getElementsByTagName("title");

        if ($titleNodes->length
            && $titleNode = $titleNodes->item(0)) {
            // @see http://stackoverflow.com/questions/717328/how-to-explode-string-right-to-left
            $title  = trim($titleNode->nodeValue);
            $result = array_map('strrev', explode($split_point, strrev($title)));
            return sizeof($result) > 1 ? array_pop($result) : $title;
        }
        return null;
    }

    /**
     * 根据评分获取页面主要内容的盒模型
     *      判定算法来自：http://code.google.com/p/arc90labs-readability/
     *
     * @return DOMNode
     */
    private function getTopBox()
    {
        $this->parentNodes = [];
        // 获得页面所有的章节
        $allParagraphs = $this->DOM->getElementsByTagName("p");


        // Study all the paragraphs and find the chunk that has the best score.
        // A score is determined by things like: Number of <p>'s, commas, special classes, etc.
        $i = 0;
        while($paragraph = $allParagraphs->item($i++))
        {
            $parentNode   = $paragraph->parentNode;
            $contentScore = intval($parentNode->getAttribute(Larability::ATTR_CONTENT_SCORE));
            $className    = $parentNode->getAttribute("class");
            $id           = $parentNode->getAttribute("id");

            // Look for a special classname
            if (preg_match("/(comment|meta|footer|footnote)/i", $className)) {
                $contentScore -= 50;
            } else if(preg_match(
                "/((^|\\s)(post|hentry|entry[-]?(content|text|body)?|article[-]?(content|text|body)?)(\\s|$))/i",
                $className)) {
                $contentScore += 25;
            }

            // Look for a special ID
            if (preg_match("/(comment|meta|footer|footnote)/i", $id)) {
                $contentScore -= 50;
            } else if (preg_match(
                "/^(post|hentry|entry[-]?(content|text|body)?|article[-]?(content|text|body)?)$/i",
                $id)) {
                $contentScore += 25;
            }

            // Add a point for the paragraph found
            // Add points for any commas within this paragraph
            if (strlen($paragraph->nodeValue) > 10) {
                $contentScore += strlen($paragraph->nodeValue);
            }

            // 保存父元素的判定得分
            $parentNode->setAttribute(Larability::ATTR_CONTENT_SCORE, $contentScore);

            // 保存章节的父元素，以便下次快速获取
            array_push($this->parentNodes, $parentNode);
        }

        $topBox = null;

        // Assignment from index for performance.
        //     See http://www.peachpit.com/articles/article.aspx?p=31567&seqNum=5
        for ($i = 0, $len = sizeof($this->parentNodes); $i < $len; $i++)
        {
            $parentNode      = $this->parentNodes[$i];
            $contentScore    = intval($parentNode->getAttribute(Larability::ATTR_CONTENT_SCORE));
            $orgContentScore = intval($topBox ? $topBox->getAttribute(Larability::ATTR_CONTENT_SCORE) : 0);

            if ($contentScore && $contentScore > $orgContentScore)
            {
                $topBox = $parentNode;
            }
        }

        // 此时，$topBox 应为已经判定后的页面内容主元素
        return $topBox;
    }

    /**
     * 删除 DOM 元素中所有的 $TagName 标签
     *
     * @return DOMDocument
     */
    private function removeJunkTag($RootNode, $TagName)
    {

        $Tags = $RootNode->getElementsByTagName($TagName);

        //Note: always index 0, because removing a tag removes it from the results as well.
        while($Tag = $Tags->item(0)){
            $parentNode = $Tag->parentNode;
            $parentNode->removeChild($Tag);
        }

        return $RootNode;
    }

    /**
     * 删除元素中所有不需要的属性
     */
    private function removeJunkAttr($RootNode, $Attr) {
        $Tags = $RootNode->getElementsByTagName("*");

        $i = 0;
        while($Tag = $Tags->item($i++)) {
            $Tag->removeAttribute($Attr);
        }

        return $RootNode;
    }

    /**
     * Get Leading Image Url
     *
     * @return String
     */
    public function getLeadImageUrl($node,$pageUrl)
    {
        $images = $node->getElementsByTagName("img");
        //\Log::info( '** ' . $images->item(0)->getAttribute('src').' **' );

        if ($images->length && $leadImage = $images->item(0))
        {
            // todo. Guzzle available images to find largest image - $images->item(1)->getAttribute('src'));

            return (!strstr('http://',$leadImage->getAttribute("src")) ? $this->getAbsoluteImageUrl($pageUrl,$leadImage->getAttribute("src")) : $leadImage->getAttribute("src"));
        }

        return null;
    }



    function getAbsoluteImageUrl($pageUrl,$imgSrc)
    {
      $imgInfo = parse_url($imgSrc);

      if (! empty($imgInfo['host'])) {
          //img src is already an absolute URL
          return $imgSrc;
      }
      else {
          $urlInfo = parse_url($pageUrl);
          $base = $urlInfo['scheme'].'://'.$urlInfo['host'];
          if (substr($imgSrc,0,1) == '/') {
              //img src is relative from the root URL
              return $base . $imgSrc;
          }
          else {
              //img src is relative from the current directory
                 return
                      $base
                      . substr($urlInfo['path'],0,strrpos($urlInfo['path'],'/'))
                      . '/' . $imgSrc;
          }
      }
    }

    private function xmlToArray($root) {
    $result = array();

    if ($root->hasAttributes()) {
        $attrs = $root->attributes;
        foreach ($attrs as $attr) {
            $result['@attributes'][$attr->name] = $attr->value;
        }
    }

    if ($root->hasChildNodes()) {
        $children = $root->childNodes;
        if ($children->length == 1) {
            $child = $children->item(0);
            if ($child->nodeType == XML_TEXT_NODE) {
                $result['_value'] = $child->nodeValue;
                return count($result) == 1
                    ? $result['_value']
                    : $result;
            }
        }
        $groups = array();
        foreach ($children as $child) {
            if (!isset($result[$child->nodeName])) {
                $result[$child->nodeName] = $this->xmlToArray($child);
            } else {
                if (!isset($groups[$child->nodeName])) {
                    $result[$child->nodeName] = array($result[$child->nodeName]);
                    $groups[$child->nodeName] = 1;
                }
                $result[$child->nodeName][] = $this->xmlToArray($child);
            }
        }
    }

    return $result;
}


}

?>
