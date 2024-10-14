<?php
header('Content-Type: text/html; charset=UTF-8');
include $_SERVER['DOCUMENT_ROOT'] . '/config.php';
include("function.php");
global $Sys_config;


//Enable error reporting
if ($Sys_config["debug"]) {
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
    $conn = new PDO("mysql:host={$Sys_config["db_host"]};dbname={$Sys_config["db_database"]};", $Sys_config["db_user"], $Sys_config["db_password"]);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    $conn->exec("set names utf8"); //设置编码
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>
