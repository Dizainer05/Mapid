<?php
// process/delete_document.php
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

if (!isset($_GET["document_id"]) || !is_numeric($_GET["document_id"]) || !isset($_GET["project_id"]) || !is_numeric($_GET["project_id"])) {
    error_log("Ошибка: некорректный document_id или project_id.");
    $_SESSION["error_msg"] = "Некорректный идентификатор документа или проекта.";
    header("Location: ../index.php");
    exit();
}

$document_id = intval($_GET["document_id"]);
$project_id = intval($_GET["project_id"]);

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

// Проверяем, имеет ли пользователь право удалять документы (должен быть admin, manager или employee)
if (!in_array($project_role, ['admin', 'manager', 'employee'])) {
    error_log("Ошибка: у пользователя нет прав для удаления документов (project_role: " . ($project_role ?? 'не определена') . ").");
    $_SESSION["error_msg"] = "У вас нет прав для удаления документов.";
    header("Location: ../pages/project_details.php?id=" . $project_id);
    exit();
}

// Получаем путь к файлу перед удалением
$sql = "SELECT file_path FROM project_documents WHERE document_id = ? AND project_id = ?";
$stmt = $conn->prepare($sql);
if ($stmt === false) {
    error_log("Ошибка подготовки запроса (get file_path): " . $conn->error);
    $_SESSION["error_msg"] = "Ошибка сервера: не удалось получить данные документа.";
    header("Location: ../pages/project_details.php?id=" . $project_id);
    exit();
}
$stmt->bind_param("ii", $document_id, $project_id);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $file_path = $row["file_path"];
} else {
    error_log("Документ не найден: document_id=$document_id, project_id=$project_id");
    $_SESSION["error_msg"] = "Документ не найден.";
    header("Location: ../pages/project_details.php?id=" . $project_id);
    exit();
}
$stmt->close();

// Удаляем запись из базы данных
// Удаляем запись из базы данных через хранимую процедуру
$sql = "CALL sp_delete_project_document(?, ?, ?)";
$stmt = $conn->prepare($sql);
if ($stmt === false) {
    error_log("Ошибка подготовки запроса (delete document): " . $conn->error);
    $_SESSION["error_msg"] = "Ошибка сервера: не удалось удалить документ.";
    header("Location: ../pages/project_details.php?id=" . $project_id);
    exit();
}
$stmt->bind_param("iii", $user_id, $project_id, $document_id);
$result = $stmt->execute();
if ($result) {
    // Удаляем файл с сервера
    if (file_exists($file_path)) {
        if (unlink($file_path)) {
            error_log("Файл успешно удалён: $file_path");
        } else {
            error_log("Ошибка при удалении файла: $file_path");
        }
    }
    error_log("Документ успешно удалён из БД: document_id=$document_id, project_id=$project_id");
    $_SESSION["success_msg"] = "Документ успешно удалён.";
} else {
    error_log("Ошибка удаления документа из БД: " . $stmt->error);
    $_SESSION["error_msg"] = "Ошибка при удалении документа.";
}
$stmt->close();

header("Location: ../pages/project_details.php?id=" . $project_id);
exit();
?>