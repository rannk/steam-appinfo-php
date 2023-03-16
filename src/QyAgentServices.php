<?php
/**
 * 奇游代理服务
 */
namespace Rannk\SteamAppinfoPhp;

class QyAgentServices
{
    private $url, $secret, $appid;
    const ITERATIONS = 1000;
    const VALID_TIME_DURATION = 300; //接口有效的时间，单位秒

    public function __construct($config)
    {
        $this->url = $config['url'];
        $this->secret = $config['secret'];
        $this->appid = $config['appid'];
    }

    public function get($url, $params = [])
    {
        $client = new \GuzzleHttp\Client();

        if(!empty($params['query'])){
            $query_str = "";
            foreach($params['query'] as $k => $v){
                $query_str .= "&" . $k . "=" . $v;
            }

            if(stripos($url, "?")){
                $url .= $query_str;
            }else{
                $url .= "?" . $query_str;
            }
        }
        $query['url'] = $url;

        $headers = $this->getHeaders();
        if(!empty($params['headers'])){
            foreach ($params['headers'] as $k => $v){
                $headers[$k] = $v;
            }
        }

        try{
            $response = $client->get( $this->url, [
                'headers' => $headers,
                'query' => $query
            ]);

            return $response;
        }catch (Exception $e){
            throw new \Exception($e->getMessage());
        }
    }

    public function post($uri, $params = [])
    {
        $client = new \GuzzleHttp\Client();
        try{
            $response = $client->post( $this->url . $uri, [
                'headers' => $this->getHeaders(),
                'form_params' => $params
            ]);

            return $response;
        }catch (\Exception $e){
            throw new \Exception($e->getMessage());
        }

    }

    protected function getHeaders()
    {
        $expired_time = time()+self::VALID_TIME_DURATION;
        $headers = [
            'qyyx-appid' => $this->appid,
            'qyyx-expired-time' => $expired_time,
            'qyyx-sign'=> $this->sign($expired_time)
        ];

        return $headers;
    }

    protected function sign($expired_time)
    {

        return hash_pbkdf2("sha256", $this->appid.$expired_time, $this->secret ,self::ITERATIONS);
    }
}