<?php 
  define('DB_SERVER', 'localhost');
  define('DB_USERNAME', 'root');
  define('DB_PASSWORD', '');
  define('DB_NAME', 'db_hydronourish');

  $conn = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);  
  
  if ($conn->connect_error) {
    die("Connection Failed: " . $con->connect_error);
  }
  $conn->set_charset("utf8");
  
  date_default_timezone_set('Asia/Manila');
  
  ini_set('display_errors', 1);
  ini_set('display_startup_errors', 1);
  error_reporting(E_ALL);
?>