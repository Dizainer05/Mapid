<?php
session_start();
require_once '../db.php';

// Доступ только для админа (role_id == 1)
if (!isset($_SESSION["user_id"]) || $_SESSION["role_id"] != 1) {
    header("Location: index.php");
    exit();
}

// Проверяем, переданы ли нужные параметры
if (!isset($_GET["user_id"]) || !is_numeric($_GET["user_id"]) || !isset($_GET["project_id"])) {
    die("Некорректные данные.");
}

$user_id = intval($_GET["user_id"]);
$project_id = intval($_GET["project_id"]);
$admin_id = $_SESSION["user_id"];

// Удаляем пользователя из проекта
$query = "DELETE FROM project_participants WHERE user_id = ? AND project_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $user_id, $project_id);
$result = $stmt->execute();
$stmt->close();

if ($result) {
    $_SESSION["success_msg"] = "Участник успешно удален.";
} else {
    $_SESSION["error_msg"] = "Ошибка при удалении участника.";
}

header("Location: ../pages/project_details.php?id=" . $project_id);
exit();
?>
