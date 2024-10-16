<?php

use voku\helper\AntiXSS;

header('Content-Type: text/html; charset=UTF-8');
include $_SERVER['DOCUMENT_ROOT'] . '/config.php';
include $_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php';
include("function.php");
include("User.php");


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
    $db_user = env('db_user');
    $db_password = env('db_password');
    $db_database = env('db_database');
    $conn = new PDO("mysql:host=$db_host;dbname=$db_database;", $db_user, $db_password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

global $antiXss;
$antiXss = new AntiXSS();
?>
