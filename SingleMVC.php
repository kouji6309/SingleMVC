<?php
#region SingleMVC
define('VERSION', '1.19.502');

if (version_compare(PHP_VERSION, '7.0', '<')) {
    header('Content-Type: text/plain');
    die('Requires PHP 7 or higher');
}

ob_start();

!defined('DS') && define('DS', DIRECTORY_SEPARATOR);
!defined('ROOT') && define('ROOT', str_replace('/', DS, dirname($_SERVER['SCRIPT_FILENAME'])));
!defined('SOURCE_DIR') && define('SOURCE_DIR', rtrim(ROOT, "/\\").DS.'source');

header('Framework: SingleMVC '.VERSION);

class SingleMVC {
    /**
     * 取得或設定程式組態
     * @var FrameworkConfig
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

    private static $ir = 0;
    private static $hl = 0;
    private static $fs = null;
    private static $hm = 'get';
    private static $ud = [];
    private static $cd = [];
    private static $fd = [];
    private static $pd = [];
    private static $ld = '';
    private static $am = ['post', 'put', 'delete', 'head', 'connect', 'options', 'patch'];

    /**
     * 產生 SingleMVC 實例並執行
     */
    public function __construct($args = []) {
        if (!defined('PHPUNIT') && self::$ir++) return;
        session_status() == PHP_SESSION_NONE && session_start(self::$config->session);
        // 參數處理
        $_S = $_SERVER;
        foreach (['REQUEST_URI', 'SCRIPT_NAME', 'CONTENT_TYPE', 'REQUEST_METHOD'] as $k) $_S[$k] = $args[$k] ?? $_S[$k] ?? null;
        // 處理路由
        if (!BCBA235AA0401FD10464DF6AFBFAAB77::check() && !starts_with($_S['REQUEST_URI'], '/BCBA235AA0401FD10464DF6AFBFAAB77')) {
            $_S['REQUEST_URI'] = '/BCBA235AA0401FD10464DF6AFBFAAB77';
        }
        $u = parse_url('http://host'.(function ($s) {
            $s = preg_split('/(?!^)(?=.)/u', $s); $r = '';
            foreach ($s as $c) {
                if (strlen($c) > 1) $c = urlencode($c);
                $r .= $c;
            }
            return $r;
        })($_S['REQUEST_URI']));
        $q = $u['query'] ?? '';
        $u = urldecode($u['path'] ?? '');
        if ($sn = $_S['SCRIPT_NAME'] ?: '') {
            $sd = dirname($sn);
            !defined('VROOT') && define('VROOT', rtrim(str_replace(DS, '/', $sd), '/'));
            if (mb_strpos($u, $sn) === 0) {
                $u = mb_substr($u, mb_strlen($sn));
            } elseif (mb_strpos($u, $sd) === 0) {
                $u = mb_substr($u, mb_strlen($sd));
            }
        } else {
            !defined('VROOT') && define('VROOT', '');
        }
        if (trim($u, '/') === '') {
            if (count($t = explode('?', $q, 2)) == 2) {
                list($u, $q) = $t;
            }
        }
        $u = trim($u, '/');
        mb_parse_str($_S['QUERY_STRING'] = $q, $_GET);
        if (($r = self::$config->routes ?? null) && is_array($r)) {
            foreach ($r as $k => $v) {
                $k = str_replace(array(':any', ':num'), array('[^/]+', '[0-9]+'), $k);
                if ($k != 'default' && $k != '404' && preg_match($k = '#^'.$k.'$#', $u)) {
                    $u = preg_replace($k, $v, $u); break;
                }
            }
        }
        // 處理輸入
        self::$ud = $_GET;
        $raw = $args['php://input'] ?? file_get_contents('php://input');
        $ct = strtolower(explode(';', $_S['CONTENT_TYPE'] ?? 'text/plain')[0]);
        self::$hm = $hm = strtolower($_S['REQUEST_METHOD'] ?? 'get');
        if (($ip = $hm == 'post') && $ct == 'application/x-www-form-urlencoded') {
            self::$cd = $args['$_POST'] ?? $_POST;
        } elseif (!($ig = $hm == 'get') && $ct == 'application/x-www-form-urlencoded') {
            mb_parse_str($raw, $tp1);
            self::$cd = $tp1;
        } elseif ($ip && $ct == 'multipart/form-data') {
            self::$cd = $args['$_POST'] ?? $_POST;
            self::$fd = $args['$_FILES'] ?? $_FILES;
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
                        mb_parse_str(urlencode($m[1]).'=temp', $tp2);
                        while (is_array($tp2 = $tp2[$ks[] = key($tp2)]));
                        $ks = array_reverse($ks);
                        $id = array_pop($ks);
                        $tp2 = ['name' => $m[2], 'type' => trim($t[1]), 'tmp_name' => $p, 'error' => ($e === FALSE) ? $e : 0, 'size'=> filesize($p)];
                        foreach ($tp2 as $l => $v) { $d[$id][$l] = $v; foreach ($ks as $k) $d[$id][$l] = [$k => $d[$id][$l]]; }
                        $bk = ['f' => $d];
                    } elseif (preg_match('/^Content-Disposition: .*; name=\"([^\"]*)\"[\n|\r]+([^\n\r].*)?\r$/s', $i, $m)) {
                        mb_parse_str(urlencode($m[1]).'='.urlencode($m[2]), $tp2);
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
     * 註冊自動載入
     * @return void
     */
    public static function autoload_register() {
        if (self::$hl++) return;
        // 載入第三方套件
        foreach (['3rd', 'helper'] as $f) {
            if (is_dir($h = SOURCE_DIR.DS.$f)) {
                $hs = array_diff(scandir($h), ['.', '..']);
                foreach ($hs as $i) self::require($h.DS.$i);
            }
        }
        // 自動載入 與 Composer
        spl_autoload_register(function ($c) {
            $fs = [SOURCE_DIR.DS.'models', SOURCE_DIR.DS.'controllers'];
            if (!self::require($fs[0].DS.str_replace('\\', DS, ltrim($c, '\\')).'.php') && strpos($c, '\\') === false) {
                if (self::$fs == null) {
                    self::$fs = []; $f1 = ''; $f1 = function ($p) use (&$f1) {
                        if (file_exists($p) && is_dir($p)) {
                            $d = []; $l = array_diff(scandir($p), ['.', '..']);
                            foreach ($l as $i) { if (is_dir($i = $p.DS.$i)) { $d[] = $i; } else if (is_file($i)) { self::$fs[] = $i; } }
                            sort($d);
                            foreach ($d as $i) $f1($i);
                        }
                    };
                    foreach ($fs as $f) $f1($f);
                }
                foreach (self::$fs as $i) { if (ends_with($i, DS.$c.'.php') && self::require($i)) break; }
            }
        });
        self::require(ROOT.DS.'vendor'.DS.'autoload.php');
        // 載入設定
        self::require(SOURCE_DIR.DS.'config.php');
    }

    /**
     * 檢查 Class, Method 和 Parameter
     * @param array $u 已分割的 URL
     * @param string $d 預設路由
     * @return array|bool
     */
    private static function cmp($u, $d = null) {
        $C = null; $M = null; $P = []; $r = self::$config->routes; $cd = SOURCE_DIR.DS.'controllers'; $pc = null;
        if (count($u) > 0 && !empty($u[0]) && lang_load($u[0])) array_shift($u);
        if (empty($u) || ($c = count($u)) == 1 && empty($u[0])) {
            if (!empty($d) && isset($r[$d]) && is_string($dr = $r[$d])) $u = explode('/', trim($dr, '/'));
        }
        if (count($u) > 0 && !empty($u[0]) && lang_load($u[0])) array_shift($u);
        while ($c = count($u)) {
            if ($c > 1 && $cm = self::ccm($u[0], $u[1])) { $C = $cm[0]; $M = $cm[1]; array_shift($u); array_shift($u); $P = $u; break; }
            if ($cm = self::ccm($u[0], 'index')) { $C = $cm[0]; $M = $cm[1]; array_shift($u); $P = $u; break; }
            if ($pc !== $u[0]) {
                if (self::require(($tf = $cd.DS.$u[0]).'.php')) {
                    $pc = $u[0]; continue;
                } elseif (file_exists($tf) && is_dir($tf)) {
                    $cd = $tf;
                }
                array_shift($u);
            } else { break; }
        }
        if ($C != null && $M != null) {
            return ['c' => $C, 'm' => $M, 'p' => $P];
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
     * @return array|bool
     */
    private static function ccm($c, $m) {
        $f1 = function ($fc, $fm) { return class_exists($fc) && is_subclass_of($fc, 'Controller') && is_callable([$fc, $fm]); };
        $f2 = function ($m) { return array_sum(array_map(function($v) use($m) { return ends_with($m, '_'.$v) ? 1 : 0; }, self::$am)) == 1; };
        if ($f1($c, $rm = ($m.'_'.self::$hm))) {
            return [$c, $rm];
        } elseif (self::$hm == 'get' && !$f2($m) && $f1($c, $m)) {
            return [$c, $m];
        }
        return false;
    }

    /**
     * 載入檔案
     * @param string $file 檔案路徑
     * @return string|bool
     */
    public static function require($file) {
        $f = false;
        if ($f = self::require_check($file)) require_once $f;
        return $f;
    }

    /**
     * 檢查檔案是否可以載入
     * @param string $file 檔案路徑
     * @return string|bool
     */
    public static function require_check($file) {
        if (!ends_with($f = $file, '.php')) $f .= '.php';
        $f = str_replace(['\\', '/'], DS, $f);
        return file_exists($f) && is_resource($h = @fopen($f, "r")) && fclose($h) ? $f : false;
    }

    /**
     * 取得輸入的資料
     * @param mixed $key 索引名稱
     * @param string $type 資料總類
     * @return mixed
     */
    public static function input($key = null, $type = null) {
        $d = self::$hm == 'get' ? self::$ud : self::$cd;
        $k = $key; $t = $type;
        if ($k !== null && !is_string($k) && !is_array($k)) return null;
        if ($t !== null && !is_string($t)) return null;
        if ($k === null && $t === null) {
            return $d;
        } elseif ($k !== null && $t === null) {
            if (is_string($k)) {
                if (is_array($d) && isset($d[$k])) {
                    return $d[$k];
                } else {
                    $t = $k; $k = null;
                }
            } else {
                $t = self::$hm;
            }
        }
        $d = null; $t = strtolower($t);
        if ($t == 'get') {
            $d = self::$ud;
        } elseif ($t == 'file') {
            $d = self::$fd;
        } elseif ($t == self::$hm && in_array($t, self::$am)) {
            $d = self::$cd;
        }
        if (is_array($k) && is_array($d)) {
            $td = $d; $f = false;
            foreach ($k as $i) if ($f = isset($td[$i])) { $td = $td[$i]; } else { break; }
            return $f ? $td : null;
        }
        return $k === null || $d === null ? $d : (is_array($d) ? $d[$k] ?? null : null);
    }

    /**
     * 輸出 View
     * @param string $view View 名稱
     * @param mixed $data 資料
     * @param mixed $flag 附加選項
     * @return null|string
     */
    public static function output($view, $data = [], $flag = false) {
        global $_DEBUG, $_TIME;
        if ($flag === true) ob_start();
        if (is_int($flag)) http_response_code($flag);
        lang(); $d = $data; $v = trim(str_replace(['\\', '/'], DS, $ov = $view), DS);
        if (!empty(self::$view[$ov])) {
            header('Content-Type: text/html; charset=utf-8');
            if (is_object($d)) $d = get_object_vars($d);
            self::$pd[] = $d;
            foreach (self::$pd as $d) extract($d);
            eval('?>'.self::$view[$ov]);
        } elseif ($vp = self::require_check(SOURCE_DIR.DS.'views'.DS.$v)) {
            header('Content-Type: text/html; charset=utf-8');
            if (is_object($d)) $d = get_object_vars($d);
            self::$pd[] = $d;
            foreach (self::$pd as $d) extract($d);
            require $vp;
        } elseif ($v == 'json') {
            header('Content-Type: application/json');
            echo json_encode($d);
        } elseif ($v == 'html') {
            header('Content-Type: text/html; charset=utf-8');
            echo $d ?: '';
        } elseif ($v == 'text') {
            header('Content-Type: text/plain; charset=utf-8');
            echo $d ?: '';
        } elseif ($v == 'jpeg') {
            header('Content-Type: image/jpeg');
            if (is_string($d)) {
                echo $d ?: '';
            } elseif (is_resource($d)) {
                imagejpeg($d);
            }
        } elseif ($v == 'png') {
            header('Content-Type: image/png');
            if (is_string($d)) {
                echo $d ?: '';
            } elseif (is_resource($d)) {
                imagepng($d);
            }
        } else {
            header('Content-Type: application/octet-stream');
            echo $d ?: '';
        }
        return $flag === true ? ob_get_clean() : null;
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
            foreach ($k as $i) if (is_string($i) && $f = isset($l[$i])) { $l = $l[$i]; } else { $f = false; break; }
            if ($f && (is_string($l) || is_array($l))) $r = $l;
        }
        return $r;
    }

    /**
     * 讀取語系
     * @param string $lang 語系名稱
     * @param string $now 目前的語系名稱
     * @return bool
     */
    public static function lang_load($lang = '', &$now = null) {
        static $ol = null; $l = $lang;
        if (!$ol) $ol = self::$lang;
        if (!self::$ld && !empty(self::$lang[$l])) {
            self::$ld = $l; self::$lang = $ol[$l];
            define('LANG', $l);
        } elseif (!self::$ld && self::require(SOURCE_DIR.DS.'lang'.DS.$l)) {
            self::$ld = $l;
            if (count(self::$lang) == 1 && isset(self::$lang[$l])) self::$lang = self::$lang[$l];
            define('LANG', $l);
        }
        return ($now = self::$ld) == $l;
    }

    /**
     * 更新 composer 套件
     * @param bool $details 是否取得詳細資料
     * @return bool|array
     */
    public static function composer_update($details = false) {
        ini_set('memory_limit', '4095M');
        ini_set('max_execution_time', 3600);
        clearstatcache();
        $r = ['status' => -1, 'message' => '', 'log' => '']; $d = $details; $tp = sys_get_temp_dir();
        if (!file_put_contents($cp = $tp.DS.'composer.phar', fopen('https://getcomposer.org/composer.phar', 'r'))) {
            $r['status'] = -1;  $r['message'] = 'Unabled to download composer.';
        } elseif (!($c = new Phar($cp)) || !$c->extractTo($ep = $tp.DS.'composer', null, true)) {
            $r['status'] = -2;  $r['message'] = 'Unabled to setup composer.';
        } elseif (!self::require($ep.DS.'/vendor/autoload.php') || !putenv('COMPOSER_HOME='.$ep) || !chdir(ROOT)) {
            $r['status'] = -3; $r['message'] = 'Unabled to setup composer.';
        } else {
            try {
                $i = new Symfony\Component\Console\Input\ArrayInput(['command' => 'update']);
                $o = new Symfony\Component\Console\Output\BufferedOutput();
                $a = new Composer\Console\Application();
                $a->setAutoExit(false);
                $r['status'] = $a->run($i, $o); $r['log'] = $o->fetch();
            }
            catch(Exception $ex) {
                $r['status'] = -4; $r['message'] = $ex->getMessage(); $r['log'] = $ex->getTraceAsString();
            }
        }
        return $d ? $r : $r['status'] == 0;
    }

    /**
     * 檢查是否有新版框架
     * @param bool $details 是否取得詳細資料
     * @return bool|array
     */
    public static function check_for_updates($details = false) {
        clearstatcache();
        $file = file_get_contents('https://raw.githubusercontent.com/kouji6309/SingleMVC/master/SingleMVC.php'); $m = [];
        if (preg_match('([\d]\.[\d\.]*[\d])', $file, $m)) {
            $r = version_compare(VERSION, $m[0]);
            return !$details ? $r < 0 : ['result' => $r, 'online' => $m[0], 'current' => VERSION];
        } else {
            return !$details ? false : ['result' => -1, 'online' => 'unknow', 'current' => VERSION];
        }
    }
}

class FrameworkConfig {
    /**
     * 取得或設定 會話設定
     * @var array
     */
    public $session = [];

    /**
     * 取得或設定 路由
     * @var array
     */
    public $routes = [];

    /**
     * 取得或設定 資料庫設定
     * @var array
     */
    public $db = [];

    /**
     * 取得或設定 預設語言
     * @var string
     */
    public $lang = null;
}

/**
 * Controller 基底類別
 */
abstract class Controller {
    public function __construct() { }
}

/**
 * Model 基底類別
 */
abstract class Model {
    public function __construct() { }

    /**
     * 建立密碼的雜湊值
     * @param string $password 輸入密碼
     * @return string
     */
    protected static function password_hash($password) {
        return password_hash($password, PASSWORD_DEFAULT, ['cost' => 12]);
    }

    /**
     * 驗證密碼與雜湊值
     * @param string $password 輸入密碼
     * @param string $hash 已加密的密碼
     * @return bool
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

    /**
     * 連線 SQL 資料庫
     * @return bool
     */
    protected function db_connect() {
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
     * @return PDOStatement|bool
     */
    protected function db_query($statement) {
        if ($this->db_connect()) return $this->db_statement = self::$db_pdo->query($statement);
        return false;
    }

    /**
     * 準備 SQL 指令
     * @param string $statement SQL 樣板
     * @return PDOStatement|bool
     */
    protected function db_prepare($statement) {
        if ($this->db_connect()) return $this->db_statement = self::$db_pdo->prepare($statement);
        return false;
    }

    /**
     * 插入資料
     * @return string|int|bool 最後新增的編號 或 新增列數
     */
    protected function db_insert() {
        if (($s = $this->db_statement) && $s->execute()) {
            $c = $s->rowCount();
            $l = self::$db_pdo->lastInsertId();
            if ($c == 1) {
                return $l ?: $c;
            } elseif ($c > 1) {
                return $c;
            }
        }
        return false;
    }

    /**
     * 取得資料
     * @param bool $force_array 單筆資料仍傳回二維陣列
     * @return array|bool
     */
    protected function db_select($force_array = false) {
        if (($s = $this->db_statement) && $s->execute()) {
            if (($r = $s->fetchAll(PDO::FETCH_ASSOC)) !== false) return (count($r) == 1) && !$force_array ? $r[0] : $r;
        }
        return false;
    }

    /**
     * 更新資料
     * @return int|bool 異動的列數
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
     * @return bool
     */
    protected function db_bind($parameter, $value = '', $type = PDO::PARAM_STR) {
        if ($s = $this->db_statement) {
            if (is_array($p = $parameter) && $r = true) {
                foreach ($p as $k => $v) {
                    if (is_int($k)) $k += 1;
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
     * @return bool
     */
    protected function db_begin() {
        if ($this->db_connect()) return self::$db_pdo->beginTransaction();
        return false;
    }

    /**
     * 提交交易
     * @return bool
     */
    protected function db_commit() {
        if ($this->db_connect()) return self::$db_pdo->commit();
        return false;
    }

    /**
     * 復原交易
     * @return bool
     */
    protected function db_rollBack() {
        if ($this->db_connect()) return self::$db_pdo->rollBack();
        return false;
    }

    /**
     * 取得除錯資訊
     * @return bool|string
     */
    protected function db_debug() {
        if ($s = $this->db_statement) {
            ob_start();
            $s->debugDumpParams();
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
     * @param bool $get_header 是否傳回 Header
     * @return string|array|bool
     */
    protected static function request($url, $method = 'get', $data = [], $option = [], $get_header = false) {
        $chs = self::request_async($url, $method, $data, $option);
        return self::request_run($chs, 0, -1, $get_header);
    }

    /**
     * 建立一個非同步請求
     * @param string $url 請求路徑
     * @param string $method 請求方法
     * @param mixed $data 資料
     * @param array $option 選項
     * @return resource|bool
     */
    protected static function request_async($url, $method = 'get', $data = [], $option = []) {
        $ch = curl_init();
        if (!$ch) return false;
        $m = strtoupper($method); $u = $url; $d = $data; $o = $option;
        if (!curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $m)) return false;
        if (!empty($option['Option']) && is_array($oo = $o['Option'])) {
            if (!curl_setopt_array($ch, $oo)) return false;
        }
        if ($m == 'GET') {
            if (!curl_setopt($ch, CURLOPT_URL, $u.(strpos($u, '?') === false ? '?' : '&').http_build_query($d))) return false;
        } else {
            if (is_array($d) && isset($d['_GET'])) {
                if (!curl_setopt($ch, CURLOPT_URL, $u.(strpos($u, '?') === false ? '?' : '&').http_build_query($d['_GET']))) return false;
                unset($d['_GET']);
            } else {
                if (!curl_setopt($ch, CURLOPT_URL, $u)) return false;
            }
            if (!curl_setopt($ch, CURLOPT_POST, true)) return false;
            $hct = ($ct = '1') && (!empty($o['Header']['Content-Type']) && is_string($ct = $o['Header']['Content-Type']));
            if ((($ia = is_array($d)) && !$hct) || ($ia && starts_with($ct, 'application/x-www-form-urlencoded'))) {
                if (!curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($d))) return false;
            } elseif ($ia && $hct && starts_with($ct, 'multipart/form-data')) {
                if (!curl_setopt($ch, CURLOPT_POSTFIELDS, $d)) return false;
            } elseif ($hct && starts_with($ct, 'application/json')) {
                if (!curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($d))) return false;
            } elseif (is_string($d)) {
                if (!$hct) $o['Header']['Content-Type'] = 'text/plain';
                if (!curl_setopt($ch, CURLOPT_POSTFIELDS, $d)) return false;
            }
        }
        if (!empty($o['Header']) && is_array($hs = $o['Header'])) {
            $t = [];
            foreach ($hs as $k => $h) $t[] = $k.': '.$h;
            if (!curl_setopt($ch, CURLOPT_HTTPHEADER, $t)) return false;
        }
        if (!empty($o['User-Agent']) && is_string($ua = $o['User-Agent'])) {
            if (!curl_setopt($ch, CURLOPT_USERAGENT, $ua)) return false;
        }
        if (!empty($o['Cookie']) && is_string($c = $o['Cookie'])) {
            if (!curl_setopt($ch, CURLOPT_COOKIE, $c)) return false;
        } elseif (defined('COOKIE_DIR')) {
            $c = rtrim(COOKIE_DIR, '/\\').DS.($o['Cookie-File'] ?? 'cookie').'.tmp';
            if (!curl_setopt($ch, CURLOPT_COOKIEJAR, $c)) return false;
            if (!curl_setopt($ch, CURLOPT_COOKIEFILE, $c)) return false;
        }
        if (isset($o['SSL-Verify']) && is_bool($s = $o['SSL-Verify'])) {
            if (!curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $s * 1)) return false;
        }
        if (!empty($o["Proxy"]) && is_string($p = $o["Proxy"])) {
            if (!curl_setopt($ch, CURLOPT_PROXY, $p)) return false;
        }
        if (isset($o["HTTP-Version"]) && is_int($v = $o["HTTP-Version"])) {
            if (!curl_setopt($ch, CURLOPT_HTTP_VERSION, $v)) return false;
        }
        if (!curl_setopt($ch, CURLOPT_RETURNTRANSFER, true)) return false;
        return $ch;
    }

    /**
     * 執行一個或多個非同步請求
     * @param mixed $rs 請求物件
     * @param int $start 開始索引
     * @param int $length 長度
     * @param bool $get_header 是否傳回 Header
     * @return string|array|bool
     */
    protected static function request_run($rs, $start = 0, $length = -1, $get_header = false) {
        $n = 'request'; $one = false;
        if (gettype($rs) == 'resource' && get_resource_type($rs) == 'curl') {
            $rs = [$rs]; $start = 0; $length = -1; $one = true;
        } elseif (gettype($rs) != 'array') {
            return false;
        }
        if (($s = $start) < 0 || $s > count($rs)) $s = 0;
        if (($l = $length) < 0 || ($s + $l) > count($rs)) $l = count($rs) - $s;
        $e = $s + $l;
        $cb = false;
        if (gettype($rs[$s][$n]) == 'resource' && get_resource_type($rs[$s][$n]) == 'curl') {
            $cb = true;
        } elseif (gettype($rs[$s]) != 'resource' || get_resource_type($rs[$s]) != 'curl') {
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
                $r[] = array_merge($rs[$i], $get_header ? self::request_parse($t) : ['content' => $t]);
                curl_close($rs[$i][$n]);
            } else {
                $t = curl_multi_getcontent($rs[$i]);
                $r[] = $get_header ? self::request_parse($t) : $t;
                curl_close($rs[$i]);
            }
        }
        return $one ? $r[0] : $r;
    }

    /**
     * 解析請求的回應
     * @param string $response 回應資料
     * @return array
     */
    protected static function request_parse($response) {
        $r = $response;
        if (empty($r)) return ['header' => [], 'content' => ''];
        list($h, $cr) = explode("\r\n\r\n", $r, 2);
        if (stripos($h, "200 Connection established\r\n") !== false) {
            list($f, $h, $cr) = explode("\r\n\r\n", $r, 3);
        }
        $fs = explode("\r\n", preg_replace('/\x0D\x0A[\x09\x20]+/', ' ', $h));
        $hr = ['Status' => intval(explode(' ', array_shift($fs))[1] ?? 0)];
        foreach ($fs as $f) {
            if (preg_match('/([^:]+): (.+)/m', $f, $m) ) {
                $m[1] = preg_replace_callback('/(?<=^|[\x09\x20\x2D])./', function ($r) { return strtoupper($r[0]); }, strtolower(trim($m[1])));
                if (isset($hr[$m[1]])) {
                    $hr[$m[1]] = [$hr[$m[1]], $m[2]];
                } else {
                    $hr[$m[1]] = trim($m[2]);
                }
            }
        }
        return ['header' => $hr, 'content' => $cr];
    }
}

/**
 * 提供 Composer 更新用控制器
 */
class BCBA235AA0401FD10464DF6AFBFAAB77 extends Controller {
    public function __construct() {
        if (self::check()) exit(header_404());
    }

    public static function check() {
        return file_exists(ROOT.DS.'vendor'.DS.'autoload.php') || !file_exists(ROOT.DS.'composer.json');
    }

    public function index() {
        output('html', '<!DOCTYPE html><title>Installing...</title>'.
            '<script src="https://code.jquery.com/jquery-3.4.0.min.js"></script>'.
            '<h3 style="color:green">Updating dependencies...</h3><pre>Please wait...</pre>'.
            '<script>$.ajax({url:"'.VROOT.'/BCBA235AA0401FD10464DF6AFBFAAB77/update",type:"post",success:function(r){'.
            '$("pre").text(r.log);if(r.status===0){$("h3").text("Update success, reload in 5 seconds.");'.
            'setTimeout(function(){location.reload();},5000);}else{'.
            '$("h3").text("Update error, please upload vendor yourself.").css("color","red");}}})</script>');
    }

    public function update_post() {
        output('json', SingleMVC::composer_update(true));
    }
}

/**
 * 設定狀態 404
 */
function header_404() {
    http_response_code(404);
}

/**
 * 取得輸入的資料
 * @param mixed $key 索引名稱
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
 * @param mixed $flag 附加選項
 * @return null|string
 */
function output($view, $data = [], $flag = false) {
    return SingleMVC::output($view, $data, $flag);
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
 * @return bool
 */
function lang_load($lang = '', &$now = null) {
    return SingleMVC::lang_load($lang, $now);
}

/**
 * 檢查字串是否以特定字串開頭
 * @param string $haystack 字串
 * @param string $needle 特定字串
 * @return bool
 */
function starts_with($haystack, $needle) {
    return substr($haystack, 0, strlen($n = $needle)) === $n;
}

/**
 * 檢查字串是否以特定字串結尾
 * @param string $haystack 字串
 * @param string $needle 特定字串
 * @return bool
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
 * @param mixed $msg 訊息
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
    if (defined('DEBUG_DIR')) {
        if (file_exists($f = rtrim(DEBUG_DIR, '/\\').DS.'debug.log') && empty($_DEBUG)) unlink($f);
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
 * @param bool $details 是否取得詳細資料
 * @return bool|array
 */
function check_for_updates($details = false) {
    return SingleMVC::check_for_updates($details);
}

/**
 * JWT 編碼
 * @param mixed $data 資料
 * @param string $secret 密鑰
 * @return string
 */
function jwt_encode($data, $secret) {
    $h = str_replace('=', '', base64_encode(json_encode(['alg' => 'HS256', "typ"=> "JWT"])));
    $p = str_replace('=', '', base64_encode(json_encode($data)));
    $s = str_replace('=', '', base64_encode(hash_hmac('sha256', $h.'.'.$p, $secret, true)));
    return $h.'.'.$p.'.'.$s;
}

/**
 * JWT 解碼與驗證
 * @param string $token TOKEN內容
 * @param string $secret 密鑰
 * @return mixed
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

SingleMVC::$config = new FrameworkConfig();
SingleMVC::autoload_register();

if (!defined('PAUSE')) register_shutdown_function(function () { new SingleMVC(); exit(); });
#endregion