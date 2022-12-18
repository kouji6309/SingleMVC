<?php
defined('ROOT') or die('Access denied');

class TestModel extends Model {
    public function getMsg() {
        $r = self::request(HOST.$_SERVER['REQUEST_URI'], 'post');
        $r = json_decode($r, true);
        return $r['msg'] ?? 'error';
    }
}