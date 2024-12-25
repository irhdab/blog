<?php
$servername = "localhost"; // Replace with your database server
$username = "seed";        // Replace with your database username
$password = "plplplo()";            // Replace with your database password
$dbname = "telegraph";     // Replace with your database name

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch all writings from the database
$sql = "SELECT id, content, created_at FROM writings ORDER BY created_at DESC";
$result = $conn->query($sql);
?>