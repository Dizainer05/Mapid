<?php
// process/delete_project_admin.php
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
$project_id = intval($_GET["project_id"]);

// Вызываем функцию удаления проекта
$result = adminDeleteProject($conn, $user_id, $project_id);

if ($result) {
    error_log("Проект успешно удалён: project_id=$project_id, admin_id=$user_id");
    //$_SESSION["success_msg"] = "Проект успешно удалён.";
    header("Location: ../index.php");
    exit();
} else {
    $error_message = $conn->error;
    error_log("Ошибка при удалении проекта: " . $error_message);
    $_SESSION["error_msg"] = "Ошибка при удалении проекта: " . htmlspecialchars($error_message);
    header("Location: ../pages/task_details.php?task_id=" . intval($_GET["task_id"]));
    exit();
}
?>