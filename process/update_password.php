<?php
// process/update_password.php
session_start();
require_once __DIR__ . '/../db.php';

// Проверяем, авторизован ли пользователь
if (!isset($_SESSION['user_id'])) {
    $_SESSION['error_msg'] = "Пользователь не авторизован.";
    header("Location: ../pages/auth.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Проверяем метод запроса
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error_msg'] = "Неверный метод запроса.";
    header("Location: ../pages/profile.php");
    exit();
}

// Получаем данные из формы
$old_password = trim($_POST['old_password'] ?? '');
$new_password = trim($_POST['new_password'] ?? '');

// Проверяем, что поля не пустые
if (empty($old_password) || empty($new_password)) {
    $_SESSION['error_msg'] = "Все поля должны быть заполнены.";
    header("Location: ../pages/profile.php");
    exit();
}

// Проверяем требования к новому паролю
if (strlen($new_password) < 8) {
    $_SESSION['error_msg'] = "Новый пароль должен содержать минимум 8 символов.";
    header("Location: ../pages/profile.php");
    exit();
}

if (!preg_match("/^(?=.*[A-Za-z])(?=.*\d).+$/", $new_password)) {
    $_SESSION['error_msg'] = "Новый пароль должен содержать как минимум одну букву и одну цифру.";
    header("Location: ../pages/profile.php");
    exit();
}

// Получаем текущий пароль пользователя из базы данных
$stmt = $conn->prepare("SELECT password FROM users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['error_msg'] = "Пользователь не найден.";
    header("Location: ../pages/profile.php");
    exit();
}

$user = $result->fetch_assoc();
$current_password_hash = $user['password'];
$stmt->close();

// Проверяем, что старый пароль введён правильно
if (!password_verify($old_password, $current_password_hash)) {
    $_SESSION['error_msg'] = "Старый пароль введён неверно.";
    header("Location: ../pages/profile.php");
    exit();
}

// Хешируем новый пароль
$new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);

// Обновляем пароль в базе данных
$stmt = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
$stmt->bind_param("si", $new_password_hash, $user_id);

if ($stmt->execute()) {
    $_SESSION['success_msg'] = "Пароль успешно обновлён.";
    error_log("Password updated successfully for user $user_id");
} else {
    $_SESSION['error_msg'] = "Ошибка при обновлении пароля: " . $stmt->error;
    error_log("Error updating password for user $user_id: " . $stmt->error);
}

$stmt->close();
$conn->close();

// Перенаправляем пользователя обратно на страницу профиля
header("Location: ../pages/profile.php");
exit();
?>