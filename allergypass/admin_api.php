<?php
session_start();
include 'db_connect.php';
header('Content-Type: application/json');

$action = $_GET['action'] ?? '';
$input = json_decode(file_get_contents("php://input"), true);

// --- AUTH ---
if ($action == 'login') {
    $user = $input['username'];
    $pass = md5($input['password']);
    
    $stmt = $conn->prepare("SELECT id FROM admins WHERE username=? AND password=?");
    $stmt->bind_param("ss", $user, $pass);
    $stmt->execute();
    
    if ($stmt->get_result()->fetch_assoc()) {
        $_SESSION['admin_logged_in'] = true;
        echo json_encode(["status" => "success"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Invalid credentials"]);
    }
}

if ($action == 'logout') {
    session_destroy();
    echo json_encode(["status" => "success"]);
}

if ($action == 'check_session') {
    if (isset($_SESSION['admin_logged_in'])) echo json_encode(["status" => "logged_in"]);
    else echo json_encode(["status" => "logged_out"]);
}

// --- MIDDLEWARE ---
if (!isset($_SESSION['admin_logged_in']) && $action != 'login') {
    echo json_encode(["status" => "error", "message" => "Unauthorized"]);
    exit;
}

// --- STATS ---
if ($action == 'get_stats') {
    $users = $conn->query("SELECT COUNT(*) as c FROM users")->fetch_assoc()['c'];
    $custom = $conn->query("SELECT COUNT(*) as c FROM custom_allergens")->fetch_assoc()['c'];
    
    $top = [];
    $sql = "SELECT allergy_name, COUNT(*) as count FROM user_allergies GROUP BY allergy_name ORDER BY count DESC LIMIT 5";
    $res = $conn->query($sql);
    while($row = $res->fetch_assoc()) $top[] = $row;

    echo json_encode([
        "total_users" => $users,
        "total_custom_rules" => $custom,
        "top_allergies" => $top
    ]);
}

// --- COMMON ALLERGENS CRUD ---

// 1. Get All (With Keywords)
if ($action == 'get_common') {
    $result = $conn->query("SELECT * FROM common_allergens ORDER BY name ASC");
    $data = [];
    while($row = $result->fetch_assoc()) {
        $cid = $row['id'];
        $row['keywords'] = [];
        $kwRes = $conn->query("SELECT word FROM common_keywords WHERE common_allergen_id=$cid");
        while($k = $kwRes->fetch_assoc()) $row['keywords'][] = $k['word'];
        $data[] = $row;
    }
    echo json_encode($data);
}

// 2. Add New
if ($action == 'add_common') {
    $name = $input['name'];
    $icon = $input['icon'];
    $stmt = $conn->prepare("INSERT INTO common_allergens (name, icon) VALUES (?, ?)");
    $stmt->bind_param("ss", $name, $icon);
    echo $stmt->execute() ? json_encode(["status" => "success"]) : json_encode(["status" => "error"]);
}

// 3. Update (Name, Icon, Ingredients)
if ($action == 'update_common_allergy') {
    $id = $input['id'];
    $name = $input['name'];
    $icon = $input['icon'];
    $words = $input['keywords']; // Array

    $stmt = $conn->prepare("UPDATE common_allergens SET name=?, icon=? WHERE id=?");
    $stmt->bind_param("ssi", $name, $icon, $id);
    
    if($stmt->execute()) {
        // Update Keywords
        $conn->query("DELETE FROM common_keywords WHERE common_allergen_id=$id");
        $kStmt = $conn->prepare("INSERT INTO common_keywords (common_allergen_id, word) VALUES (?, ?)");
        foreach($words as $w) {
            $kStmt->bind_param("is", $id, $w);
            $kStmt->execute();
        }
        echo json_encode(["status" => "success"]);
    } else {
        echo json_encode(["status" => "error"]);
    }
}

// 4. Delete
if ($action == 'delete_common') {
    $id = $input['id'];
    $conn->query("DELETE FROM common_allergens WHERE id=$id");
    echo json_encode(["status" => "success"]);
}

// --- USER MANAGEMENT ---
if ($action == 'get_users') {
    $sql = "SELECT id, username, full_name, created_at FROM users ORDER BY created_at DESC LIMIT 50";
    $result = $conn->query($sql);
    $users = [];
    while($row = $result->fetch_assoc()) {
        $uid = $row['id'];
        $c_common = $conn->query("SELECT COUNT(*) as c FROM user_allergies WHERE user_id=$uid")->fetch_assoc()['c'];
        $c_custom = $conn->query("SELECT COUNT(*) as c FROM custom_allergens WHERE user_id=$uid")->fetch_assoc()['c'];
        $row['stats'] = "$c_common Common, $c_custom Custom";
        $users[] = $row;
    }
    echo json_encode($users);
}

if ($action == 'delete_user') {
    $id = $input['id'];
    $conn->query("DELETE FROM users WHERE id=$id");
    echo json_encode(["status" => "success"]);
}
?>