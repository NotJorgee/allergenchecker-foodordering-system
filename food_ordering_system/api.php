<?php
include 'db_connect.php';
header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

// --- GET ALL ORDERS ---
if ($action == 'get_orders') {
    $sql = "SELECT * FROM orders WHERE status != 'completed' ORDER BY created_at DESC";
    $result = $conn->query($sql);
    
    $orders = [];
    while($row = $result->fetch_assoc()) {
        $order_id = $row['id'];
        // Fetch items for this order
        $item_sql = "SELECT * FROM order_items WHERE order_id = $order_id";
        $item_result = $conn->query($item_sql);
        $items = [];
        while($i = $item_result->fetch_assoc()) {
            $items[] = ['name' => $i['product_name'], 'qty' => $i['quantity']];
        }
        $row['items'] = $items;
        $orders[] = $row;
    }
    echo json_encode($orders);
}

// --- UPDATE STATUS ---
if ($action == 'update_status') {
    $data = json_decode(file_get_contents("php://input"), true);
    $id = $data['id'];
    $status = $data['status'];

    if($status == 'delete') {
        $conn->query("DELETE FROM orders WHERE id=$id");
    } else {
        $stmt = $conn->prepare("UPDATE orders SET status=? WHERE id=?");
        $stmt->bind_param("si", $status, $id);
        $stmt->execute();
    }
    echo json_encode(["success" => true]);
}
?>