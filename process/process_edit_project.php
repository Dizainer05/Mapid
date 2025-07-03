<?php
// process/process_edit_project.php
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

// Получаем ID проекта из POST
if (!isset($_POST["project_id"]) || !is_numeric($_POST["project_id"])) {
    error_log("Ошибка: некорректный идентификатор проекта.");
    $_SESSION["error_msg"] = "Некорректный идентификатор проекта.";
    header("Location: ../index.php");
    exit();
}
$project_id = intval($_POST["project_id"]);
error_log("Получен project_id: $project_id");

// Данные пользователя
$user_id = $_SESSION["user_id"];
$role_id = $_SESSION["role_id"];
error_log("Пользователь: user_id=$user_id, role_id=$role_id");

// Получаем проектную роль пользователя
$project_role = null;
$sql = "SELECT project_role FROM project_participants WHERE project_id = ? AND user_id = ?";
$stmt = $conn->prepare($sql);
if ($stmt === false) {
    error_log("Ошибка подготовки запроса (project_role): " . $conn->error);
    $_SESSION["error_msg"] = "Ошибка сервера: не удалось проверить доступ.";
    header("Location: ../index.php");
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

// Если пользователь не является участником проекта, но имеет глобальную роль admin или manager
if ($project_role === null && ($role_id == 1 || $role_id == 2)) {
    $project_role = ($role_id == 1) ? 'admin' : 'manager';
    error_log("Роль присвоена на основе глобальной роли: $project_role");
}

// Проверяем, имеет ли пользователь право доступа (должен быть admin или manager в проекте)
if ($project_role !== 'admin' && $project_role !== 'manager') {
    error_log("Ошибка: у пользователя нет прав для редактирования проекта (project_role: $project_role).");
    $_SESSION["error_msg"] = "У вас нет прав для редактирования проекта.";
    header("Location: ../index.php");
    exit();
}

// Обработка формы
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    error_log("Начало обработки формы для project_id=$project_id");

    // Получаем текущие данные проекта
    $current_project = getProjectDetails($conn, $project_id);
    if (!$current_project) {
        error_log("Ошибка: проект с ID $project_id не найден.");
        $_SESSION["error_msg"] = "Проект не найден.";
        header("Location: ../index.php");
        exit();
    }
    error_log("Текущие данные проекта: " . json_encode($current_project));

    // Инициализируем значения текущими данными из базы
    $name = !empty(trim($_POST["name"])) ? trim($_POST["name"]) : $current_project["name"];
    $short_name = !empty(trim($_POST["short_name"])) ? trim($_POST["short_name"]) : $current_project["short_name"];
    $description = !empty(trim($_POST["description"])) ? trim($_POST["description"]) : $current_project["description"];
    $planned_start_date = !empty(trim($_POST["planned_start_date"])) ? trim($_POST["planned_start_date"]) : $current_project["planned_start_date"];
    $actual_start_date = !empty(trim($_POST["actual_start_date"])) ? trim($_POST["actual_start_date"]) : $current_project["actual_start_date"];
    $planned_end_date = !empty(trim($_POST["planned_end_date"])) ? trim($_POST["planned_end_date"]) : $current_project["planned_end_date"];
    $actual_end_date = !empty(trim($_POST["actual_end_date"])) ? trim($_POST["actual_end_date"]) : $current_project["actual_end_date"];
    
    // Дополнительные поля
    $planned_budget = isset($_POST["planned_budget"]) && trim($_POST["planned_budget"]) !== '' ? floatval($_POST["planned_budget"]) : $current_project["planned_budget"];
    $actual_budget = isset($_POST["actual_budget"]) && trim($_POST["actual_budget"]) !== '' ? floatval($_POST["actual_budget"]) : $current_project["actual_budget"];
    $planned_digitalization = isset($_POST["planned_digitalization"]) && trim($_POST["planned_digitalization"]) !== '' ? floatval($_POST["planned_digitalization"]) : $current_project["planned_digitalization_level"];
    $actual_digitalization = isset($_POST["actual_digitalization"]) && trim($_POST["actual_digitalization"]) !== '' ? floatval($_POST["actual_digitalization"]) : $current_project["actual_digitalization_level"];
    $planned_labor = isset($_POST["planned_labor"]) && trim($_POST["planned_labor"]) !== '' ? floatval($_POST["planned_labor"]) : $current_project["planned_labor_costs"];
    $actual_labor = isset($_POST["actual_labor"]) && trim($_POST["actual_labor"]) !== '' ? floatval($_POST["actual_labor"]) : $current_project["actual_labor_costs"];
    $status = !empty(trim($_POST["status"])) ? trim($_POST["status"]) : $current_project["status"];
    $lifecycle_stage = !empty(trim($_POST["lifecycle_stage"])) ? trim($_POST["lifecycle_stage"]) : $current_project["lifecycle_stage"];
    $scale = !empty(trim($_POST["scale"])) ? trim($_POST["scale"]) : $current_project["scale"];
    error_log("Значение scale перед сохранением: $scale");
    
    // Путь к уставу
    $charter_file_path = $current_project["charter_file_path"] ?? '';
    if (isset($_FILES["charter_file"]) && $_FILES["charter_file"]["error"] == UPLOAD_ERR_OK) {
        $uploadDir = "../uploads/";
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        $filename = basename($_FILES["charter_file"]["name"]);
        $targetFile = $uploadDir . time() . "_" . $filename;
        if (move_uploaded_file($_FILES["charter_file"]["tmp_name"], $targetFile)) {
            $charter_file_path = $targetFile;
            error_log("Файл устава успешно загружен: $charter_file_path");
        } else {
            error_log("Ошибка при загрузке файла устава: " . $_FILES["charter_file"]["error"]);
            $_SESSION["error_msg"] = "Ошибка при загрузке файла устава.";
            header("Location: ../edit_project.php?id=" . $project_id);
            exit();
        }
    }
    
    $expected_resources = !empty(trim($_POST["expected_resources"])) ? trim($_POST["expected_resources"]) : $current_project["expected_resources"];
    $access_code = !empty(trim($_POST["access_code"])) ? trim($_POST["access_code"]) : $current_project["access_code"];
    $start_date = !empty(trim($_POST["start_date"])) ? trim($_POST["start_date"]) : $current_project["start_date"];
    
    // Валидация данных на стороне сервера
    $errors = [];
    if ($planned_budget < 0) {
        $errors[] = "Плановый бюджет не может быть отрицательным.";
    }
    if ($actual_budget < 0) {
        $errors[] = "Фактический бюджет не может быть отрицательным.";
    }
    if ($planned_digitalization < 0) {
        $errors[] = "Плановый уровень цифровизации не может быть отрицательным.";
    }
    if ($actual_digitalization < 0) {
        $errors[] = "Фактический уровень цифровизации не может быть отрицательным.";
    }
    if ($planned_labor < 0) {
        $errors[] = "Плановые затраты на труд не могут быть отрицательными.";
    }
    if ($actual_labor < 0) {
        $errors[] = "Фактические затраты на труд не могут быть отрицательными.";
    }

    // Если есть ошибки, возвращаем пользователя на страницу редактирования
    if (!empty($errors)) {
        $_SESSION["error_msg"] = implode("<br>", $errors);
        header("Location: ../pages/edit_project.php?id=" . $project_id);
        exit();
    }
    
    // Логируем данные перед обновлением
    error_log("Обновление проекта ID $project_id: " . json_encode([
        'name' => $name,
        'short_name' => $short_name,
        'description' => $description,
        'planned_start_date' => $planned_start_date,
        'actual_start_date' => $actual_start_date,
        'planned_end_date' => $planned_end_date,
        'actual_end_date' => $actual_end_date,
        'planned_budget' => $planned_budget,
        'actual_budget' => $actual_budget,
        'planned_digitalization' => $planned_digitalization,
        'actual_digitalization' => $actual_digitalization,
        'planned_labor' => $planned_labor,
        'actual_labor' => $actual_labor,
        'status' => $status,
        'lifecycle_stage' => $lifecycle_stage,
        'scale' => $scale,
        'charter_file_path' => $charter_file_path,
        'expected_resources' => $expected_resources,
        'access_code' => $access_code,
        'start_date' => $start_date
    ]));

    // Обновляем проект с обработкой исключений
    try {
        $result = updateProject(
            $conn,
            $user_id,
            $project_id,
            $name,
            $short_name,
            $description,
            $planned_start_date,
            $actual_start_date,
            $planned_end_date,
            $actual_end_date,
            $planned_budget,
            $actual_budget,
            $planned_digitalization,
            $actual_digitalization,
            $planned_labor,
            $actual_labor,
            $status,
            $lifecycle_stage,
            $scale,
            $charter_file_path,
            $expected_resources,
            $access_code,
            $start_date
        );
        
        if ($result) {
            error_log("Проект ID $project_id успешно обновлен.");
            $_SESSION["success_msg"] = "Проект успешно обновлен.";
            header("Location: ../pages/project_details.php?id=" . $project_id);
            exit();
        } else {
            error_log("Ошибка обновления проекта ID $project_id: " . $conn->error);
            $_SESSION["error_msg"] = "Ошибка обновления проекта: " . $conn->error;
            header("Location: ../pages/edit_project.php?id=" . $project_id);
            exit();
        }
    } catch (mysqli_sql_exception $e) {
        error_log("Ошибка базы данных: " . $e->getMessage());
        $_SESSION["error_msg"] = "Ошибка базы данных: нарушение ограничений.";
        header("Location: ../pages/edit_project.php?id=" . $project_id);
        exit();
    }
} else {
    error_log("Ошибка: запрос не является POST.");
    header("Location: ../index.php");
    exit();
}
?>