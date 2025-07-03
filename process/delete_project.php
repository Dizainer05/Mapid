<?php
session_start();
require_once __DIR__ . '/../db.php';

header('Content-Type: application/json');

if (!isset($_SESSION["user_id"])) {
    echo json_encode(['success' => false, 'message' => 'Неавторизованный доступ.']);
    exit();
}

$user_id = $_SESSION["user_id"];
$project_id = $_POST['project_id'] ?? null;

if (!$project_id || !is_numeric($project_id)) {
    echo json_encode(['success' => false, 'message' => 'Некорректный ID проекта.']);
    exit();
}

// Проверка, существует ли запись в project_participants
$sql = "SELECT COUNT(*) as count FROM project_participants WHERE user_id = ? AND project_id = ?";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    error_log("delete_project.php: Ошибка подготовки запроса: " . $conn->error);
    echo json_encode(['success' => false, 'message' => 'Ошибка сервера.']);
    exit();
}
$stmt->bind_param("ii", $user_id, $project_id);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($result['count'] == 0) {
    echo json_encode(['success' => false, 'message' => 'Вы не привязаны к этому проекту.']);
    exit();
}

// Удаляем запись из таблицы project_participants
$sql = "DELETE FROM project_participants WHERE user_id = ? AND project_id = ?";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    error_log("delete_project.php: Ошибка подготовки запроса: " . $conn->error);
    echo json_encode(['success' => false, 'message' => 'Ошибка сервера.']);
    exit();
}
$stmt->bind_param("ii", $user_id, $project_id);

if ($stmt->execute()) {
    error_log("delete_project.php: Успешно удалена привязка user_id=$user_id, project_id=$project_id");
    echo json_encode(['success' => true, 'message' => 'Вы успешно отвязались от проекта.']);
} else {
    error_log("delete_project.php: Ошибка удаления: " . $stmt->error);
    echo json_encode(['success' => false, 'message' => 'Ошибка при отвязке от проекта: ' . $stmt->error]);
}

$stmt->close();
$conn->close();
?>