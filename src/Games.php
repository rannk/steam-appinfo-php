<?php
namespace Rannk\SteamAppinfoPhp;

use GuzzleHttp\Client as GuzzleClient;

class Games
{
    private $client;

    public function __construct()
    {
        $this->client = new GuzzleClient();
    }

    /**
     * 获取appids
     * @param int $start
     * @param int $limit
     * @param string $filter 过滤条件，比如热销，传topsellers
     * @return array
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function getAppids($start = 0, $limit = 50, $filter = '')
    {
        $url = 'https://store.steampowered.com/search/results/?query&start='.$start.'&count='.$limit.'&sort_by=_ASC&filter='.$filter.'&infinite=1&l=zh';
        $response = $this->client->get($url);
        if($response->getStatusCode() == 200){
            return $this->anayAppContent($response->getBody()->getContents());
        }
    }

    /**
     * 获取游戏详情，如果游戏包含dlc，同时获取dlc信息
     * @param $appid
     * @param string $lang 语言
     * @param string $cc  货币
     * @return mixed
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function gameDetail($appid, $lang='cn', $cc="cn")
    {
        $lang_arr = ['cn' => 'zh-cn;zh'];
        $params = [];
        $query = ['appids' => $appid];
        if(!empty($cc)){
            $query['cc'] = $cc;
        }

        if(!empty($lang)){
            $query['l'] = $lang;
            if(array_key_exists($lang, $lang_arr)){
                $params['headers'] = ['Accept-Language'=> $lang_arr[$lang]];
            }
        }

        $params['query'] = $query;

        $url = 'http://store.steampowered.com/api/appdetails';
        $response = $this->client->get($url, $params);
        if($response->getStatusCode() == 200){
            $content = json_decode($response->getBody()->getContents(), true);
            if(!empty($content) && !empty($content[$appid]) && !empty($content[$appid]['data']['dlc'])){
                // 批量查DLC的内容
                foreach($content[$appid]['data']['dlc'] as $dlc){
                    $dlc_cont = $this->gameDetail($dlc);
                    if(!empty($dlc_cont)){
                        $content[$dlc] = $dlc_cont[$dlc];
                    }
                }
            }
            return $content;
        }
    }

    /**
     * 分析appid接口返回的信息
     * 获取到apppid， 好评率，评论用户人数
     * @param $content
     * @return array
     */
    public function anayAppContent($content)
    {
        $arr = json_decode($content, true);
        $data = [];
        if(!empty($arr['results_html'])){
            libxml_use_internal_errors(true);
            $dom = new \DOMDocument();
            $dom->loadHTML($arr['results_html']);
            $domxpath = new \DOMXPath($dom);

            $q = $domxpath->query('//a');
            if(!empty($q)){
                $i=0;
                foreach($q as $node){
                    foreach($node->attributes as $attr){
                        if($attr->name == 'data-ds-appid'){
                            $appid = $attr->value;
                            $data[$appid] = ["appid"=>$appid];
                            break;
                        }
                    }

                    $value = $this->findAttr($node->childNodes, "data-tooltip-html");

                    preg_match('/(\d{1,3})%/', $value, $matches);
                    if(count($matches)>1){
                        $data[$appid]['rate'] = $matches[1];
                    }
                    preg_match('/([\d,]{1,}) /', $value, $matches);
                    if(count($matches)>1){
                        $data[$appid]['users'] = $matches[1];
                    }
                }
            }
        }

        return $data;
    }

    public function findAttr($nodes, $name)
    {
        if(!empty($nodes)){
            foreach($nodes as $n){
                if(empty($n->tagName)){
                    continue;
                }

                foreach($n->attributes as $attr){
                    if($attr->name == $name){
                        return $attr->value;
                        break;
                    }
                }

                if(!empty($n->childNodes)){
                    $value = $this->findAttr($n->childNodes, $name);
                    if(!empty($value)){
                        return $value;
                    }
                }
            }
        }
    }
}