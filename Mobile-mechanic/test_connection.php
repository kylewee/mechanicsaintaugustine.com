<?php
echo "<h2>Database Connection Test</h2>";

// Test with root user and no password
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "mm";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
} else {
    echo "<p style='color: green;'>✅ Database connection successful!</p>";
    
    // Test admin query
    $result = $conn->query("SELECT * FROM admin");
    if ($result) {
        echo "<p style='color: green;'>✅ Admin table found!</p>";
        while($row = $result->fetch_assoc()) {
            echo "<p>Admin Email: " . $row['email'] . "</p>";
            echo "<p>Password Hash: " . $row['password'] . "</p>";
        }
    } else {
        echo "<p style='color: red;'>❌ Error querying admin table: " . $conn->error . "</p>";
    }
}

$conn->close();
?>