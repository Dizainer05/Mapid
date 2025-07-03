<?php
session_start();
require_once '../db.php';

// Проверка авторизации
if (!isset($_SESSION["user_id"])) {
    echo json_encode(['success' => false, 'message' => 'Пользователь не авторизован']);
    exit();
}

$user_id = $_SESSION["user_id"];
$role_id = $_SESSION["role_id"];
$search = trim($_GET['search'] ?? '');
$status = isset($_GET['status']) ? trim($_GET['status']) : 'all';

// Логирование входящего запроса
error_log("ajax_project_search.php: Получен запрос: role_id=$role_id, user_id=$user_id, search='$search', status='$status'");

// Маппинг статусов для перевода
$statusTranslations = [
    'planning' => 'Планирование',
    'in_progress' => 'В процессе',
    'completed' => 'Завершено',
    'on_hold' => 'На паузе'
];

// Функция для получения проектов
function fetchProjects($conn, $sql, $params, $types, $statusTranslations) {
    error_log("ajax_project_search.php: Выполняется SQL: $sql, params=" . json_encode($params) . ", types=$types");
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("ajax_project_search.php: Ошибка подготовки запроса: " . $conn->error);
        return ['success' => false, 'message' => 'Ошибка подготовки запроса: ' . $conn->error];
    }
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    if (!$stmt->execute()) {
        error_log("ajax_project_search.php: Ошибка выполнения запроса: " . $stmt->error);
        return ['success' => false, 'message' => 'Ошибка выполнения запроса: ' . $stmt->error];
    }
    $result = $stmt->get_result();
    $projects = $result->fetch_all(MYSQLI_ASSOC);
    $result->free();
    $stmt->close();

    // Переводим статусы
    foreach ($projects as &$project) {
        $project['status_display'] = $statusTranslations[$project['status']] ?? $project['status'];
    }
    error_log("ajax_project_search.php: Найдено проектов: " . count($projects));
    return ['success' => true, 'projects' => $projects];
}

$activeProjects = [];
$completedProjects = [];

if ($role_id == 1 || $role_id == 2) {
    // Админ или менеджер: все проекты
    // Незавершённые проекты
    $sqlActive = "SELECT DISTINCT * FROM projects WHERE status != 'completed'";
    $params = [];
    $types = "";
    if (!empty($search)) {
        $sqlActive .= " AND (name LIKE ? OR short_name LIKE ? OR description LIKE ?)";
        $params = ["%$search%", "%$search%", "%$search%"];
        $types = "sss";
    }
    if ($status !== 'all') {
        $sqlActive .= " AND status = ?";
        $params[] = $status;
        $types .= "s";
    }
    $activeResult = fetchProjects($conn, $sqlActive, $params, $types, $statusTranslations);
    if ($activeResult['success']) {
        $activeProjects = $activeResult['projects'];
    } else {
        error_log("ajax_project_search.php: Ошибка загрузки активных проектов: " . $activeResult['message']);
    }

    // Завершённые проекты
    $sqlCompleted = "SELECT DISTINCT * FROM projects WHERE status = 'completed'";
    $params = [];
    $types = "";
    if (!empty($search)) {
        $sqlCompleted .= " AND (name LIKE ? OR short_name LIKE ? OR description LIKE ?)";
        $params = ["%$search%", "%$search%", "%$search%"];
        $types = "sss";
    }
    $completedResult = fetchProjects($conn, $sqlCompleted, $params, $types, $statusTranslations);
    if ($completedResult['success']) {
        $completedProjects = $completedResult['projects'];
    } else {
        error_log("ajax_project_search.php: Ошибка загрузки завершённых проектов: " . $completedResult['message']);
    }
} elseif ($role_id == 3 || $role_id == 4) {
    // Проверка, есть ли пользователь в project_participants
    $checkStmt = $conn->prepare("SELECT COUNT(*) as count FROM project_participants WHERE user_id = ?");
    $checkStmt->bind_param("i", $user_id);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result()->fetch_assoc();
    $participantCount = $checkResult['count'];
    $checkStmt->close();
    error_log("ajax_project_search.php: Пользователь user_id=$user_id имеет $participantCount записей в project_participants");

    if ($participantCount == 0) {
        echo json_encode([
            'success' => true,
            'activeProjects' => [],
            'completedProjects' => [],
            'message' => 'Вы не привязаны ни к одному проекту'
        ]);
        $conn->close();
        exit();
    }

    // Сотрудник или пользователь: только проекты, в которых участвует пользователь
    // Незавершённые проекты
    $sqlActive = "SELECT DISTINCT p.* FROM projects p INNER JOIN project_participants pp ON p.project_id = pp.project_id WHERE pp.user_id = ?";
    $params = [$user_id];
    $types = "i";
    if ($status !== 'all') {
        $sqlActive .= " AND p.status = ?";
        $params[] = $status;
        $types .= "s";
    } else {
        $sqlActive .= " AND p.status != 'completed'";
    }
    if (!empty($search)) {
        $sqlActive .= " AND (p.name LIKE ? OR p.short_name LIKE ? OR p.description LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $types .= "sss";
    }
    $activeResult = fetchProjects($conn, $sqlActive, $params, $types, $statusTranslations);
    if ($activeResult['success']) {
        $activeProjects = $activeResult['projects'];
    } else {
        error_log("ajax_project_search.php: Ошибка загрузки активных проектов (роль 3/4): " . $activeResult['message']);
    }

    // Завершённые проекты
    $sqlCompleted = "SELECT DISTINCT p.* FROM projects p INNER JOIN project_participants pp ON p.project_id = pp.project_id WHERE pp.user_id = ? AND p.status = 'completed'";
    $params = [$user_id];
    $types = "i";
    if (!empty($search)) {
        $sqlCompleted .= " AND (p.name LIKE ? OR p.short_name LIKE ? OR p.description LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $types .= "sss";
    }
    $completedResult = fetchProjects($conn, $sqlCompleted, $params, $types, $statusTranslations);
    if ($completedResult['success']) {
        $completedProjects = $completedResult['projects'];
    } else {
        error_log("ajax_project_search.php: Ошибка загрузки завершённых проектов (роль 3/4): " . $completedResult['message']);
    }
}

// Логирование результатов
error_log("ajax_project_search.php: activeProjects=" . json_encode(array_column($activeProjects, 'project_id')));
error_log("ajax_project_search.php: completedProjects=" . json_encode(array_column($completedProjects, 'project_id')));

// Формируем ответ
$response = [
    'success' => true,
    'activeProjects' => $activeProjects,
    'completedProjects' => $completedProjects
];

// Проверяем, есть ли проекты
if (empty($activeProjects) && empty($completedProjects)) {
    //$response['message'] = 'Проекты не найдены';
}

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
echo json_encode($response);
$conn->close();
?>