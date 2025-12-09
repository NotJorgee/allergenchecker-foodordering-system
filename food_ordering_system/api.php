<?php
session_start();
include 'db_connect.php';
header('Content-Type: application/json');

// Helper
function isLogged() { return isset($_SESSION['role']); }

$action = $_GET['action'] ?? '';

// 1. GET ACTIVE ORDERS (For Operations)
if ($action == 'get_orders') {
    if(!isLogged()) { echo json_encode([]); exit; }
    
    // Only fetch active orders
    $sql = "SELECT * FROM orders WHERE status NOT IN ('completed', 'cancelled') ORDER BY created_at ASC";
    $result = $conn->query($sql);
    
    $orders = [];
    while($row = $result->fetch_assoc()) {
        $order_id = $row['id'];
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

// 2. GET HISTORY & FINANCE (With Filters)
if ($action == 'get_history') {
    if(!isLogged()) { echo json_encode([]); exit; }

    // 1. Get Params (Default to last 30 days if empty)
    $start = $_GET['start'] ?? date('Y-m-d', strtotime('-30 days'));
    $end = $_GET['end'] ?? date('Y-m-d');
    $status = $_GET['status'] ?? 'all'; // 'all', 'completed', 'cancelled'

    // 2. Build Query
    // We cast created_at to DATE() so we can compare YYYY-MM-DD
    $whereClause = "WHERE DATE(created_at) BETWEEN '$start' AND '$end'";

    if ($status == 'completed') {
        $whereClause .= " AND status = 'completed'";
    } elseif ($status == 'cancelled') {
        $whereClause .= " AND status = 'cancelled'";
    } else {
        $whereClause .= " AND status IN ('completed', 'cancelled')";
    }

    // 3. Fetch History
    $sql = "SELECT * FROM orders $whereClause ORDER BY created_at DESC";
    $result = $conn->query($sql);
    
    $history = [];
    $total_rev = 0;
    $total_orders = 0;

    while($row = $result->fetch_assoc()) {
        $order_id = $row['id'];
        
        // Calculate totals dynamically based on the filtered results
        if ($row['status'] == 'completed') {
            $total_rev += $row['total_price'];
        }
        $total_orders++;

        // Fetch items
        $item_sql = "SELECT product_name, quantity FROM order_items WHERE order_id = $order_id";
        $i_res = $conn->query($item_sql);
        $item_str = [];
        while($i = $i_res->fetch_assoc()) {
            $item_str[] = $i['quantity'] . "x " . $i['product_name'];
        }
        $row['items_summary'] = implode(", ", $item_str);
        $history[] = $row;
    }

    echo json_encode([
        "stats" => ["total_revenue" => $total_rev, "total_orders" => $total_orders],
        "history" => $history
    ]);
}

// 3. UPDATE STATUS (With 'Cancelled' logic)
if ($action == 'update_status') {
    if(!isLogged()) { echo json_encode(["status" => "error"]); exit; }

    $data = json_decode(file_get_contents("php://input"), true);
    $id = $data['id'];
    $status = $data['status'];

    if($status == 'delete') {
        // Permanently delete (only for mistakes/tests)
        $conn->query("DELETE FROM orders WHERE id=$id");
    } else {
        // Update status (includes 'cancelled' and 'completed')
        $stmt = $conn->prepare("UPDATE orders SET status=? WHERE id=?");
        $stmt->bind_param("si", $status, $id);
        $stmt->execute();
    }
    echo json_encode(["success" => true]);
}
?>