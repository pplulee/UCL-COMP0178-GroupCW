<?php

use Phpfastcache\Helper\Psr16Adapter;
use voku\helper\AntiXSS;

header('Content-Type: text/html; charset=UTF-8');
include_once($_SERVER['DOCUMENT_ROOT'] . '/config.php');
include_once($_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php');
include_once("function.php");

//Enable error reporting
if (env("debug", false)) {
    ini_set("display_errors", "On");
    error_reporting(E_ALL);
}

//Initialize session
session_start();
if (! (isset($_SESSION["logged_in"]))) {
    $_SESSION["logged_in"] = false;
}
if (! (isset($_SESSION["account_type"]))) {
    $_SESSION["account_type"] = null;
}

try {
    $db_host = env('db_host');
    $db_port = env('db_port');
    $db_user = env('db_user');
    $db_password = env('db_password');
    $db_database = env('db_database');
    $conn = new PDO("mysql:host=$db_host;dbname=$db_database;port=$db_port", $db_user, $db_password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

global $antiXss;
$antiXss = new AntiXSS();

global $cache;
$cache = new Psr16Adapter('Files');
?>
