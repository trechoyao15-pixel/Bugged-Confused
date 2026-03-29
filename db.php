<?php

$db_host = '127.0.0.1';
$db_user = 'root';
$db_pass = '';
$db_name = 'ltms';
$db_port = 3306;

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name, $db_port);
 
if ($conn->connect_error) {
    error_log('DB connect error: ' . $conn->connect_error);
    http_response_code(500);
    echo "Database connection failed.";
    exit;
}

$conn->set_charset('utf8mb4');