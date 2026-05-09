<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "club management"; // Note: Contains space, use backticks in SQL queries

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset to utf8mb4 for full Unicode support
$conn->set_charset("utf8mb4");

// Enable error reporting for development
error_reporting(E_ALL);
ini_set('display_errors', 1);
?>