<?php
// project_details.php
session_start();
require_once __DIR__ . '/../db.php';

// Проверяем авторизацию
if (!isset($_SESSION["user_id"])) {
    header("Location: auth.php");
    exit();
}

// Получаем ID проекта
if (!isset($_GET["id"]) || !is_numeric($_GET["id"])) {
    die("Некорректный идентификатор проекта.");
}
$project_id = intval($_GET["id"]);

// Получаем данные проекта
$project = getProjectDetails($conn, $project_id);
if (!$project) {
    die("Проект не найден.");
}
$can_create_report = false;

// Получаем связанные данные
$participants = getProjectParticipants($conn, $project_id);
$stages = getProjectStages($conn, $project_id);
$documents = getProjectDocuments($conn, $project_id);
$tasks = getProjectTasks($conn, $project_id);

// Данные пользователя
$user_id = $_SESSION["user_id"];
$role_id = $_SESSION["role_id"];
$username = $_SESSION["username"];

// Получаем аватар пользователя
$sql = "SELECT avatar FROM users WHERE user_id = ?";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    error_log("project_details.php: Ошибка подготовки запроса для аватара: " . $conn->error);
    die("Ошибка сервера");
}
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$userAvatar = (!empty($user['avatar']) && file_exists(__DIR__ . '/../Uploads/' . $user['avatar'])) 
    ? $user['avatar'] 
    : 'default_avatar.jpg';
$result->free();
$stmt->close();

// Получаем проектную роль пользователя
$project_role = null;
$sql = "SELECT project_role FROM project_participants WHERE project_id = ? AND user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $project_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $project_role = $row["project_role"];
}
$stmt->close();

// Назначаем роль для admin/manager, если не участник
if ($project_role === null && ($role_id == 1 || $role_id == 2)) {
    $project_role = ($role_id == 1) ? 'admin' : 'manager';
}

// Проверяем доступ
if ($project_role === null) {
    die("У вас нет доступа к этому проекту.");
}

// Массивы для перевода
$statusTranslations = [
    'planning' => 'Планирование',
    'active' => 'Активен',
    'in_progress' => 'В процессе',
    'completed' => 'Завершен',
    'on_hold' => 'Приостановлен'
];
$lifecycleStageTranslations = [
    'initiation' => 'Инициация',
    'planning' => 'Планирование',
    'execution' => 'Исполнение',
    'monitoring' => 'Мониторинг',
    'closure' => 'Завершение'
];
$scaleTranslations = [
    'small' => 'Малый',
    'medium' => 'Средний',
    'large' => 'Крупный'
];
$documentTypeTranslations = [
    'drawing' => 'Чертеж',
    'report' => 'Отчет',
    'photo' => 'Фотография',
    'document' => 'Документ'
];
$taskStatusTranslations = [
    'not_started' => 'Не начата',
    'in_progress' => 'В процессе',
    'completed' => 'Завершена',
    'delayed' => 'Задержана',
    'pending' => 'Ожидание'
];
$roleTranslations = [
    'admin' => 'Администратор',
    'manager' => 'Менеджер',
    'employee' => 'Сотрудник',
    'user' => 'Пользователь'
];

$rating = getProjectRating($conn, $project_id);

// Проверяем право на создание отчетов
if (in_array($project_role, ['admin', 'manager'])) {
    $can_create_report = true;
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Детали проекта – <?= htmlspecialchars($project["name"] ?? '') ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="../css/project_details.css">
    <style>
        body {
            background: #f8f9fa;
            transition: background-color 0.3s, color 0.3s;
            opacity: 0;
            transition: opacity 0.5s ease-in-out;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        body.loaded {
            opacity: 1;
        }
        .chart-container {
            position: relative;
            height: 300px;
        }
        .list {
            list-style: none;
            padding: 0;
        }
        .element {
            display: flex;
            align-items: center;
            padding: 10px;
            cursor: pointer;
            border-radius: 5px;
            transition: background-color 0.2s;
        }
        .element:hover {
            background-color: #f0f0f0;
        }
        .element svg {
            margin-right: 10px;
        }
        .label {
            margin: 0;
            font-size: 1rem;
        }
        .stages-container {
            max-width: 800px;
            margin: 0 auto;
        }
        .nav-pills .nav-link {
            text-align: center;
            padding: 10px;
            border-radius: 5px;
            background: #f8f9fa;
            margin: 0 5px;
            transition: all 0.3s ease;
        }
        .nav-pills .nav-link.active {
            background: #007bff;
            color: white;
        }
        .nav-pills .nav-link.completed {
            background: #28a745;
            color: white;
        }
        .nav-pills .nav-link .stage-name {
            display: block;
            font-weight: bold;
        }
        .progress {
            height: 8px;
            background: #e9ecef;
        }
        .stage-controls {
            text-align: center;
        }
    </style>
</head>
<body>
    <script>
        if (localStorage.getItem('darkMode') === 'true') {
            document.body.classList.add('dark-mode');
        }
    </script>

    <nav class="navbar navbar-expand-lg navbar-light bg-light fixed-top shadow-sm">
        <div class="container-fluid">
            <button class="btn btn-outline-secondary me-2" type="button" data-bs-toggle="offcanvas" data-bs-target="#offcanvasSidebar" aria-controls="offcanvasSidebar">
                <span class="navbar-toggler-icon"></span>
            </button>
            <a class="navbar-brand" href="../index.php">Учет проектов</a>
            <ul class="navbar-nav ms-auto">
                <?php if ($project_role === 'admin' || $project_role === 'manager'): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="edit_project.php?id=<?= $project_id ?>"><i class="bi bi-pencil"></i> Редактировать</a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </nav>

    <div class="offcanvas offcanvas-start" tabindex="-1" id="offcanvasSidebar" aria-labelledby="offcanvasSidebarLabel">
        <div class="offcanvas-header">
            <h5 id="offcanvas-modalLabel">Меню</h5>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Закрыть"></button>
        </div>
        <div class="offcanvas-body">
            <div class="text-center mb-4">
                <a href="../pages/profile.php?user_id=<?= htmlspecialchars($user_id) ?>">
                    <img src="../uploads/<?= htmlspecialchars($userAvatar) ?>" alt="Avatar" class="rounded-circle" width="100" height="100" style="display: block; margin: 0 auto;">
                </a>
                <p class="mt-2"><?= htmlspecialchars($username) ?></p>
            </div>
            <ul class="list">
                <li class="element" onclick="window.location.href='../index.php'">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#7e8590" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-home">
                        <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                        <polyline points="9 22 9 12 15 12 15 22"></polyline>
                    </svg>
                    <p class="label">Главная</p>
                </li>
                <?php if ($_SESSION['role_id'] == 1): ?>
                    <li class="element" onclick="window.location.href='admin_panel.php'">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#7e8590" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-user-plus">
                            <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                            <circle cx="8.5" cy="7" r="4"></circle>
                            <line x1="20" y1="8" x2="20" y2="14"></line>
                            <line x1="23" y1="11" x2="17" y2="11"></line>
                        </svg>
                        <p class="label">Админ-панель</p>
                    </li>
                <?php endif; ?>
                <?php if ($project_role === 'admin' || $project_role === 'manager'): ?>
                    <li class="element" onclick="window.location.href='add_participant.php?project_id=<?= $project_id ?>'">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#7e8590" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-user-plus">
                            <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                            <circle cx="8.5" cy="7" r="4"></circle>
                            <line x1="20" y1="8" x2="20" y2="14"></line>
                            <line x1="23" y1="11" x2="17" y2="11"></line>
                        </svg>
                        <p class="label">Добавить участника</p>
                    </li>
                <?php endif; ?>
                <li class="element" onclick="window.location.href='../process/logout.php'">
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

    <div class="content-container container mt-5">
        <?php if (isset($_SESSION["success_msg"])): ?>
            <div class="alert alert-success">
                <?= htmlspecialchars($_SESSION["success_msg"]) ?>
                <?php unset($_SESSION["success_msg"]); ?>
            </div>
        <?php endif; ?>
        <?php if (isset($_SESSION["error_msg"])): ?>
            <div class="alert alert-danger">
                <?= htmlspecialchars($_SESSION["error_msg"]) ?>
                <?php unset($_SESSION["error_msg"]); ?>
            </div>
        <?php endif; ?>
        <div class="row">
            <div class="col-md-8">
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h2 class="mb-0"><?= htmlspecialchars($project["name"] ?? '') ?></h2>
                        <?php if ($_SESSION["role_id"] == 1): ?>
                            <a href="../process/delete_project_admin.php?project_id=<?= $project_id ?>" class="btn btn-danger" onclick="return confirm('Вы уверены, что хотите удалить проект? Это действие необратимо и удалит все связанные данные.');">
                                <i class="bi bi-trash"></i> Удалить
                            </a>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <p><strong>Краткое название:</strong> <?= htmlspecialchars($project["short_name"] ?? '') ?></p>
                        <p><strong>Описание:</strong></p>
                        <p><?= nl2br(htmlspecialchars($project["description"] ?? '')) ?></p>
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Плановая дата начала:</strong> <?= htmlspecialchars($project["planned_start_date"] ?? '') ?></p>
                                <p><strong>Фактическая дата начала:</strong> <?= htmlspecialchars($project["actual_start_date"] ?? '') ?></p>
                                <p><strong>Плановая дата завершения:</strong> <?= htmlspecialchars($project["planned_end_date"] ?? '') ?></p>
                                <p><strong>Фактическая дата завершения:</strong> <?= htmlspecialchars($project["actual_end_date"] ?? '') ?></p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Плановый бюджет:</strong> <?= htmlspecialchars($project["planned_budget"] ?? '') ?></p>
                                <p><strong>Фактический бюджет:</strong> <?= htmlspecialchars($project["actual_budget"] ?? '') ?></p>
                                <p><strong>Уровень цифровизации (план/факт):</strong> <?= htmlspecialchars($project["planned_digitalization_level"] ?? '') ?> / <?= htmlspecialchars($project["actual_digitalization_level"] ?? '') ?></p>
                                <p><strong>Затраты на труд (план/факт):</strong> <?= htmlspecialchars($project["planned_labor_costs"] ?? '') ?> / <?= htmlspecialchars($project["actual_labor_costs"] ?? '') ?></p>
                            </div>
                        </div>
                        <p><strong>Статус проекта:</strong> <?= htmlspecialchars($statusTranslations[$project["status"] ?? ''] ?? $project["status"] ?? '') ?></p>
                        <p><strong>Этап жизненного цикла:</strong> <?= htmlspecialchars($lifecycleStageTranslations[$project["lifecycle_stage"] ?? ''] ?? $project["lifecycle_stage"] ?? '') ?></p>
                        <p><strong>Масштаб проекта:</strong> <?= htmlspecialchars($scaleTranslations[$project["scale"] ?? ''] ?? $project["scale"] ?? '') ?></p>
                        <p><strong>Устав (файл):</strong> <a href="<?= htmlspecialchars($project["charter_file_path"] ?? '') ?>" target="_blank">Скачать</a></p>
                        <p><strong>Ожидаемые ресурсы:</strong></p>
                        <p><?= nl2br(htmlspecialchars($project["expected_resources"] ?? '')) ?></p>
                        <p><strong>Код доступа:</strong> <?= htmlspecialchars($project["access_code"] ?? '') ?></p>
                        <p><strong>Рейтинг проекта:</strong> <?= htmlspecialchars($rating ?? '') ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="mt-4 chart-container">
                    <h4>Диаграмма бюджета</h4>
                    <canvas id="budgetChart"></canvas>
                </div>
            </div>
        </div>

        <ul class="nav nav-tabs mt-4" id="projectTab" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="participants-tab" data-bs-toggle="tab" data-bs-target="#participants" type="button" role="tab" aria-controls="participants" aria-selected="true">Участники</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="stages-tab" data-bs-toggle="tab" data-bs-target="#stages" type="button" role="tab" aria-controls="stages" aria-selected="false">Этапы</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="documents-tab" data-bs-toggle="tab" data-bs-target="#documents" type="button" role="tab" aria-controls="documents" aria-selected="false">Документы</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="tasks-tab" data-bs-toggle="tab" data-bs-target="#tasks" type="button" role="tab" aria-controls="tasks" aria-selected="false">Задачи</button>
            </li>
            <?php if ($project_role === 'admin' || $project_role === 'manager' || $project_role === 'employee'): ?>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="report-tab" data-bs-toggle="tab" data-bs-target="#report" type="button" role="tab" aria-controls="report" aria-selected="false">Создать отчет</button>
                </li>
            <?php endif; ?>
        </ul>
        <div class="tab-content" id="projectTabContent">
            <div class="tab-pane fade show active p-3" id="participants" role="tabpanel" aria-labelledby="participants-tab">
                <div class="mb-3 d-flex justify-content-between">
                    <div>
                        <input type="text" id="participantSearch" class="form-control" placeholder="Поиск по имени">
                        <select id="roleFilter" class="form-select mt-2">
                            <option value="">Все роли</option>
                            <option value="manager"><?= htmlspecialchars($roleTranslations['manager']) ?></option>
                            <option value="employee"><?= htmlspecialchars($roleTranslations['employee']) ?></option>
                            <option value="user"><?= htmlspecialchars($roleTranslations['user']) ?></option>
                        </select>
                    </div>
                    <?php if ($project_role === 'admin' || $project_role === 'manager'): ?>
                        <a href="add_participant.php?project_id=<?= $project_id ?>" class="btn btn-success"><i class="bi bi-person-plus"></i> Добавить участника</a>
                    <?php endif; ?>
                </div>
                <table class="table table-hover" id="participantsTable">
                    <thead>
                        <tr>
                            <th>Имя пользователя</th>
                            <th>Роль в проекте</th>
                            <?php if ($project_role === 'admin' || $project_role === 'manager'): ?>
                                <th>Действия</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($participants as $participant): ?>
                            <tr>
                                <td><?= htmlspecialchars($participant["full_name"] ?? '') ?></td>
                                <td><?= htmlspecialchars($roleTranslations[$participant["project_role"]] ?? $participant["project_role"]) ?></td>
                                <?php if ($project_role === 'admin' || $project_role === 'manager'): ?>
                                    <td>
                                        <a href="add_task.php?project_id=<?= $project_id ?>&assigned_to=<?= $participant["user_id"] ?>" class="btn btn-primary btn-sm"><i class="bi bi-plus-circle"></i> Добавить задачу</a>
                                        <?php if (!empty($participant["user_id"])): ?>
                                            <a href="../process/process_remove_participant.php?user_id=<?= htmlspecialchars($participant["user_id"]) ?>&project_id=<?= intval($project_id) ?>" class="btn btn-danger btn-sm" onclick="return confirm('Удалить участника?');"><i class="bi bi-trash"></i></a>
                                            <?php if ($project_role === 'admin'): ?>
                                                <button class="btn btn-sm btn-secondary change-role-btn" data-bs-toggle="modal" data-bs-target="#changeRoleModal" data-user-id="<?= htmlspecialchars($participant["user_id"]) ?>" data-current-role="<?= htmlspecialchars($participant["project_role"]) ?>">
                                                    <i class="bi bi-pencil-square"></i> Изменить роль
                                                </button>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <p style="color: red;">Ошибка: ID пользователя не найден.</p>
                                        <?php endif; ?>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="tab-pane fade p-3" id="stages" role="tabpanel" aria-labelledby="stages-tab">
                <h4>Этапы проекта</h4>
                <?php
                $stages = [
                    'initiation' => ['name' => 'Инициация', 'progress' => 20],
                    'planning' => ['name' => 'Планирование', 'progress' => 40],
                    'execution' => ['name' => 'Исполнение', 'progress' => 60],
                    'monitoring' => ['name' => 'Мониторинг', 'progress' => 80],
                    'closure' => ['name' => 'Завершение', 'progress' => 100],
                ];
                $current_stage = $project["lifecycle_stage"];
                $stage_keys = array_keys($stages);
                $current_index = array_search($current_stage, $stage_keys);
                $can_manage_stages = in_array($project_role, ['admin', 'manager']);
                ?>
                <div class="stages-container">
                    <ul class="nav nav-pills nav-fill mb-3">
                        <?php foreach ($stages as $stage_key => $stage): ?>
                            <?php
                            $is_active = ($stage_key === $current_stage);
                            $is_completed = (array_search($stage_key, $stage_keys) < $current_index);
                            ?>
                            <li class="nav-item">
                                <a class="nav-link <?= $is_active ? 'active' : ($is_completed ? 'completed' : '') ?>" href="#" data-stage="<?= $stage_key ?>">
                                    <span class="stage-name"><?= htmlspecialchars($stage["name"]) ?></span>
                                    <div class="progress mt-1">
                                        <div class="progress-bar <?= $is_active || $is_completed ? 'bg-success' : '' ?>" role="progressbar" style="width: <?= $is_active || $is_completed ? '100%' : '0%' ?>;" aria-valuenow="<?= $is_active || $is_completed ? 100 : 0 ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                    </div>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php if ($can_manage_stages): ?>
                        <div class="stage-controls mt-3">
                            <?php if ($current_index > 0): ?>
                                <a href="../process/change_stage.php?project_id=<?= $project["project_id"] ?>&stage=<?= $stage_keys[$current_index - 1] ?>" class="btn btn-warning me-2">Назад</a>
                            <?php endif; ?>
                            <?php if ($current_index < count($stage_keys) - 1): ?>
                                <a href="../process/change_stage.php?project_id=<?= $project["project_id"] ?>&stage=<?= $stage_keys[$current_index + 1] ?>" class="btn btn-primary">Вперёд</a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="tab-pane fade p-3" id="documents" role="tabpanel" aria-labelledby="documents-tab">
                <div class="mb-3 d-flex justify-content-between">
                    <div>
                        <input type="text" id="documentSearch" class="form-control" placeholder="Поиск по названию">
                        <select id="typeFilter" class="form-select mt-2">
                            <option value="">Все типы</option>
                            <option value="drawing">Чертеж</option>
                            <option value="report">Отчет</option>
                            <option value="photo">Фотография</option>
                            <option value="document">Документ</option>
                        </select>
                    </div>
                    <?php if ($project_role === 'admin' || $project_role === 'manager' || $project_role === 'employee'): ?>
                        <a href="add_document.php?project_id=<?= $project_id ?>" class="btn btn-primary"><i class="bi bi-file-earmark-plus"></i> Загрузить документ</a>
                    <?php endif; ?>
                </div>
                <table class="table table-hover" id="documentsTable">
                    <thead>
                        <tr>
                            <th>Название файла</th>
                            <th>Тип</th>
                            <th>Дата загрузки</th>
                            <th>Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($documents as $doc): ?>
                            <tr>
                                <td><a href="<?= htmlspecialchars($doc["file_path"] ?? '') ?>" target="_blank" download="<?= htmlspecialchars($doc["file_name"] ?? '') ?>"><?= htmlspecialchars($doc["file_name"] ?? '') ?></a></td>
                                <td><?= htmlspecialchars($documentTypeTranslations[strtolower($doc["document_type"] ?? '')] ?? $doc["document_type"] ?? '') ?></td>
                                <td><?= htmlspecialchars($doc["upload_date"] ?? '') ?></td>
                                <td>
                                    <?php if ($project_role === 'admin' || $project_role === 'manager' || $project_role === 'employee'): ?>
                                        <a href="../process/delete_document.php?document_id=<?= htmlspecialchars($doc["document_id"] ?? '') ?>&project_id=<?= $project_id ?>" class="btn btn-danger btn-sm" onclick="return confirm('Удалить документ?');"><i class="bi bi-trash"></i></a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="tab-pane fade p-3" id="tasks" role="tabpanel" aria-labelledby="tasks-tab">
                <?php if ($project_role === 'admin' || $project_role === 'manager'): ?>
                    <a href="add_task.php?project_id=<?= $project_id ?>" class="btn btn-primary mb-3"><i class="bi bi-plus-circle"></i> Добавить задачу</a>
                <?php endif; ?>
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Название задачи</th>
                            <th>Ответственный</th>
                            <th>Помощники</th>
                            <th>Статус</th>
                            <th>Срок</th>
                            <th>Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tasks as $task): ?>
                            <?php
                            $responsible = getUserById($conn, $task["responsible_id"]);
                            $responsible_name = 'Не указан';
                            if ($responsible && isset($responsible["username"])) {
                                $fullNameStmt = $conn->prepare("SELECT full_name FROM users WHERE user_id = ?");
                                $fullNameStmt->bind_param("i", $task["responsible_id"]);
                                $fullNameStmt->execute();
                                $fullNameResult = $fullNameStmt->get_result();
                                if ($fullNameRow = $fullNameResult->fetch_assoc()) {
                                    $responsible_name = $fullNameRow["full_name"] ?? $responsible["username"];
                                }
                                $fullNameStmt->close();
                            }
                            $assistants = json_decode($task["assistants"], true) ?? [];
                            $assistant_names = [];
                            foreach ($assistants as $assistant_id) {
                                $assistant = getUserById($conn, $assistant_id);
                                $assistant_name = 'Не указан';
                                if ($assistant && isset($assistant["username"])) {
                                    $fullNameStmt = $conn->prepare("SELECT full_name FROM users WHERE user_id = ?");
                                    $fullNameStmt->bind_param("i", $assistant_id);
                                    $fullNameStmt->execute();
                                    $fullNameResult = $fullNameStmt->get_result();
                                    if ($fullNameRow = $fullNameResult->fetch_assoc()) {
                                        $assistant_name = $fullNameRow["full_name"] ?? $assistant["username"];
                                    }
                                    $fullNameStmt->close();
                                }
                                $assistant_names[] = $assistant_name;
                            }
                            $display_assistants = array_slice($assistant_names, 0, 2);
                            if (count($assistant_names) > 2) {
                                $display_assistants[] = '...';
                            }
                            ?>
                            <tr>
                                <td><a href="task_details.php?task_id=<?= $task["task_id"] ?>"><?= htmlspecialchars($task["task_name"] ?? '') ?></a></td>
                                <td><?= htmlspecialchars($responsible_name) ?></td>
                                <td><?= implode(', ', array_map('htmlspecialchars', $display_assistants)) ?: 'Нет помощников' ?></td>
                                <td><?= htmlspecialchars($taskStatusTranslations[$task["status"] ?? ''] ?? $task["status"] ?? '') ?></td>
                                <td><?= htmlspecialchars($task["deadline"] ?? '') ?></td>
                                <td>
                                    <?php if ($project_role === 'admin' || $project_role === 'manager'): ?>
                                        <a href="task_details.php?task_id=<?= $task["task_id"] ?>" class="btn btn-warning btn-sm"><i class="bi bi-pencil"></i></a>
                                        <a href="../process/delete_task.php?task_id=<?= $task["task_id"] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Удалить задачу?');"><i class="bi bi-trash"></i></a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($can_create_report): ?>
                <div class="tab-pane fade p-3" id="report" role="tabpanel" aria-labelledby="report-tab">
                    <h4>Создать отчет по проекту</h4>
                    <form action="generate_report.php" method="post">
                        <input type="hidden" name="project_id" value="<?= $project_id ?>">
                        <div class="list-group mb-3">
                            <div class="list-group-item">
                                <input class="form-check-input me-2" type="checkbox" name="include_info" id="includeInfo">
                                <label class="form-check-label" for="includeInfo">Информация о проекте</label>
                            </div>
                            <div class="list-group-item">
                                <input class="form-check-input me-2" type="checkbox" name="include_documents" id="includeDocuments">
                                <label class="form-check-label" for="includeDocuments">Ссылки на документы</label>
                            </div>
                            <div class="list-group-item">
                                <input class="form-check-input me-2" type="checkbox" name="include_diagrams" id="includeDiagrams">
                                <label class="form-check-label" for="includeDiagrams">Диаграммы</label>
                            </div>
                            <div class="list-group-item">
                                <input class="form-check-input me-2" type="checkbox" name="include_photos" id="includePhotos">
                                <label class="form-check-label" for="includePhotos">Фотографии</label>
                            </div>
                        </div>
                        <div class="form-group mt-3">
                            <label for="format">Формат отчета:</label>
                            <select class="form-control" name="format" id="format">
                                <option value="docx">Word (DOCX)</option>
                                <option value="xlsx">Excel (XLSX)</option>
                                <option value="pdf">PDF</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary mt-3">Сгенерировать отчет</button>
                    </form>
                </div>
            <?php endif; ?>
        </div>

        <!-- Модальное окно изменения роли -->
        <div class="modal fade" id="changeRoleModal" tabindex="-1" aria-labelledby="changeRoleModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="changeRoleModalLabel">Изменить роль участника</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
                    </div>
                    <div class="modal-body">
                        <form id="changeRoleForm">
                            <input type="hidden" id="roleUserId" name="user_id">
                            <input type="hidden" id="roleProjectId" name="project_id" value="<?= $project_id ?>">
                            <div class="mb-3">
                                <label for="projectRole" class="form-label">Новая роль</label>
                                <select class="form-select" id="projectRole" name="project_role" required>
                                    <option value="manager">Менеджер</option>
                                    <option value="employee">Сотрудник</option>
                                    <option value="user">Пользователь</option>
                                </select>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                        <button type="button" class="btn btn-primary" id="saveRoleBtn">Сохранить</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    $(document).ready(function () {
        $("#themeSwitchModal").prop("checked", localStorage.getItem('darkMode') === 'true');
        $("#themeSwitchModal").on("change", function () {
            if ($(this).is(":checked")) {
                $("body").addClass("dark-mode");
                localStorage.setItem('darkMode', 'true');
            } else {
                $("body").removeClass("dark-mode");
                localStorage.setItem('darkMode', 'false');
            }
        });

        $("#participantSearch, #roleFilter").on("keyup change", function () {
            var searchVal = $("#participantSearch").val().toLowerCase();
            var roleVal = $("#roleFilter").val();
            var roleTranslations = {
                'manager': 'Менеджер',
                'employee': 'Сотрудник',
                'user': 'Пользователь'
            };
            var translatedRoleVal = roleTranslations[roleVal] || '';
            $("#participantsTable tbody tr").each(function () {
                var name = $(this).find("td:first").text().toLowerCase();
                var role = $(this).find("td:nth-child(2)").text().trim();
                var matchName = name.includes(searchVal);
                var matchRole = roleVal === "" || role === translatedRoleVal;
                $(this).toggle(matchName && matchRole);
            });
        });

        $("#documentSearch, #typeFilter").on("keyup change", function () {
            var searchVal = $("#documentSearch").val().toLowerCase();
            var typeVal = $("#typeFilter").val();
            var typeTranslations = {
                'drawing': 'Чертеж',
                'report': 'Отчет',
                'photo': 'Фотография',
                'document': 'Документ'
            };
            var translatedTypeVal = typeTranslations[typeVal] || typeVal;
            $("#documentsTable tbody tr").each(function () {
                var name = $(this).find("td:first").text().toLowerCase();
                var type = $(this).find("td:nth-child(2)").text().trim();
                var matchName = name.includes(searchVal);
                var matchType = typeVal === "" || type === translatedTypeVal;
                $(this).toggle(matchName && matchType);
            });
        });

        var budgetCanvas = document.getElementById('budgetChart');
        if (budgetCanvas) {
            var ctx = budgetCanvas.getContext('2d');
            var plannedBudget = <?= json_encode($project["planned_budget"] ?? 0) ?>;
            var actualBudget = <?= json_encode($project["actual_budget"] ?? 0) ?>;
            new Chart(ctx, {
                type: 'pie',
                data: {
                    labels: ['Плановый бюджет', 'Фактический бюджет'],
                    datasets: [{
                        data: [plannedBudget, actualBudget],
                        backgroundColor: ['#36A2EB', '#FF6384']
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false
                }
            });
        }

        // Обработка кнопки изменения роли
        $(document).on('click', '.change-role-btn', function () {
            const userId = $(this).data('user-id');
            const currentRole = $(this).data('current-role');
            $('#roleUserId').val(userId);
            $('#projectRole').val(currentRole);
        });

        // Сохранение новой роли
        $('#saveRoleBtn').on('click', function () {
            const userId = $('#roleUserId').val();
            const projectId = $('#roleProjectId').val();
            const newRole = $('#projectRole').val();
            $.ajax({
                url: '../process/process_change_role.php',
                method: 'POST',
                data: { user_id: userId, project_id: projectId, project_role: newRole },
                dataType: 'json',
                success: function (response) {
                    if (response.success) {
                        $('#changeRoleModal').modal('hide');
                        location.reload(); // Обновляем страницу
                    } else {
                        alert(response.message || 'Ошибка изменения роли');
                    }
                },
                error: function (xhr) {
                    console.error('Ошибка AJAX:', xhr);
                    alert('Ошибка запроса: ' + (xhr.responseJSON?.message || 'Неизвестная ошибка'));
                }
            });
        });

        $("body").addClass("loaded");
    });
    </script>
</body>
</html>