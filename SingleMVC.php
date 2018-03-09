<?php
#region SingleMVC
if (version_compare(PHP_VERSION, '7.0', '<')) {
    header('Content-Type: text/plain');
    die('Requires PHP 7 or higher');
}

ob_start();

if (!defined('ROOT')) define('ROOT', dirname($_SERVER['SCRIPT_FILENAME']));

if (defined('DEBUG')) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
}

define('DS', DIRECTORY_SEPARATOR);
define('SOURCE_DIR', rtrim(ROOT, "/\\").DS.'source');
define('VERSION', '1.3.7');
header('Framework: SingleMVC '.VERSION);

class SingleMVC {
    /**
     * 取得或設定程式組態
     * @var Config
     */
    public static $config = null;

    /**
     * 取得或設定語系資料
     * @var array
     */
    public static $lang = [];

    /**
     * 取得或設定 View 內容(單頁用)
     * @var array
     */
    public static $view = [];

    private static $hm = 'get';
    private static $ud = [];
    private static $cd = [];
    private static $fd = [];
    private static $is_run = false;
    private static $ld = '';

    /**
     * 產生 SingleMVC 實例並執行
     */
    public function __construct() {
        SingleMVC::run();
    }

    private static function run() {
        if (self::$is_run) return;
        self::$is_run = true;
        session_start();
        // 載入第三方套件
        foreach (['3rd', 'helper'] as $f) {
            if (is_dir($h = SOURCE_DIR.DS.$f)) {
                $hs = array_diff(scandir($h), ['.', '..']);
                foreach ($hs as $i) self::require($h.DS.$i);
            }
        }
        // 載入設定
        self::require(SOURCE_DIR.DS.'config.php');
        // 處理路由
        $_S = $_SERVER;
        $u = parse_url('http://host'.$_S['REQUEST_URI']);
        $q = $u['query'] ?? '';
        $u = urldecode($u['path'] ?? '');
        if (!empty($_S['SCRIPT_NAME']) && ($sn = $_S['SCRIPT_NAME'])) {
            define('VROOT', $sd = dirname($sn));
            if (mb_strpos($u, $sn) === 0) {
                $u = mb_substr($u, mb_strlen($sn));
            } elseif (mb_strpos($u, $sd) === 0) {
                $u = mb_substr($u, mb_strlen($sd));
            }
        } else {
            define('VROOT', '');
        }
        if (trim($u, '/') === '') {
            if (count($t = preg_split('/[?&]/', $q, 2)) == 2) {
				list($u, $q) = $t;
			}
        }
        $u = trim($u, '/');
        parse_str($_S['QUERY_STRING'] = $q, $_GET);
        if (!empty(self::$config->routes) && ($r = self::$config->routes) && is_array($r)) {
            foreach ($r as $k => $v) {
                if (preg_match($k = '#^'.$k.'$#', $u)) {
                    $u = preg_replace($k, $v, $u); break;
                }
            }
        }
        // 處理輸入
        self::$ud = $_GET;
        $raw = file_get_contents('php://input');
        $ct = strtolower(explode(';', $_S['CONTENT_TYPE'] ?? 'text/plain')[0]);
        self::$hm = $hm = strtolower($_S['REQUEST_METHOD'] ?? 'get');
        if (($ip = $hm == 'post') && $ct == 'application/x-www-form-urlencoded') {
            self::$cd = $_POST;
        } elseif (!($ig = $hm == 'get') && $ct == 'application/x-www-form-urlencoded') {
            parse_str($raw, $tp1);
            self::$cd = $tp1;
        } elseif ($ip && $ct == 'multipart/form-data') {
            self::$cd = $_POST;
            self::$fd = $_FILES;
        } elseif (!$ip && $ct == 'multipart/form-data') {
            $tp1 = ['c' => [], 'f' => []];
            preg_match('/boundary=(.*)$/', $_S['CONTENT_TYPE'], $b);
            if (count($b) > 0) {
                $b = preg_split('/-+'.$b[1].'/', $raw);
                array_pop($b);
                foreach($b as $i) {
                    if (empty($i = ltrim($i))) continue;
                    $bk = $d = $ks = [];
                    if (preg_match('/^Content-Disposition: .*; name=\"([^\"]*)\"; filename=\"([^\"]*)\"[\n|\r]+([^\n\r].*)?\r$/s', $i, $m)) {
                        preg_match('/Content-Type: (.*)?/', $m[3], $t);
                        $p = sys_get_temp_dir().DS.'php'.substr(sha1(random_bytes(10)), 0, 6);
                        $e = file_put_contents($p, preg_replace('/Content-Type: (.*)[^\n\r]/', '', $m[3]));
                        parse_str(urlencode($m[1]).'=temp', $tp2);
                        while (is_array($tp2 = $tp2[$ks[] = key($tp2)]));
                        $ks = array_reverse($ks);
                        $id = array_pop($ks);
                        $tp2 = ['name' => $m[2], 'type' => trim($t[1]), 'tmp_name' => $p, 'error' => ($e === FALSE) ? $e : 0, 'size'=> filesize($p)];
                        foreach ($tp2 as $l => $v) { $d[$id][$l] = $v; foreach ($ks as $k) $d[$id][$l] = [$k => $d[$id][$l]]; }
                        $bk = ['f' => $d];
                    } elseif (preg_match('/^Content-Disposition: .*; name=\"([^\"]*)\"[\n|\r]+([^\n\r].*)?\r$/s', $i, $m)) {
                        parse_str(urlencode($m[1]).'='.urlencode($m[2]), $tp2);
                        $bk = ['c' => $tp2];
                    }
                    $tp1 = array_merge_recursive($tp1, $bk);
                }
            }
            self::$cd = $tp1['c'];
            self::$fd = $tp1['f'];
        } elseif (!$ig && $ct == 'application/json') {
            self::$cd = json_decode($raw, true);
        } elseif (!$ig) {
            self::$cd = $raw;
        }
        // 執行
        if ($crp = self::cmp(explode('/', trim($u, '/')), 'default')) {
            call_user_func_array([new $crp['c'](), $crp['m']], $crp['p']);
        } else {
            header_404();
        }
        ob_flush();
    }

    /**
     * 檢查 Class, Method 和 Parameter
     * @param array $u 已分割的 URL
     * @param string $d 預設路由
     * @return array|boolean
     */
    private static function cmp($u, $d = null) {
        $p = []; $r = self::$config->routes;
        if (count($u) > 0 && !empty($u[0]) && lang_load($u[0])) array_shift($u);
        if (empty($u) || ($c = count($u)) == 1 && empty($u[0])) {
            if (!empty($d) && isset($r[$d]) && is_string($dr = $r[$d])) $u = explode('/', trim($dr, '/'));
        }
        if (count($u) > 0 && !empty($u[0]) && lang_load($u[0])) array_shift($u);
        if (($c = count($u)) == 1 && !empty($u[0])) {
            $u[1] = 'index';
        } elseif ($c > 1) {
            for ($i = 2; $i < count($u); $i++) $p[] = $u[$i];
        }
        if (count($u) > 1 && ($cm = self::ccm($u[0], $u[1]))) {
            return ['c' => $cm[0], 'm' => $cm[1], 'p' => $p];
        } elseif ($d != '404') {
            header_404();
            return self::cmp([], '404');
        }
        return false;
    }

    /**
     * 檢查 Class 和 Method 是否正確
     * @param string $c Class 名稱
     * @param string $m Method 名稱
     * @return array|boolean
     */
    private static function ccm($c, $m) {
        $f = function ($fc, $fm) { return class_exists($fc) && is_subclass_of($fc, 'Controller') && is_callable([$fc, $fm]); };
        for ($i = 0; $i < 2; $i++) {
            if ($f($c, $rm = ($m.'_'.self::$hm))) {
                return [$c, $rm];
            } elseif (self::$hm == 'get' && $f($c, $m)) {
                return [$c, $m];
            }
            if (!$i) self::require(SOURCE_DIR.DS.'controllers'.DS.$c.'.php');
        }
        return false;
    }

    /**
     * 載入檔案
     * @param string $file 檔案路徑
     */
    private static function require($file) {
        if ($f = self::require_check($file)) require_once $f;
    }

    /**
     * 檢查檔案是否可以載入
     * @param string $file 檔案路徑
     * @return string|boolean
     */
    private static function require_check($file) {
        if (!ends_with($f = $file, '.php')) $f .= '.php';
        return file_exists($f) && is_readable($f) ? $f : false;
    }

    /**
     * 取得輸入的資料
     * @param string $key 索引名稱
     * @param string $type 資料總類
     * @return mixed
     */
    public static function input($key = null, $type = null) {
        $d = self::$hm == 'get' ? self::$ud : self::$cd;
        $k = $key; $t = $type;
        if ($k !== null && !is_string($k)) return null;
        if ($t !== null && !is_string($t)) return null;
        if ($k === null && $t === null) {
            return $d;
        } elseif ($k !== null && $t === null) {
            if (is_array($d) && isset($d[$k])) {
                return $d[$k];
            } else {
                $t = $k;
                $k = null;
            }
        }
        $d = null; $t = strtolower($t);
        if ($t == 'get') {
            $d = self::$ud;
        } elseif ($t == 'file') {
            $d = self::$fd;
        } elseif ($t == self::$hm && in_array($t, ['post', 'put', 'delete', 'head', 'connect', 'options', 'patch'])) {
            $d = self::$cd;
        }
        return $k === null || $d === null ? $d : (is_array($d) ? $d[$k] ?? null : null);
    }

    /**
     * 輸出 View
     * @param string $view View 名稱
     * @param mixed $data 資料
     * @param boolean $cr 是否回傳輸出結果
     * @return null|string
     */
    public static function output($view, $data = [], $cr = false) {
        if ($cr) ob_start(); lang();
        $d = $data; $v = trim(str_replace(['\\', '/'], DS, $ov = $view), DS);
        if (!empty(self::$view[$ov])) {
            header('Content-Type: text/html; charset=utf-8');
            if (is_object($d)) $d = get_object_vars($d);
            extract($d);
            eval('?>'.self::$view[$ov]);
        } elseif ($vp = self::require_check(SOURCE_DIR.DS.'views'.DS.$v)) {
            header('Content-Type: text/html; charset=utf-8');
            if (is_object($d)) $d = get_object_vars($d);
            extract($d);
            require $vp;
        } elseif ($v == 'json') {
            header('Content-Type: application/json');
            echo json_encode($d);
        } elseif ($v == 'html') {
            header('Content-Type: text/html; charset=utf-8');
            echo $d;
        } elseif ($v == 'text') {
            header('Content-Type: text/plain; charset=utf-8');
            echo $d;
        } else {
            header('Content-Type: application/octet-stream');
            echo $d;
        }
        return $cr ? ob_get_clean() : null;
    }

    /**
     * 取得語系內容
     * @param string|array $key 索引
     * @return string|array
     */
    public static function lang($key = '') {
        $r = '{MISSING}'; $k = $key;
        if (empty(self::$ld)) lang_load(self::$config->lang);
        if (is_string($k)) $k = [$k];
        if (is_array($k) && !empty(self::$lang) && ($l = self::$lang) && !($f = false)) {
            foreach ($k as $i) if (is_string($i) && $f = isset($l[$i])) { $l = $l[$i]; break; }
            if ($f && (is_string($l) || is_array($l))) $r = $l;
        }
        return $r;
    }

    /**
     * 讀取語系
     * @param string $lang 語系名稱
     * @param string $now 目前的語系名稱
     * @return boolean
     */
    public static function lang_load($lang = '', &$now = null) {
        static $ol = null; $l = $lang;
        if (!$ol) $ol = self::$lang;
        if (!self::$ld && !empty(self::$lang[$l])) {
            self::$ld = $l; self::$lang = $ol[$l];
            define('LANG', $l);
        } elseif (!self::$ld && $lp = self::require_check(SOURCE_DIR.DS.'lang'.DS.$l)) {
            self::$ld = $l; require $lp;
            if (count(self::$lang) == 1 && isset(self::$lang[$l])) self::$lang = self::$lang[$l];
            define('LANG', $l);
        }
        return ($now = self::$ld) == $l;
    }
}

class Config {
    /**
     * 取得或設定 路由
     * @var array
     */
    public $routes = [];

    /**
     * 取得或設定 資料庫設定
     * @var string
     */
    public $db = [];

    /**
     * 取得或設定 預設語言
     * @var string
     */
    public $lang = null;
}

/**
 * 實作自動載入 Model
 */
abstract class AutoLoader {
    public function __construct() {
        spl_autoload_register(function ($c) {
            $c = ltrim($c, '\\');
            $f  = SOURCE_DIR.DS.'models';
            if ($i = mb_strrpos($c, '\\')) {
                $f .= DS.str_replace('\\', DS, mb_substr($c, 0, $i)).DS;
                $c = mb_substr($c, $i + 1);
            }
            if (file_exists($f = $f.DS.$c.'.php') && is_readable($f)) require $f;
        });
    }
}

/**
 * Controller 基底類別
 */
abstract class Controller extends AutoLoader { }

/**
 * Model 基底類別
 */
abstract class Model extends AutoLoader {
    /**
     * 加密密碼
     * @param string $password 輸入密碼
     * @return string
     */
    protected static function password_hash($password) {
        return password_hash($password, PASSWORD_DEFAULT, ['cost' => 12]);
    }

    /**
     * 驗證密碼
     * @param string $password 輸入密碼
     * @param string $hash 已加密的密碼
     * @return boolean
     */
    protected static function password_verify($password, $hash) {
        return password_verify($password, $hash);
    }

    /**
     * 取得或設定 PDO 物件
     * @var PDO
     */
    protected static $db_pdo = null;

    /**
     * 取得或設定 最後的 PDO 敘述
     * @var PDOStatement
     */
    protected $db_statement = null;

    private static function db_connect() {
        try {
            if (self::$db_pdo == null) {
                $c = SingleMVC::$config;
                self::$db_pdo = new PDO(
                    'mysql:host='.($c->db['host'] ?? 'localhost').(!empty($c->db['name']) ? ';dbname='.$c->db['name'] : '').';charset=utf8mb4',
                    $c->db['username'] ?? 'root', $c->db['password'] ?? '',
                    [PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4', PDO::ATTR_EMULATE_PREPARES => false]
                );
            }
        }
        catch (Exception $ex) { }
        return self::$db_pdo != null;
    }

    /**
     * 執行 SQL 指令
     * @param string $statement SQL 指令
     * @return PDOStatement
     */
    protected function db_query($statement) {
        if (self::db_connect()) return $this->db_statement = self::$db_pdo->query($statement);
        return false;
    }

    /**
     * 準備 SQL 指令
     * @param string $statement SQL 樣板
     * @return PDOStatement
     */
    protected function db_prepare($statement) {
        if (self::db_connect()) return $this->db_statement = self::$db_pdo->prepare($statement);
        return false;
    }

    /**
     * 插入資料
     * @return string 最後新增的編號
     */
    protected function db_insert() {
        if (($s = $this->db_statement) && $s->execute()) return self::$db_pdo->lastInsertId();
        return false;
    }

    /**
     * 取得資料
     * @param boolean $force_array 單筆資料仍傳回陣列
     * @return array
     */
    protected function db_select($force_array = false) {
        if (($s = $this->db_statement) && $s->execute()) {
            if (($r = $s->fetchAll(PDO::FETCH_ASSOC)) !== false) return (count($r) == 1) && !$force_array ? $r[0] : $r;
        }
        return false;
    }

    /**
     * 更新資料
     * @return int 異動的列數
     */
    protected function db_update() {
        if (($s = $this->db_statement) && $s->execute()) return $s->rowCount();
        return false;
    }

    /**
     * 綁定數值
     * @param int|string|array $parameter 名稱/參數
     * @param mixed $value 數值
     * @param int $type 型別
     * @return boolean
     */
    protected function db_bind($parameter, $value = '', $type = PDO::PARAM_STR) {
        if ($s = $this->db_statement) {
            if (is_array($p = $parameter) && $r = true) {
                foreach ($p as $k => $v) {
                    if (is_array($v)) {
                        if (count($v) == 1) {
                            $r &= $s->bindValue($k, $v[0]);
                        } elseif (count($v) == 2) {
                            $r &= $s->bindValue($k, $v[0], $v[1]);
                        }
                    } elseif (is_int($v)) {
                        $r &= $s->bindValue($k, $v, PDO::PARAM_INT);
                    } elseif (is_bool($v)) {
                        $r &= $s->bindValue($k, $v, PDO::PARAM_BOOL);
                    } elseif (is_null($v)) {
                        $r &= $s->bindValue($k, $v, PDO::PARAM_NULL);
                    } elseif (is_resource($v)) {
                        $r &= $s->bindValue($k, $v, PDO::PARAM_LOB);
                    } else {
                        $r &= $s->bindValue($k, $v, PDO::PARAM_STR);
                    }
                }
                return $r;
            } else {
                return $s->bindValue($p, $value, $type);
            }
        }
        return false;
    }

    /**
     * 開始交易
     * @return boolean
     */
    protected function db_begin() {
        if (self::db_connect()) return self::$db_pdo->beginTransaction();
        return false;
    }

    /**
     * 提交交易
     * @return boolean
     */
    protected function db_commit() {
        if (self::db_connect()) return self::$db_pdo->commit();
        return false;
    }

    /**
     * 復原交易
     * @return boolean
     */
    protected function db_rollBack() {
        if (self::db_connect()) return self::$db_pdo->rollBack();
        return false;
    }

    /**
     * 取得除錯資訊
     * @return boolean|string
     */
    protected function db_debug() {
        if ($statement = $this->db_statement) {
            ob_start();
            $statement->debugDumpParams();
            return ob_get_clean();
        }
        return false;
    }

    /**
     * 建立並執行一個請求
     * @param string $url 請求路徑
     * @param string $method 請求方法
     * @param mixed $data 資料
     * @param array $option 選項
     * @param boolean $get_header 是否傳回 Header
     * @return mixed
     */
    protected static function request($url, $method = 'get', $data = [], $option = [], $get_header = false) {
        $chs = [self::request_async($url, $method, $data, $option)];
        $chs = self::request_run($chs, 0, -1, $get_header);
        return reset($chs);
    }

    /**
     * 建立一個非同步請求
     * @param string $url 請求路徑
     * @param string $method 請求方法
     * @param mixed $data 資料
     * @param array $option 選項
     * @return resource
     */
    protected static function request_async($url, $method = 'get', $data = [], $option = []) {
        $ch = curl_init();
        $m = strtoupper($method); $u = $url; $d = $data; $o = $option;
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $m);
        if ($m == 'GET') {
            curl_setopt($ch, CURLOPT_URL, $u.(strpos($u, '?') === false ? '?' : '&').http_build_query($d));
        } else {
            if (is_array($d) && isset($d['_GET'])) {
                curl_setopt($ch, CURLOPT_URL, $u.(strpos($u, '?') === false ? '?' : '&').http_build_query($d['_GET']));
                unset($d['_GET']);
            } else {
                curl_setopt($ch, CURLOPT_URL, $u);
            }
            curl_setopt($ch, CURLOPT_POST, true);
            $hct = ($ct = '1') && (!empty($o['Header']['Content-Type']) && is_string($ct = $o['Header']['Content-Type']));
            if ((($ia = is_array($d)) && !$hct) || ($ia && starts_with($ct, 'application/x-www-form-urlencoded'))) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($d));
            } elseif ($ia && $hct && starts_with($ct, 'multipart/form-data')) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $d);
            } elseif ($hct && starts_with($ct, 'application/json')) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($d));
            } elseif (is_string($d)) {
                if (!$hct) $o['Header']['Content-Type'] = 'text/plain';
                curl_setopt($ch, CURLOPT_POSTFIELDS, $d);
            }
        }
        if (!empty($o['Header']) && is_array($hs = $o['Header'])) {
            $t = [];
            foreach ($hs as $k => $h) $t[] = $k.': '.$h;
            curl_setopt($ch, CURLOPT_HTTPHEADER, $t);
        }
        if (!empty($o['User-Agent']) && is_string($ua = $o['User-Agent'])) {
            curl_setopt($ch, CURLOPT_USERAGENT, $ua);
        } else {
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
        }
        if (!empty($o['Cookie']) && is_string($c = $o['Cookie'])) {
            curl_setopt($ch, CURLOPT_COOKIE, $c);
        } elseif (defined('COOKIE_ROOT')) {
            $c = rtrim(COOKIE_ROOT, '/\\').DS.($o['Cookie-File'] ?? 'cookie').'.tmp';
            curl_setopt($ch, CURLOPT_COOKIEJAR, $c);
            curl_setopt($ch, CURLOPT_COOKIEFILE, $c);
        }
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        return $ch;
    }

    /**
     * 執行多個非同步請求
     * @param array $rs 請求物件
     * @param int $start 開始索引
     * @param int $length 長度
     * @param boolean $get_header 是否傳回 Header
     * @return array
     */
    protected static function request_run(&$rs = [], $start = 0, $length = -1, $get_header = false) {
        $n = 'request';
        if (($s = $start) < 0 || $s > count($rs)) $s = 0;
        if (($l = $length) < 0 || ($s + $l) > count($rs)) $l = count($rs) - $s;
        $e = $s + $l;
        $cb = false;
        if (gettype($rs[$s][$n]) == 'resource') {
            $cb = true;
        } elseif (gettype($rs[$s]) != 'resource') {
            return [];
        }
        $mh = curl_multi_init();
        for ($i = $s; $i < $e; $i++) {
            curl_setopt($cb ? $rs[$i][$n] : $rs[$i], CURLOPT_HEADER, $get_header);
            curl_multi_add_handle($mh, $cb ? $rs[$i][$n] : $rs[$i]);
        }
        $rg = null;
        do {
            curl_multi_exec($mh, $rg);
            curl_multi_select($mh);
        } while ($rg > 0);
        for ($i = $s; $i < $e; $i++) curl_multi_remove_handle($mh, $cb ? $rs[$i][$n]: $rs[$i]);
        curl_multi_close($mh);
        $r = [];
        for ($i = $s; $i < $e; $i++) {
            if ($cb) {
                $t = curl_multi_getcontent($rs[$i][$n]);
                $rs[$i] = array_merge($rs[$i], $get_header ? self::phc($t) : ['content' => $t]);
                curl_close($rs[$i][$n]);
            } else {
                $t = curl_multi_getcontent($rs[$i]);
                $r[] = $get_header ? self::phc($t) : $t;
                curl_close($rs[$i]);
            }
        }
        return $cb ? $rs : $r;
    }

    /**
     * 解析請求的回應
     * @param string $r 回應資料
     * @return array
     */
    private static function phc($r) {
        list($h, $cr) = explode("\r\n\r\n", $r, 2);
        $hs = explode("\r\n", $h);
        $hr = ['Status' => array_shift($hs)];
        foreach ($hs as $h) {
            list($k, $v) = explode(":", $h, 2);
            $hr[trim($k)] = ltrim($v);
        }
        return ['header' => $hr, 'content' => $cr];
    }
}

/**
 * 設定狀態 404
 */
function header_404() {
    header('HTTP/1.1 404 Not Found');
}

/**
 * 取得輸入的資料
 * @param string $key 索引名稱
 * @param string $type 資料總類
 * @return mixed
 */
function input($key = null, $type = null) {
    return SingleMVC::input($key, $type);
}

/**
 * 輸出 View
 * @param string $view View 名稱
 * @param mixed $data 資料
 * @param boolean $clean 是否回傳輸出結果
 * @return null|string
 */
function output($view, $data = [], $clean = false) {
    return SingleMVC::output($view, $data, $clean);
}

/**
 * 取得語系內容
 * @param string|array $key 索引
 * @return string|array
 */
function lang($key = '') {
    return SingleMVC::lang($key);
}

/**
 * 讀取語系
 * @param string $lang 語系名稱
 * @param string $now 目前的語系名稱
 * @return boolean
 */
function lang_load($lang = '', &$now = null) {
    return SingleMVC::lang_load($lang, $now);
}

/**
 * 檢查字串是否以特定字串開頭
 * @param string $haystack 字串
 * @param string $needle 特定字串
 * @return boolean
 */
function starts_with($haystack, $needle) {
    return substr($haystack, 0, strlen($n = $needle)) === $n;
}

/**
 * 檢查字串是否以特定字串結尾
 * @param string $haystack 字串
 * @param string $needle 特定字串
 * @return boolean
 */
function ends_with($haystack, $needle) {
    return !($l = strlen($n = $needle)) || (substr($haystack, -$l) === $n);
}

/**
 * 除錯資訊
 */
$_DEBUG = [];

/**
 * 記錄除錯資訊
 * @param string $msg 訊息
 * @return string
 */
function debug($msg = '') {
    global $_DEBUG;
    list($us, $s) = explode(' ', microtime());
    $t = date('H:i:s', $s).'.'.sprintf('%03d', floor($us * 1000));
    if (is_string($m = $msg)) {
        $d = $t."\t".$m;
    } else {
        ob_start();
        var_dump($m);
        $d = $t."\tdata:\n".ob_get_clean();
    }
    if (defined('DEBUG_ROOT')) {
        if (file_exists($f = rtrim(DEBUG_ROOT, '/\\').DS.'debug.log') && empty($_DEBUG)) unlink($f);
        file_put_contents($f, $d."\n", FILE_APPEND);
    }
    return ($_DEBUG[] = $d)."\n";
}

/**
 * 傾印資料
 * @param mixed $data 資料內容
 * @param mixed $vars 更多資料內容
 */
function dump($data, ...$vars) {
    var_dump($data, ...$vars);
}

/**
 * 時間紀錄
 */
$_TIME = ['total' => [], 'block' => []];

/**
 * 紀錄執行時間
 * @param string $tag 註記
 */
function stopwatch($tag = '') {
    global $_TIME; $_T = &$_TIME;
    $t = microtime(true); $c = $tag;
    if (!empty($_T['block'][$c])) {
        if ($_T['block'][$c]['start'] === false) {
            $_T['block'][$c]['start'] = $t;
        } else {
            $_T['block'][$c]['count']++;
            $_T['block'][$c]['time'] += $t - $_T['block'][$c]['start'];
            $_T['block'][$c]['start'] = false;
        }
    } elseif (!empty($_T['total'][$c])) {
        $_T['block'][$c] = ['start' => false, 'count' => 1, 'time' => $t - $_T['total'][$c]['time']];
        unset($_T['total'][$c]);
    } else {
        if (empty($_T['total'])) {
            $_T['total'][$c ?: 'start'] = ['time' => $t, 'splits' => 0, 'laps' => 0];
        } else {
            $dt_s = $t - reset($_T['total'])['time'];
            $dt_l = $t - end($_T['total'])['time'];
            $_T['total'][$c] = ['time' => $t, 'splits' => $dt_s, 'laps' => $dt_l];
        }
    }
}

/**
 * 依格式輸出時間
 * @param array $format 輸出格式
 * $format = [
 *     'total' => ['head' => '...', 'body' => '...', 'foot' => '...'],
 *     'block' => ['head' => '...', 'body' => '...', 'foot' => '...']
 * ]
 * @return string
 */
function stopwatch_format($format = []) {
    global $_TIME; $_T = &$_TIME; $f = $format;
    $r = $f['total']['head'] ?? "Tag\tTime\t\tSplits\tLaps\n";
    foreach ($_T['total'] ?? [] as $t => $d) $r .= sprintf($f['total']['body'] ?? "%s\t%.3f\t%.3f\t%.3f\n", $t, $d['time'], $d['splits'], $d['laps']);
    $r .= ($f['total']['foot'] ?? "\n").($f['block']['head'] ?? "Tag\tCount\tTime\n");
    foreach ($_T['block'] ?? [] as $t => $d) $r .= sprintf($f['block']['body'] ?? "%s\t%.3f\t%.3f\n", $t, $d['count'], $d['time']);
    return $r.($f['block']['foot'] ?? "\n");
}

/**
 * 檢查是否有新版框架
 * @param boolean $details 是否取得詳細資料
 * @return int|array
 */
function check_for_updates($details = false) {
    if (preg_match('^[\d]\.[\d\.]*[\d]$', $o = file_get_contents('https://tails03119.atomdragon.tw/works/SingleMVC/version'))) {
        $r = version_compare(VERSION, $o);
        return !$details ? $r < 0 : ['result' => $r, 'online' => $o, 'current' => VERSION];
    } else {
        return !$details ? false : ['result' => -1, 'online' => 'unknow', 'current' => VERSION];
    }
}

/**
 * JWT 編碼
 * @param string $data 資料
 * @param string $secret 密鑰
 * @return string
 */
function jwt_encode($data, $secret) {
    $h = base64_encode(json_encode(['alg' => 'HS256', "typ"=> "JWT"]));
    $p = base64_encode(json_encode($data));
    $s = base64_encode(hash_hmac('sha256', $h.'.'.$p, $secret, true));
    return $h.'.'.$p.'.'.$s;
}

/**
 * JWT 解碼與驗證
 * @param string $token TOKEN內容
 * @param string $secret 密鑰
 * @return array|boolean
 */
function jwt_decode($token, $secret) {
	if (!is_string($token) || !is_string($secret)) return false;
	$t = explode('.', $token);
	if (count($t) != 3) return false;
	list($h, $p, $s) = $t;
	$hd = json_decode(base64_decode($h), true);
	if (!$hd) return false;
	$sd = base64_decode($s);
	$sc = hash_hmac('sha'.substr($hd['alg'] ?? 'HS256', 2, 3), $h.'.'.$p, $secret, true);
	if ($sd === $sc) {
		return json_decode(base64_decode($p), true);
	}
	return false;
}

SingleMVC::$config = new Config();
register_shutdown_function(function () { new SingleMVC(); exit(); });
#endregion