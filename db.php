<?php
$servername = "127.0.0.1";
$username = "root";
$password = "";
$database = "assignment_grader";

$conn = mysqli_connect($servername, $username, $password, $database);

if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}
?>