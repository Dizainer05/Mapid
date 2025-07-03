<?php
// upload_task_document.php
session_start();
require_once __DIR__ . '/../db.php';

// Включаем отображение ошибок и логирование
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

// Проверяем авторизацию
if (!isset($_SESSION["user_id"])) {
    error_log("Ошибка: пользователь не авторизован.");
    header("Location: pages/auth.php");
    exit();
}

$task_id = intval($_POST["task_id"]);
$uploaded_by = $_SESSION["user_id"];

// Проверяем права на загрузку
if (!canUploadTaskDocument($conn, $uploaded_by, $task_id)) {
    error_log("Ошибка: у пользователя нет прав на загрузку документов для задачи task_id=$task_id.");
    $_SESSION["error_msg"] = "У вас нет прав на загрузку документов для этой задачи.";
    header("Location: task_details.php?task_id=" . $task_id);
    exit();
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $document_type = trim($_POST["document_type"]);
    
    // Определяем папку для загрузки в зависимости от типа документа
    $uploadBaseDir = "../uploads/task_documents/";
    switch ($document_type) {
        case 'drawing':
            $uploadDir = $uploadBaseDir . "drawings/";
            break;
        case 'photo':
            $uploadDir = $uploadBaseDir . "photoproject/";
            break;
        case 'report':
            $uploadDir = $uploadBaseDir . "reports/";
            break;
        case 'document':
        default:
            $uploadDir = $uploadBaseDir . "documents/";
            break;
    }

    // Создаём папку, если она не существует
    if (!file_exists($uploadDir)) {
        if (!mkdir($uploadDir, 0777, true)) {
            error_log("Ошибка: не удалось создать папку $uploadDir.");
            $_SESSION["error_msg"] = "Ошибка сервера: не удалось создать папку для загрузки.";
            header("Location: task_details.php?task_id=" . $task_id);
            exit();
        }
    }

    // Обработка загрузки файла
    if (isset($_FILES["document_file"]) && $_FILES["document_file"]["error"] == UPLOAD_ERR_OK) {
        $filename = basename($_FILES["document_file"]["name"]);
        $targetFile = $uploadDir . time() . "_" . $filename;
        
        if (move_uploaded_file($_FILES["document_file"]["tmp_name"], $targetFile)) {
            // Файл успешно загружен. Сохраняем информацию в БД.
            $result = uploadTaskDocument($conn, $task_id, $filename, $targetFile, $document_type, $uploaded_by);
            if ($result) {
                error_log("Документ успешно загружен: task_id=$task_id, file_name=$filename, file_path=$targetFile");
                $_SESSION["success_msg"] = "Документ успешно загружен.";
            } else {
                // Если не удалось добавить в БД, удаляем загруженный файл
                if (file_exists($targetFile)) {
                    unlink($targetFile);
                    error_log("Файл удалён из-за ошибки добавления в БД: $targetFile");
                }
                error_log("Ошибка при сохранении документа в БД: " . $conn->error);
                $_SESSION["error_msg"] = "Ошибка при сохранении документа в БД.";
            }
        } else {
            error_log("Ошибка загрузки файла: " . $_FILES["document_file"]["error"]);
            $_SESSION["error_msg"] = "Ошибка при загрузке файла.";
        }
    } else {
        error_log("Файл не выбран или произошла ошибка при загрузке: " . ($_FILES["document_file"]["error"] ?? 'неизвестная ошибка'));
        $_SESSION["error_msg"] = "Файл не выбран или произошла ошибка при загрузке.";
    }
    header("Location: task_details.php?task_id=" . $task_id);
    exit();
} else {
    error_log("Ошибка: запрос не является POST.");
    header("Location: task_details.php?task_id=" . $task_id);
    exit();
}
?>