<?php
session_start();
include 'db_connect.php';
header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

// 1. LOGIN
if ($action == 'login') {
    $data = json_decode(file_get_contents("php://input"), true);
    $user = $data['username'];
    $pass = md5($data['password']); 

    $stmt = $conn->prepare("SELECT id, role FROM users WHERE username=? AND password=?");
    $stmt->bind_param("ss", $user, $pass);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        $_SESSION['user_id'] = $row['id'];
        $_SESSION['role'] = $row['role']; // Store role in session
        echo json_encode(["status" => "success", "role" => $row['role']]);
    } else {
        echo json_encode(["status" => "error"]);
    }
}

// 2. CHECK SESSION
if ($action == 'check_session') {
    if (isset($_SESSION['user_id'])) {
        echo json_encode(["status" => "logged_in", "role" => $_SESSION['role']]);
    } else {
        echo json_encode(["status" => "logged_out"]);
    }
}

// 3. LOGOUT
if ($action == 'logout') {
    session_destroy();
    echo json_encode(["status" => "success"]);
}

// ==========================================
//  PROTECTED ROUTES (ADMIN ONLY)
// ==========================================

// Helper function to check admin
function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

// 4. LIST USERS
if ($action == 'get_users') {
    if (!isAdmin()) { echo json_encode([]); exit; } // Block access
    
    $result = $conn->query("SELECT id, username, role, created_at FROM users");
    $users = [];
    while($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
    echo json_encode($users);
}

// 5. ADD USER
if ($action == 'add_user') {
    if (!isAdmin()) { echo json_encode(["status" => "error", "message" => "Unauthorized"]); exit; }

    $data = json_decode(file_get_contents("php://input"), true);
    $user = $data['username'];
    $pass = md5($data['password']);
    $role = $data['role'];

    $stmt = $conn->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $user, $pass, $role);
    
    if($stmt->execute()) echo json_encode(["status" => "success"]);
    else echo json_encode(["status" => "error"]);
}

// 6. DELETE USER
if ($action == 'delete_user') {
    if (!isAdmin()) { echo json_encode(["status" => "error", "message" => "Unauthorized"]); exit; }

    $id = $_POST['id'];
    if ($id == $_SESSION['user_id']) {
        echo json_encode(["status" => "error", "message" => "Cannot delete yourself"]);
    } else {
        $conn->query("DELETE FROM users WHERE id=$id");
        echo json_encode(["status" => "success"]);
    }
}
?>