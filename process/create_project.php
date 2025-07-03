<?php
session_start();
require_once '../db.php';
ini_set('error_log', __DIR__ . '/logcreate_project');

// Проверка прав доступа
if (!isset($_SESSION["user_id"]) || ($_SESSION["role_id"] != 1 && $_SESSION["role_id"] != 2)) {
    header("HTTP/1.1 403 Forbidden");
    exit(json_encode(['error' => 'Доступ запрещён']));
}

try {
    // Валидация обязательных полей
    $required = [
        'name' => 'Название проекта',
        'planned_start_date' => 'Планируемая дата начала',
        'status' => 'Статус проекта',
        'access_code' => 'Код доступа',
        'scale' => 'Масштаб проекта'
    ];

    $errors = [];
    foreach ($required as $field => $title) {
        if (empty($_POST[$field]) && $_POST[$field] !== '0') {
            $errors[] = "Поле '$title' обязательно для заполнения";
        }
    }

    // Проверка масштаба
    $valid_scales = ['small', 'medium', 'large', 'megaproject'];
    if (!isset($_POST['scale']) || !in_array($_POST['scale'], $valid_scales)) {
        $errors[] = "Недопустимый масштаб проекта: " . ($_POST['scale'] ?? 'не указано');
        error_log("create_project.php: Ошибка валидации scale: " . ($_POST['scale'] ?? 'не указано'));
    }

    // Проверка статуса с очисткой
    $status = trim($_POST['status'] ?? '');
    if (!in_array($status, ['planning', 'in_progress', 'on_hold'])) {
        $errors[] = "Недопустимый статус проекта: " . $status;
        error_log("create_project.php: Ошибка валидации status: " . $status);
    } else {
        error_log("create_project.php: Очищенное значение status: '$status', длина: " . strlen($status));
    }

    // Проверка дат
    if (!empty($_POST['planned_start_date']) && !empty($_POST['planned_end_date'])) {
        $planned_start = new DateTime($_POST['planned_start_date']);
        $planned_end = new DateTime($_POST['planned_end_date']);
        if ($planned_end < $planned_start) {
            $errors[] = "Планируемая дата завершения не может быть раньше даты начала";
        }
    }

    if (!empty($_POST['actual_start_date']) && !empty($_POST['actual_end_date'])) {
        $actual_start = new DateTime($_POST['actual_start_date']);
        $actual_end = new DateTime($_POST['actual_end_date']);
        if ($actual_end < $actual_start) {
            $errors[] = "Фактическая дата завершения не может быть раньше даты начала";
        }
    }

    // Проверка числовых полей
    if (!empty($_POST['planned_digitalization_level']) && (!is_numeric($_POST['planned_digitalization_level']) || $_POST['planned_digitalization_level'] < 0 || $_POST['planned_digitalization_level'] > 100)) {
        $errors[] = "Плановый уровень цифровизации должен быть числом от 0 до 100";
    }
    if (!empty($_POST['actual_digitalization_level']) && (!is_numeric($_POST['actual_digitalization_level']) || $_POST['actual_digitalization_level'] < 0 || $_POST['actual_digitalization_level'] > 100)) {
        $errors[] = "Фактический уровень цифровизации должен быть числом от 0 до 100";
    }
    if (!empty($_POST['planned_labor_costs']) && (!is_numeric($_POST['planned_labor_costs']) || $_POST['planned_labor_costs'] < 0)) {
        $errors[] = "Плановые трудозатраты должны быть неотрицательным числом";
    }
    if (!empty($_POST['actual_labor_costs']) && (!is_numeric($_POST['actual_labor_costs']) || $_POST['actual_labor_costs'] < 0)) {
        $errors[] = "Фактические трудозатраты должны быть неотрицательным числом";
    }
    if (!empty($_POST['planned_budget']) && (!is_numeric($_POST['planned_budget']) || $_POST['planned_budget'] < 0)) {
        $errors[] = "Плановый бюджет должен быть неотрицательным числом";
    }
    if (!empty($_POST['actual_budget']) && (!is_numeric($_POST['actual_budget']) || $_POST['actual_budget'] < 0)) {
        $errors[] = "Фактический бюджет должен быть неотрицательным числом";
    }

    // Проверка этапа жизненного цикла
    if (!empty($_POST['lifecycle_stage']) && !in_array($_POST['lifecycle_stage'], ['initiation', 'planning', 'execution', 'monitoring', 'closure'])) {
        $errors[] = "Недопустимый этап жизненного цикла";
    }

    if (!empty($errors)) {
        header("HTTP/1.1 400 Bad Request");
        exit(json_encode(['errors' => $errors]));
    }

    // Генерация кода доступа, если не указан
    $access_code = trim($_POST['access_code'] ?? '');
    if (empty($access)) {
        $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $access_code = '';
        for ($i = 0; $i < 10; $i++) {
            $access_code .= $characters[rand(0, strlen($characters) - 1)];
        }
    }

    // Обработка файла устава
    $charter_file_path = '';
    if (isset($_FILES['charter_file']) && $_FILES['charter_file']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../uploads/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
            error_log("create_project.php: Создана директория $upload_dir");
        }
        if (!is_writable($upload_dir)) {
            error_log("create_project.php: Директория $upload_dir не доступна для записи");
            header("HTTP/1.1 500 Internal Server Error");
            exit(json_encode(['error' => 'Нет прав на запись в директорию uploads']));
        }
        $file_name = time() . '_' . basename($_FILES['charter_file']['name']);
        $file_path = $upload_dir . $file_name;
        if (move_uploaded_file($_FILES['charter_file']['tmp_name'], $file_path)) {
            $charter_file_path = $file_path;
            error_log("create_project.php: Файл успешно сохранён: $file_path");
        } else {
            error_log("create_project.php: Ошибка перемещения файла: " . $_FILES['charter_file']['tmp_name']);
            header("HTTP/1.1 500 Internal Server Error");
            exit(json_encode(['error' => 'Ошибка при сохранении файла']));
        }
    } elseif (isset($_FILES['charter_file']) && $_FILES['charter_file']['error'] !== UPLOAD_ERR_NO_FILE) {
        error_log("create_project.php: Ошибка загрузки файла, код: " . $_FILES['charter_file']['error']);
        header("HTTP/1.1 400 Bad Request");
        exit(json_encode(['error' => 'Ошибка загрузки файла: ' . $_FILES['charter_file']['error']]));
    } else {
        error_log("create_project.php: Файл не был загружен");
    }

    // Подготовка данных
    $data = [
        'user_id' => (int)$_SESSION['user_id'],
        'name' => htmlspecialchars(trim($_POST['name'])),
        'short_name' => htmlspecialchars(trim($_POST['short_name'] ?? '')),
        'description' => htmlspecialchars(trim($_POST['description'] ?? '')),
        'planned_start_date' => !empty($_POST['planned_start_date']) ? $_POST['planned_start_date'] : null,
        'actual_start_date' => !empty($_POST['actual_start_date']) ? $_POST['actual_start_date'] : null,
        'planned_end_date' => !empty($_POST['planned_end_date']) ? $_POST['planned_end_date'] : null,
        'actual_end_date' => !empty($_POST['actual_end_date']) ? $_POST['actual_end_date'] : null,
        'planned_budget' => !empty($_POST['planned_budget']) && is_numeric($_POST['planned_budget']) ? (float)$_POST['planned_budget'] : null,
        'actual_budget' => !empty($_POST['actual_budget']) && is_numeric($_POST['actual_budget']) ? (float)$_POST['actual_budget'] : null,
        'planned_digitalization' => !empty($_POST['planned_digitalization_level']) && is_numeric($_POST['planned_digitalization_level']) ? (int)$_POST['planned_digitalization_level'] : null,
        'actual_digitalization' => !empty($_POST['actual_digitalization_level']) && is_numeric($_POST['actual_digitalization_level']) ? (int)$_POST['actual_digitalization_level'] : null,
        'planned_labor' => !empty($_POST['planned_labor_costs']) && is_numeric($_POST['planned_labor_costs']) ? (int)$_POST['planned_labor_costs'] : null,
        'actual_labor' => !empty($_POST['actual_labor_costs']) && is_numeric($_POST['actual_labor_costs']) ? (int)$_POST['actual_labor_costs'] : null,
        'status' => $status,
        'lifecycle_stage' => !empty($_POST['lifecycle_stage']) ? $_POST['lifecycle_stage'] : null,
        'scale' => $_POST['scale'],
        'charter_file_path' => $charter_file_path,
        'expected_resources' => htmlspecialchars(trim($_POST['expected_resources'] ?? '')),
        'access_code' => $access_code
    ];

    // Логирование данных перед вызовом процедуры
    error_log("create_project.php: Путь к файлу перед сохранением: '$charter_file_path'");
    error_log("create_project.php: Данные для createProject: " . json_encode($data));

    // Вызов процедуры создания проекта
    $result = createProject($conn, ...array_values($data));

    if ($result) {
        $project_id = $conn->insert_id; // Получаем ID созданного проекта
        // Проверка, сохранён ли путь в базе
        $check_stmt = $conn->prepare("SELECT charter_file_path FROM projects WHERE project_id = ?");
        $check_stmt->bind_param("i", $project_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result()->fetch_assoc();
        $saved_path = $check_result['charter_file_path'] ?? '';
        error_log("create_project.php: Путь к файлу после сохранения в БД: '$saved_path'");
        $check_stmt->close();

        echo json_encode(['success' => true, 'project_id' => $project_id]);
    } else {
        throw new Exception("Ошибка при создании проекта: " . $conn->error);
    }

} catch (Exception $e) {
    error_log("create_project.php: Общая ошибка: " . $e->getMessage());
    header("HTTP/1.1 400 Bad Request");
    exit(json_encode(['error' => $e->getMessage()]));
}
?>