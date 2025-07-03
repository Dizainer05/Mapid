<?php
// process/process_edit_stage.php
session_start();
require_once '../db.php';

// Доступ только для админа и менеджера
if (!isset($_SESSION["user_id"]) || !in_array($_SESSION["role_id"], [1, 2])) {
    header("Location: ../index.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $user_id = $_SESSION["user_id"];
    $stage_id = intval($_POST["stage_id"]);
    $project_id = intval($_POST["project_id"]);
    $stage_name = trim($_POST["stage_name"]);
    $description = trim($_POST["description"]);
    $start_date = trim($_POST["start_date"]);
    $end_date = trim($_POST["end_date"]);
    $status = trim($_POST["status"]);
    $stage_order = intval($_POST["stage_order"]);
    
    // Если роль admin, используем adminUpdateProjectStage, иначе updateProjectStage
    if ($_SESSION["role_id"] == 1) {
        $result = adminUpdateProjectStage($conn, $user_id, $stage_id, $stage_name, $description, $start_date, $end_date, $status, $stage_order);
    } else {
        $result = updateProjectStage($conn, $user_id, $stage_id, $stage_name, $description, $start_date, $end_date, $status, $stage_order);
    }
    
    if ($result) {
        $_SESSION["success_msg"] = "Этап успешно обновлен.";
    } else {
        $_SESSION["error_msg"] = "Ошибка обновления этапа.";
    }
    header("Location: ../project_details.php?id=" . $project_id);
    exit();
} else {
    header("Location: ../index.php");
    exit();
}
?>
