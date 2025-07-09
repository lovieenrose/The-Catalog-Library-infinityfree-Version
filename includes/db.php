<?php
// DO NOT call session_start() here

// Set PHP timezone to Philippines
date_default_timezone_set('Asia/Manila');

// InfinityFree database configuration
$host = 'sql213.infinityfree.com';  // InfinityFree MySQL server
$db   = 'if0_39421280_the_catalog_library';  // Your database name from InfinityFree
$user = 'if0_39421280';  // Your database username from InfinityFree
$pass = 'dprb97TvBpBO';  // Replace with your actual vPanel password
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

try {
    $conn = new PDO($dsn, $user, $pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
    // Set timezone for both PHP and MySQL to Philippines time
    $conn->exec("SET time_zone = '+08:00'");
    
    // Mark that database connection is included
    define('DB_CONNECTION_INCLUDED', true);
    
} catch (PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    die("Database connection failed. Please try again later.");
}
?>