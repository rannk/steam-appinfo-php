<?php
namespace Rannk\SteamAppinfoPhp;

use GuzzleHttp\Client as GuzzleClient;

/**
 * Class Games
 * @property \Redis $redis
 * @package Rannk\SteamAppinfoPhp
 */
class Games
{
    const CACHE_KEY = "_STEAM_APPINFO_PHP_";
    private $client;
    private $redis;
    private $wait_time = 0;
    private $agent;

    public function __construct()
    {
        $this->client = new GuzzleClient();
    }

    public function hasCache($ip="127.0.0.1", $port = "6379", $password="")
    {
        $redis = new \Redis();
        try{
            $redis->connect($ip, $port, 1);
            if(!empty($password)){
                $redis->auth($password);
            }

            $this->redis = $redis;
        }catch (\Exception $e){}
    }

    /**
     * 设置获取dlc之间等待的时间
     * 主要用于防止频繁抓取被抓
     * @param $second
     */
    public function setWait($second)
    {
        $this->wait_time = $second;
    }

    public function setAgent($url, $appid, $secret)
    {
        $config['url'] = $url;
        $config['appid'] = $appid;
        $config['secret'] = $secret;

        $this->agent = new QyAgentServices($config);
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
        if(!empty($this->agent)){
            $response = $this->agent->get($url);
        }else{
            $response = $this->client->get($url);
        }

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
    public function gameDetail($appid, $lang='cn', $cc="cn", $type='game')
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
        $content = "";
        if(!empty($this->redis)){
            $content = $this->redis->get(self::CACHE_KEY . $appid);
        }

        if(empty($content)){
            $url = 'https://store.steampowered.com/api/appdetails';
            if(!empty($this->agent)){
                $response = $this->agent->get($url, $params);
            }else{
                $response = $this->client->get($url, $params);
            }

            if($response->getStatusCode() == 200){
                $content = json_decode($response->getBody()->getContents(), true);

                // 如果缓存开启
                if(!empty($this->redis) && !empty($content) && !empty($content[$appid])){
                    $this->redis->set(self::CACHE_KEY . $appid, json_encode($content), 7200);
                }
            }
        }else{
            $content = json_decode($content, true);
        }

        if(!empty($content) && !empty($content[$appid]) && !empty($content[$appid]['data']['dlc']) && $type=='game'){
            // 批量查DLC的内容
            $catch_dlc_count = 0;
            foreach($content[$appid]['data']['dlc'] as $dlc){
                $dlc_cont = $this->gameDetail($dlc, $lang, $cc, 'dlc');
                if(!empty($dlc_cont)){
                    $content[$dlc] = $dlc_cont[$dlc];
                }

                if($catch_dlc_count >= 100){
                    break; // dlc只抓100个.
                }

                $catch_dlc_count++;
                sleep($this->wait_time);
            }
        }

        return $content;
    }

    public function gameDetailFromSite($appid)
    {
        $content = "";
        if(!empty($this->redis)){
            $content = $this->redis->get(self::CACHE_KEY . "_SITE_" . $appid);
        }

        if(empty($content)){
            $url = 'https://store.steampowered.com/app/';
            if(!empty($this->agent)){
                $response = $this->agent->get($url.$appid);
            }else{
                $response = $this->client->get($url.$appid);
            }

            if($response->getStatusCode() == 200){
                $content = $response->getBody()->getContents();

                // 如果缓存开启
                if(!empty($this->redis) && !empty($content)){
                    $this->redis->set(self::CACHE_KEY . "_SITE_" . $appid, $content, 7200);
                }
            }
        }

        $anay = new anayAppGame($content);

        $data['reviewSummary'] = $anay->findReviewSummary();
        $data['reviewRecent'] = $anay->findReviewRecent();

        return $data;
    }

    /**
     * 根据filter获取详情
     * @param $appids
     * @param $filter
     * @return bool|mixed|string
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function gameDetailHasFilter($appids, $filter, $lang="cn", $cc = "cn")
    {
        if(!is_array($appids)){
            $appids = [$appids];
        }

        $lang_arr = ['cn' => 'zh-cn;zh'];
        $params = [];
        $query = ['appids' => implode(",", $appids), "filters" => $filter];
        $cache_id = md5($query['appids']);
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
        $content = "";
        if(!empty($this->redis)){
            $content = $this->redis->get(self::CACHE_KEY . $cache_id);
        }

        if(empty($content)){
            $url = 'https://store.steampowered.com/api/appdetails';
            if(!empty($this->agent)){
                $response = $this->agent->get($url, $params);
            }else{
                $response = $this->client->get($url, $params);
            }

            if($response->getStatusCode() == 200){
                $content = json_decode($response->getBody()->getContents(), true);

                // 如果缓存开启
                if(!empty($this->redis) && !empty($content)){
                    $this->redis->set(self::CACHE_KEY . $cache_id, json_encode($content), 7200);
                }
            }
        }else{
            $content = json_decode($content, true);
        }

        return $content;
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