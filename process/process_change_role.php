<?php
// process/process_change_role.php
session_start();
require_once '../db.php';

if (!isset($_SESSION["user_id"]) || $_SESSION["role_id"] != 1) {
    echo json_encode(['success' => false, 'message' => 'Доступ запрещён']);
    exit();
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $user_id = intval($_POST["user_id"]);
    $project_id = intval($_POST["project_id"]);
    $new_role = trim($_POST["project_role"]);

    if (!in_array($new_role, ['admin', 'manager', 'employee', 'user'])) {
        echo json_encode(['success' => false, 'message' => 'Недопустимая роль']);
        exit();
    }

    $stmt = $conn->prepare("UPDATE project_participants SET project_role = ? WHERE project_id = ? AND user_id = ?");
    $stmt->bind_param("sii", $new_role, $project_id, $user_id);
    $result = $stmt->execute();
    $stmt->close();

    if ($result) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Ошибка обновления роли']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Недопустимый метод запроса']);
}
?>