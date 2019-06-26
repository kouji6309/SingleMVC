<?php
define('VERSION', '1.19.626');
define('DS', DIRECTORY_SEPARATOR);
define('ROOT', '');
define('HOST', '');
define('VROOT', '');
define('SOURCE_DIR', '');

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

    /**
     * 產生 SingleMVC 實例並執行
     */
    public function __construct() { }

    /**
     * 註冊自動載入
     * @return void
     */
    public static function autoload_register() { }

    /**
     * 載入檔案
     * @param string $file 檔案路徑
     * @return string|bool
     */
    public static function require($file) { return ""; }

    /**
     * 檢查檔案是否可以載入
     * @param string $file 檔案路徑
     * @return string|bool
     */
    public static function require_check($file) { return ''; }

    /**
     * 取得輸入的資料
     * @param mixed $key 索引名稱
     * @param string $type 資料總類
     * @return mixed
     */
    public static function input($key = null, $type = null) { return ""; }

    /**
     * 輸出 View
     * @param string $view View 名稱
     * @param mixed $data 資料
     * @param mixed $flag 附加選項
     * @return null|string
     */
    public static function output($view, $data = [], $flag = false) { return ""; }

    /**
     * 取得語系內容
     * @param string|array $key 索引
     * @return string|array
     */
    public static function lang($key = '') { return ""; }

    /**
     * 讀取語系
     * @param string $lang 語系名稱
     * @param string $now 目前的語系名稱
     * @return bool
     */
    public static function lang_load($lang = '', &$now = null) { return true; }

    /**
     * 更新 composer 套件
     * @param bool $details 是否取得詳細資料
     * @return bool|array
     */
    public static function composer_update($details = false) { return true; }

    /**
     * 檢查是否有新版框架
     * @param bool $details 是否取得詳細資料
     * @return bool|array
     */
    public static function check_for_updates($details = false) { return false; }
}

class FrameworkConfig {
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
abstract class Controller { }

/**
 * Model 基底類別
 */
abstract class Model {
    /**
     * 建立密碼的雜湊值
     * @param string $password 輸入密碼
     * @return string
     */
    protected static function password_hash($password) { return ""; }

    /**
     * 驗證密碼與雜湊值
     * @param string $password 輸入密碼
     * @param string $hash 已加密的密碼
     * @return bool
     */
    protected static function password_verify($password, $hash) { return true; }

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
    protected function db_connect() { return true; }

    /**
     * 執行 SQL 指令
     * @param string $statement SQL 指令
     * @return PDOStatement|bool
     */
    protected function db_query($statement) {return null;  }

    /**
     * 準備 SQL 指令
     * @param string $statement SQL 樣板
     * @return PDOStatement|bool
     */
    protected function db_prepare($statement) { return null; }

    /**
     * 插入資料
     * @return string|int|bool 最後新增的編號 或 新增列數
     */
    protected function db_insert() { return ""; }

    /**
     * 取得資料
     * @param bool $force_array 單筆資料仍傳回二維陣列
     * @return array|bool
     */
    protected function db_select($force_array = false) { return []; }

    /**
     * 更新資料
     * @return int|bool 異動的列數
     */
    protected function db_update() { return 0; }

    /**
     * 綁定數值
     * @param int|string|array $parameter 名稱/參數
     * @param mixed $value 數值
     * @param int $type 型別
     * @return bool
     */
    protected function db_bind($parameter, $value = '', $type = PDO::PARAM_STR) { return true; }

    /**
     * 開始交易
     * @return bool
     */
    protected function db_begin() { return true; }

    /**
     * 提交交易
     * @return bool
     */
    protected function db_commit() { return true; }

    /**
     * 復原交易
     * @return bool
     */
    protected function db_rollBack() { return true; }

    /**
     * 取得除錯資訊
     * @return bool|string
     */
    protected function db_debug() { return true; }

    /**
     * 建立並執行一個請求
     * @param string $url 請求路徑
     * @param string $method 請求方法
     * @param mixed $data 資料
     * @param array $option 選項
     * @param bool $get_header 是否傳回 Header
     * @return string|array|bool
     */
    protected static function request($url, $method = 'get', $data = [], $option = [], $get_header = false) { return ""; }

    /**
     * 建立一個非同步請求
     * @param string $url 請求路徑
     * @param string $method 請求方法
     * @param mixed $data 資料
     * @param array $option 選項
     * @return resource|bool
     */
    protected static function request_async($url, $method = 'get', $data = [], $option = []) { return null; }

    /**
     * 執行一個或多個非同步請求
     * @param mixed $rs 請求物件
     * @param int $start 開始索引
     * @param int $length 長度
     * @param bool $get_header 是否傳回 Header
     * @return string|array|bool
     */
    protected static function request_run($rs, $start = 0, $length = -1, $get_header = false) { return []; }

    /**
     * 解析請求的回應
     * @param string $response 回應資料
     * @return array
     */
    protected static function request_parse($response) { return []; }
}

/**
 * 設定狀態 404
 */
function header_404() { }

/**
 * 取得輸入的資料
 * @param mixed $key 索引名稱
 * @param string $type 資料總類
 * @return mixed
 */
function input($key = null, $type = null) { return ""; }

/**
 * 輸出 View
 * @param string $view View 名稱
 * @param mixed $data 資料
 * @param mixed $flag 附加選項
 * @return null|string
 */
function output($view, $data = [], $flag = false) { return ""; }

/**
 * 取得語系內容
 * @param string|array $key 索引
 * @return string|array
 */
function lang($key = '') { return ""; }

/**
 * 讀取語系
 * @param string $lang 語系名稱
 * @param string $now 目前的語系名稱
 * @return bool
 */
function lang_load($lang = '', &$now = null) { return true; }

/**
 * 檢查字串是否以特定字串開頭
 * @param string $haystack 字串
 * @param string $needle 特定字串
 * @return bool
 */
function starts_with($haystack, $needle) { return true; }

/**
 * 檢查字串是否以特定字串結尾
 * @param string $haystack 字串
 * @param string $needle 特定字串
 * @return bool
 */
function ends_with($haystack, $needle) { return true; }

/**
 * 除錯資訊
 */
$_DEBUG = [];

/**
 * 記錄除錯資訊
 * @param mixed $msg 訊息
 * @return string
 */
function debug($msg = '') { return ""; }

/**
 * 傾印資料
 * @param mixed $data 資料內容
 * @param mixed $vars 更多資料內容
 */
function dump($data, ...$vars) { }

/**
 * 時間紀錄
 */
$_TIME = ['total' => [], 'block' => []];

/**
 * 紀錄執行時間
 * @param string $tag 註記
 */
function stopwatch($tag = '') { }

/**
 * 依格式輸出時間
 * @param array $format 輸出格式
 * $format = [
 *     'total' => ['head' => '...', 'body' => '...', 'foot' => '...'],
 *     'block' => ['head' => '...', 'body' => '...', 'foot' => '...']
 * ]
 * @return string
 */
function stopwatch_format($format = []) { return ""; }

/**
 * 檢查是否有新版框架
 * @param bool $details 是否取得詳細資料
 * @return bool|array
 */
function check_for_updates($details = false) { return false; }

/**
 * JWT 編碼
 * @param mixed $data 資料
 * @param string $secret 密鑰
 * @return string
 */
function jwt_encode($data, $secret) { return ""; }

/**
 * JWT 解碼與驗證
 * @param string $token TOKEN內容
 * @param string $secret 密鑰
 * @return mixed
 */
function jwt_decode($token, $secret) { return []; }