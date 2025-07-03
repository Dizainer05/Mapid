<?php
// process/process_registration.php
session_start();
require_once '../db.php';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Получаем и очищаем данные из формы регистрации
    $username   = trim($_POST["username"]);
    $password   = trim($_POST["password"]);
    $email      = trim($_POST["email"]);
    $full_name  = trim($_POST["full_name"]);
    $position   = trim($_POST["position"]);
    $department = trim($_POST["department"]);
    
    // По умолчанию назначаем роль "пользователь" (role_id = 4)
    $role_id = 4;
    // Если аватар не загружается, оставляем пустую строку
    $avatar = "";
    
    try {
        // Дополнительные проверки
        if (empty($username) || empty($password) || empty($email) || empty($full_name)) {
            throw new Exception("Все обязательные поля должны быть заполнены.");
        }

        // Проверка пароля: не короче 8 символов и содержит не менее 8 цифр
        $digitCount = preg_match_all('/\d/', $password);
        if (strlen($password) < 8 || $digitCount < 8) {
            throw new Exception("Пароль должен быть не короче 8 символов и содержать не менее 8 цифр.");
        }

        // Проверка, что ФИО не содержит цифр
        if (preg_match('/[0-9]/', $full_name)) {
            throw new Exception("ФИО не должно содержать цифры.");
        }

        // Проверка, что ФИО содержит только кириллицу и пробелы
        if (!preg_match('/^[А-Яа-яЁё\s]+$/u', $full_name)) {
            throw new Exception("ФИО должно содержать только кириллицу и пробелы.");
        }

        // Отладка: логируем входные данные
        error_log("Регистрация: username=$username, email=$email, full_name=$full_name, position=$position, department=$department");

        // Хэшируем пароль с использованием bcrypt
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
        
        // Вызываем функцию регистрации
        $result = registerUser($conn, $username, $hashedPassword, $email, $full_name, $role_id, $position, $department, $avatar);
        
        if ($result) {
            // Очищаем сохраненные данные и ошибки при успешной регистрации
            unset($_SESSION["form_data"]);
            unset($_SESSION["error_msg"]);
            $_SESSION["success_msg"] = "Регистрация прошла успешно! Теперь вы можете авторизоваться.";
            header("Location: ../pages/auth.php");
            exit();
        } else {
            throw new Exception("Ошибка при регистрации пользователя. Попробуйте ещё раз.");
        }
    } catch (Exception $e) {
        // Сохраняем введенные данные в сессию
        $_SESSION["form_data"] = [
            'username' => $username,
            'email' => $email,
            'full_name' => $full_name,
            'position' => $position,
            'department' => $department
        ];
        $_SESSION["error_msg"] = $e->getMessage();
        error_log("Ошибка регистрации: " . $e->getMessage());
        header("Location: ../pages/register.php");
        exit();
    }
} else {
    header("Location: ../pages/register.php");
    exit();
}
?>