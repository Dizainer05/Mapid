<?php
session_start();
require_once 'db.php';

// Проверка авторизации
if (!isset($_SESSION["user_id"])) {
    header("Location: pages/auth.php");
    exit();
}

$user_id = $_SESSION["user_id"];
$role_id = $_SESSION["role_id"];
$username = $_SESSION["username"];
$search = $_GET['search'] ?? '';
$status = isset($_GET['status']) ? $_GET['status'] : 'all';

// Маппинг статусов для перевода
$statusTranslations = [
    'planning' => 'Планирование',
    'in_progress' => 'В процессе',
    'completed' => 'Завершено',
    'on_hold' => 'На паузе'
];

// Получаем аватар пользователя из базы данных
$sql = "SELECT avatar FROM users WHERE user_id = ?";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    error_log("index.php: Ошибка подготовки запроса для аватара: " . $conn->error);
    die("Ошибка сервера");
}
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
 $userAvatar = (!empty($user['avatar']) && file_exists(__DIR__ . '/uploads/' . $user['avatar'])) 
        ? $user['avatar'] 
        : 'default_avatar.jpg';
$result->free();
$stmt->close();

// Функция для получения проектов
function fetchProjects($conn, $sql, $params, $types, $statusTranslations) {
    error_log("index.php: Выполняется SQL: $sql, params=" . json_encode($params) . ", types=$types");
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("index.php: Ошибка подготовки запроса: " . $conn->error);
        return ['success' => false, 'message' => 'Ошибка подготовки запроса: ' . $conn->error];
    }
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $projects = $result->fetch_all(MYSQLI_ASSOC);
    $result->free();
    $stmt->close();

    // Переводим статусы
    foreach ($projects as &$project) {
        $project['status_display'] = $statusTranslations[$project['status']] ?? $project['status'];
    }
    error_log("index.php: Найдено проектов: " . count($projects));
    return ['success' => true, 'projects' => $projects];
}

// Получаем проекты
$activeProjects = [];
$completedProjects = [];

if ($role_id == 1 || $role_id == 2) {
    // Админ или менеджер: все проекты
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
        error_log("index.php: Ошибка загрузки активных проектов: " . $activeResult['message']);
    }

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
        error_log("index.php: Ошибка загрузки завершённых проектов: " . $completedResult['message']);
    }
} elseif ($role_id == 3 || $role_id == 4) {
    // Проверка, есть ли пользователь в project_participants
    $checkStmt = $conn->prepare("SELECT COUNT(*) as count FROM project_participants WHERE user_id = ?");
    $checkStmt->bind_param("i", $user_id);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result()->fetch_assoc();
    $participantCount = $checkResult['count'];
    $checkStmt->close();
    error_log("index.php: Пользователь user_id=$user_id имеет $participantCount записей в project_participants");

    // Обработка ввода кода приглашения
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["access_code"])) {
        $access_code = trim($_POST["access_code"]);
        $stmt = $conn->prepare("CALL sp_join_project(?, ?)");
        if (!$stmt) {
            error_log("index.php: Ошибка подготовки запроса sp_join_project: " . $conn->error);
            die("Ошибка сервера");
        }
        $stmt->bind_param("is", $user_id, $access_code);
        try {
            if ($stmt->execute()) {
                while ($stmt->more_results()) {
                    $stmt->next_result();
                    if ($result = $stmt->get_result()) {
                        while ($row = $result->fetch_assoc()) {
                            $project_id = $row['project_id'] ?? null;
                        }
                        $result->free();
                    }
                }
                $stmt->close();
                header("Location: " . $_SERVER['PHP_SELF']);
                exit();
            } else {
                throw new Exception($stmt->error);
            }
        } catch (Exception $e) {
            $error = $e->getMessage();
            $stmt->close();
            while ($conn->more_results()) {
                $conn->next_result();
            }
            $_SESSION['error_msg'] = "Ошибка присоединения к проекту: " . htmlspecialchars($error);
            error_log("index.php: Ошибка sp_join_project: $error, user_id=$user_id, access_code=$access_code");
        }
    }

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
        error_log("index.php: Ошибка загрузки активных проектов (роль 3/4): " . $activeResult['message']);
    }

    // Завершённые проекты
    $sqlCompleted = "SELECT DISTINCT p.* FROM projects p INNER JOIN project_participants pp ON p.project_id = pp.project_id WHERE pp.user_id = ? AND p.status = 'completed'";
    $params = [$user_id];
    $types = "i";
    if (!empty($search)) {
        $sqlCompleted .= " AND (p.name LIKE ? OR short_name LIKE ? OR description LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $types .= "sss";
    }
    $completedResult = fetchProjects($conn, $sqlCompleted, $params, $types, $statusTranslations);
    if ($completedResult['success']) {
        $completedProjects = $completedResult['projects'];
    } else {
        error_log("index.php: Ошибка загрузки завершённых проектов (роль 3/4): " . $completedResult['message']);
    }
}

// Логирование для отладки
error_log("index.php: role_id=$role_id, user_id=$user_id, search='$search', status='$status'");
error_log("index.php: activeProjects=" . json_encode(array_column($activeProjects, 'project_id')));
error_log("index.php: completedProjects=" . json_encode(array_column($completedProjects, 'project_id')));

?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Панель управления проектами</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/css/index.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        #completedProjectsTable {
            display: none;
        }
        .form-switch {
            margin-left: 10px;
        }
        .alert-error {
            margin-bottom: 20px;
        }
        .required::after {
            content: '*';
            color: red;
            margin-left: 4px;
        }
    </style>
</head>
<body>
    <!-- Навигационная панель -->
    <nav class="navbar navbar-expand-lg fixed-top shadow-sm">
        <div class="container-fluid">
            <label class="hamburger me-2">
                <input type="checkbox" id="burgerCheckbox" data-bs-toggle="offcanvas" data-bs-target="#offcanvasSidebar" aria-controls="offcanvasSidebar">
                <svg viewBox="0 0 32 32">
                    <path class="line line-top-bottom" d="M27 10 13 10C10.8 10 9 8.2 9 6 9 3.5 10.8 2 13 2 15.2 2 17 3.8 17 6L17 26C17 28.2 18.8 30 21 30 23.2 30 25 28.2 25 26 25 23.8 23.2 22 21 22L7 22"></path>
                    <path class="line" d="M7 16 27 16"></path>
                </svg>
            </label>
            <a class="navbar-brand" href="index.php">Панель управления</a>
            <div class="collapse navbar-collapse">
                <form class="d-flex ms-auto">
                    <input id="searchInput" class="form-control me-2" type="search" placeholder="Поиск проектов" value="<?= htmlspecialchars($search) ?>">
                    <select id="statusSelect" class="form-select me-2">
                        <option value="all" <?= $status == "all" ? "selected" : "" ?>>Все</option>
                        <option value="planning" <?= $status == "planning" ? "selected" : "" ?>>Планирование</option>
                        <option value="in_progress" <?= $status == "in_progress" ? "selected" : "" ?>>В процессе</option>
                        <option value="on_hold" <?= $status == "on_hold" ? "selected" : "" ?>>На паузе</option>
                    </select>
                </form>
            </div>
        </div>
    </nav>

    <!-- Боковая панель -->
    <div class="offcanvas offcanvas-start" tabindex="-1" id="offcanvasSidebar" aria-labelledby="offcanvasSidebarLabel">
        <div class="offcanvas-header">
            <h5 id="offcanvasSidebarLabel">Меню</h5>
            <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Закрыть"></button>
        </div>
        <div class="offcanvas-body">
            <div class="text-center mb-4">
                <a href="./pages/profile.php?user_id=<?= htmlspecialchars($user_id) ?>">
                    <img src="../uploads/<?= htmlspecialchars($userAvatar) ?>" alt="Avatar" class="rounded-circle" width="100" height="100" style="display: block; margin: 0 auto;">
                </a>
                <p class="mt-2"><?= htmlspecialchars($username) ?></p>
            </div>
            <ul class="list">
                <?php if ($role_id == 1): ?>
                    <li class="element" onclick="window.location.href='pages/admin_panel.php'">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#7e8590" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-user-plus">
                            <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                            <circle cx="8.5" cy="7" r="4"></circle>
                            <line x1="20" y1="8" x2="20" y2="14"></line>
                            <line x1="23" y1="11" x2="17" y2="11"></line>
                        </svg>
                        <p class="label">Админ-панель</p>
                    </li>
                <?php endif; ?>
                <!-- <li class="element" data-bs-toggle="modal" data-bs-target="#settingsModal">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#7e8590" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-settings">
                        <path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l-.43-.25a2 2 0 0 1 2 0l-.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l-.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"></path>
                        <circle r="3" cy="12" cx="12"></circle>
                    </svg>
                    <p class="label">Настройки</p>
                </li> -->
                <li class="element" onclick="window.location.href='process/logout.php'">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#7e8590" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-log-out">
                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                        <polyline points="16 17 21 12 16 7"></polyline>
                        <line x1="21" y1="12" x2="9" y2="12"></line>
                    </svg>
                    <p class="label">Выход</p>
                </li>
            </ul>
        </div>
    </div>

    <!-- Основной контент -->
    <div class="main-content-container">
        <?php if (isset($_SESSION['error_msg'])): ?>
            <div class="alert alert-danger alert-error">
                <?= htmlspecialchars($_SESSION['error_msg']) ?>
            </div>
            <?php unset($_SESSION['error_msg']); ?>
        <?php endif; ?>

        <?php if ($role_id == 3 || $role_id == 4): ?>
        <div class="access-code-container">
            <h3>Введите код приглашения</h3>
            <form method="post" action="">
                <div class="input-group mb-3">
                    <input type="text" name="access_code" class="form-control" placeholder="Код проекта" required>
                    <button class="btn btn-primary" type="submit">Войти</button>
                </div>
            </form>
        </div>
        <?php endif; ?>

        <!-- Кнопка "Создать проект" и свитч для завершённых проектов -->
        <div class="d-flex justify-content-end mb-3 align-items-center">
            <?php if ($role_id == 1 || $role_id == 2): ?>
            <button class="btn btn-primary me-2" data-bs-toggle="modal" data-bs-target="#createProjectModal">
                Создать проект
            </button>
            <?php endif; ?>
            <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" id="showCompletedSwitch">
                <label class="form-check-label" for="showCompletedSwitch">Показать завершённые</label>
            </div>
        </div>

        <!-- Таблица незавершённых проектов -->
        <div class="table-container table-responsive">
            <h4>Активные проекты</h4>
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Название</th>
                        <th>Статус</th>
                        <th>Плановый бюджет</th>
                        <th>Фактический бюджет</th>
                        <th>Действия</th>
                    </tr>
                </thead>
                <tbody id="projectTableBody">
                <?php if (empty($activeProjects)): ?>
                    <tr><td colspan="5">Проекты не найдены. <?php if ($role_id == 3 || $role_id == 4): ?>Возможно, вы не привязаны к проектам. Введите код приглашения.<?php endif; ?></td></tr>
                <?php else: ?>
                    <?php foreach ($activeProjects as $project): ?>
                    <tr id="project-<?= htmlspecialchars($project["project_id"]) ?>">
                        <td><?= htmlspecialchars($project["name"]) ?></td>
                        <td><?= htmlspecialchars($project["status_display"]) ?></td>
                        <td><?= htmlspecialchars($project["planned_budget"]) ?> BYN</td>
                        <td><?= htmlspecialchars($project["actual_budget"]) ?> BYN</td>
                        <td>
                            <a href="pages/project_details.php?id=<?= htmlspecialchars($project["project_id"]) ?>" class="btn btn-sm btn-info">Подробнее</a>
                            <?php if ($role_id == 1 || $role_id == 2): ?>
                            <button class="btn btn-sm btn-warning change-status-btn" data-bs-toggle="modal" data-bs-target="#changeStatusModal" data-project-id="<?= htmlspecialchars($project["project_id"]) ?>" data-current-status="<?= htmlspecialchars($project["status"]) ?>">Изменить статус</button>
                            <a href="pages/edit_project.php?id=<?= htmlspecialchars($project["project_id"]) ?>" class="btn btn-sm btn-primary">Изменить</a>
                            <?php endif; ?>
                            <?php if ($role_id == 3 || $role_id == 4): ?>
                            <button class="btn btn-sm btn-danger delete-project" data-project-id="<?= htmlspecialchars($project["project_id"]) ?>">Отвязаться</button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Таблица завершённых проектов -->
        <div class="table-container table-responsive" id="completedProjectsTable">
            <h4>Завершённые проекты</h4>
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Название</th>
                        <th>Статус</th>
                        <th>Плановый бюджет</th>
                        <th>Фактический бюджет</th>
                        <th>Действия</th>
                    </tr>
                </thead>
                <tbody id="completedProjectTableBody">
                <?php if (empty($completedProjects)): ?>
                    <tr><td colspan="5">Нет завершённых проектов.</td></tr>
                <?php else: ?>
                    <?php foreach ($completedProjects as $project): ?>
                    <tr id="project-<?= htmlspecialchars($project["project_id"]) ?>">
                        <td><?= htmlspecialchars($project["name"]) ?></td>
                        <td><?= htmlspecialchars($project["status_display"]) ?></td>
                        <td><?= htmlspecialchars($project["planned_budget"]) ?> BYN</td>
                        <td><?= htmlspecialchars($project["actual_budget"]) ?> BYN</td>
                        <td>
                            <a href="pages/project_details.php?id=<?= htmlspecialchars($project["project_id"]) ?>" class="btn btn-sm btn-info">Подробнее</a>
                            <?php if ($role_id == 1 || $role_id == 2): ?>
                            <button class="btn btn-sm btn-warning change-status-btn" data-bs-toggle="modal" data-bs-target="#changeStatusModal" data-project-id="<?= htmlspecialchars($project["project_id"]) ?>" data-current-status="<?= htmlspecialchars($project["status"]) ?>">Изменить статус</button>
                            <a href="pages/edit_project.php?id=<?= htmlspecialchars($project["project_id"]) ?>" class="btn btn-sm btn-primary">Изменить</a>
                            <?php endif; ?>
                            <?php if ($role_id == 3 || $role_id == 4): ?>
                            <button class="btn btn-sm btn-danger delete-project" data-project-id="<?= htmlspecialchars($project["project_id"]) ?>">Отвязаться</button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Модальное окно настроек -->
    <!-- <div class="modal fade" id="settingsModal" tabindex="-1" aria-labelledby="settingsModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 id="settingsModalLabel" class="modal-title">Настройки</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
                </div>
                <div class="modal-body">
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" id="themeSwitch_modal">
                        <label class="form-check-label" for="themeSwitch_modal">Темная тема</label>
                    </div>
                    <button id="deleteAccountBtn" class="btn btn-danger w-100">Удалить аккаунт</button>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Закрыть</button>
                </div>
            </div>
        </div>
    </div> -->

    <!-- Модальное окно изменения статуса -->
    <div class="modal fade" id="changeStatusModal" tabindex="-1" aria-labelledby="changeStatusModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="changeStatusModalLabel">Изменить статус проекта</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
                </div>
                <div class="modal-body">
                    <form id="changeStatusForm">
                        <input type="hidden" id="statusProjectId" name="project_id">
                        <div class="mb-3">
                            <label for="projectStatus" class="form-label">Новый статус</label>
                            <select class="form-select" id="projectStatus" name="status" required>
                                <option value="planning">Планирование</option>
                                <option value="in_progress">В процессе</option>
                                <option value="completed">Завершено</option>
                                <option value="on_hold">На паузе</option>
                            </select>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                    <button type="button" class="btn btn-primary" id="saveStatusBtn">Сохранить</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Модальное окно создания проекта -->
    <div class="modal fade" id="createProjectModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content form">
                <div class="modal-header">
                    <h5 class="modal-title title">Создание проекта</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="post" action="process/create_project.php" id="createProjectForm" enctype="multipart/form-data">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label required">Название проекта</label>
                            <input type="text" name="name" class="form-control input" maxlength="255" required placeholder="Например, Строительство жилого комплекса">
                            <small class="form-text text-muted">Осталось символов: <span id="nameChars">255</span></small>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label required">Планируемая дата начала</label>
                                <input type="date" name="planned_start_date" class="form-control input" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Планируемая дата завершения</label>
                                <input type="date" name="planned_end_date" class="form-control input">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Планируемый бюджет</label>
                            <input type="number" name="planned_budget" class="form-control input" step="0.01" min="0" placeholder="Например, 100000.00 BYN">
                        </div>
                        <div class="mb-3">
                            <label class="form-label required">Статус</label>
                            <select name="status" class="form-select input" required>
                                <option value="planning">Планирование</option>
                                <option value="in_progress">В процессе</option>
                                <option value="on_hold">На паузе</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Короткое название</label>
                            <input type="text" name="short_name" class="form-control input" maxlength="50" placeholder="Например, ЖК Солнечный">
                            <small class="form-text text-muted">Осталось символов: <span id="shortNameChars">50</span></small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Описание</label>
                            <textarea name="description" class="form-control input" rows="2" maxlength="1000" placeholder="Например, Проект по строительству жилого комплекса на 200 квартир"></textarea>
                            <small class="form-text text-muted">Осталось символов: <span id="descriptionChars">1000</span></small>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Фактическая дата начала</label>
                                <input type="date" name="actual_start_date" class="form-control input">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Фактическая дата завершения</label>
                                <input type="date" name="actual_end_date" class="form-control input">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Фактический бюджет</label>
                            <input type="number" name="actual_budget" class="form-control input" step="0.01" min="0" placeholder="Например, 95000.00 BYN">
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Уровень цифровизации (план)</label>
                                <input type="number" name="planned_digitalization_level" class="form-control input" min="0" max="100" placeholder="Например, 50">
                                <small class="form-text text-muted">0–30: низкий (Excel, PDF, ручное управление); 31–70: средний (BIM LOD 2-4, облачные системы); 71–100: высокий (BIM LOD 5+, IoT, AI).</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Уровень цифровизации (факт)</label>
                                <input type="number" name="actual_digitalization_level" class="form-control input" min="0" max="100" placeholder="Например, 45">
                                <small class="form-text text-muted">0–30: низкий (Excel, PDF, ручное управление); 31–70: средний (BIM LOD 2-4, облачные системы); 71–100: высокий (BIM LOD 5+, IoT, AI).</small>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Трудозатраты (план)</label>
                            <input type="number" name="planned_labor_costs" class="form-control input" min="0" placeholder="Например, 1000 часов">
                            <small class="form-text text-muted">В часах. Суммарное время, необходимое для выполнения задач (например, работа команды).</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Трудозатраты (факт)</label>
                            <input type="number" name="actual_labor_costs" class="form-control input" min="0" placeholder="Например, 950 часов">
                            <small class="form-text text-muted">В часах. Фактическое время, затраченное на задачи.</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Этап жизненного цикла</label>
                            <select name="lifecycle_stage" class="form-select input">
                                <option value="">Не выбрано</option>
                                <option value="initiation">Инициация</option>
                                <option value="planning">Планирование</option>
                                <option value="execution">Исполнение</option>
                                <option value="monitoring">Мониторинг</option>
                                <option value="closure">Завершение</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label required">Масштаб</label>
                            <select name="scale" class="form-select input" required>
                                <option value="small">Малый</option>
                                <option value="medium">Средний</option>
                                <option value="large">Крупный</option>
                                <option value="megaproject">Мегапроект</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Ресурсы</label>
                            <textarea name="expected_resources" class="form-control input" maxlength="500" placeholder="Например, 10 инженеров, 2 крана, BIM-софт"></textarea>
                            <small class="form-text text-muted">Осталось символов: <span id="resourcesChars">500</span></small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Файл устава</label>
                            <div class="file-upload-wrapper">
                                <div class="file-upload-icon">
                                    <div class="file-upload-front">
                                        <div class="file-upload-tab"></div>
                                        <div class="file-upload-body"></div>
                                    </div>
                                    <div class="file-upload-back file-upload-body"></div>
                                </div>
                                <label class="file-upload-button">
                                    <input type="file" name="charter_file" class="file-input" accept=".pdf,.doc,.docx" />
                                    Выберите файл
                                </label>
                            </div>
                            <small class="form-text text-muted">Поддерживаемые форматы: PDF, DOC, DOCX</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label required">Код доступа</label>
                            <div class="input-group">
                                <input type="text" name="access_code" id="accessCodeInput" class="form-control input" maxlength="20" required placeholder="Например, ABC123XYZ">
                               
                                <button type="button" class="btn btn-outline-secondary submit" onclick="generateAccessCode()">Сгенерировать</button>
                            </div>
                             <small class="form-text text-muted">Осталось символов: <span id="accessCodeChars">20</span></small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary submit" data-bs-dismiss="modal">Отмена</button>
                        <button type="submit" class="btn btn-primary submit">Создать проект</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.addEventListener("DOMContentLoaded", function() {
        // Проверка загрузки jQuery
        console.log('jQuery загружен:', typeof $ !== 'undefined' ? $.fn.jquery : 'не загружен');
        console.log('role_id:', <?= $role_id ?>, 'user_id:', <?= $user_id ?>);

        // Проверка наличия элементов в DOM
        const searchInput = document.getElementById('searchInput');
        const statusSelect = document.getElementById('statusSelect');
        console.log('searchInput найден:', !!searchInput);
        console.log('statusSelect найден:', !!statusSelect);

        const offcanvasElement = document.getElementById('offcanvasSidebar');
        const burgerCheckbox = document.getElementById('burgerCheckbox');
        offcanvasElement.addEventListener('hidden.bs.offcanvas', function() {
            burgerCheckbox.checked = false;
        });

        let theme = localStorage.getItem('darkMode') === 'true' ? 'dark-mode' : 'light-mode';
        document.body.classList.add(theme);
        document.body.classList.add("loaded");

        $("#themeSwitch_modal").prop("checked", localStorage.getItem('darkMode') === 'true');
        $("#themeSwitch_modal").on("change", function() {
            if ($(this).is(":checked")) {
                $("body").addClass("dark-mode").removeClass("light-mode");
                localStorage.setItem('darkMode', 'true');
            } else {
                $("body").removeClass("dark-mode").addClass("light-mode");
                localStorage.setItem('darkMode', 'false');
            }
        });

        // Свитч для показа завершённых проектов
        const showCompletedSwitch = document.getElementById('showCompletedSwitch');
        const completedProjectsTable = document.getElementById('completedProjectsTable');

        // Восстановление состояния переключателя из localStorage
        const savedShowCompleted = localStorage.getItem('showCompletedProjects') === 'true';
        showCompletedSwitch.checked = savedShowCompleted;
        completedProjectsTable.style.display = savedShowCompleted ? 'block' : 'none';
        console.log('Восстановлено состояние showCompletedSwitch:', savedShowCompleted);

        // Обработчик изменения состояния переключателя
        showCompletedSwitch.addEventListener('change', function() {
            const isChecked = this.checked;
            completedProjectsTable.style.display = isChecked ? 'block' : 'none';
            localStorage.setItem('showCompletedProjects', isChecked);
            console.log('Состояние showCompletedSwitch сохранено:', isChecked);
        });

        // Маппинг статусов для JavaScript
        const statusTranslations = {
            'planning': 'Планирование',
            'in_progress': 'В процессе',
            'completed': 'Завершено',
            'on_hold': 'На паузе'
        };

        // Поиск и фильтрация
        function refreshProjects() {
            const searchVal = $("#searchInput").val().trim();
            const statusVal = $("#statusSelect").val();
            console.log(`Отправка AJAX: search="${searchVal}", status="${statusVal}", role_id=<?= $role_id ?>, user_id=<?= $user_id ?>`);
            $.ajax({
                url: "process/ajax_project_search.php",
                method: "GET",
                data: { 
                    search: searchVal, 
                    status: statusVal,
                    role_id: <?= $role_id ?>,
                    user_id: <?= $user_id ?>
                },
                dataType: "json",
                cache: false,
                success: function(data) {
                    console.log('Данные от сервера:', JSON.stringify(data, null, 2));
                    if (data.success) {
                        renderProjects(data.activeProjects, '#projectTableBody');
                        renderProjects(data.completedProjects, '#completedProjectTableBody');
                        if (data.message && !data.activeProjects.length && !data.completedProjects.length) {
                            console.log('Проекты не найдены:', data.message);
                        }
                    } else {
                        console.error('Ошибка от сервера:', data.message);
                        alert(data.message || 'Ошибка загрузки проектов');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Ошибка AJAX:', xhr.status, status, error);
                    console.error('Ответ сервера:', xhr.responseText);
                    alert("Ошибка AJAX запроса: " + (xhr.responseJSON?.message || 'Неизвестная ошибка'));
                }
            });
        }

        // Привязка событий через делегирование
        $(document).on("keyup", "#searchInput", function() {
            console.log('Событие keyup на searchInput, значение:', $(this).val());
            refreshProjects();
        });
        $(document).on("change", "#statusSelect", function() {
            console.log('Событие change на statusSelect, значение:', $(this).val());
            refreshProjects();
        });

        // Инициализация фильтрации при загрузке
        console.log('Инициализация refreshProjects при загрузке страницы');
        refreshProjects();

        // Функция для рендеринга проектов
        function renderProjects(projects, tableBodySelector) {
            console.log(`Рендеринг ${projects.length} проектов в ${tableBodySelector}`);
            const tbody = $(tableBodySelector);
            tbody.empty();
            if (!Array.isArray(projects) || projects.length === 0) {
                const message = tableBodySelector === '#completedProjectTableBody' 
                    ? 'Нет завершённых проектов.' 
                    : 'Проекты не найдены. <?php if ($role_id == 3 || $role_id == 4): ?>Возможно, вы не привязаны к проектам. Введите код приглашения.<?php endif; ?>';
                tbody.append(`<tr><td colspan="5">${message}</td></tr>`);
                console.log(`Нет проектов для ${tableBodySelector}`);
                return;
            }
            projects.forEach(project => {
                if (!project.project_id) {
                    console.warn('Проект без project_id:', project);
                    return;
                }
                if ($(`#project-${project.project_id}`).length > 0) {
                    console.warn(`Проект с ID ${project.project_id} уже существует в DOM`);
                    $(`#project-${project.project_id}`).remove();
                }
                const row = `
                    <tr id="project-${project.project_id}">
                        <td>${project.name ? project.name : 'Без названия'}</td>
                        <td>${project.status_display ? project.status_display : 'Неизвестный статус'}</td>
                        <td>${project.planned_budget ? project.planned_budget + ' BYN' : '0 BYN'}</td>
                        <td>${project.actual_budget ? project.actual_budget + ' BYN' : '0 BYN'}</td>
                        <td>
                            <a href="pages/project_details.php?id=${project.project_id}" class="btn btn-sm btn-info">Подробнее</a>
                            <?php if ($role_id == 1 || $role_id == 2): ?>
                            <button class="btn btn-sm btn-warning change-status-btn" data-bs-toggle="modal" data-bs-target="#changeStatusModal" data-project-id="${project.project_id}" data-current-status="${project.status || ''}">Изменить статус</button>
                            <a href="pages/edit_project.php?id=${project.project_id}" class="btn btn-sm btn-primary">Изменить</a>
                            <?php endif; ?>
                            <?php if ($role_id == 3 || $role_id == 4): ?>
                            <button class="btn btn-sm btn-danger delete-project" data-project-id="${project.project_id}">Отвязаться</button>
                            <?php endif; ?>
                        </td>
                    </tr>
                `;
                tbody.append(row);
                console.log(`Добавлена строка для проекта ${project.project_id} в ${tableBodySelector}`);
            });
        }

        // Обработка кнопки изменения статуса
        $(document).on('click', '.change-status-btn', function() {
            const projectId = $(this).data('project-id');
            const currentStatus = $(this).data('current-status');
            $('#statusProjectId').val(projectId);
            $('#projectStatus').val(currentStatus);
        });

        // Сохранение нового статуса
        $('#saveStatusBtn').on('click', function() {
            const projectId = $('#statusProjectId').val();
            const newStatus = $('#projectStatus').val();
            if (!['planning', 'in_progress', 'completed', 'on_hold'].includes(newStatus)) {
                alert('Недопустимый статус');
                return;
            }
            $.ajax({
                url: 'process/update_project_status.php',
                method: 'POST',
                data: { project_id: projectId, status: newStatus },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        console.log(`Статус проекта ${projectId} обновлён на ${newStatus}`);
                        $('#changeStatusModal').modal('hide');
                        refreshProjects();
                    } else {
                        alert(response.message || 'Ошибка обновления статуса');
                    }
                },
                error: function(xhr) {
                    console.error('Ошибка AJAX при обновлении статуса:', xhr);
                    alert('Ошибка AJAX запроса: ' + (xhr.responseJSON?.message || 'Неизвестная ошибка'));
                }
            });
        });

        $("#deleteAccountBtn").on("click", function() {
            if (confirm("Вы уверены, что хотите удалить аккаунт? Это действие необратимо.")) {
                window.location.href = "process/delete_account.php";
            }
        });

        $('#createProjectForm').on('submit', function(e) {
    e.preventDefault();

    // Валидация дат
    const plannedStart = document.querySelector('input[name="planned_start_date"]').value;
    const plannedEnd = document.querySelector('input[name="planned_end_date"]').value;
    const actualStart = document.querySelector('input[name="actual_start_date"]').value;
    const actualEnd = document.querySelector('input[name="actual_end_date"]').value;

    if (plannedStart && plannedEnd && plannedEnd < plannedStart) {
        alert('Планируемая дата завершения не может быть раньше даты начала.');
        return;
    }
    if (actualStart && actualEnd && actualEnd < actualStart) {
        alert('Фактическая дата завершения не может быть раньше даты начала.');
        return;
    }

    // Валидация масштаба
    const scale = document.querySelector('select[name="scale"]').value;
    const validScales = ['small', 'medium', 'large', 'megaproject'];
    if (!validScales.includes(scale)) {
        alert('Недопустимый масштаб проекта. Выберите: Малый, Средний, Крупный или Мегапроект.');
        return;
    }

    // Валидация статуса
    const status = document.querySelector('select[name="status"]').value;
    const validStatuses = ['planning', 'in_progress', 'on_hold'];
    if (!validStatuses.includes(status)) {
        alert('Недопустимый статус проекта. Выберите: Планирование, В процессе или На паузе.');
        return;
    }

    // Логирование данных формы для отладки
    const formData = new FormData(this);
    console.log('Отправляемые данные формы:');
    for (let [key, value] of formData.entries()) {
        if (key === 'charter_file' && value instanceof File && value.size > 0) {
            console.log(`${key}: File - ${value.name}, size: ${value.size} bytes`);
        } else {
            console.log(`${key}: ${value}`);
        }
    }

    // Отправка AJAX
    $.ajax({
        url: $(this).attr('action'),
        method: 'POST',
        data: formData,
        contentType: false,
        processData: false,
        dataType: 'json',
        success: function(response) {
            console.log('Ответ сервера:', response);
            if (response.success) {
                $('#createProjectModal').modal('hide');
                refreshProjects();
                alert('Проект успешно создан!');
            } else {
                alert(response.error || 'Ошибка при создании проекта');
            }
        },
        error: function(xhr) {
            console.error('Ошибка AJAX:', xhr.status, xhr.responseText);
            const response = xhr.responseJSON || {};
            let errorMessage = 'Ошибка при создании проекта';
            if (xhr.status === 400 && response.errors) {
                errorMessage = response.errors.join('\n');
            } else if (response.error) {
                errorMessage = response.error;
            } else if (xhr.status === 500) {
                errorMessage = 'Внутренняя ошибка сервера. Пожалуйста, попробуйте позже.';
            }
            alert(errorMessage);
        }
    });
});

function setupCharCounter(inputSelector, spanId, maxLength) {
    const input = document.querySelector(inputSelector);
    if (!input) return;
    const span = document.getElementById(spanId);
    span.textContent = maxLength;
    input.addEventListener('input', () => {
        span.textContent = maxLength - input.value.length;
    });
}

setupCharCounter('[name="name"]', 'nameChars', 255);
setupCharCounter('[name="short_name"]', 'shortNameChars', 50);
setupCharCounter('[name="description"]', 'descriptionChars', 1000);
setupCharCounter('[name="expected_resources"]', 'resourcesChars', 500);
setupCharCounter('[name="access_code"]', 'accessCodeChars', 20);

const plannedStartDate = document.querySelector('input[name="planned_start_date"]');
const plannedEndDate = document.querySelector('input[name="planned_end_date"]');
const actualStartDate = document.querySelector('input[name="actual_start_date"]');
const actualEndDate = document.querySelector('input[name="actual_end_date"]');

plannedEndDate.addEventListener('change', function() {
    if (plannedStartDate.value && plannedEndDate.value < plannedStartDate.value) {
        alert('Планируемая дата завершения не может быть раньше даты начала.');
        plannedEndDate.value = '';
    }
});

actualEndDate.addEventListener('change', function() {
    if (actualStartDate.value && actualEndDate.value < actualStartDate.value) {
        alert('Фактическая дата завершения не может быть раньше даты начала.');
        actualEndDate.value = '';
    }
});

        // Удаление проекта (отвязка пользователя)
        $(document).on('click', '.delete-project', function(e) {
            e.preventDefault();
            const projectId = $(this).data('project-id');
            if (confirm("Вы уверены, что хотите отвязаться от этого проекта?")) {
                $.ajax({
                    url: "process/delete_project.php",
                    method: "POST",
                    data: { project_id: projectId, user_id: <?= $user_id ?> },
                    dataType: "json",
                    success: function(response) {
                        if (response.success) {
                            $(`#project-${projectId}`).remove();
                            alert("Вы успешно отвязались от проекта.");
                            refreshProjects();
                        } else {
                            alert(response.message || "Ошибка при отвязке от проекта.");
                        }
                    },
                    error: function(xhr) {
                        console.error('Ошибка AJAX при отвязке от проекта:', xhr);
                        alert("Ошибка AJAX запроса: " + (xhr.responseJSON?.message || 'Неизвестная ошибка'));
                    }
                });
            }
        });
    });

    function generateAccessCode() {
        const characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        let result = '';
        for (let i = 0; i < 10; i++) {
            result += characters.charAt(Math.floor(Math.random() * characters.length));
        }
        document.getElementById('accessCodeInput').value = result;
        document.getElementById('accessCodeChars').textContent = 20 - result.length;
    }
    </script>
</body>
</html>