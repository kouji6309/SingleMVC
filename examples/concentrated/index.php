<?php
//require 'SingleMVC.php';
require '../../SingleMVC.php';


// Config ======================================================================
SingleMVC::$config->routes = [
    'default' => 'welcome/index',
];

SingleMVC::$config->lang = 'en-US';


// Language ======================================================================
SingleMVC::$lang['en-US'] = [
    'say' => 'Say',
    'msg' => 'HI~',
];

SingleMVC::$lang['zh-TW'] = [
    'say' => '說',
    'msg' => '嗨~',
];


// Helper ======================================================================
function get_times() {
    return '× 10!';
}


// Controller ======================================================================
class welcome extends Controller {
    public function index() {
        $m = new TestModel();
        output('home', ['say' => lang('say'), 'msg' => $m->getMsg(), 'times' => get_times()]);
    }

    public function index_post() {
        output('json', ['msg' => lang('msg')]);
    }
}


// Model ======================================================================
class TestModel extends Model {
    public function getMsg() {
        $r = self::request(HOST.$_SERVER['REQUEST_URI'], 'post');
        $r = json_decode($r, true);
        return $r['msg'] ?? 'error';
    }
}


// View ======================================================================
SingleMVC::$view['home'] = '
<div style="color:green;text-align:center;font-size:2em;">
    <a style="text-decoration:none;" href="<?=VROOT."/".LANG;?>/welcome/index">
        <?=$say;?> <?=$msg;?> <?=$times;?>
    </a>
</div>';