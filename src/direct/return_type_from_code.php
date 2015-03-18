<?php
use Mouf\MoufManager;
use Mouf\CodeValidatorService;
use Mouf\MoufUtils;
use PhpParser\Error;

/**
 * The proxy server.
 * Executes a passed method of an instance and returns the result.
 * The user must be logged in Mouf to be able to run this script.
 */

// Disable output buffering
while (ob_get_level() != 0) {
    ob_end_clean();
}

ini_set('display_errors', 1);
// Add E_ERROR to error reporting if it is not already set
error_reporting(E_ERROR | error_reporting());

if (!isset($_REQUEST["selfedit"]) || $_REQUEST["selfedit"] != "true") {
    //define('ROOT_URL', $_SERVER['BASE']."/../../../");

    require_once '../../../../../mouf/Mouf.php';
    $mouf_base_path = ROOT_PATH;
    $selfEdit = false;
} else {
    //define('ROOT_URL', $_SERVER['BASE']."/");

    require_once '../../mouf/Mouf.php';
    $mouf_base_path = ROOT_PATH."mouf/";
    $selfEdit = true;
}

// Note: checking rights is done after loading the required files because we need to open the session
// and only after can we check if it was not loaded before loading it ourselves...
MoufUtils::checkRights();

$encode = "php";
if (isset($_REQUEST["encode"]) && $_REQUEST["encode"] = "json") {
    $encode = "json";
}

$code = $_REQUEST["code"];
if (get_magic_quotes_gpc() == 1) {
    if (isset($_REQUEST["code"])) {
        $code = stripslashes($code);
    }
}

// Let's execute the code and get the return value.
$fullCode = 'return function($container) { '.$code;
$fullCode .= "\n};";

try {
    CodeValidatorService::validateCode($fullCode);
} catch (Error $e) {
    echo $e->getMessage();
    exit;
}

class CodeTester
{
    public function getClosure()
    {
        global $fullCode;

        return eval($fullCode);
    }
}

ob_start();
$codeTester = new CodeTester();
$closure = $codeTester->getClosure();
$moufManager = Mouf\MoufManager::getMoufManager();
$closure = $closure->bindTo($moufManager);
$returnedValue = $closure($moufManager);
$error = ob_get_clean();
if ($error) {
    echo $error;
    exit;
}

$result = [
    "status" => "success",
    "data" => [
        "type" => gettype($returnedValue),
        "class" => is_object($returnedValue) ? get_class($returnedValue) : null,
    ],
];

if ($encode == "php") {
    echo serialize($result);
} elseif ($encode == "json") {
    header('Content-Type: application/json');
    echo json_encode($result);
}
