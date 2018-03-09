<?php
defined('ROOT') or die('Access denied');

class welcome extends Controller {
    public function index() {
        $m = new TestModel();
        output('home', ['say' => lang('say'), 'msg' => $m->getMsg(), 'times' => get_times()]);
    }

    public function index_post() {
        output('json', ['msg' => lang('msg')]);
    }
}