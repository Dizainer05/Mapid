<?php
// process/process_edit_task.php
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

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $task_id = intval($_POST["task_id"]);
    $project_id = intval($_POST["project_id"]);
    
    // Получаем данные из формы
    $task_name = trim($_POST["task_name"]);
    $responsible = intval($_POST["responsible"]);
    $assistants = $_POST["assistants"] ?? [];
    $status = trim($_POST["status"]);
    $deadline = trim($_POST["deadline"]);

    // Проверяем, что ответственный выбран
    if (empty($responsible)) {
        error_log("Ошибка: ответственный не выбран.");
        $_SESSION["error_msg"] = "Необходимо выбрать ответственного за задачу.";
        header("Location: ../pages/edit_task.php?task_id=" . $task_id);
        exit();
    }

    // Проверяем статус
    $valid_statuses = ['pending', 'in_progress', 'completed'];
    if (!in_array($status, $valid_statuses)) {
        error_log("Ошибка: неверное значение статуса: $status");
        $_SESSION["error_msg"] = "Неверное значение статуса.";
        header("Location: ../pages/edit_task.php?task_id=" . $task_id);
        exit();
    }

    // Проверяем и форматируем дату
    $deadline_date = DateTime::createFromFormat('Y-m-d', $deadline);
    if ($deadline_date === false) {
        error_log("Ошибка: неверный формат даты: $deadline");
        $_SESSION["error_msg"] = "Неверный формат даты. Используйте формат YYYY-MM-DD.";
        header("Location: ../pages/edit_task.php?task_id=" . $task_id);
        exit();
    }
    $formatted_deadline = $deadline_date->format('Y-m-d');

    // Логируем данные перед обновлением
    error_log("Данные для обновления задачи: task_id=$task_id, task_name=$task_name, responsible=$responsible, assistants=" . json_encode($assistants) . ", status=$status, deadline=$formatted_deadline");

    // Формируем JSON для помощников
    $assistants_json = json_encode($assistants);

    // Вызываем хранимую процедуру
    $stmt = $conn->prepare("CALL sp_update_task(?, ?, ?, ?, ?, ?, ?)");
    if ($stmt === false) {
        error_log("Ошибка подготовки запроса (sp_update_task): " . $conn->error);
        $_SESSION["error_msg"] = "Ошибка сервера: не удалось обновить задачу.";
        header("Location: ../pages/edit_task.php?task_id=" . $task_id);
        exit();
    }
    
    $stmt->bind_param("iisisss", $user_id, $task_id, $task_name, $responsible, $assistants_json, $status, $formatted_deadline);
    $result = $stmt->execute();
    if ($result) {
        error_log("Задача успешно обновлена: task_id=$task_id, project_id=$project_id");
        $_SESSION["success_msg"] = "Задача успешно обновлена.";
        $stmt->close();
        header("Location: ../pages/task_details.php?task_id=" . $task_id);
        exit();
    } else {
        $error_message = $stmt->error;
        error_log("Ошибка при обновлении задачи: " . $error_message);
        $_SESSION["error_msg"] = "Ошибка при обновлении задачи: " . htmlspecialchars($error_message);
        $stmt->close();
        header("Location: ../pages/edit_task.php?task_id=" . $task_id);
        exit();
    }
} else {
    error_log("Ошибка: запрос не является POST.");
    header("Location: ../index.php");
    exit();
}
?>