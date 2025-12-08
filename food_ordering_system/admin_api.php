<?php
include 'db_connect.php';
header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

// 1. GET ALL PRODUCTS
if ($action == 'get_all') {
    $sql = "SELECT * FROM products ORDER BY sort_order ASC";
    $result = $conn->query($sql);
    $products = [];
    while($row = $result->fetch_assoc()) {
        // Add full path to image so frontend can display it easily
        $row['image_url'] = $row['image'] ? "uploads/" . $row['image'] : null;
        $products[] = $row;
    }
    echo json_encode($products);
}

// 2. SAVE PRODUCT (With Image Upload)
if ($action == 'save') {
    $name = $_POST['name'];
    $price = $_POST['price'];
    $category = $_POST['category'];
    $id = $_POST['id'] ?? '';
    $is_available = $_POST['is_available']; // 1 or 0

    // Handle Image Upload
    $imagePath = null;
    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $filename = time() . "_" . $_FILES['image']['name']; // Unique name
        move_uploaded_file($_FILES['image']['tmp_name'], "uploads/" . $filename);
        $imagePath = $filename;
    }

    if ($id) {
        // UPDATE existing item
        if ($imagePath) {
            // If new image uploaded, update image column too
            $stmt = $conn->prepare("UPDATE products SET name=?, price=?, category=?, is_available=?, image=? WHERE id=?");
            $stmt->bind_param("sdsisi", $name, $price, $category, $is_available, $imagePath, $id);
        } else {
            // Keep old image
            $stmt = $conn->prepare("UPDATE products SET name=?, price=?, category=?, is_available=? WHERE id=?");
            $stmt->bind_param("sdsii", $name, $price, $category, $is_available, $id);
        }
    } else {
        // INSERT new item
        $stmt = $conn->prepare("INSERT INTO products (name, price, category, is_available, image) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sdsis", $name, $price, $category, $is_available, $imagePath);
    }
    
    if($stmt->execute()) {
        echo json_encode(["status" => "success"]);
    } else {
        echo json_encode(["status" => "error", "error" => $conn->error]);
    }
}

// 3. DELETE PRODUCT
if ($action == 'delete') {
    $id = $_POST['id'];
    $conn->query("DELETE FROM products WHERE id=$id");
    echo json_encode(["status" => "success"]);
}

// 4. TOGGLE AVAILABILITY (Quick Switch)
if ($action == 'toggle_stock') {
    $id = $_POST['id'];
    $status = $_POST['status']; // 1 or 0
    $stmt = $conn->prepare("UPDATE products SET is_available=? WHERE id=?");
    $stmt->bind_param("ii", $status, $id);
    $stmt->execute();
    echo json_encode(["status" => "success"]);
}

// ==========================
// CATEGORY MANAGEMENT
// ==========================

// 5. GET ALL CATEGORIES
if ($action == 'get_categories') {
    $sql = "SELECT * FROM categories ORDER BY sort_order ASC";
    $result = $conn->query($sql);
    $cats = [];
    while($row = $result->fetch_assoc()) {
        $cats[] = $row;
    }
    echo json_encode($cats);
}

// 6. SAVE CATEGORY (Add/Edit)
if ($action == 'save_category') {
    $data = json_decode(file_get_contents("php://input"), true);
    $id = $data['id'] ?? null;
    $name = $data['name'];
    $icon = $data['icon'];
    
    if ($id) {
        // Fetch old name first to update products table automatically
        $oldQuery = $conn->query("SELECT name FROM categories WHERE id=$id");
        $oldName = $oldQuery->fetch_assoc()['name'];

        // Update Category
        $stmt = $conn->prepare("UPDATE categories SET name=?, icon=? WHERE id=?");
        $stmt->bind_param("ssi", $name, $icon, $id);
        $stmt->execute();

        // SMART FEATURE: Update all products that had the old category name
        $updateProds = $conn->prepare("UPDATE products SET category=? WHERE category=?");
        $updateProds->bind_param("ss", $name, $oldName);
        $updateProds->execute();

    } else {
        // Insert New
        $stmt = $conn->prepare("INSERT INTO categories (name, icon) VALUES (?, ?)");
        $stmt->bind_param("ss", $name, $icon);
        $stmt->execute();
    }
    echo json_encode(["status" => "success"]);
}

// 7. DELETE CATEGORY
if ($action == 'delete_category') {
    $id = $_POST['id'];
    $conn->query("DELETE FROM categories WHERE id=$id");
    echo json_encode(["status" => "success"]);
}

// ==========================
// DRAG & DROP REORDERING
// ==========================

// 8. REORDER PRODUCTS
if ($action == 'reorder_products') {
    $data = json_decode(file_get_contents("php://input"), true);
    $items = $data['items']; // Array of {id: 1, order: 0}, {id: 5, order: 1}...

    $stmt = $conn->prepare("UPDATE products SET sort_order=? WHERE id=?");
    foreach ($items as $index => $item) {
        $stmt->bind_param("ii", $index, $item['id']); // $index is the new sort_order
        $stmt->execute();
    }
    echo json_encode(["status" => "success"]);
}

// 9. REORDER CATEGORIES
if ($action == 'reorder_categories') {
    $data = json_decode(file_get_contents("php://input"), true);
    $items = $data['items']; 

    $stmt = $conn->prepare("UPDATE categories SET sort_order=? WHERE id=?");
    foreach ($items as $index => $item) {
        $stmt->bind_param("ii", $index, $item['id']);
        $stmt->execute();
    }
    echo json_encode(["status" => "success"]);
}

// UPDATE "GET ALL" TO SORT BY ORDER
// Find the "get_all" section at the top of this file and CHANGE the SQL line to this:
// $sql = "SELECT * FROM products ORDER BY sort_order ASC";
?>