<?php
// process/delete_task.php
session_start();
require_once '../db.php';

// Включаем отображение ошибок и логирование
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

// Проверяем авторизацию
if (!isset($_SESSION["user_id"])) {
    error_log("Ошибка: пользователь не авторизован.");
    header("Location: ../pages/auth.php");
    exit();
}

$user_id = $_SESSION["user_id"];
$role_id = $_SESSION["role_id"];
$task_id = intval($_GET["task_id"]);

// Получаем задачу
$task = getTaskDetails($conn, $task_id);
if (!$task) {
    error_log("Ошибка: задача не найдена, task_id=$task_id");
    $_SESSION["error_msg"] = "Задача не найдена.";
    header("Location: ../pages/project_details.php?id=" . $_GET["project_id"]);
    exit();
}

$project_id = $task["project_id"];

// Проверяем права на удаление (доступно только для admin и manager)
$project_role = null;
$sql = "SELECT project_role FROM project_participants WHERE project_id = ? AND user_id = ?";
$stmt = $conn->prepare($sql);
if ($stmt === false) {
    error_log("Ошибка подготовки запроса (project_role): " . $conn->error);
    $_SESSION["error_msg"] = "Ошибка сервера: не удалось проверить доступ.";
    header("Location: ../pages/project_details.php?id=" . $project_id);
    exit();
}
$stmt->bind_param("ii", $project_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $project_role = $row["project_role"];
}
$stmt->close();

// Если пользователь не является участником проекта, проверяем его глобальную роль
if ($project_role === null && ($role_id == 1 || $role_id == 2)) {
    $project_role = ($role_id == 1) ? 'admin' : 'manager';
}

// Проверяем, имеет ли пользователь право удалять задачи
if (!in_array($project_role, ['admin', 'manager'])) {
    error_log("Ошибка: у пользователя нет прав для удаления задачи (project_role: " . ($project_role ?? 'не определена') . ").");
    $_SESSION["error_msg"] = "У вас нет прав для удаления задачи.";
    header("Location: ../pages/project_details.php?id=" . $project_id);
    exit();
}

// 1. Получаем все документы, связанные с задачей
$documents = getTaskDocuments($conn, $task_id);

// 2. Удаляем документы с сервера и из базы данных
foreach ($documents as $doc) {
    $file_path = $doc["file_path"];
    if (file_exists($file_path)) {
        if (unlink($file_path)) {
            error_log("Файл успешно удалён: $file_path");
        } else {
            error_log("Ошибка при удалении файла: $file_path");
        }
    }
    
    // Удаляем запись о документе из базы
    $stmt = $conn->prepare("DELETE FROM task_documents WHERE document_id = ?");
    if ($stmt === false) {
        error_log("Ошибка подготовки запроса (delete document): " . $conn->error);
        $_SESSION["error_msg"] = "Ошибка сервера: не удалось удалить документы.";
        header("Location: ../pages/project_details.php?id=" . $project_id);
        exit();
    }
    $stmt->bind_param("i", $doc["document_id"]);
    $stmt->execute();
    $stmt->close();
}

// 3. Удаляем задачу
$stmt = $conn->prepare("DELETE FROM tasks WHERE task_id = ?");
if ($stmt === false) {
    error_log("Ошибка подготовки запроса (delete task): " . $conn->error);
    $_SESSION["error_msg"] = "Ошибка сервера: не удалось удалить задачу.";
    header("Location: ../pages/project_details.php?id=" . $project_id);
    exit();
}
$stmt->bind_param("i", $task_id);
$result = $stmt->execute();
if ($result) {
    error_log("Задача успешно удалена: task_id=$task_id, project_id=$project_id");
    $_SESSION["success_msg"] = "Задача успешно удалена.";
} else {
    error_log("Ошибка удаления задачи: " . $stmt->error);
    $_SESSION["error_msg"] = "Ошибка при удалении задачи.";
}
$stmt->close();

header("Location: ../pages/project_details.php?id=" . $project_id);
exit();
?>