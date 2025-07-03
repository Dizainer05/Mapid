<?php
// process/process_add_task.php
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
    $project_id = intval($_POST["project_id"]);
    
    // Получаем проектную роль пользователя
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
    error_log("Проектная роль пользователя: " . ($project_role ?? 'не определена'));

    // Если пользователь не является участником проекта, проверяем его глобальную роль
    if ($project_role === null && ($role_id == 1 || $role_id == 2)) {
        $project_role = ($role_id == 1) ? 'admin' : 'manager';
        error_log("Роль присвоена на основе глобальной роли: $project_role");
    }

    // Проверяем, имеет ли пользователь право добавлять задачи (должен быть admin или manager)
    if (!in_array($project_role, ['admin', 'manager'])) {
        error_log("Ошибка: у пользователя нет прав для добавления задачи (project_role: " . ($project_role ?? 'не определена') . ").");
        $_SESSION["error_msg"] = "У вас нет прав для добавления задачи.";
        header("Location: ../pages/project_details.php?id=" . $project_id);
        exit();
    }

    // Получаем данные из формы
    $task_name = trim($_POST["task_name"]);
    $responsible = intval($_POST["responsible"]);
    $assistants = $_POST["assistants"] ?? [];
    $status = trim($_POST["status"]);
    $deadline = trim($_POST["deadline"]);

    // Логируем данные из формы
    error_log("Данные формы: task_name=$task_name, responsible=$responsible, assistants=" . json_encode($assistants) . ", status=$status, deadline=$deadline");

    // Проверяем, что ответственный выбран
    if (empty($responsible)) {
        error_log("Ошибка: ответственный не выбран.");
        $_SESSION["error_msg"] = "Необходимо выбрать ответственного за задачу.";
        header("Location: ../pages/add_task.php?project_id=" . $project_id);
        exit();
    }

    // Формируем JSON для помощников
    $assistants_json = json_encode($assistants);

    // Добавляем задачу
    $result = addProjectTask($conn, $user_id, $project_id, $task_name, $responsible, $assistants_json, $status, $deadline);
    if ($result) {
        error_log("Задача успешно добавлена: project_id=$project_id, task_name=$task_name");
        $_SESSION["success_msg"] = "Задача успешно добавлена.";
        header("Location: ../pages/project_details.php?id=" . $project_id);
        exit();
    } else {
        error_log("Ошибка при добавлении задачи: " . $conn->error);
        $_SESSION["error_msg"] = "Ошибка при добавлении задачи.";
        header("Location: ../pages/add_task.php?project_id=" . $project_id);
        exit();
    }
} else {
    error_log("Ошибка: запрос не является POST.");
    header("Location: ../index.php");
    exit();
}
?>