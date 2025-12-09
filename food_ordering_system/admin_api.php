<?php
session_start(); // START SESSION TO CHECK ROLES
include 'db_connect.php';
header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

// Helper: Check if Admin
function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

// Helper: Check if Logged In (Staff or Admin)
function isLogged() {
    return isset($_SESSION['role']);
}

// 1. GET ALL PRODUCTS (Open to Staff & Admin)
if ($action == 'get_all') {
    if(!isLogged()) { echo json_encode([]); exit; }
    
    $sql = "SELECT * FROM products ORDER BY sort_order ASC";
    $result = $conn->query($sql);
    $products = [];
    while($row = $result->fetch_assoc()) {
        $row['image_url'] = $row['image'] ? "uploads/" . $row['image'] : null;
        $products[] = $row;
    }
    echo json_encode($products);
}

// 2. SAVE PRODUCT (ADMIN ONLY)
if ($action == 'save') {
    if (!isAdmin()) { echo json_encode(["status" => "error", "message" => "Unauthorized"]); exit; }

    $name = $_POST['name'];
    $price = $_POST['price'];
    $category = $_POST['category'];
    $id = $_POST['id'] ?? '';
    $is_available = $_POST['is_available']; 

    $imagePath = null;
    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $filename = time() . "_" . $_FILES['image']['name'];
        move_uploaded_file($_FILES['image']['tmp_name'], "uploads/" . $filename);
        $imagePath = $filename;
    }

    if ($id) {
        if ($imagePath) {
            $stmt = $conn->prepare("UPDATE products SET name=?, price=?, category=?, is_available=?, image=? WHERE id=?");
            $stmt->bind_param("sdsisi", $name, $price, $category, $is_available, $imagePath, $id);
        } else {
            $stmt = $conn->prepare("UPDATE products SET name=?, price=?, category=?, is_available=? WHERE id=?");
            $stmt->bind_param("sdsii", $name, $price, $category, $is_available, $id);
        }
    } else {
        $stmt = $conn->prepare("INSERT INTO products (name, price, category, is_available, image) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sdsis", $name, $price, $category, $is_available, $imagePath);
    }
    
    if($stmt->execute()) echo json_encode(["status" => "success"]);
    else echo json_encode(["status" => "error"]);
}

// 3. DELETE PRODUCT (ADMIN ONLY)
if ($action == 'delete') {
    if (!isAdmin()) { echo json_encode(["status" => "error"]); exit; }
    $id = $_POST['id'];
    $conn->query("DELETE FROM products WHERE id=$id");
    echo json_encode(["status" => "success"]);
}

// 4. TOGGLE STOCK (STAFF & ADMIN ALLOWED)
if ($action == 'toggle_stock') {
    if (!isLogged()) { echo json_encode(["status" => "error"]); exit; } // Allow Staff
    
    $id = $_POST['id'];
    $status = $_POST['status']; 
    $stmt = $conn->prepare("UPDATE products SET is_available=? WHERE id=?");
    $stmt->bind_param("ii", $status, $id);
    $stmt->execute();
    echo json_encode(["status" => "success"]);
}

// 5. GET CATEGORIES (Logged In)
if ($action == 'get_categories') {
    if(!isLogged()) { echo json_encode([]); exit; }
    $sql = "SELECT * FROM categories ORDER BY sort_order ASC";
    $result = $conn->query($sql);
    $cats = [];
    while($row = $result->fetch_assoc()) $cats[] = $row;
    echo json_encode($cats);
}

// 6. SAVE CATEGORY (ADMIN ONLY)
if ($action == 'save_category') {
    if (!isAdmin()) { echo json_encode(["status" => "error"]); exit; }
    $data = json_decode(file_get_contents("php://input"), true);
    $id = $data['id'] ?? null;
    $name = $data['name'];
    $icon = $data['icon'];
    
    if ($id) {
        $oldQuery = $conn->query("SELECT name FROM categories WHERE id=$id");
        $oldName = $oldQuery->fetch_assoc()['name'];
        $stmt = $conn->prepare("UPDATE categories SET name=?, icon=? WHERE id=?");
        $stmt->bind_param("ssi", $name, $icon, $id);
        $stmt->execute();
        $updateProds = $conn->prepare("UPDATE products SET category=? WHERE category=?");
        $updateProds->bind_param("ss", $name, $oldName);
        $updateProds->execute();
    } else {
        $stmt = $conn->prepare("INSERT INTO categories (name, icon) VALUES (?, ?)");
        $stmt->bind_param("ss", $name, $icon);
        $stmt->execute();
    }
    echo json_encode(["status" => "success"]);
}

// 7. DELETE CATEGORY (ADMIN ONLY)
if ($action == 'delete_category') {
    if (!isAdmin()) { echo json_encode(["status" => "error"]); exit; }
    $id = $_POST['id'];
    $conn->query("DELETE FROM categories WHERE id=$id");
    echo json_encode(["status" => "success"]);
}

// 8. REORDER PRODUCTS (ADMIN ONLY)
if ($action == 'reorder_products') {
    if (!isAdmin()) { echo json_encode(["status" => "error"]); exit; }
    $data = json_decode(file_get_contents("php://input"), true);
    $stmt = $conn->prepare("UPDATE products SET sort_order=? WHERE id=?");
    foreach ($data['items'] as $index => $item) {
        $stmt->bind_param("ii", $index, $item['id']);
        $stmt->execute();
    }
    echo json_encode(["status" => "success"]);
}

// 9. REORDER CATEGORIES (ADMIN ONLY)
if ($action == 'reorder_categories') {
    if (!isAdmin()) { echo json_encode(["status" => "error"]); exit; }
    $data = json_decode(file_get_contents("php://input"), true);
    $stmt = $conn->prepare("UPDATE categories SET sort_order=? WHERE id=?");
    foreach ($data['items'] as $index => $item) {
        $stmt->bind_param("ii", $index, $item['id']);
        $stmt->execute();
    }
    echo json_encode(["status" => "success"]);
}
?>