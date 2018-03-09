<?php
defined('ROOT') or die('Access denied');

class TestModel extends Model {
    public function getMsg() {
        $r = self::request('http://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'], 'post');
        $r = json_decode($r, true);
        return $r['msg'] ?? 'error';
    }
}