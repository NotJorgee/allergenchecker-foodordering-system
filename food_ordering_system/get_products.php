<?php
include 'db_connect.php';
header('Content-Type: application/json');

// Get all products that are marked as "Available"
$sql = "SELECT * FROM products WHERE is_available = 1 ORDER BY sort_order ASC";
$result = $conn->query($sql);

$products = [];

while($row = $result->fetch_assoc()) {
    // We add each row to our array
    $products[] = [
        'id' => $row['id'],
        'name' => $row['name'],
        'price' => $row['price'],
        'category' => $row['category'],
        'image' => $row['image']
    ];
}

echo json_encode($products);
?>