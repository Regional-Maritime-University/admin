<?php
require_once 'vendor/autoload.php';

use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Make environment variables available to getenv()
foreach ($_ENV as $key => $value) {
    putenv("$key=$value");
}

define("ROOT_DIR", dirname(__FILE__));
define("UPLOAD_DIR", ROOT_DIR . "/uploads/");
define("VENDOR_AUTO_PATH", "vendor" . DIRECTORY_SEPARATOR . "dompdf");

// Define project-wide constants
define('PROJECT_NAME', 'Admissions Dashboard');
define('PROJECT_VERSION', '1.0.0');

// In a bootstrap.php or config file
define('ROOT_PATH', str_replace('\\', '/', realpath(__DIR__)));
define('INC_PATH', ROOT_PATH . '/inc');

// Paths
define('ASSETS_PATH', ROOT_PATH . '/assets');
define('VENDOR_PATH', ASSETS_PATH . '/vendor');
define('CSS_PATH', ASSETS_PATH . '/css');
define('JS_PATH', ROOT_PATH . '/js');

// Add this after your other defines
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'];
$baseDir = '/rmu/admin'; // Change this according to your setup
define('BASE_URL', $baseDir);

// Add this to bootstrap.php
function url($path)
{
    return BASE_URL . '/' . ltrim($path, '/');
}
