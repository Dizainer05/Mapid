<?php
// process/process_add_document.php
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

    // Проверяем, имеет ли пользователь право загружать документы (должен быть admin, manager или employee)
    if (!in_array($project_role, ['admin', 'manager', 'employee'])) {
        error_log("Ошибка: у пользователя нет прав для загрузки документов (project_role: " . ($project_role ?? 'не определена') . ").");
        $_SESSION["error_msg"] = "У вас нет прав для загрузки документов.";
        header("Location: ../pages/project_details.php?id=" . $project_id);
        exit();
    }

    $document_type = trim($_POST["document_type"]);
    
    // Определяем папку для загрузки в зависимости от типа документа
    $uploadBaseDir = "../uploads/";
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
            header("Location: ../pages/project_details.php?id=" . $project_id);
            exit();
        }
    }

    // Обработка загрузки файла
    if (isset($_FILES["document_file"]) && $_FILES["document_file"]["error"] == UPLOAD_ERR_OK) {
        $filename = basename($_FILES["document_file"]["name"]);
        $targetFile = $uploadDir . time() . "_" . $filename;
        
        if (move_uploaded_file($_FILES["document_file"]["tmp_name"], $targetFile)) {
            // Файл успешно загружен. Сохраняем информацию в БД.
            $file_name = $filename;
            $file_path = $targetFile;
            // Допустим, uploaded_by = текущий пользователь
            $result = addProjectDocumentMan($conn, $user_id, $project_id, $file_name, $file_path, $user_id, $document_type);
            if ($result) {
                error_log("Документ успешно добавлен: project_id=$project_id, file_name=$file_name, file_path=$file_path");
                $_SESSION["success_msg"] = "Документ успешно добавлен.";
            } else {
                // Если не удалось добавить в БД, удаляем загруженный файл
                if (file_exists($targetFile)) {
                    unlink($targetFile);
                    error_log("Файл удалён из-за ошибки добавления в БД: $targetFile");
                }
                error_log("Ошибка при добавлении документа в БД: " . $conn->error);
                $_SESSION["error_msg"] = "Ошибка при добавлении документа в БД.";
            }
        } else {
            error_log("Ошибка загрузки файла: " . $_FILES["document_file"]["error"]);
            $_SESSION["error_msg"] = "Ошибка загрузки файла.";
        }
    } else {
        error_log("Файл не выбран или произошла ошибка при загрузке: " . ($_FILES["document_file"]["error"] ?? 'неизвестная ошибка'));
        $_SESSION["error_msg"] = "Файл не выбран или произошла ошибка при загрузке.";
    }
    header("Location: ../pages/project_details.php?id=" . $project_id);
    exit();
} else {
    error_log("Ошибка: запрос не является POST.");
    header("Location: ../index.php");
    exit();
}
?>