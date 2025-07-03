<?php
session_start();
require_once __DIR__ . '/../db.php';

if (!isset($_SESSION["user_id"]) || $_SESSION["role_id"] != 1) {
    echo json_encode(['success' => false, 'error' => 'Доступ запрещён']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Неверный метод запроса']);
    exit();
}

$user_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
if ($user_id === 0) {
    echo json_encode(['success' => false, 'error' => 'Неверный ID пользователя']);
    exit();
}

$stmt = $conn->prepare("CALL sp_delete_user(?)");
$stmt->bind_param("i", $user_id);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Ошибка при удалении: ' . $stmt->error]);
}

$stmt->close();
$conn->close();
?>