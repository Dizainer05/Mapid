<?php
// process/process_login.php
session_start();
require_once '../db.php';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Получаем данные формы
    $username = trim($_POST["username"]);
    $password = trim($_POST["password"]);
    
    try {
        // Проверяем, что поля заполнены
        if (empty($username) || empty($password)) {
            throw new Exception("Логин и пароль обязательны для заполнения.");
        }

        // Вызываем функцию аутентификации
        $user = authenticateUser($conn, $username);
        
        if ($user === null) {
            // Логин не существует
            throw new Exception("Логин '$username' не существует.");
        }
        
        if (!password_verify($password, $user["password"])) {
            // Неверный пароль
            throw new Exception("Неверный пароль.");
        }
        
        // Успешная аутентификация: сохраняем данные пользователя в сессии
        $_SESSION["user_id"]   = $user["user_id"];
        $_SESSION["username"]  = $user["username"];
        $_SESSION["role_id"]   = $user["role_id"];
        if (isset($user["avatar"])) {
            $_SESSION["avatar"] = $user["avatar"];
        }
        
        // Опционально: устанавливаем куки на 30 дней для "запоминания" пользователя
        setcookie("user_id", $user["user_id"], time() + (30 * 24 * 60 * 60), "/");
        setcookie("username", $user["username"], time() + (30 * 24 * 60 * 60), "/");
        
        // Очищаем сохраненные данные формы
        unset($_SESSION["form_data"]);
        
        // Перенаправляем на главную страницу
        header("Location: ../index.php");
        exit();
    } catch (Exception $e) {
        // Сохраняем логин в сессии для повторного отображения
        $_SESSION["form_data"] = [
            'username' => $username
        ];
        $_SESSION["error_msg"] = $e->getMessage();
        error_log("Ошибка авторизации: " . $e->getMessage());
        header("Location: ../pages/auth.php");
        exit();
    }
} else {
    header("Location: ../pages/auth.php");
    exit();
}
?>