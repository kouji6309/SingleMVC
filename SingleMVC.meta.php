<?php
define('ROOT', '');
define('VROOT', '');
define('DS', '');
define('SOURCE_DIR', '');
define('VERSION', '1.6.13');
class SingleMVC {
    /**
     * ���o�γ]�w�{���պA
     * @var Config
     */
    public static $config = null;

    /**
     * ���o�γ]�w�y�t���
     * @var array
     */
    public static $lang = [];

    /**
     * ���o�γ]�w View ���e(�歶��)
     * @var array
     */
    public static $view = [];

    /**
     * ���� SingleMVC ��Ҩð���
     */
    public function __construct() { }

    /**
     * ���J�ɮ�
     * @param string $file �ɮ׸��|
     * @return string|boolean
     */
    public static function require($file) { }

    /**
     * �ˬd�ɮ׬O�_�i�H���J
     * @param string $file �ɮ׸��|
     * @return string|boolean
     */
    public static function require_check($file) { return ''; }

    /**
     * ���o��J�����
     * @param mixed $key ���ަW��
     * @param string $type ����`��
     * @return mixed
     */
    public static function input($key = null, $type = null) { return ""; }

    /**
     * ��X View
     * @param string $view View �W��
     * @param mixed $data ���
     * @param mixed $flag ���[�ﶵ
     * @return null|string
     */
    public static function output($view, $data = [], $flag = false) { return ""; }

    /**
     * ���o�y�t���e
     * @param string|array $key ����
     * @return string|array
     */
    public static function lang($key = '') { return ""; }

    /**
     * Ū���y�t
     * @param string $lang �y�t�W��
     * @param string $now �ثe���y�t�W��
     * @return boolean
     */
    public static function lang_load($lang = '', &$now = null) { return true; }
}

class Config {
    /**
     * ���o�γ]�w ����
     * @var array
     */
    public $routes = [];

    /**
     * ���o�γ]�w ��Ʈw�]�w
     * @var array
     */
    public $db = [];

    /**
     * ���o�γ]�w �w�]�y��
     * @var string
     */
    public $lang = null;
}

/**
 * ��@�۰ʸ��J
 */
abstract class AutoLoader { }

/**
 * Controller �����O
 */
abstract class Controller extends AutoLoader { }

/**
 * Model �����O
 */
abstract class Model extends AutoLoader {
    /**
     * �إ߱K�X�������
     * @param string $password ��J�K�X
     * @return string
     */
    protected static function password_hash($password) { return ""; }

    /**
     * ���ұK�X�P�����
     * @param string $password ��J�K�X
     * @param string $hash �w�[�K���K�X
     * @return boolean
     */
    protected static function password_verify($password, $hash) { return true; }

    /**
     * ���o�γ]�w PDO ����
     * @var PDO
     */
    protected static $db_pdo = null;

    /**
     * ���o�γ]�w �̫᪺ PDO �ԭz
     * @var PDOStatement
     */
    protected $db_statement = null;

    /**
     * �s�u SQL ��Ʈw
     * @return boolean
     */
    protected function db_connect() { }

    /**
     * ���� SQL ���O
     * @param string $statement SQL ���O
     * @return PDOStatement
     */
    protected function db_query($statement) { }

    /**
     * �ǳ� SQL ���O
     * @param string $statement SQL �˪O
     * @return PDOStatement
     */
    protected function db_prepare($statement) { }

    /**
     * ���J���
     * @return string �̫�s�W���s��
     */
    protected function db_insert() { return ""; }

    /**
     * ���o���
     * @param boolean $force_array �浧��Ƥ��Ǧ^�}�C
     * @return array
     */
    protected function db_select($force_array = false) { return []; }

    /**
     * ��s���
     * @return int ���ʪ��C��
     */
    protected function db_update() { }

    /**
     * �j�w�ƭ�
     * @param int|string|array $parameter �W��/�Ѽ�
     * @param mixed $value �ƭ�
     * @param int $type ���O
     * @return boolean
     */
    protected function db_bind($parameter, $value = '', $type = PDO::PARAM_STR) { return true; }

    /**
     * �}�l���
     * @return boolean
     */
    protected function db_begin() { return true; }

    /**
     * ������
     * @return boolean
     */
    protected function db_commit() { return true; }

    /**
     * �_����
     * @return boolean
     */
    protected function db_rollBack() { return true; }

    /**
     * ���o������T
     * @return boolean|string
     */
    protected function db_debug() { return true; }

    /**
     * �إߨð���@�ӽШD
     * @param string $url �ШD���|
     * @param string $method �ШD��k
     * @param mixed $data ���
     * @param array $option �ﶵ
     * @param boolean $get_header �O�_�Ǧ^ Header
     * @return mixed
     */
    protected static function request($url, $method = 'get', $data = [], $option = [], $get_header = false) { return ""; }

    /**
     * �إߤ@�ӫD�P�B�ШD
     * @param string $url �ШD���|
     * @param string $method �ШD��k
     * @param mixed $data ���
     * @param array $option �ﶵ
     * @return resource
     */
    protected static function request_async($url, $method = 'get', $data = [], $option = []) { return null; }

    /**
     * ����h�ӫD�P�B�ШD
     * @param array $rs �ШD����
     * @param int $start �}�l����
     * @param int $length ����
     * @param boolean $get_header �O�_�Ǧ^ Header
     * @return array
     */
    protected static function request_run(&$rs = [], $start = 0, $length = -1, $get_header = false) { return []; }
}

/**
 * �]�w���A 404
 */
function header_404() { }

/**
 * ���o��J�����
 * @param mixed $key ���ަW��
 * @param string $type ����`��
 * @return mixed
 */
function input($key = null, $type = null) { return ""; }

/**
 * ��X View
 * @param string $view View �W��
 * @param mixed $data ���
 * @param mixed $flag ���[�ﶵ
 * @return null|string
 */
function output($view, $data = [], $flag = false) { return ""; }

/**
 * ���o�y�t���e
 * @param string|array $key ����
 * @return string|array
 */
function lang($key = '') { return ""; }

/**
 * Ū���y�t
 * @param string $lang �y�t�W��
 * @param string $now �ثe���y�t�W��
 * @return boolean
 */
function lang_load($lang = '', &$now = null) { return true; }

/**
 * �ˬd�r��O�_�H�S�w�r��}�Y
 * @param string $haystack �r��
 * @param string $needle �S�w�r��
 * @return boolean
 */
function starts_with($haystack, $needle) { return true; }

/**
 * �ˬd�r��O�_�H�S�w�r�굲��
 * @param string $haystack �r��
 * @param string $needle �S�w�r��
 * @return boolean
 */
function ends_with($haystack, $needle) { return true; }

/**
 * ������T
 */
$_DEBUG = [];

/**
 * �O��������T
 * @param mixed $msg �T��
 * @return string
 */
function debug($msg = '') { return ""; }

/**
 * �ɦL���
 * @param mixed $data ��Ƥ��e
 * @param mixed $vars ��h��Ƥ��e
 */
function dump($data, ...$vars) { }

/**
 * �ɶ�����
 */
$_TIME = ['total' => [], 'block' => []];

/**
 * ��������ɶ�
 * @param string $tag ���O
 */
function stopwatch($tag = '') { }

/**
 * �̮榡��X�ɶ�
 * @param array $format ��X�榡
 * $format = [
 *     'total' => ['head' => '...', 'body' => '...', 'foot' => '...'],
 *     'block' => ['head' => '...', 'body' => '...', 'foot' => '...']
 * ]
 * @return string
 */
function stopwatch_format($format = []) { return ""; }

/**
 * �ˬd�O�_���s���ج[
 * @param boolean $details �O�_���o�ԲӸ��
 * @return int|array
 */
function check_for_updates($details = false) { return []; }

/**
 * JWT �s�X
 * @param mixed $data ���
 * @param string $secret �K�_
 * @return string
 */
function jwt_encode($data, $secret) { return ""; }

/**
 * JWT �ѽX�P����
 * @param string $token TOKEN���e
 * @param string $secret �K�_
 * @return mixed
 */
function jwt_decode($token, $secret) { return []; }