<?php
/*
    db.php

    This file creates the database connection for the Chinook project.
    It is included in the other PHP pages so they all use the same
    database settings.
*/

$host = "localhost";
$username = "root";
$password = "";
$database = "chinook";

$conn = new mysqli($host, $username, $password, $database);

if ($conn->connect_error) {
    die("Database connection failed.");
}

$conn->set_charset("utf8mb4");
?>