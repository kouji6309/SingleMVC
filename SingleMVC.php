<?php
#region SingleMVC
define('VERSION', '1.24.1017');
header('Framework: SingleMVC '.VERSION);
ob_start();

/** 框架主體 */
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

    private static $is_ran = false;
    private static $is_loaded = false;
    private static $autoload_on = true;
    private static $file_list = null;
    private static $method = 'get';
    private static $uri_data = [];
    private static $content_data = [];
    private static $file_data = [];
    private static $page_data = [];
    private static $loaded_lang = '';
    private static $allowed_method = ['post', 'put', 'delete', 'head', 'connect', 'options', 'patch'];

    /** 產生 SingleMVC 實例並執行 */
    public function __construct($args = []) {
        // 檢查是否為單元測試
        if (!defined('PHPUNIT') && self::$is_ran) {
            return;
        }
        self::$is_ran = true;

        // 開始工作階段
        if (session_status() == PHP_SESSION_NONE) {
            session_start(self::$config->session ?: ['read_and_close' => true]);
        }

        // 參數處理
        $_SERVER['REQUEST_URI'] = $_SERVER['UNENCODED_URL'] ?? $_SERVER['REQUEST_URI'] ?? $args['REQUEST_URI'] ?? null;
        foreach (['SCRIPT_NAME', 'CONTENT_TYPE', 'REQUEST_METHOD'] as $key) {
            $_SERVER[$key] = $args[$key] ?? $_SERVER[$key] ?? null;
        }

        // 檢查 Composser 路由
        $composser_class = 'BCBA235AA0401FD10464DF6AFBFAAB77';
        if (!BCBA235AA0401FD10464DF6AFBFAAB77::check() && !str_contains($_SERVER['REQUEST_URI'], '/'.$composser_class)) {
            $_SERVER['REQUEST_URI'] = '/'.$composser_class;
        }

        // 解析網址，處理編碼
        $url = parse_url('http://host'.(function ($uri) {
            $uri = preg_split('/(?!^)(?=.)/u', $uri);
            $result = '';
            foreach ($uri as $part) {
                if (strlen($part) > 1) {
                    $part = urlencode($part);
                }
                $result .= $part;
            }
            return $result;
        })($_SERVER['REQUEST_URI']));

        // 解析虛擬目錄
        $query = $url['query'] ?? '';
        $url = urldecode($url['path'] ?? '');
        if ($_SERVER['SCRIPT_NAME'] ?: '') {
            $script_dir = dirname($_SERVER['SCRIPT_NAME']);
            !defined('VROOT') && define('VROOT', rtrim(str_replace(DS, '/', $script_dir), '/'));
            if (str_starts_with($url, $_SERVER['SCRIPT_NAME'])) {
                $url = mb_substr($url, mb_strlen($_SERVER['SCRIPT_NAME']));
            } elseif (str_starts_with($url, $script_dir)) {
                $url = mb_substr($url, mb_strlen($script_dir));
            }
        } else {
            !defined('VROOT') && define('VROOT', '');
        }

        // 解析主機
        if (!defined('HOST')) {
            if (defined('PHPUNIT')) {
                define('HOST', 'http://localhost');
            } else {
                $server_post = $_SERVER['SERVER_PORT'];
                $scheme = 'http'.(($_SERVER['HTTPS'] ?? '') == 'on' ? 's' : '');
                if (($scheme == 'https' && $server_post == '443') || ($scheme == 'http' && $server_post == '80')) {
                    // 預設埠不顯示
                    $server_post = '';
                } else {
                    $server_post = ':'.$server_post;
                }
                define('HOST', $scheme.'://'.$_SERVER['HTTP_HOST'].$server_post);
            }
        }

        // 解析查詢字串
        if (trim($url, '/') === '') {
            $querys = explode('?', $query, 2);
            if (count($querys) == 2) {
                list($url, $query) = $querys;
            }
        }
        $url = trim($url, '/');
        mb_parse_str($_SERVER['QUERY_STRING'] = $query, $_GET);

        // 複寫路由
        $routes = self::$config->routes ?? null;
        if ($routes && is_array($routes) && !str_contains($url, $composser_class)) {
            foreach ($routes as $key => $route) {
                $key = str_replace(array(':any', ':num'), array('[^/]+', '[0-9]+'), $key);
                if ($key != 'default' && $key != '404' && preg_match($key = '#^'.$key.'$#', $url)) {
                    $url = preg_replace($key, $route, $url);
                    break;
                }
            }
        }

        // 處理輸入
        self::$uri_data = $_GET;
        self::$method = strtolower($_SERVER['REQUEST_METHOD'] ?? 'get');
        $raw = $args['php://input'] ?? file_get_contents('php://input');
        $content_type = strtolower(explode(';', $_SERVER['CONTENT_TYPE'] ?? 'text/plain')[0]);
        $is_post = self::$method == 'post';
        $is_get = self::$method == 'get';
        if ($is_post && $content_type == 'application/x-www-form-urlencoded') {
            self::$content_data = $args['$_POST'] ?? $_POST;
        } elseif (!$is_get && $content_type == 'application/x-www-form-urlencoded') {
            mb_parse_str($raw, $temp);
            self::$content_data = $temp;
        } elseif ($is_post && $content_type == 'multipart/form-data') {
            self::$content_data = $args['$_POST'] ?? $_POST;
            self::$file_data = $args['$_FILES'] ?? $_FILES;
        } elseif (!$is_post && $content_type == 'multipart/form-data') {
            $form_content = ['field' => [], 'file' => []];
            preg_match('/boundary=(.*)$/', $_SERVER['CONTENT_TYPE'], $boundary);
            if (count($boundary) > 0) {
                // 用邊界分割
                $blocks = preg_split('/-+'.$boundary[0].'/', $raw);
                array_pop($blocks);
                foreach ($blocks as $block) {
                    $block = ltrim($block);
                    if (empty($block)) {
                        continue;
                    }
                    $block_content = [];
                    $key_list = [];
                    // 檢查參數種類
                    if (preg_match('/^Content-Disposition: .*; name=\"([^\"]*)\"; filename=\"([^\"]*)\"[\n|\r]+([^\n\r].*)?\r$/s', $block, $split_content)) {
                        // 處理檔案
                        preg_match('/Content-Type: (.*)?/', $split_content[3], $split_content_type);
                        $tmp_path = sys_get_temp_dir().DS.'php'.substr(sha1(random_bytes(10)), 0, 6);
                        $save_result = file_put_contents($tmp_path, preg_replace('/Content-Type: (.*)[^\n\r]/', '', $split_content[3]));
                        // 解析巢狀索引
                        mb_parse_str(urlencode($split_content[1]).'=temp', $parsed_str);
                        while (is_array($parsed_str = $parsed_str[$key_list[] = key($parsed_str)])) {
                        }
                        $key_list = array_reverse($key_list);
                        // 取主要參數名稱
                        $id = array_pop($key_list);
                        $file_data = [];
                        $fields = [
                            'name'     => $split_content[2],
                            'type'     => trim($split_content_type[1]),
                            'tmp_name' => $tmp_path,
                            'error'    => ($save_result === FALSE) ? $save_result : 0,
                            'size'     => filesize($tmp_path)
                        ];
                        foreach ($fields as $key => $val) {
                            $file_data[$id][$key] = $val;
                            // 重組巢狀索引
                            foreach ($key_list as $k) {
                                $file_data[$id][$key] = [$k => $file_data[$id][$key]];
                            }
                        }
                        $block_content = ['file' => $file_data];
                    } elseif (preg_match('/^Content-Disposition: .*; name=\"([^\"]*)\"[\n|\r]+([^\n\r].*)?\r$/s', $block, $split_content)) {
                        // 處理欄位
                        mb_parse_str(urlencode($split_content[1]).'='.urlencode($split_content[2]), $parsed_str);
                        $block_content = ['field' => $parsed_str];
                    }
                    $form_content = array_merge_recursive($form_content, $block_content);
                }
            }
            self::$content_data = $form_content['field'];
            self::$file_data = $form_content['file'];
        } elseif (!$is_get && $content_type == 'application/json') {
            self::$content_data = json_decode($raw, true);
        } elseif (!$is_get) {
            self::$content_data = $raw;
        }

        // 執行
        if ($action_data = self::get_action(explode('/', trim($url, '/')), 'default')) {
            call_user_func_array([
                $action_data['class'],
                $action_data['method']
            ], $action_data['parameter']);
        } else {
            header_404();
        }
        header('Content-Length: '.ob_get_length());
        ob_flush();
        self::$config->auto_update && self::check_for_updates();
    }

    /**
     * 註冊自動載入
     * @return void
     */
    public static function autoload_register() {
        if (self::$is_loaded) {
            return;
        }
        self::$is_loaded = true;

        // 載入第三方套件
        foreach (['3rd', 'helper'] as $folder) {
            $dir = SOURCE_DIR.DS.$folder;
            if (is_dir($dir)) {
                $files = array_diff(scandir($dir), ['.', '..']);
                foreach ($files as $file) {
                    self::require($dir.DS.$file);
                }
            }
        }

        // 自動載入 與 Composer
        spl_autoload_register(function ($class) {
            if (!self::$autoload_on) {
                return;
            }

            $dirs = [SOURCE_DIR.DS.'models', SOURCE_DIR.DS.'controllers'];
            if (!self::require($dirs[0].DS.str_replace('\\', DS, ltrim($class, '\\')).'.php') && !str_contains($class, '\\')) {
                if (self::$file_list == null) {
                    self::$file_list = [];
                    $list_paths = '';
                    $list_paths = function ($dir) use (&$list_paths) {
                        // 遞迴列出檔案
                        if (file_exists($dir) && is_dir($dir)) {
                            $dirs = [];
                            $names = array_diff(scandir($dir), ['.', '..']);
                            foreach ($names as $name) {
                                $path = $dir.DS.$name;
                                if (is_dir($path)) {
                                    $dirs[] = $path;
                                } else if (is_file($path)) {
                                    self::$file_list[] = $path;
                                }
                            }
                            sort($dirs);
                            foreach ($dirs as $dir) {
                                $list_paths($dir);
                            }
                        }
                    };
                    foreach ($dirs as $dir) {
                        $list_paths($dir);
                    }
                }

                // 尋找目標的檔案
                foreach (self::$file_list as $file) {
                    if (str_ends_with($file, DS.$class.'.php') && self::require($file)) {
                        break;
                    }
                }
            }
        });
        self::require(ROOT.DS.'vendor'.DS.'autoload.php');

        // 載入設定
        self::require(SOURCE_DIR.DS.'config.php');
    }

    /**
     * 取得 Class, Method 和 Parameter
     * @param array $urls 已分割的 URL
     * @param string $default 預設路由
     * @return array|bool
     */
    private static function get_action($urls, $default = null) {
        $class = null;
        $method = null;
        $parameter = [];
        $routes = self::$config->routes;
        $controller_dir = SOURCE_DIR.DS.'controllers';
        $pc = null;

        // 檢查是否載入語系
        if (count($urls) > 0 && !empty($urls[0]) && lang_load($urls[0])) {
            array_shift($urls);
        }

        // 檢查預設路由
        $url_length = count($urls);
        if (empty($urls) || ($url_length == 1 && empty($urls[0]))) {
            if (!empty($default) && isset($routes[$default]) && is_string($routes[$default])) {
                $urls = explode('/', trim($routes[$default], '/'));
            }
        }

        // 再次檢查是否載入語系
        if (count($urls) > 0 && !empty($urls[0]) && lang_load($urls[0])) {
            array_shift($urls);
        }

        while ($url_length = count($urls)) {
            // 一般情況
            if ($url_length > 1 && $callable = self::get_callable($urls[0], $urls[1])) {
                $class = $callable['class'];
                $method = $callable['method'];
                array_shift($urls);
                array_shift($urls);
                $parameter = $urls;
                break;
            }

            // 缺少 method，找 index
            if ($callable = self::get_callable($urls[0], 'index')) {
                $class = $callable['class'];
                $method = $callable['method'];
                array_shift($urls);
                $parameter = $urls;
                break;
            }

            // 檢查子目錄
            $path = $controller_dir.DS.$urls[0];
            if ($pc !== $urls[0]) {
                if (self::require($path.'.php')) {
                    $pc = $urls[0];
                    continue;
                } elseif (file_exists($path) && is_dir($path)) {
                    $controller_dir = $path;
                }
                array_shift($urls);
            } else {
                if (file_exists($path) && is_dir($path)) {
                    $controller_dir = $path;
                    array_shift($urls);
                } else {
                    break;
                }
            }
        }

        if ($class != null && $method != null) {
            return [
                'class'     => $class,
                'method'    => $method,
                'parameter' => $parameter
            ];
        } elseif ($default != '404') {
            header_404();
            return self::get_action([], '404');
        }
        return false;
    }

    /**
     * 取得可呼叫的 Class 和 Method
     * @param string $class Class 名稱
     * @param string $method Method 名稱
     * @return array|bool
     */
    private static function get_callable($class, $method) {
        // 轉換成有效名稱
        $class = self::make_valid_name($class);
        $method = self::make_valid_name($method);

        self::$autoload_on = false;
        // 檢查繼承
        if (!class_exists($class) || !is_subclass_of($class, 'Controller')) {
            return false;
        }
        self::$autoload_on = true;

        $class = new $class();

        // 檢查可用方法
        if (is_callable([$class, $rm = ($method.'_'.self::$method)])) {
            return [
                'class'  => $class,
                'method' => $rm
            ];
        } elseif (
            self::$method == 'get' &&
            array_sum(array_map(function ($v) use ($method) {
                return str_ends_with($method, '_'.$v) ? 1 : 0;
            }, self::$allowed_method)) !== 1 &&
            is_callable([$class, $method])
        ) {
            return [
                'class'  => $class,
                'method' => $method
            ];
        }
        return false;
    }

    /**
     * 將字串轉成有效的命名
     * @param string $name 名稱
     * @return string
     */
    private static function make_valid_name($name) {
        // 取代無效字元
        $name = preg_replace('/[\x00-\/:-@[-^`{-~]/', '_', $name);

        // 開頭為數字，增加底線
        if (preg_match('/^\d/', $name)) {
            $name = '_' . $name;
        }
        return $name;
    }

    /**
     * 使用索引陣列取出陣列的值
     * @param array $array 目標陣列
     * @param array|string $keys 索引陣列
     * @return mixed
     */
    private static function get_value($array, $keys) {
        if (!is_array($keys)) {
            $keys = [$keys];
        }

        // 取出多層陣列
        $found = false;
        foreach ($keys as $key) {
            if ($found = isset($array[$key])) {
                $array = $array[$key];
            } else {
                break;
            }
        }
        return $found ? $array : null;
    }

    /**
     * 使用索引陣列設定陣列的值
     * @param array $array 目標陣列
     * @param array|string $keys 索引陣列
     * @param mixed $value 數值
     */
    private static function set_value(&$array, $keys, $value) {
        if (!is_array($keys)) {
            $keys = [$keys];
        }

        // 設定多層陣列
        foreach ($keys as $key) {
            if (!is_array($array)) {
                $array = [];
            }
            if (is_array($array) && !isset($array[$key])) {
                $array[$key] = [];
            }
            $array = &$array[$key];
        }
        $array = $value;
    }

    /**
     * 載入檔案
     * @param string $file 檔案路徑
     * @return string|bool
     */
    public static function require($file) {
        if ($file = self::require_check($file)) {
            require_once $file;
        }
        return $file;
    }

    /**
     * 檢查檔案是否可以載入
     * @param string $file 檔案路徑
     * @return string|bool
     */
    public static function require_check($file) {
        if (!str_ends_with($file, '.php')) {
            $file .= '.php';
        }
        $file = str_replace(['\\', '/'], DS, $file);
        return file_exists($file) && is_resource($handler = @fopen($file, 'r')) && fclose($handler) ? $file : false;
    }

    /**
     * 檢查變數是否為指定型別
     * @param mixed $var
     * @param string|string[] $types
     * @return bool
     */
    public static function is_instanceof($var, $types) {
        if (is_string($types)) {
            $types = [$types];
        }

        if (!is_array($types)) {
            return false;
        }

        foreach ($types as $type) {
            if (!is_string($type)) {
                continue;
            }

            if (gettype($var) === $type || is_a($var, $type) || is_resource($var) && get_resource_type($var) === $type) {
                return true;
            }
        }
        return false;
    }

    public static function instanceof ($var, $types) {
        trigger_error('Deprecated: The starts_with() function is deprecated', E_USER_DEPRECATED);
        return SingleMVC::is_instanceof($var, $types);
    }

    /**
     * 取得輸入的資料
     * @param string|string[] $key 索引名稱
     * @param string $type 資料總類
     * @return mixed
     */
    public static function input($key = null, $type = null) {
        $data = self::$method == 'get' ? self::$uri_data : self::$content_data;
        // 檢查參數
        if ($key !== null && !is_string($key) && !is_array($key)) {
            return null;
        }
        if ($type !== null && !is_string($type)) {
            return null;
        }
        if ($key === null && $type === null) {
            return $data;
        } elseif ($key !== null && $type === null) {
            if (is_string($key)) {
                if (is_array($data) && isset($data[$key])) {
                    return $data[$key];
                } else {
                    $type = $key;
                    $key = null;
                }
            } else {
                $type = self::$method;
            }
        }
        $data = null;
        $type = strtolower($type);
        // 取出對應的資料
        if ($type == 'get') {
            $data = self::$uri_data;
        } elseif ($type == 'file') {
            $data = self::$file_data;
        } elseif ($type == self::$method && in_array($type, self::$allowed_method)) {
            $data = self::$content_data;
        }
        if (is_array($key) && is_array($data)) {
            return self::get_value($data, $key);
        }
        return $key === null || $data === null ? $data : (is_array($data) ? $data[$key] ?? null : null);
    }

    /**
     * 輸出資料至緩衝區
     * @param string $view View 名稱
     * @param mixed $data 資料
     * @param mixed $flag 附加選項
     * @return null|string
     */
    public static function output($view, $data = [], $flag = false) {
        // 防止名稱衝突
        $view_1bda = $view;
        $data_8d77 = $data;
        $flag_327a = $flag;

        // 給如果視圖要使用這兩的變數用
        global $_DEBUG, $_TIME;

        // 檢查旗標
        if ($flag_327a === true) {
            ob_start();
        }
        if (is_int($flag_327a)) {
            http_response_code($flag_327a);
        }

        // 載入語系
        lang_load(self::$config->lang);

        // 選定輸出內容
        $original_view = $view_1bda;
        $view_1bda = trim(str_replace(['\\', '/'], DS, $view_1bda), DS);
        if (!empty(self::$view[$original_view])) {
            // 從設定讀視圖
            header('Content-Type: text/html; charset=utf-8');
            if (is_object($data_8d77)) {
                $data_8d77 = get_object_vars($data_8d77);
            }
            self::$page_data[] = $data_8d77;
            foreach (self::$page_data as $data_8d77) {
                extract($data_8d77);
            }
            eval('?>'.self::$view[$original_view]);
        } elseif ($view_path = self::require_check(SOURCE_DIR.DS.'views'.DS.$view_1bda)) {
            // 從檔案讀視圖
            header('Content-Type: text/html; charset=utf-8');
            if (is_object($data_8d77)) {
                $data_8d77 = get_object_vars($data_8d77);
            }
            self::$page_data[] = $data_8d77;
            foreach (self::$page_data as $data_8d77) {
                extract($data_8d77);
            }
            require $view_path;
        } else {
            if ($scd = str_contains($view_1bda, '.')) {
                header('Content-Disposition: attachment; filename='.rawurlencode(str_replace(['\\', '/'], '_', $view_1bda)));
            }
            // 輸出各種格式
            if (str_ends_with($view_1bda, 'json')) {
                header('Content-Type: application/json');
                echo json_encode($data_8d77, JSON_UNESCAPED_UNICODE);
            } elseif (str_ends_with($view_1bda, 'html') || str_ends_with($view_1bda, 'htm')) {
                header('Content-Type: text/html; charset=utf-8');
                echo $data_8d77 ?: '';
            } elseif ($view_1bda == 'text' || str_ends_with($view_1bda, 'txt')) {
                header('Content-Type: text/plain; charset=utf-8');
                echo $data_8d77 ?: '';
            } elseif (str_ends_with($view_1bda, 'jpeg') || str_ends_with($view_1bda, 'jpg')) {
                header('Content-Type: image/jpeg');
                if (is_string($data_8d77)) {
                    echo $data_8d77 ?: '';
                } elseif (SingleMVC::is_instanceof($data_8d77, ['GdImage', 'gd'])) {
                    imagejpeg($data_8d77);
                }
            } elseif (str_ends_with($view_1bda, 'png')) {
                header('Content-Type: image/png');
                if (is_string($data_8d77)) {
                    echo $data_8d77 ?: '';
                } elseif (SingleMVC::is_instanceof($data_8d77, ['GdImage', 'gd'])) {
                    imagepng($data_8d77);
                }
            } elseif ($scd) {
                header('Content-Type: application/octet-stream');
                echo $data_8d77 ?: '';
            }
        }

        return $flag_327a === true ? ob_get_clean() : null;
    }

    /**
     * 取得或設定 session
     * @param string|string[] $key 索引
     * @param mixed $value 數值
     * @return mixed
     */
    public static function session($key, $value = null) {
        $result = null;
        $args = func_get_args();
        if (count($args) == 1) {
            // 取出資料
            $result = self::get_value($_SESSION, $args[0]);
        } else {
            // 存入資料
            session_status() !== PHP_SESSION_ACTIVE && session_start();
            $value = $args[1] instanceof \Closure ? $args[1](session($key)) : $args[1];
            self::set_value($_SESSION, $args[0], $value);
            $result = session_write_close();
        }
        return $result;
    }

    /**
     * 取得或設定 cookie
     * @param string|string[] $key 索引
     * @param mixed $value 數值
     * @param int|array $expires 逾時時間/選項
     * @param string $path 路徑
     * @param string $domain 網域
     * @param bool $secure 需使用加密連線
     * @param bool $httponly 限制HTTP存取
     * @return mixed
     */
    public static function cookie($key, $value = null, $expires = 0, $path = '', $domain = '', $secure = false, $httponly = false) {
        $result = null;
        $args = func_get_args();
        if (count($args) == 1) {
            // 取出資料
            $result = self::get_value($_COOKIE, $args[0]);
        } else {
            // 存入資料
            $value = $args[1] instanceof \Closure ? $args[1](cookie($key)) : $args[1];
            self::set_value($_COOKIE, $args[0], $value);
            $temp = [];
            self::set_value($temp, $args[0], '');
            $name = substr(urldecode(http_build_query($temp)), 0, -1);
            $result = is_array($expires) ? setcookie($name, $value, $expires) : setcookie($name, $value, $expires, $path, $domain, $secure, $httponly);
        }
        return $result;
    }

    /**
     * 取得語系內容
     * @param string|string[] $key 索引
     * @return string|array
     */
    public static function lang($key = '') {
        $result = '{MISSING}';

        // 讀取語系
        if (empty(self::$loaded_lang)) {
            lang_load(self::$config->lang);
        }
        if (is_string($key)) {
            $key = [$key];
        }

        // 取出指定的值
        if (is_array($key) && !empty(self::$lang)) {
            $value = self::get_value(self::$lang, $key);
            if (is_string($value) || is_array($value)) {
                $result = $value;
            }
        }
        return $result;
    }

    /**
     * 載入指定的語系
     * @param string $lang 語系名稱
     * @param string $now 目前的語系名稱
     * @return bool
     */
    public static function lang_load($lang = '', &$now = null) {
        static $original_lang = null;
        if (!$original_lang) {
            $original_lang = self::$lang;
        }
        if (!self::$loaded_lang && !empty(self::$lang[$lang])) {
            // 讀取設定的語系
            self::$loaded_lang = $lang;
            self::$lang = $original_lang[$lang];
            define('LANG', $lang);
        } elseif (!self::$loaded_lang && self::require(SOURCE_DIR.DS.'lang'.DS.$lang)) {
            // 讀取檔案的語系
            self::$loaded_lang = $lang;
            if (count(self::$lang) == 1 && isset(self::$lang[$lang])) {
                self::$lang = self::$lang[$lang];
            }
            define('LANG', $lang);
        }
        return ($now = self::$loaded_lang) == $lang;
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
        $result = [
            'status'  => -1,
            'message' => '',
            'log'     => '',
        ];
        $dir = sys_get_temp_dir();
        $file = $dir.DS.'composer.phar';
        // 下載 composer
        if (!file_put_contents($file, fopen('https://getcomposer.org/composer.phar', 'r'))) {
            $result['status'] = -1;
            $result['message'] = 'Unabled to download composer.';
        } elseif (!($phar = new Phar($file)) || !$phar->extractTo($extrac_dir = $dir.DS.'composer', null, true)) {
            $result['status'] = -2;
            $result['message'] = 'Unabled to setup composer.';
        } elseif (!self::require($extrac_dir.DS.'/vendor/autoload.php') || !putenv('COMPOSER_HOME='.$extrac_dir) || !chdir(ROOT)) {
            $result['status'] = -3;
            $result['message'] = 'Unabled to setup composer.';
        } else {
            // 安裝相依性
            try {
                $input = new Symfony\Component\Console\Input\ArrayInput(['command' => 'update']);
                $output = new Symfony\Component\Console\Output\BufferedOutput();
                $application = new Composer\Console\Application();
                $application->setAutoExit(false);
                $result['status'] = $application->run($input, $output);
                $result['log'] = $output->fetch();
            } catch (Exception $ex) {
                $result['status'] = -4;
                $result['message'] = $ex->getMessage();
                $result['log'] = $ex->getTraceAsString();
            }
        }
        return $details ? $result : $result['status'] == 0;
    }

    /**
     * 檢查 SingleMVC 更新
     * @param bool $details 是否取得詳細資料
     * @return bool|array
     */
    public static function check_for_updates($details = false) {
        !self::$config->auto_update && clearstatcache();
        $source = file_get_contents('https://raw.githubusercontent.com/kouji6309/SingleMVC/master/SingleMVC.php');
        $temp = [];
        if (preg_match('([\d]\.[\d\.]*[\d])', $source, $temp)) {
            $result = version_compare(VERSION, $temp[0]);
            self::$config->auto_update && $result < 0 && file_put_contents(__FRAMEWORK__, $source);
            return !$details ? $result < 0 : [
                'result'  => $result,
                'online'  => $temp[0],
                'current' => VERSION,
                'file'    => $source,
            ];
        } else {
            return !$details ? false : [
                'result'  => 0,
                'online'  => 'unknow',
                'current' => VERSION,
                'file'    => null,
            ];
        }
    }
}

/** 設定 */
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

    /**
     * 取得或設定 是否自動更新框架
     * @var bool
     */
    public $auto_update = false;
}

/** 控制器基底 */
abstract class Controller {
    public function __construct() {
    }
}

/** 模組基底 */
abstract class Model {
    public function __construct() {
    }

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
    protected $db_pdo = null;

    /**
     * 取得或設定 最後的 PDO 敘述
     * @var PDOStatement
     */
    protected $db_statement = null;

    private static $db_pdo_index = [];
    private static $db_pdo_list = [];

    /**
     * 連線 SQL 資料庫
     * @param array $config PDO 連線參數
     * @return bool
     */
    protected function db_connect($config = null) {
        try {
            if ($this->db_pdo == null) {
                if (empty($config['dsn'])) {
                    $config = SingleMVC::$config->db;
                    if (empty($config['dsn'])) {
                        // 處理舊版設定
                        // trigger_error('Deprecated: The database config structure is deprecated', E_USER_DEPRECATED);
                        $config = [
                            'dsn'      => 'mysql:host='.($config['host'] ?? 'localhost').(!empty($config['name']) ? ';dbname='.$config['name'] : '').';charset=utf8mb4',
                            'username' => $config['username'] ?? 'root',
                            'password' => $config['password'] ?? '',
                            'options'  => [PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4', PDO::ATTR_EMULATE_PREPARES => false],
                        ];
                    }
                }

                // 同設定只建立一次連線
                $index = array_search($config, self::$db_pdo_index);
                if ($index === false) {
                    $index = count(self::$db_pdo_list);
                    self::$db_pdo_list[] = new PDO($config['dsn'], $config['username'], $config['password'], $config['options']);
                    self::$db_pdo_index[] = $config;
                }
                $this->db_pdo = &self::$db_pdo_list[$index];
            }
        } catch (Exception $ex) {
        }
        return $this->db_pdo != null;
    }

    /**
     * 執行 SQL 指令
     * @param string $statement SQL 指令
     * @return PDOStatement|bool
     */
    protected function db_query($statement) {
        if ($this->db_connect()) {
            return $this->db_statement = $this->db_pdo->query($statement);
        }
        return false;
    }

    /**
     * 準備 SQL 指令
     * @param string $statement SQL 樣板
     * @return PDOStatement|bool
     */
    protected function db_prepare($statement) {
        if ($this->db_connect()) {
            return $this->db_statement = $this->db_pdo->prepare($statement);
        }
        return false;
    }

    /**
     * 插入資料
     * @return string|int|bool 最後新增的編號 或 新增列數
     */
    protected function db_insert() {
        if (($s = $this->db_statement) && $s->execute()) {
            $count = $s->rowCount();
            $last_id = $this->db_pdo->lastInsertId();
            if ($count == 1) {
                return $last_id ?: $count;
            } else {
                return $count;
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
        $statement = $this->db_statement;
        if ($statement && $statement->execute()) {
            $datas = $statement->fetchAll(PDO::FETCH_ASSOC);
            if ($datas !== false) {
                return (count($datas) == 1) && !$force_array ? $datas[0] : $datas;
            }
        }
        return false;
    }

    /**
     * 更新資料
     * @return int|bool 異動的列數
     */
    protected function db_update() {
        $statement = $this->db_statement;
        if ($statement && $statement->execute()) {
            return $statement->rowCount();
        }
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
        if ($statement = $this->db_statement) {
            $result = true;
            if (is_array($parameter)) {
                // 擴充 bind 可接受鍵值物件
                foreach ($parameter as $key => $val) {
                    if (is_int($key)) {
                        $key += 1;
                    }
                    if (is_array($val)) {
                        if (count($val) == 1) {
                            $result &= $statement->bindValue($key, $val[0]);
                        } elseif (count($val) == 2) {
                            $result &= $statement->bindValue($key, $val[0], $val[1]);
                        }
                    } elseif (is_int($val)) {
                        $result &= $statement->bindValue($key, $val, PDO::PARAM_INT);
                    } elseif (is_bool($val)) {
                        $result &= $statement->bindValue($key, $val, PDO::PARAM_BOOL);
                    } elseif (is_null($val)) {
                        $result &= $statement->bindValue($key, $val, PDO::PARAM_NULL);
                    } elseif (is_resource($val)) {
                        $result &= $statement->bindValue($key, $val, PDO::PARAM_LOB);
                    } else {
                        $result &= $statement->bindValue($key, $val, PDO::PARAM_STR);
                    }
                }
                return $result;
            } else {
                return $statement->bindValue($parameter, $value, $type);
            }
        }
        return false;
    }

    /**
     * 開始交易
     * @return bool
     */
    protected function db_begin() {
        if ($this->db_connect()) {
            return $this->db_pdo->beginTransaction();
        }
        return false;
    }

    /**
     * 提交交易
     * @return bool
     */
    protected function db_commit() {
        if ($this->db_connect()) {
            return $this->db_pdo->commit();
        }
        return false;
    }

    /**
     * 復原交易
     * @return bool
     */
    protected function db_rollBack() {
        if ($this->db_connect()) {
            return $this->db_pdo->rollBack();
        }
        return false;
    }

    /**
     * 取得除錯資訊
     * @return bool|string
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
     * @param array $options 選項
     * @param bool $get_header 是否傳回 Header
     * @return string|array|false
     */
    protected static function request($url, $method = 'get', $data = [], $options = [], $get_header = false) {
        $chs = self::request_async($url, $method, $data, $options);
        return self::request_run($chs, 0, -1, $get_header);
    }

    /**
     * 建立一個非同步請求
     * @param string $url 請求路徑
     * @param string $method 請求方法
     * @param mixed $data 資料
     * @param array $options 選項
     * @return CurlHandle|false
     */
    protected static function request_async($url, $method = 'get', $data = [], $options = []) {
        $handler = curl_init();
        $method = strtoupper($method);
        if (!$handler || !$url || !$method) {
            return false;
        }
        if (!curl_setopt($handler, CURLOPT_CUSTOMREQUEST, $method)) {
            return false;
        }
        if (!empty($options['Option']) && is_array($options['Option'])) {
            if (!curl_setopt_array($handler, $options['Option'])) {
                return false;
            }
        }
        if ($method == 'GET') {
            // 處理 URI
            if (!curl_setopt($handler, CURLOPT_URL, $url.(!str_contains($url, '?') ? '?' : '&').http_build_query($data ?: []))) {
                return false;
            }
        } else {
            // 處理 URI
            if (is_array($data) && isset($data['_GET'])) {
                if (!curl_setopt($handler, CURLOPT_URL, $url.(!str_contains($url, '?') ? '?' : '&').http_build_query($data['_GET']))) {
                    return false;
                }
                unset($data['_GET']);
            } else {
                if (!curl_setopt($handler, CURLOPT_URL, $url)) {
                    return false;
                }
            }
            if (!curl_setopt($handler, CURLOPT_POST, true)) {
                return false;
            }
            // 處理內容資料
            $is_array = is_array($data);
            $content_type = 'application/octet-stream';
            $has_content_type = false;
            if (!empty($options['Header']['Content-Type']) && is_string($options['Header']['Content-Type'])) {
                $has_content_type = true;
                $content_type = $options['Header']['Content-Type'];
            }
            if (($is_array && !$has_content_type) || ($is_array && str_starts_with($content_type, 'application/x-www-form-urlencoded'))) {
                if (!curl_setopt($handler, CURLOPT_POSTFIELDS, http_build_query($data))) {
                    return false;
                }
            } elseif ($is_array && $has_content_type && str_starts_with($content_type, 'multipart/form-data')) {
                if (!curl_setopt($handler, CURLOPT_POSTFIELDS, $data)) {
                    return false;
                }
            } elseif ($has_content_type && str_starts_with($content_type, 'application/json')) {
                if (!curl_setopt($handler, CURLOPT_POSTFIELDS, json_encode($data))) {
                    return false;
                }
            } elseif (is_string($data)) {
                if (!$has_content_type) {
                    $options['Header']['Content-Type'] = 'text/plain';
                }
                if (!curl_setopt($handler, CURLOPT_POSTFIELDS, $data)) {
                    return false;
                }
            }
        }
        // 設定各種欄位
        if (!empty($options['Header']) && is_array($options['Header'])) {
            $headers = [];
            foreach ($options['Header'] as $key => $val) {
                $headers[] = $key.': '.$val;
            }
            if (!curl_setopt($handler, CURLOPT_HTTPHEADER, $headers)) {
                return false;
            }
        }
        if (!empty($options['User-Agent']) && is_string($options['User-Agent'])) {
            if (!curl_setopt($handler, CURLOPT_USERAGENT, $options['User-Agent'])) {
                return false;
            }
        }
        if (!empty($options['Cookie']) && is_string($options['Cookie'])) {
            if (!curl_setopt($handler, CURLOPT_COOKIE, $options['Cookie'])) {
                return false;
            }
        } elseif (defined('COOKIE_DIR')) {
            $path = rtrim(COOKIE_DIR, '/\\').DS.($options['Cookie-File'] ?? 'cookie').'.tmp';
            if (!curl_setopt($handler, CURLOPT_COOKIEJAR, $path)) {
                return false;
            }
            if (!curl_setopt($handler, CURLOPT_COOKIEFILE, $path)) {
                return false;
            }
        }
        if (isset($options['SSL-Verify']) && is_bool($options['SSL-Verify'])) {
            if (!curl_setopt($handler, CURLOPT_SSL_VERIFYPEER, $options['SSL-Verify'] * 1)) {
                return false;
            }
        }
        if (!empty($options['Proxy']) && is_string($options['Proxy'])) {
            if (!curl_setopt($handler, CURLOPT_PROXY, $options['Proxy'])) {
                return false;
            }
        }
        if (isset($options['HTTP-Version']) && is_int($options['HTTP-Version'])) {
            if (!curl_setopt($handler, CURLOPT_HTTP_VERSION, $options['HTTP-Version'])) {
                return false;
            }
        }
        if (!curl_setopt($handler, CURLOPT_RETURNTRANSFER, true)) {
            return false;
        }
        return $handler;
    }

    /**
     * 執行一個或多個非同步請求
     * @param mixed $handlers 請求物件
     * @param int $start 開始索引
     * @param int $length 長度
     * @param bool $get_header 是否傳回 Header
     * @return string|array|false
     */
    protected static function request_run($handlers, $start = 0, $length = -1, $get_header = false) {
        $is_one = false;
        $handlers_types = ['CurlHandle', 'curl'];
        if (SingleMVC::is_instanceof($handlers, $handlers_types)) {
            $handlers = [$handlers];
            $start = 0;
            $length = -1;
            $is_one = true;
        } elseif (gettype($handlers) != 'array') {
            return false;
        }

        // 檢查執行範圍
        if ($start < 0 || $start > count($handlers)) {
            $start = 0;
        }
        if ($length < 0 || ($start + $length) > count($handlers)) {
            $length = count($handlers) - $start;
        }
        $end = $start + $length;
        $is_named = false;
        if (!SingleMVC::is_instanceof($handlers[$start], $handlers_types)) {
            if (SingleMVC::is_instanceof($handlers[$start]['request'], $handlers_types)) {
                $is_named = true;
            } else {
                return [];
            }
        }

        // 執行請求
        $multi_handler = curl_multi_init();
        for ($i = $start; $i < $end; $i++) {
            curl_setopt($is_named ? $handlers[$i]['request'] : $handlers[$i], CURLOPT_HEADER, $get_header);
            curl_multi_add_handle($multi_handler, $is_named ? $handlers[$i]['request'] : $handlers[$i]);
        }
        $is_active = null;
        do {
            curl_multi_exec($multi_handler, $is_active);
            curl_multi_select($multi_handler);
        } while ($is_active > 0);
        for ($i = $start; $i < $end; $i++) {
            curl_multi_remove_handle($multi_handler, $is_named ? $handlers[$i]['request'] : $handlers[$i]);
        }
        curl_multi_close($multi_handler);

        // 處理回應
        $result = [];
        for ($i = $start; $i < $end; $i++) {
            if ($is_named) {
                $t = curl_multi_getcontent($handlers[$i]['request']);
                $result[] = array_merge($handlers[$i], $get_header ? self::request_parse($t) : ['content' => $t]);
                curl_close($handlers[$i]['request']);
            } else {
                $t = curl_multi_getcontent($handlers[$i]);
                $result[] = $get_header ? self::request_parse($t) : $t;
                curl_close($handlers[$i]);
            }
        }
        return $is_one ? $result[0] : $result;
    }

    /**
     * 解析請求的回應
     * @param string $response 回應資料
     * @return array
     */
    protected static function request_parse($response) {
        if (empty($response)) {
            return [
                'header'  => [],
                'content' => '',
            ];
        }
        list($header, $content) = explode("\r\n\r\n", $response, 2);
        if (stripos($header, "200 Connection established\r\n") !== false) {
            // 處理使用 Proxy 會多的標頭
            list(, $header, $content) = explode("\r\n\r\n", $response, 3);
        }
        $headers = explode("\r\n", preg_replace('/\x0D\x0A[\x09\x20]+/', ' ', $header));
        $header_result = ['Status' => intval(explode(' ', array_shift($headers))[1] ?? 0)];

        // 解析回應標頭
        foreach ($headers as $header) {
            if (preg_match('/([^:]+): (.+)/m', $header, $split_header)) {
                $split_header[1] = preg_replace_callback('/(?<=^|[\x09\x20\x2D])./', function ($r) {
                    return strtoupper($r[0]);
                }, strtolower(trim($split_header[1])));
                if (isset($header_result[$split_header[1]])) {
                    $header_result[$split_header[1]] = [$header_result[$split_header[1]], $split_header[2]];
                } else {
                    $header_result[$split_header[1]] = trim($split_header[2]);
                }
            }
        }
        return ['header' => $header_result, 'content' => $content];
    }
}

/**
 * 提供 Composer 更新用控制器
 */
class BCBA235AA0401FD10464DF6AFBFAAB77 extends Controller {
    public function __construct() {
        if (self::check()) {
            exit(header_404());
        }
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

/** 設定 HTTP/1.1 404 的狀態碼 */
function header_404() {
    http_response_code(404);
}

/**
 * 取得輸入的資料
 * @param string|string[] $key 索引名稱
 * @param string $type 資料總類
 * @return mixed
 */
function input($key = null, $type = null) {
    return SingleMVC::input($key, $type);
}

/**
 * 輸出資料至緩衝區
 * @param string $view View 名稱
 * @param mixed $data 資料
 * @param mixed $flag 附加選項
 * @return null|string
 */
function output($view, $data = [], $flag = false) {
    return SingleMVC::output($view, $data, $flag);
}

/**
 * 取得或設定 Session
 * @param string|string[] $key 索引
 * @param mixed $value 數值
 * @return mixed
 */
function session($key, $value = null) {
    return call_user_func_array('SingleMVC::session', func_get_args());
}

/**
 * 取得或設定 Cookie
 * @param string|string[] $key 索引
 * @param mixed $value 數值
 * @param int|array $expires 逾時時間/選項
 * @param string $path 路徑
 * @param string $domain 網域
 * @param bool $secure 需使用加密連線
 * @param bool $httponly 限制HTTP存取
 * @return mixed
 */
function cookie($key, $value = null, $expires = 0, $path = '', $domain = '', $secure = false, $httponly = false) {
    return call_user_func_array('SingleMVC::cookie', func_get_args());
}

/**
 * 取得語系內容
 * @param string|string[] $key 索引
 * @return string|array
 */
function lang($key = '') {
    return SingleMVC::lang($key);
}

/**
 * 載入指定的語系
 * @param string $lang 語系名稱
 * @param string $now 目前的語系名稱
 * @return bool
 */
function lang_load($lang = '', &$now = null) {
    return SingleMVC::lang_load($lang, $now);
}

if (version_compare(PHP_VERSION, '8.0', '<')) {
    /**
     * 檢查字串是否包含特定字串
     * @param string $haystack 字串
     * @param string $needle 特定字串
     * @return bool
     */
    function str_contains($haystack, $needle) {
        return strpos($haystack, $needle) !== false;
    }

    /**
     * 檢查字串是否以特定字串開頭
     * @param string $haystack 字串
     * @param string $needle 特定字串
     * @return bool
     */
    function str_starts_with($haystack, $needle) {
        return substr($haystack, 0, strlen($needle)) === $needle;
    }

    /**
     * 檢查字串是否以特定字串結尾
     * @param string $haystack 字串
     * @param string $needle 特定字串
     * @return bool
     */
    function str_ends_with($haystack, $needle) {
        return !($l = strlen($needle)) || (substr($haystack, -$l) === $needle);
    }
}

/**
 * 檢查字串是否以特定字串開頭
 * @param string $haystack 字串
 * @param string $needle 特定字串
 * @return bool
 */
function starts_with($haystack, $needle) {
    trigger_error('Deprecated: The starts_with() function is deprecated', E_USER_DEPRECATED);
    return str_starts_with($haystack, $needle);
}

/**
 * 檢查字串是否以特定字串結尾
 * @param string $haystack 字串
 * @param string $needle 特定字串
 * @return bool
 */
function ends_with($haystack, $needle) {
    trigger_error('Deprecated: The ends_with() function is deprecated', E_USER_DEPRECATED);
    return str_ends_with($haystack, $needle);
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
    list($us, $sec) = explode(' ', microtime());
    $t = date('H:i:s', $sec * 1).'.'.sprintf('%03d', floor($us * 1000));
    if (is_string($msg)) {
        $data = $t."\t".$msg;
    } else {
        ob_start();
        var_dump($msg);
        $data = $t."\tdata:\n".ob_get_clean();
    }
    if (defined('DEBUG_DIR')) {
        $file = rtrim(DEBUG_DIR, '/\\').DS.'debug.log';
        if (file_exists($file) && empty($_DEBUG)) {
            unlink($file);
        }
        file_put_contents($file, $data."\n", FILE_APPEND);
    }
    return ($_DEBUG[] = $data)."\n";
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
    global $_TIME;
    $time = microtime(true);
    if (!empty($_TIME['block'][$tag])) {
        if ($_TIME['block'][$tag]['start'] === false) {
            $_TIME['block'][$tag]['start'] = $time;
        } else {
            $_TIME['block'][$tag]['count']++;
            $_TIME['block'][$tag]['time'] += $time - $_TIME['block'][$tag]['start'];
            $_TIME['block'][$tag]['start'] = false;
        }
    } elseif (!empty($_TIME['total'][$tag])) {
        $_TIME['block'][$tag] = ['start' => false, 'count' => 1, 'time' => $time - $_TIME['total'][$tag]['time']];
        unset($_TIME['total'][$tag]);
    } else {
        if (empty($_TIME['total'])) {
            $_TIME['total'][$tag ?: 'start'] = ['time' => $time, 'splits' => 0, 'laps' => 0];
        } else {
            $dt_s = $time - reset($_TIME['total'])['time'];
            $dt_l = $time - end($_TIME['total'])['time'];
            $_TIME['total'][$tag] = ['time' => $time, 'splits' => $dt_s, 'laps' => $dt_l];
        }
    }
}

/**
 * 格式化輸出儲存於 $_TIME 中的時間紀錄
 * @param array $format 輸出格式
 * $format = [
 *     'total' => ['head' => '...', 'body' => '...', 'foot' => '...'],
 *     'block' => ['head' => '...', 'body' => '...', 'foot' => '...']
 * ]
 * @return string
 */
function stopwatch_format($format = []) {
    global $_TIME;
    $result = $format['total']['head'] ?? "Tag\tTime\tSplits\tLaps\n";
    foreach ($_TIME['total'] ?? [] as $tag => $data) {
        $result .= sprintf($format['total']['body'] ?? "%s\t%.3f\t%.3f\t%.3f\n", $tag, $data['time'], $data['splits'], $data['laps']);
    }
    $result .= ($format['total']['foot'] ?? "\n").($format['block']['head'] ?? "Tag\tCount\tTime\n");
    foreach ($_TIME['block'] ?? [] as $tag => $data) {
        $result .= sprintf($format['block']['body'] ?? "%s\t%.3f\t%.3f\n", $tag, $data['count'], $data['time']);
    }
    return $result.($format['block']['foot'] ?? "\n");
}

/**
 * 檢查 SingleMVC 更新
 * @param bool $details 是否取得詳細資料
 * @return bool|array
 */
function check_for_updates($details = false) {
    return SingleMVC::check_for_updates($details);
}

/**
 * JSON Web Token 編碼
 * @param mixed $data 資料
 * @param string $secret 密鑰
 * @return string
 */
function jwt_encode($data, $secret) {
    $header = str_replace('=', '', base64_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT'])));
    $payload = str_replace('=', '', base64_encode(json_encode($data)));
    $signature = str_replace('=', '', base64_encode(hash_hmac('sha256', $header.'.'.$payload, $secret, true)));
    return $header.'.'.$payload.'.'.$signature;
}

/**
 * JSON Web Token 解碼
 * @param string $token TOKEN內容
 * @param string $secret 密鑰
 * @return mixed
 */
function jwt_decode($token, $secret) {
    if (!is_string($token) || !is_string($secret)) {
        return false;
    }
    $token = explode('.', $token);
    if (count($token) != 3) {
        return false;
    }
    list($header, $payload, $signature) = $token;
    $decoded_header = json_decode(base64_decode($header), true);
    if (!$decoded_header) {
        return false;
    }
    $decoded_signature = base64_decode($signature);
    $signature = hash_hmac('sha'.substr($decoded_header['alg'] ?? 'HS256', 2, 3), $header.'.'.$payload, $secret, true);
    if ($decoded_signature === $signature) {
        return json_decode(base64_decode($payload), true);
    }
    return false;
}

!defined('DS') && define('DS', DIRECTORY_SEPARATOR);
!defined('ROOT') && define('ROOT', str_replace('/', DS, dirname($_SERVER['SCRIPT_FILENAME'])));
!defined('SOURCE_DIR') && define('SOURCE_DIR', rtrim(ROOT, "/\\").DS.'source');
!defined('__FRAMEWORK__') && define('__FRAMEWORK__', __FILE__);
!defined('PAUSE') && register_shutdown_function(function () {
    new SingleMVC();
    exit();
});
SingleMVC::$config = new FrameworkConfig();
SingleMVC::autoload_register();
#endregion
