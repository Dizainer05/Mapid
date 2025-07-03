<?php
session_start();
require_once __DIR__ . '/../db.php';

header('Content-Type: application/json');

if ($_SESSION['role_id'] != 1) {
    echo json_encode(['success' => false, 'error' => 'Доступ запрещён']);
    exit;
}

$errors = [];

$username = trim($_POST['username'] ?? '');
$password = trim($_POST['password'] ?? '');
$email = trim($_POST['email'] ?? '');
$full_name = trim($_POST['full_name'] ?? '');
$role_id = trim($_POST['role_id'] ?? '');
$position = trim($_POST['position'] ?? '');
$department = trim($_POST['department'] ?? '');
$avatar = 'default_avatar.jpg'; // Default avatar
$created_at = date('Y-m-d H:i:s'); // Current timestamp
$updated_at = $created_at;

// Валидация
if (empty($username)) {
    $errors[] = 'Имя пользователя обязательно';
} elseif (strlen($username) > 50) {
    $errors[] = 'Имя пользователя не должно превышать 50 символов';
} else {
    $stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $count = $stmt->get_result()->fetch_row()[0];
    $stmt->close();
    if ($count > 0) {
        $errors[] = 'Имя пользователя уже занято';
    }
}

if (empty($password)) {
    $errors[] = 'Пароль обязателен';
} elseif (strlen($password) > 255) {
    $errors[] = 'Пароль слишком длинный';
}

if (empty($email)) {
    $errors[] = 'Электронная почта обязательна';
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Недопустимый формат электронной почты';
} elseif (strlen($email) > 100) {
    $errors[] = 'Электронная почта не должна превышать 100 символов';
} else {
    $stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $count = $stmt->get_result()->fetch_row()[0];
    $stmt->close();
    if ($count > 0) {
        $errors[] = 'Электронная почта уже используется';
    }
}

if (empty($full_name)) {
    $errors[] = 'ФИО обязательно';
} elseif (strlen($full_name) > 100) {
    $errors[] = 'ФИО не должно превышать 100 символов';
}

if (empty($role_id)) {
    $errors[] = 'Роль обязательна';
} elseif (!in_array($role_id, ['1', '2', '3', '4'])) {
    $errors[] = 'Недопустимая роль';
}

if (!empty($position) && strlen($position) > 100) {
    $errors[] = 'Должность не должна превышать 100 символов';
}

if (!empty($department) && strlen($department) > 100) {
    $errors[] = 'Подразделение не должно превышать 100 символов';
}

if (!empty($errors)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'errors' => $errors]);
    exit;
}

// Хеширование пароля
$password_hash = password_hash($password, PASSWORD_DEFAULT);

// Вставка пользователя
$stmt = $conn->prepare("INSERT INTO users (username, password, email, full_name, role_id, position, department, avatar, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
$stmt->bind_param("ssssisssss", $username, $password_hash, $email, $full_name, $role_id, $position, $department, $avatar, $created_at, $updated_at);

if ($stmt->execute()) {
    $stmt->close();
    echo json_encode(['success' => true]);
} else {
    $stmt->close();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Ошибка при создании пользователя: ' . $conn->error]);
}

$conn->close();
?>