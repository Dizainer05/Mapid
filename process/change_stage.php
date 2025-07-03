<?php
// process/change_stage.php
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
$new_stage = trim($_GET["stage"]);

// Проверяем, что новый этап указан
if (empty($new_stage)) {
    error_log("Ошибка: новый этап не указан.");
    $_SESSION["error_msg"] = "Новый этап не указан.";
    header("Location: ../pages/project_details.php?id=" . $project_id);
    exit();
}

// Вызываем хранимую процедуру
$stmt = $conn->prepare("CALL sp_change_project_stage(?, ?, ?)");
if ($stmt === false) {
    error_log("Ошибка подготовки запроса (sp_change_project_stage): " . $conn->error);
    $_SESSION["error_msg"] = "Ошибка сервера: не удалось изменить этап.";
    header("Location: ../pages/project_details.php?id=" . $project_id);
    exit();
}

$stmt->bind_param("iis", $user_id, $project_id, $new_stage);
$result = $stmt->execute();
if ($result) {
    error_log("Этап проекта успешно изменён: project_id=$project_id, new_stage=$new_stage");
    $_SESSION["success_msg"] = "Этап проекта успешно изменён.";
    $stmt->close();
    header("Location: ../pages/project_details.php?id=" . $project_id);
    exit();
} else {
    $error_message = $stmt->error;
    error_log("Ошибка при изменении этапа: " . $error_message);
    $_SESSION["error_msg"] = "Ошибка при изменении этапа: " . htmlspecialchars($error_message);
    $stmt->close();
    header("Location: ../pages/project_details.php?id=" . $project_id);
    exit();
}
?>