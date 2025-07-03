<?php
// process/process_add_participant.php
session_start();
require_once '../db.php';

// Доступ только для админа и менеджера
if (!isset($_SESSION["user_id"]) || !in_array($_SESSION["role_id"], [1, 2])) {
    header("Location: index.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $user_id = $_SESSION["user_id"];
    $project_id = intval($_POST["project_id"]);
    $project_role = trim($_POST["project_role"]);
    
    if (!isset($_POST["user_ids"]) || !is_array($_POST["user_ids"])) {
        $_SESSION["error_msg"] = "Не выбран ни один пользователь.";
        header("Location: ../add_participant.php?project_id=" . $project_id);
        exit();
    }
    
    $success = true;
    foreach ($_POST["user_ids"] as $target_user_id) {
        $target_user_id = intval($target_user_id);
        $result = addProjectParticipantMan($conn, $user_id, $project_id, $target_user_id, $project_role);
        if (!$result) {
            $success = false;
        }
    }
    
    if ($success) {
        $_SESSION["success_msg"] = "Участники успешно добавлены.";
    } else {
        $_SESSION["error_msg"] = "Ошибка при добавлении некоторых участников.";
    }
    header("Location: ../pages/project_details.php?id=" . $project_id);
    exit();
} else {
    header("Location: ../index.php");
    exit();
}
?>
