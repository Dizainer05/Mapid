<?php
require_once __DIR__ . '/../db.php';

// Получение фильтров из GET-запроса
$filters = [
    'name' => $_GET['name'] ?? '',
    'budget_min' => $_GET['budget_min'] ?? 0,
    'budget_max' => $_GET['budget_max'] ?? PHP_INT_MAX,
    'status' => $_GET['status'] ?? '',
    'start_date' => $_GET['start_date'] ?? '',
    'end_date' => $_GET['end_date'] ?? ''
];

// Формирование SQL-запроса
$sql = "
    SELECT 
        p.project_id,
        p.name,
        p.status,
        p.planned_budget,
        p.planned_start_date,
        p.planned_end_date,
        COUNT(pp.user_id) as participants
    FROM projects p
    LEFT JOIN project_participants pp ON p.project_id = pp.project_id
    WHERE 1=1
";

$params = [];
$types = '';

if (!empty($filters['name'])) {
    $sql .= " AND p.name LIKE ?";
    $params[] = "%{$filters['name']}%";
    $types .= 's';
}
if (!empty($filters['budget_min'])) {
    $sql .= " AND p.planned_budget >= ?";
    $params[] = (float)$filters['budget_min'];
    $types .= 'd';
}
if (!empty($filters['budget_max'])) {
    $sql .= " AND p.planned_budget <= ?";
    $params[] = (float)$filters['budget_max'];
    $types .= 'd';
}
if (!empty($filters['status'])) {
    $sql .= " AND p.status = ?";
    $params[] = $filters['status'];
    $types .= 's';
}
if (!empty($filters['start_date'])) {
    $sql .= " AND p.planned_start_date >= ?";
    $params[] = $filters['start_date'];
    $types .= 's';
}
if (!empty($filters['end_date'])) {
    $sql .= " AND p.planned_end_date <= ?";
    $params[] = $filters['end_date'];
    $types .= 's';
}

$sql .= " GROUP BY p.project_id";

// Подготовка и выполнение запроса
$stmt = $conn->prepare($sql);
if ($stmt === false) {
    error_log("Ошибка подготовки запроса search_projects: " . $conn->error);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Ошибка подготовки запроса']);
    exit;
}

if ($params) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();
$projects = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Логирование результата
error_log("search_projects.php: Возвращено проектов: " . count($projects));

// Убедимся, что выводится только JSON
header('Content-Type: application/json');
echo json_encode($projects);
exit;
?>