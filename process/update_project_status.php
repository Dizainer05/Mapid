<?php
// process/update_project_status.php
session_start();
require_once '../db.php';

header('Content-Type: application/json');

if (!isset($_SESSION["user_id"])) {
    echo json_encode(['success' => false, 'message' => 'Пользователь не авторизован']);
    exit();
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode(['success' => false, 'message' => 'Недопустимый метод запроса']);
    exit();
}

$project_id = isset($_POST['project_id']) ? (int)$_POST['project_id'] : 0;
$new_status = isset($_POST['status']) ? trim($_POST['status']) : '';

$valid_statuses = ['planning', 'in_progress', 'completed', 'on_hold'];
if (!in_array($new_status, $valid_statuses)) {
    error_log("Недопустимый статус в update_project_status: '$new_status'");
    echo json_encode(['success' => false, 'message' => "Недопустимый статус: '$new_status'"]);
    exit();
}

try {
    $sql = "UPDATE projects SET status = ? WHERE project_id = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Ошибка подготовки запроса: " . $conn->error);
    }
    $stmt->bind_param("si", $new_status, $project_id);
    $result = $stmt->execute();
    $stmt->close();

    if ($result) {
        echo json_encode(['success' => true, 'new_status' => $new_status]);
    } else {
        throw new Exception("Ошибка обновления статуса проекта: " . $conn->error);
    }
} catch (Exception $e) {
    error_log("Ошибка в update_project_status.php: " . $e->getMessage() . ", status='$new_status', project_id=$project_id");
    echo json_encode(['success' => false, 'message' => 'Ошибка сервера: ' . $e->getMessage()]);
}

$conn->close();
?>