<?php
/**
 * 根据steam app html页面分析数据
 */
namespace Rannk\SteamAppinfoPhp;


class anayAppGame
{
    private $content;
    public function __construct($content)
    {
        $this->content = $content;
    }

    public function findReviewSummary()
    {
        $content = $this->content;
        $pos = stripos($content, "review_histogram_rollup_section");
        $s = stripos($content, 'data-tooltip-html="', $pos);
        $e = stripos($content, '"', $s+20);
        $str = substr($content, $s + 19, $e - $s - 19);
        preg_match('/(\d{1,3})%/', $str, $matches);
        $data = [];
        if(count($matches)>1){
            $data['rate'] = $matches[1];
        }
        preg_match('/([\d,]{1,}) /', $str, $matches);
        if(count($matches)>1){
            $data['users'] = $matches[1];
        }

        return $data;
    }

    public function findReviewRecent()
    {
        $content = $this->content;
        $pos = stripos($content, "review_histogram_recent_section");
        $s = stripos($content, 'data-tooltip-html="', $pos);
        $e = stripos($content, '"', $s+20);
        $str = substr($content, $s + 19, $e - $s - 19);

        preg_match('/(\d{1,3})%/', $str, $matches);
        $data = [];
        if(count($matches)>1){
            $data['rate'] = $matches[1];
        }
        preg_match('/([\d,]{1,}) /', $str, $matches);
        if(count($matches)>1){
            $data['users'] = $matches[1];
        }

        return $data;
    }
}