<?php
session_start();
require_once __DIR__ . '/../db.php';

if (!isset($_SESSION["role_id"]) || $_SESSION["role_id"] != 1) {
    echo json_encode(['success' => false, 'error' => 'Доступ запрещён']);
    exit();
}

$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
if ($user_id === 0) {
    echo json_encode(['error' => 'Неверный ID пользователя']);
    exit();
}

$stmt = $conn->prepare("SELECT user_id, username, email, full_name, role_id, position, department, avatar FROM users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if ($user) {
    echo json_encode($user);
} else {
    echo json_encode(['error' => 'Пользователь не найден']);
}

$stmt->close();
$conn->close();
?>