<?php


/*
 * DomDocument is much havier then regexp case but
 * use the following http://stackoverflow.com/questions/1732348/regex-match-open-tags-except-xhtml-self-contained-tags/1732454#1732454
 *
 */

class Parser
{
    protected $baseUrl;
    protected $startUrl;
    protected $level = 0;
    protected $limit = 1;
    protected $nolimit = false;

    protected $cssList = array();
    protected $urlList = array();
    protected $pageCss = array();

    protected $cssCompare;
    protected $map;

    public function __construct($page_url)
    {
        $url = parse_url($page_url);
        $this->baseUrl = sprintf('%s://%s/', $url['scheme'], $url['host']);
        $this->startUrl = $page_url;
        $this->urlList[] = $page_url;
        $this->cssCompare = new cssSemantic();
        $this->map = new DomMap();
    }

    public function setLevel($level)
    {
        $this->level = $level;
    }

    public function setLimit($limit)
    {
        if(!($this->limit = $limit))
            $this->nolimit = true;
    }

    public function run()
    {
        $this->pageProcess($this->startUrl);
        $files = array();
        foreach($this->pageCss as $page=>$css){
            foreach($css as $file=>$unused){
                $files[$file][] = $unused;
            }
        }
        $result = array();
        foreach($files as $file=>$css)
            if(count($css)>1)
                $result[$file] = call_user_func_array('array_intersect', $css);
            else
                $result[$file] = array_shift($css);
        return $result;
    }

    protected function pageProcess($url, $deep = 0)
    {
        var_dump($url);
        $dom = new DOMDocument();
        $file = FileLoad::load($url);
        if(!$file) return;
        @$dom->loadHTML($file);
        $dom->normalizeDocument();
        $this->collectStyles($dom, $url);
        $this->cssCompare->unused = array();
        $this->map->recursionParent($dom->getElementsByTagName('body')->item(0), array('html'));
        $documentCSS = new Search($this->map->list);
        foreach($this->cssCompare->list as $selector) {
            $notused = false;
            foreach(explode(',', $selector['selector']) as $search) {
                if(!$documentCSS->searchDom($search)) {
                    $notused = true;
                    break;
                }
            }
            if($notused)
                $this->cssCompare->unused[$selector['file']][] = $selector['selector'];
        }
        if($this->level>$deep) {
            $urls = $this->collectLinks($dom);
            foreach($urls as $url) {
                if($this->nolimit || (--$this->limit)>0)
                    $this->pageProcess($url, $deep+1);
            }
        }
        $this->pageCss[] = $this->cssCompare->unused;
    }

    protected function collectStyles($dom, $pageUrl = 'onpage')
    {
        foreach($dom->getElementsByTagName('link') as $link) {
            $style = false;
            $url = '';
            foreach($link->attributes as $attr) {
                if($attr->nodeName == 'rel' && $attr->nodeValue == 'stylesheet')
                    $style = true;
                if($attr->nodeName == 'href')
                    $url = $attr->nodeValue;
            }
            if(strpos($url, $this->baseUrl)!==false)
                $css_file = $url;
            else
                $css_file = sprintf('%s%s', $this->baseUrl, trim($url,'/'));
            if(in_array($css_file, $this->cssList)) continue;
            $css = FileLoad::load($css_file);
            $css = preg_replace('|/\*.*\*/|Us','', $css);
            $this->cssCompare->parse($css, $css_file);
            $this->cssList[] = $css_file;
        }

        foreach($dom->getElementsByTagName('style') as $style) {
            $this->cssCompare->parse($style->textContent, $pageUrl);
        }
        $this->cssCompare->compareList();
    }

    protected function collectLinks($dom)
    {
        $list = array();
        foreach($dom->getElementsByTagName('a') as $link) {
            $url = '';
            foreach($link->attributes as $attr) {
                if($attr->nodeName == 'href')
                    $url = $attr->nodeValue;
            }
            if(strpos($url, $this->baseUrl)!==false)
                $list[] = $url;
            elseif(strpos($url, 'http')===0)
                continue;
            else
                $list[] = sprintf('%s%s', $this->baseUrl, trim($url,'/'));
        }
        $list = array_diff($list, $this->urlList);
        $this->urlList = array_merge($this->urlList, $list);
        return $list;
    }
}











class cssSemantic
{
    public $list = array();
    public $unused = array();
    public function parse($text, $file='')
    {
        $css = preg_replace('|/\*\*.*\*/|Uis','',$text);
        preg_match_all('/(.+)\{(.+)\}/Uis', $css, $m);
        $class = array_map('trim', $m[1]);
        $styles = array_map('trim', $m[2]);

        foreach($styles as $k => $prop) {
            if(!empty($prop)) {
                preg_match_all('/\b(.*)\s*:(.*);/Uis', $prop, $style);
                $this->list[] = array(
                    'selector'=>$this->extraNormalize($class[$k]),
                    'style'=>$style[1],
                    'file'=>$file
                );
            } else {
                $this->unused[$file][] = $class[$k];
            }
        }
    }

    public function compareList()
    {
        $list = $this->list;
        $size = sizeof($list);
        for($i=0;$i<$size;$i++){
            for($j=$i+1;$j<$size;$j++){
                if($list[$i]['selector']==$list[$j]['selector'])
                    if(!$this->compare($list[$i]['style'], $list[$j]['style'])) {
                        $this->unused[$list[$i]['file']][] = $list[$i]['selector'];
                    }
            }
        }
    }

    public function compare($from, $to)
    {
        if(sizeof($from)!=sizeof($to)) return true;
        $not = true;
        foreach($from as $r1)
            foreach($to as $r2)
                if(strlen($r1)>=strlen($r2) && strpos($r1, $r2)!==false) {
                    return false;
                }
        return $not;
    }

    /**
     * sorts but not work with *+~>
     * @param $selector
     * @return string
     */
    protected function normalize($selector)
    {
        $t = explode(' ', $selector);
        foreach($t as $row) {
            $attrk = false;
            $id = '';
            $classes = $attrs = array();
            preg_match_all('|(\W?)(\w+)|', $row, $r);
            foreach($r[0] as $k=>$rl) {
                $val = $r[2][$k];
                switch($r[1][$k]){
                    case '':
                        $tag = $val;
                        break;
                    case '#':
                        $id = '#'.$val;
                        break;
                    case '.':
                        $classes[] = $val;
                        break;
                    case '[':
                        $attrk = $val;
                        break;
                    default:
                        if($attrk)
                            $attrs[$attrk] = $val;
                        break;
                }
            }
            if(is_array($classes))
                sort($classes);
            if(is_array($attrs)) {
                ksort($attrs);
                array_walk($attrs, function(&$val, $key){
                    $val = "[$key=$val]";
                });
            }
            $tags[] = sprintf('%s%s%s%s', $tag, $id, $classes?'.'.implode('.',$classes):'', $attrs?implode('', $attrs):'');
        }
        return implode(' ', $tags);
    }

    /**
     * Very important function that makes selectors cleaner and normalized by sorting attributes and removing quotes
     * @param $selector
     * @return mixed
     */
    protected function extraNormalize($selector)
    {
        $normal = function($row){
            $attrk = false;
            $id = '';
            $tag = '';
            $classes = $attrs = array();
            $rw = str_replace(array('"','\''), array('',''),$row[1]);
            if(!preg_match_all('|(\W*)(\w+)|', $rw, $r)) return ' '.$row[1].' ';
            foreach($r[0] as $k=>$rl) {
                $val = $r[2][$k];
                switch($r[1][$k]){
                    case '':
                        $tag = $val;
                        break;
                    case '#':
                        $id = '#'.$val;
                        break;
                    case '.':
                        $classes[] = $val;
                        break;
                    case '[':
                        $attrk = $val;
                        break;
                    default:
                        if($attrk)
                            $attrs[$attrk] = $rl;
                        break;
                }
            }
            if(is_array($classes))
                sort($classes);
            if(is_array($attrs)) {
                ksort($attrs);
                array_walk($attrs, function(&$val, $key){
                    $val = "[$key=$val]";
                });
            }
            return sprintf('%s%s%s%s ', $tag, $id, $classes?'.'.implode('.',$classes):'', $attrs?implode('', $attrs):'');
        };
        return preg_replace_callback('|(\S+)([ \+~>]?)|m', $normal, $selector);
    }
}


class RegExMap
{
    protected $validation = 0;
    public $list = array();
    public function parse($file)
    {
        $lvl = 0;
        $selectors = array();
        preg_match_all('|<([^>]+)>|Uis',$file,$m);
        foreach($m[1] as $tag) {
            if(strpos($tag, '/')===0)
            {
                array_pop($selectors);
                $lvl--;
            } elseif(strpos($tag, '/')==(strlen($tag)-1)) {

            } else {
                if($t = $this->buildSelector($tag)) {
                    array_push($selectors, $t);
                    $lvl++;
                    $this->list[] = array(implode(' ', $selectors), $lvl);
                }
            }
        }
    }
    protected function buildSelector($tag)
    {
        preg_match('|^\w+|',$tag, $result);
        if(empty($result)) return;
        preg_match_all('|(\w+)=[\'"](.*)[\'"]|U', $tag, $attributes);
        $attributes = array_combine($attributes[1],$attributes[2]);
        $attrs = array('','');
        $tmp = array();
        if($attributes)
            foreach($attributes as $key=>$item) {
                switch($key) {
                    case 'id':
                        $attrs[0] = '#'.$item;
                        break;
                    case 'class':
                        $cls = explode(' ', $item);
                        sort($cls);
                        $attrs[1] = '.'.implode('.', array_filter($cls, function($item) { return !empty($item); }));
                        break;
                    default:
                        if($this->validation===1)
                            $attrs[] = sprintf('[%s=%s]', $key, $item);
                        break;
                }
                if($this->validation===2)
                    $tmp[$key] = sprintf('[%s=%s]', $key, $item);
            }

        if($this->validation===2) {
            ksort($tmp);
        }
        return sprintf('%s%s%s', $result[0], implode('', $attrs),implode('', $tmp));
    }
}

class DomMap
{
    public $list = array();

    /**
     * specifies level of normalization 0-2
     * 0 - is totally valid no [attr] in CSS
     * 1 - valid no [class=name] in CSS
     * 2 - some crazy code [id=name][class=name] in css (better kill web master)
     * @var
     */
    protected $validation = 0;
    public function __construct($validation = null)
    {
        if($validation !== null)
            $this->validation = $validation;
    }

    /**
     * @param DOMDocument $el
     */
    function recursionTree($el)
    {
        if($el->nodeName == '#text') return array();
        /** @var DomAttr $item */
        $attrs = array('','');
        foreach($el->attributes as $item) {
            switch($item->nodeName) {
                case 'id':
                    $attrs[0] = '#'.$item->nodeValue;
                    break;
                case 'class':
                    $cls = explode(' ', $item->nodeValue);
                    sort($cls);
                    $attrs[1] = '.'.implode('.', array_filter($cls, function($item) { return !empty($item); }));
                    break;
                default:
                    $attrs[] = sprintf('[%s=%s]', $item->nodeName, $item->nodeValue);
                    break;
            }
        }
        $tag = sprintf('%s%s', $el->nodeName, implode('', $attrs));
        $childs = array();
        foreach($el->childNodes as $item) {
            if($t = $this->recursionTree($item)) $childs[] = $t;
        }
        return array($tag=>$childs);
    }
    /**
     * @param DOMDocument $el
     */
    function recursionParent($el, $parent=array(), $lvl=1)
    {
        if($el->nodeName == '#text') return;
        $tag = $this->buildSelector($el);

        $this->list[] = array(
            implode(' ', array_merge($parent, array($tag))),$lvl
        );

        if($el->childNodes)
        foreach($el->childNodes as $item) {
            $this->recursionParent($item, array_merge($parent, array($tag)),$lvl+1);
        }
        return;
    }

    protected function buildSelector($el)
    {
        /** @var DomAttr $item */
        $attrs = array('','');
        $tmp = array();
        if($el->attributes)
            foreach($el->attributes as $item) {
                switch($item->nodeName) {
                    case 'id':
                        $attrs[0] = '#'.$item->nodeValue;
                        break;
                    case 'class':
                        $cls = explode(' ', $item->nodeValue);
                        sort($cls);
                        $attrs[1] = '.'.implode('.', array_filter($cls, function($item) { return !empty($item); }));
                        break;
                    default:
                        if($this->validation===1)
                            $attrs[] = sprintf('[%s=%s]', $item->nodeName, $item->nodeValue);
                        break;
                }
                if($this->validation===2)
                    $tmp[$item->nodeName] = sprintf('[%s=%s]', $item->nodeName, $item->nodeValue);
            }

        if($this->validation===2) {
            ksort($tmp);
        }
        return sprintf('%s%s%s', $el->nodeName, implode('', $attrs),implode('', $tmp));
    }

    /**
     * @param DOMDocument $el
     */
    public function parents($el)
    {
        $res = array();
        do{
            $el = $el->parentNode;
            array_unshift($res, $this->buildSelector($el));
        } while($el);
        return $res;
    }
}

/**
 * Search over given plain tree [name, lvl]
 * Class Search
 */
class Search
{

    protected $list;
    public function __construct($list)
    {
        $this->list = $list;
    }
    /**
     * Allows to search over CSS 1-3 rules excepts +,~ symbols
     * @param $search
     * @return bool
     */
    public function selectorSearch($search)
    {
        $search = $this->formatSearch($search);
        foreach($this->list as $selector) {
            if(preg_match('|'.$search.'|Ui', $selector[0])) return true;
        }
    }

    /**
     * Allows to search by more specifics of CSS 1-3 rules
     * @param $search
     * @return bool
     */
    public function searchDom($search)
    {
        $lvl = null;
        $next = false;
        $result = 0;
        $limit = 1;
        $original = $search;
        if(preg_match_all('/\s*([\+~])\s*/', $search, $spliter)) {
            $limit = count($spliter[1])+1;
            $parts = preg_split('|[\+~]|', $search);
            $original = array_shift($parts);
        }
        $search = $this->formatSearch($original);
        foreach($this->list as $selector) {
            if($lvl && $selector[1]!=$lvl) {
                continue;
            } elseif ($selector[1]==$lvl) {
                $lvl = null;
            }
            if($result = preg_match('|'.$search.'|Ui', $selector[0], $match) && !(--$limit))
                return true;
            elseif (!$result && $next) {
                return false;
            } elseif ($match) {
                switch(array_shift($spliter)) {
                    case '+':
                        $next = true;
                }
                $lvl = $selector[1];
                $original = preg_replace('|^(.+\s)\S*$|is', '$1 '.array_shift($parts), $selector[0]);
                $search = $this->formatSearch($original);
            }
        }
    }
    protected function formatSearch($search)
    {
        $search = trim($search);
        $hard = strpos($search, '['); // its useless but no make worse
        $search = preg_replace('|\s+|',' ',$search); // remove multiple spaces for string normalize
        $search = preg_replace('|:\w|Ui', '', $search); // remove subclasses @todo one way to improve this work
        $search = preg_replace('|\s*>\s*|','\s+',$search); // search in child list [div > p]
        if($hard!==false)
            $search = preg_replace('|\[(\w+)\*=(\w+)\]|','[$1~=$2]',$search); // convert to skip next step operation
        $search = str_replace(array('*', '.', ' '), array('\S+', '\S*\.', '.*\s.*'), $search); //basic manipulations
        if($hard!==false) {
            /*
             * attribute manipulations
             */
            $search = preg_replace('|\[(\w+)=(\w+)\]|','\S*\[$1=$2\]',$search); // [type=text]
            $search = preg_replace('|\[(\w+)\^=(\w+)\]|','\S*\[$1=$2\S*\]',$search); // [type^=te]
            $search = preg_replace('|\[(\w+)~=(\w+)\]|','\S*\[$1=\S*$2\S*\]',$search); // [type*=ex]
            $search = preg_replace('|\[(\w+)\$=(\w+)\]|','\S*\[$1=\S*$2\]',$search); // [type$=xt]
        }
        return $search;
    }
}

class FileLoad
{
    public static function load($file)
    {
        return @file_get_contents($file);
    }
}