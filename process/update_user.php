<?php
// process/update_user.php
session_start();
require_once __DIR__ . '/../db.php';

// Функция для отправки JSON-ответа
function sendJsonResponse($success, $message = null, $data = null) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

// Проверяем, авторизован ли пользователь
if (!isset($_SESSION["user_id"])) {
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        sendJsonResponse(false, "Вы не авторизованы.");
    } else {
        $_SESSION['error_msg'] = "Пользователь не авторизован.";
        header("Location: ../pages/auth.php");
        exit();
    }
}

$current_user_id = $_SESSION["user_id"];
$current_role_id = $_SESSION["role_id"];

// Проверяем метод запроса
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        sendJsonResponse(false, "Неверный метод запроса.");
    } else {
        $_SESSION['error_msg'] = "Неверный метод запроса.";
        header("Location: ../pages/profile.php");
        exit();
    }
}

// Определяем, является ли запрос AJAX
$is_ajax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

// Определяем, какого пользователя редактируем
$target_user_id = ($current_role_id == 1 && isset($_POST['user_id'])) ? (int)$_POST['user_id'] : $current_user_id;

if ($current_role_id != 1 && $target_user_id != $current_user_id) {
    if ($is_ajax) {
        sendJsonResponse(false, "У вас нет прав для редактирования этого пользователя.");
    } else {
        $_SESSION['error_msg'] = "У вас нет прав для редактирования этого пользователя.";
        header("Location: ../pages/profile.php");
        exit();
    }
}

// Обработка загрузки аватара
if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
    $fileTmpPath = $_FILES['avatar']['tmp_name'];
    $fileName = uniqid() . '_' . basename($_FILES['avatar']['name']);
    $uploadFileDir = __DIR__ . '/../Uploads/';
    $dest_path = $uploadFileDir . $fileName;

    if (!file_exists($uploadFileDir)) {
        if (!mkdir($uploadFileDir, 0777, true)) {
            if ($is_ajax) {
                sendJsonResponse(false, "Не удалось создать директорию для аватара.");
            } else {
                $_SESSION['error_msg'] = "Не удалось создать директорию для аватара.";
                header("Location: ../pages/profile.php");
                exit();
            }
        }
    }

    if (move_uploaded_file($fileTmpPath, $dest_path)) {
        $stmt = $conn->prepare("UPDATE users SET avatar = ? WHERE user_id = ?");
        $stmt->bind_param("si", $fileName, $target_user_id);
        if ($stmt->execute()) {
            if ($is_ajax) {
                sendJsonResponse(true, "Аватар успешно обновлён.");
            } else {
                $_SESSION['success_msg'] = "Аватар успешно обновлён.";
                header("Location: ../pages/profile.php");
                exit();
            }
        } else {
            if ($is_ajax) {
                sendJsonResponse(false, "Ошибка при обновлении аватара: " . $stmt->error);
            } else {
                $_SESSION['error_msg'] = "Ошибка при обновлении аватара: " . $stmt->error;
                header("Location: ../pages/profile.php");
                exit();
            }
        }
        $stmt->close();
    } else {
        if ($is_ajax) {
            sendJsonResponse(false, "Ошибка при загрузке аватара.");
        } else {
            $_SESSION['error_msg'] = "Ошибка при загрузке аватара.";
            header("Location: ../pages/profile.php");
            exit();
        }
    }
}

// Обработка обновления профиля
$username = trim($_POST['username'] ?? (isset($_POST['update_profile']) ? $_SESSION['username'] : ''));
$email = trim($_POST['email'] ?? '');
$full_name = trim($_POST['full_name'] ?? (isset($_POST['fullname']) ? trim($_POST['fullname']) : ''));
$password = trim($_POST['password'] ?? '');
$role_id = isset($_POST['role_id']) ? (int)$_POST['role_id'] : null;
$position = trim($_POST['position'] ?? '');
$department = trim($_POST['department'] ?? '');

// Валидация
$errors = [];
if (empty($username)) {
    $errors[] = "Имя пользователя обязательно.";
}
if (empty($email)) {
    $errors[] = "Email обязателен.";
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = "Неверный формат email.";
}
if (empty($full_name)) {
    $errors[] = "ФИО обязательно.";
} elseif (!preg_match('/^[А-ЯЁа-яё\s-]+$/u', $full_name)) {
    $errors[] = "ФИО должно содержать только кириллические символы, пробелы или дефис.";
}
if ($password && strlen($password) < 8) {
    $errors[] = "Пароль должен содержать минимум 8 символов.";
}
if ($password && !preg_match('/^(?=.*[A-Za-z])(?=.*\d).+$/', $password)) {
    $errors[] = "Пароль должен содержать как минимум одну букву и одну цифру.";
}
if ($current_role_id == 1 && isset($_POST['role_id']) && !in_array($role_id, [1, 2, 3, 4])) {
    $errors[] = "Недопустимая роль.";
}

if (!empty($errors)) {
    if ($is_ajax) {
        sendJsonResponse(false, implode(" ", $errors));
    } else {
        $_SESSION['error_msg'] = implode(" ", $errors);
        header("Location: ../pages/profile.php");
        exit();
    }
}

// Проверка уникальности email
$stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
$stmt->bind_param("si", $email, $target_user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    if ($is_ajax) {
        sendJsonResponse(false, "Этот email уже используется другим пользователем.");
    } else {
        $_SESSION['error_msg'] = "Этот email уже используется другим пользователем.";
        header("Location: ../pages/profile.php");
        exit();
    }
}
$stmt->close();

// Проверка уникальности username
$stmt = $conn->prepare("SELECT user_id FROM users WHERE username = ? AND user_id != ?");
$stmt->bind_param("si", $username, $target_user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    if ($is_ajax) {
        sendJsonResponse(false, "Это имя пользователя уже занято.");
    } else {
        $_SESSION['error_msg'] = "Это имя пользователя уже занято.";
        header("Location: ../pages/profile.php");
        exit();
    }
}
$stmt->close();

// Формируем запрос на обновление
$update_fields = [];
$update_values = [];
$types = '';

$update_fields[] = "username = ?";
$update_values[] = $username;
$types .= 's';

$update_fields[] = "email = ?";
$update_values[] = $email;
$types .= 's';

$update_fields[] = "full_name = ?";
$update_values[] = $full_name;
$types .= 's';

$update_fields[] = "position = ?";
$update_values[] = $position;
$types .= 's';

$update_fields[] = "department = ?";
$update_values[] = $department;
$types .= 's';

if ($password && $current_role_id == 1) {
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    $update_fields[] = "password = ?";
    $update_values[] = $hashed_password;
    $types .= 's';
}

if ($current_role_id == 1 && $role_id) {
    $update_fields[] = "role_id = ?";
    $update_values[] = $role_id;
    $types .= 'i';
}

$update_values[] = $target_user_id;
$types .= 'i';

$sql = "UPDATE users SET " . implode(', ', $update_fields) . " WHERE user_id = ?";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    if ($is_ajax) {
        sendJsonResponse(false, "Ошибка подготовки запроса: " . $conn->error);
    } else {
        $_SESSION['error_msg'] = "Ошибка подготовки запроса: " . $conn->error;
        header("Location: ../pages/profile.php");
        exit();
    }
}
$stmt->bind_param($types, ...$update_values);

if ($stmt->execute()) {
    if ($is_ajax) {
        sendJsonResponse(true, "Данные пользователя успешно обновлены.");
    } else {
        $_SESSION['success_msg'] = "Данные успешно обновлены.";
        header("Location: ../pages/profile.php");
        exit();
    }
} else {
    if ($is_ajax) {
        sendJsonResponse(false, "Ошибка при обновлении данных: " . $stmt->error);
    } else {
        $_SESSION['error_msg'] = "Ошибка при обновлении данных: " . $stmt->error;
        header("Location: ../pages/profile.php");
        exit();
    }
}
$stmt->close();
?>