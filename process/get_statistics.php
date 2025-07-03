<?php
session_start();
require_once __DIR__ . '/../db.php';

header('Content-Type: application/json');

if ($_SESSION['role_id'] != 1) {
    echo json_encode(['success' => false, 'error' => 'Доступ запрещён']);
    exit;
}

$stmt = $conn->prepare("SELECT COUNT(*) as user_count FROM users");
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();
$user_count = $result['user_count'];
$stmt->close();

echo json_encode(['success' => true, 'user_count' => $user_count]);

$conn->close();
?>