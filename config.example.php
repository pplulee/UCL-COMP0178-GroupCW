<?php
$_ENV["debug"] = true;

$_ENV["app_name"] = "iBay";
$_ENV["app_url"] = "http://localhost:8000";
$_ENV["register_email_verify"] = true;

// Database
$_ENV["db_host"] = "localhost";
$_ENV["db_port"] = "3306";
$_ENV["db_user"] = "root";
$_ENV["db_password"] = "123456";
$_ENV["db_database"] = "auction";

// Email
$_ENV["email_driver"] = "postal"; // null, smtp, postal

$_ENV["email_smtp_host"] = "";
$_ENV["email_smtp_port"] = "";
$_ENV["email_smtp_username"] = "";
$_ENV["email_smtp_password"] = "";
$_ENV["email_smtp_encryption"] = "";
$_ENV["email_smtp_from_address"] = "";
$_ENV["email_smtp_from_name"] = "";

$_ENV["email_postal_url"] = "http://localhost:2500";
$_ENV["email_postal_key"] = "";
$_ENV["email_postal_from_address"] = "";
$_ENV["email_postal_from_name"] = "";
