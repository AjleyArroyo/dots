<?php
$servername = "localhost";
$username = "root";
$password = "";
$database = "dots"; // Your database name

// Create connection
$conn = new mysqli($servername, $username, $password, $database);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get the latest temperature entry
$sql = "SELECT * FROM registro ORDER BY id DESC LIMIT 1"; // Only the most recent entry
$result = $conn->query($sql);

// Prepare data to be sent as JSON
$temperatureData = null;

if ($result->num_rows > 0) {
    // Output the first row
    $row = $result->fetch_assoc();
    $temperatureData = array(
        "id" => $row['id'],
        "temperatura" => $row['temperatura'], // Make sure this column is in your table
    );
}

echo json_encode($temperatureData); // Return the most recent temperature data as JSON

$conn->close();
?>

