<?php
include 'db_connect.php';
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *"); // Allow TV to fetch if on different device

// Fetch only relevant statuses for the screen
$sql = "SELECT id, status FROM orders WHERE status IN ('preparing', 'ready') ORDER BY updated_at DESC, id DESC";
// Note: You might need to add 'updated_at' column to table for perfect sorting, 
// but for now 'id DESC' is fine (newest orders first).

$result = $conn->query($sql);

$orders = [];
while($row = $result->fetch_assoc()) {
    $orders[] = $row;
}

echo json_encode($orders);
?>