<?php
define('DS', DIRECTORY_SEPARATOR);
define('ROOT', dirname(__DIR__));
define('PAUSE', true);

if (file_exists($f = ROOT.DS.'index.php')) {
    require_once($f);
} elseif (file_exists($f = ROOT.DS.'SingleMVC.php')) {
    require_once($f);
}