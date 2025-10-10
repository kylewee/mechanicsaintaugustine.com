<?php

//database_connection.php

try {
    $connect = new PDO("mysql:host=localhost;dbname=mm", "root", "");
    $connect->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

?>