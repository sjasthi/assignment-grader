<?php

$conn = mysqli_connect(
    "localhost",
    "root",
    "",
    "assignment_grader"
);

if (!$conn) {
    die("Connection Failed: " . mysqli_connect_error());
}
?>
