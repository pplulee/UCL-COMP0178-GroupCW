<?php
header('Content-Type: text/html; charset=UTF-8');
date_default_timezone_set("Europe/London");
define('ROOT', dirname(__FILE__) . '/');
include ROOT . '../config.php';
include("function.php");

//Initialize session
session_start();
if (! (isset($_SESSION["logged_in"]))) {
    $_SESSION["logged_in"] = false;
}
if (! (isset($_SESSION["account_type"]))) {
    $_SESSION["account_type"] = null;
}
global $servername, $username, $password, $dbname;
$conn = mysqli_connect($servername, $username, $password, $dbname);
if (! $conn) {
    die("Connection failed: " . mysqli_connect_error());
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script src="https://fastly.jsdelivr.net/npm/@tabler/core@latest/dist/js/tabler.min.js"></script>
    <link rel="stylesheet" href="https://fastly.jsdelivr.net/npm/@tabler/core@latest/dist/css/tabler.min.css">
    <link href="https://fastly.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css" rel="stylesheet"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
          crossorigin="anonymous" referrerpolicy="no-referrer"/>
    <script src="https://fastly.jsdelivr.net/npm/jquery@3.7.0/dist/jquery.min.js"></script>
    <script src="https://unpkg.com/htmx.org@1.9.12"></script>
</head>
<body>
</body>
</html>
