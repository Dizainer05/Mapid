<?php
session_start();
require_once __DIR__ . '/../db.php';

// Проверка прав доступа
if (!isset($_SESSION["user_id"]) || $_SESSION["role_id"] != 1) {
    header("Location: ../pages/register.php");
    exit();
}

// Параметры для пагинации
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;
$username = $_SESSION["username"];
$user_id = $_SESSION["user_id"];

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
$userAvatar = (!empty($user['avatar']) && file_exists(__DIR__ . '/../Uploads/' . $user['avatar'])) 
    ? $user['avatar'] 
    : 'default_avatar.jpg';
$result->free();
$stmt->close();

// Функция для получения названия роли
function getRoleName($role_id) {
    switch ($role_id) {
        case 1: return 'Администратор';
        case 2: return 'Менеджер';
        case 3: return 'Сотрудник';
        case 4: return 'Пользователь';
        default: return 'Неизвестно';
    }
}

// Функция для поиска пользователей
function searchUsers($conn, $search_term, $role_filter, $limit = null, $offset = null) {
    $sql = "SELECT * FROM users WHERE 1=1";
    $params = [];
    $types = '';

    if ($search_term) {
        $sql .= " AND (username LIKE ? OR full_name LIKE ? OR email LIKE ?)";
        $search_like = "%$search_term%";
        $params[] = $search_like;
        $params[] = $search_like;
        $params[] = $search_like;
        $types .= 'sss';
    }

    if ($role_filter) {
        $sql .= " AND role_id = ?";
        $params[] = $role_filter;
        $types .= 'i';
    }

    if ($limit !== null && $offset !== null) {
        $sql .= " LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        $types .= 'ii';
    }

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("Ошибка подготовки запроса: " . $conn->error);
        return [];
    }
    if ($params) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $result;
}

// Функция для экспорта отчета
function exportUsersReport($conn, $search_term, $role_filter) {
    return searchUsers($conn, $search_term, $role_filter, null, null);
}

// Параметры для поиска и фильтрации
$search_term = isset($_GET['search']) ? trim($_GET['search']) : '';
$role_filter = isset($_GET['role']) ? (int)$_GET['role'] : null;

// Получение пользователей
$users = searchUsers($conn, $search_term, $role_filter, $limit, $offset);
$total_users = count(searchUsers($conn, $search_term, $role_filter, null, null));

// Данные для диаграммы
$role_data = $conn->query("SELECT role_id, COUNT(*) as count FROM users GROUP BY role_id")->fetch_all(MYSQLI_ASSOC);
$role_labels = array_map(function($role) { return getRoleName($role['role_id']); }, $role_data);
$role_counts = array_column($role_data, 'count');

// AJAX-обработка таблицы пользователей
if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest' && !isset($_GET['chart_data'])) {
    ?>
    <div class="table-responsive">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Имя пользователя</th>
                    <th>Email</th>
                    <th>Полное имя</th>
                    <th>Роль</th>
                    <th>Должность</th>
                    <th>Отдел</th>
                    <th>Действия</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                <tr id="user-row-<?php echo $user['user_id']; ?>">
                    <td><?php echo $user['user_id']; ?></td>
                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                    <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                    <td><?php echo getRoleName($user['role_id']); ?></td>
                    <td><?php echo htmlspecialchars($user['position'] ?? 'Не указано'); ?></td>
                    <td><?php echo htmlspecialchars($user['department'] ?? 'Не указано'); ?></td>
                    <td>
                        <button class="btn btn-sm btn-danger" onclick="deleteUser(<?php echo $user['user_id']; ?>)">Удалить</button>
                        <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editUserModal" onclick="loadUserData(<?php echo $user['user_id']; ?>)">Редактировать</button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <nav>
        <ul class="pagination">
            <?php for ($i = 1; $i <= ceil($total_users / $limit); $i++): ?>
                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search_term); ?>&role=<?php echo $role_filter; ?>"><?php echo $i; ?></a>
                </li>
            <?php endfor; ?>
        </ul>
    </nav>
    <?php
    exit();
}

// AJAX-обработка данных для диаграммы
if (isset($_GET['chart_data']) && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    $role_data = $conn->query("SELECT role_id, COUNT(*) as count FROM users GROUP BY role_id")->fetch_all(MYSQLI_ASSOC);
    $role_labels = array_map(function($role) { return getRoleName($role['role_id']); }, $role_data);
    $role_counts = array_column($role_data, 'count');
    echo json_encode([
        'labels' => $role_labels,
        'counts' => $role_counts
    ]);
    exit();
}

// Обработка генерации отчета
if (isset($_GET['generate_report'])) {
    $report_data = exportUsersReport($conn, $search_term, $role_filter);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="users_report.csv"');
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM для корректного отображения кириллицы
    fputcsv($output, ['ID', 'Имя пользователя', 'Email', 'Полное имя', 'Роль', 'Должность', 'Отдел']);
    foreach ($report_data as $row) {
        fputcsv($output, [
            $row['user_id'],
            $row['username'],
            $row['email'],
            $row['full_name'],
            getRoleName($row['role_id']),
            $row['position'] ?? 'Не указано',
            $row['department'] ?? 'Не указано'
        ]);
    }
    fclose($output);
    exit();
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Управление пользователями</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body {
            background: #f8f9fa;
            transition: background-color 0.3s, color 0.3s;
        }
        .content-container {
            margin-top: 80px;
        }
        .card {
            border-radius: 0.75rem;
        }
        .table-hover tbody tr:hover {
            background-color: rgba(0,0,0,0.05);
        }
        #avatar-preview {
            max-width: 100px;
            margin-top: 10px;
            border-radius: 50%;
        }
        #roleChart {
            height: 200px !important;
        }
        .chart-card .card-body {
            padding: 0.75rem;
        }
        .dark-mode {
            background-color: #121212 !important;
            color: #e0e0e0 !important;
        }
        .dark-mode .navbar, .dark-mode .offcanvas, .dark-mode .card, .dark-mode .modal-content {
            background-color: #1e1e1e !important;
            color: #e0e0e0 !important;
        }
        .dark-mode .form-control, .dark-mode .form-select {
            background-color: #2c2c2c !important;
            color: #e0e0e0 !important;
            border-color: #555 !important;
        }
        .dark-mode .btn-close {
            filter: invert(1);
        }
        .password-toggle {
            cursor: pointer;
            padding: 0 10px;
            display: flex;
            align-items: center;
        }
        .required::after {
            content: '*';
            color: red;
            margin-left: 4px;
        }
        /* Стили для меню */
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
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-light bg-light fixed-top shadow-sm">
        <div class="container-fluid">
            <button class="btn btn-outline-secondary me-2" type="button" data-bs-toggle="offcanvas" data-bs-target="#offcanvasSidebar">
                <span class="navbar-toggler-icon"></span>
            </button>
            <a class="navbar-brand" href="admin_panel.php">Панель проектов</a>
        </div>
    </nav>

    <div class="offcanvas offcanvas-start" tabindex="-1" id="offcanvasSidebar" aria-labelledby="offcanvasSidebarLabel">
        <div class="offcanvas-header">
            <h5 id="offcanvasSidebarLabel">Меню</h5>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
        </div>
        <div class="offcanvas-body">
            <div class="text-center mb-4">
                <a href="../pages/profile.php?user_id=<?= htmlspecialchars($user_id) ?>">
                    <img src="../Uploads/<?= htmlspecialchars($userAvatar) ?>" alt="Avatar" class="rounded-circle" width="100" height="100" style="display: block; margin: 0 auto;">
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
                <li class="element" onclick="window.location.href='admin_panel.php'">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#7e8590" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-user-plus">
                        <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                        <circle cx="8.5" cy="7" r="4"></circle>
                        <line x1="20" y1="8" x2="20" y2="14"></line>
                        <line x1="23" y1="11" x2="17" y2="11"></line>
                    </svg>
                    <p class="label">Админ-панель</p>
                </li>
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

    <div class="modal fade" id="createUserModal" tabindex="-1" aria-labelledby="createUserModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="createUserModalLabel">Создание пользователя</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="post" id="createUserForm">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label required">Имя пользователя</label>
                            <input type="text" name="username" class="form-control" maxlength="50" required placeholder="Например, ivanov">
                            <small class="form-text text-muted">Осталось символов: <span id="usernameChars">50</span></small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label required">Пароль</label>
                            <div class="input-group">
                                <input type="password" name="password" id="passwordInput" class="form-control" maxlength="255" required placeholder="Введите пароль">
                                <button type="button" class="btn btn-outline-secondary" id="generatePasswordBtn">Сгенерировать</button>
                                <span class="btn btn-outline-secondary password-toggle" id="passwordToggleBtn">
                                    <i class="bi bi-eye" id="passwordToggleIcon"></i>
                                </span>
                                <button type="button" class="btn btn-outline-secondary" id="copyCredentialsBtn">Копировать</button>
                            </div>
                            <small class="form-text text-muted">Осталось символов: <span id="passwordChars">255</span></small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label required">Электронная почта</label>
                            <input type="email" name="email" class="form-control" maxlength="100" required placeholder="Например, ivanov@example.com">
                            <small class="form-text text-muted">Осталось символов: <span id="emailChars">100</span></small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label required">ФИО</label>
                            <input type="text" name="full_name" class="form-control" maxlength="100" required placeholder="Например, Иванов Иван Иванович">
                            <small class="form-text text-muted">Осталось символов: <span id="fullNameChars">100</span></small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label required">Роль</label>
                            <select name="role_id" class="form-select" required>
                                <option value="1">Администратор</option>
                                <option value="2">Менеджер</option>
                                <option value="3">Сотрудник</option>
                                <option value="4">Пользователь</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Должность</label>
                            <input type="text" name="position" class="form-control" maxlength="100" placeholder="Например, Инженер">
                            <small class="form-text text-muted">Осталось символов: <span id="positionChars">100</span></small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Подразделение</label>
                            <input type="text" name="department" class="form-control" maxlength="100" placeholder="Например, Отдел проектирования">
                            <small class="form-text text-muted">Осталось символов: <span id="departmentChars">100</span></small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                        <button type="submit" class="btn btn-primary">Создать пользователя</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="content-container container-fluid mt-5 pt-4">
        <div class="row">
            <div class="col-md-12">
                <div class="card shadow-sm mb-4">
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <input type="text" name="search" class="form-control" placeholder="Поиск по имени" value="<?php echo htmlspecialchars($search_term); ?>">
                            </div>
                            <div class="col-md-3">
                                <select name="role" class="form-select">
                                    <option value="">Все роли</option>
                                    <option value="1" <?php echo $role_filter == 1 ? 'selected' : ''; ?>>Администратор</option>
                                    <option value="2" <?php echo $role_filter == 2 ? 'selected' : ''; ?>>Менеджер</option>
                                    <option value="3" <?php echo $role_filter == 3 ? 'selected' : ''; ?>>Сотрудник</option>
                                    <option value="4" <?php echo $role_filter == 4 ? 'selected' : ''; ?>>Пользователь</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <button class="btn btn-secondary w-100" id="resetFilters">Сброс</button>
                            </div>
                            <div class="col-md-3">
                                <button class="btn btn-success w-100" data-bs-toggle="modal" data-bs-target="#createUserModal">Добавить пользователя</button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm">
                    <div class="card-header">Пользователи</div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Имя пользователя</th>
                                        <th>Email</th>
                                        <th>Полное имя</th>
                                        <th>Роль</th>
                                        <th>Должность</th>
                                        <th>Отдел</th>
                                        <th>Действия</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($users as $user): ?>
                                        <tr id="user-row-<?php echo $user['user_id']; ?>">
                                            <td><?php echo $user['user_id']; ?></td>
                                            <td><?php echo htmlspecialchars($user['username']); ?></td>
                                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                                            <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                            <td><?php echo getRoleName($user['role_id']); ?></td>
                                            <td><?php echo htmlspecialchars($user['position'] ?? 'Не указано'); ?></td>
                                            <td><?php echo htmlspecialchars($user['department'] ?? 'Не указано'); ?></td>
                                            <td>
                                                <button class="btn btn-sm btn-danger" onclick="deleteUser(<?php echo $user['user_id']; ?>)">Удалить</button>
                                                <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editUserModal" onclick="loadUserData(<?php echo $user['user_id']; ?>)">Редактировать</button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <!-- <nav>
                            <ul class="pagination">
                                <?php for ($i = 1; $i <= ceil($total_users / $limit); $i++): ?>
                                    <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search_term); ?>&role=<?php echo $role_filter; ?>"><?php echo $i; ?></a>
                                    </li>
                                <?php endfor; ?>
                            </ul>
                        </nav> -->
                    </div>
                </div>

                <div class="row mt-4">
                    <div class="col-md-6">
                        <div class="card shadow-sm chart-card">
                            <div class="card-header">Распределение ролей</div>
                            <div class="card-body">
                                <canvas id="roleChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editUserModalLabel">Редактирование пользователя</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
                </div>
                <div class="modal-body">
                    <form id="editUserForm" enctype="multipart/form-data">
                        <input type="hidden" id="edit_user_id" name="user_id">
                        <div class="mb-3">
                            <label for="edit_username" class="form-label required">Имя пользователя</label>
                            <input type="text" class="form-control" id="edit_username" name="username" maxlength="50" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_password" class="form-label">Пароль</label>
                            <input type="password" class="form-control" id="edit_password" name="password" placeholder="Оставьте пустым для сохранения текущего" maxlength="255">
                        </div>
                        <div class="mb-3">
                            <label for="edit_email" class="form-label required">Email</label>
                            <input type="email" class="form-control" id="edit_email" name="email" maxlength="100" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_full_name" class="form-label required">Полное имя</label>
                            <input type="text" class="form-control" id="edit_full_name" name="full_name" maxlength="100" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_role_id" class="form-label required">Роль</label>
                            <select class="form-select" id="edit_role_id" name="role_id" required>
                                <option value="1">Администратор</option>
                                <option value="2">Менеджер</option>
                                <option value="3">Сотрудник</option>
                                <option value="4">Пользователь</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="edit_position" class="form-label">Должность</label>
                            <input type="text" class="form-control" id="edit_position" name="position" maxlength="100">
                        </div>
                        <div class="mb-3">
                            <label for="edit_department" class="form-label">Отдел</label>
                            <input type="text" class="form-control" id="edit_department" name="department" maxlength="100">
                        </div>
                        <div class="mb-3">
                            <label for="edit_avatar" class="form-label">Аватар</label>
                            <input type="file" class="form-control" id="edit_avatar" name="avatar" accept="image/*" onchange="previewAvatar(event)">
                            <img id="avatar-preview" src="" alt="Avatar Preview" style="max-width: 100px; margin-top: 10px; display: none; border-radius: 50%;">
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                    <button type="button" class="btn btn-primary" onclick="saveUserChanges()">Сохранить</button>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    $(document).ready(function() {
        // Инициализация темной темы
        if (localStorage.getItem('darkMode') === 'true') {
            $("body").addClass("dark-mode");
            $("#themeSwitchModal").prop("checked", true);
        }
        $("#themeSwitchModal").on("change", function() {
            if($(this).is(":checked")) {
                $("body").addClass("dark-mode");
                localStorage.setItem('darkMode', 'true');
            } else {
                $("body").removeClass("dark-mode");
                localStorage.setItem('darkMode', 'false');
            }
        });

        // Загрузка пользователей
        function loadUsers(page = 1) {
            const search = $('input[name="search"]').val();
            const role = $('select[name="role"]').val();

            $.ajax({
                url: window.location.pathname,
                type: 'GET',
                data: { 
                    search: search,
                    role: role,
                    page: page 
                },
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                success: function(data) {
                    const $data = $(data);
                    $('.table-responsive').html($data.filter('.table-responsive').html());
                    $('nav').html($data.find('nav').html());
                    updateRoleChart();
                },
                error: function(xhr) {
                    console.error('Ошибка AJAX:', xhr.status, xhr.responseText);
                    alert('Ошибка загрузки данных: ' + (xhr.responseJSON?.message || 'Неизвестная ошибка'));
                }
            });
        }

        // Инициализация диаграммы
        const roleChart = new Chart(document.getElementById('roleChart'), {
            type: 'pie',
            data: {
                labels: <?php echo json_encode($role_labels); ?>,
                datasets: [{
                    label: 'Распределение ролей',
                    data: <?php echo json_encode($role_counts); ?>,
                    backgroundColor: ['#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0']
                }]
            },
            options: {
                maintainAspectRatio: false
            }
        });

        // Обновление диаграммы
        function updateRoleChart() {
            $.ajax({
                url: window.location.pathname,
                type: 'GET',
                data: { chart_data: true },
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                dataType: 'json',
                success: function(data) {
                    roleChart.data.labels = data.labels;
                    roleChart.data.datasets[0].data = data.counts;
                    roleChart.update();
                },
                error: function(xhr) {
                    console.error('Ошибка загрузки данных диаграммы:', xhr.responseText);
                    alert('Ошибка обновления диаграммы: ' + (xhr.responseJSON?.message || 'Неизвестная ошибка'));
                }
            });
        }

        // Фильтры и пагинация
        $('input[name="search"]').on('input', function() {
            loadUsers(1);
        });

        $('select[name="role"]').on('change', function() {
            loadUsers(1);
        });

        $('#resetFilters').click(function() {
            $('input[name="search"]').val('');
            $('select[name="role"]').val('');
            loadUsers(1);
        });

        $(document).on('click', '.pagination a', function(e) {
            e.preventDefault();
            const url = new URL($(this).attr('href'));
            loadUsers(url.searchParams.get('page'));
        });

        // Удаление пользователя
        window.deleteUser = function(userId) {
            if (confirm('Вы уверены, что хотите удалить этого пользователя?')) {
                fetch('../process/delete_user.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: `user_id=${userId}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const row = document.getElementById(`user-row-${userId}`);
                        if (row) {
                            row.remove();
                            alert('Пользователь удален');
                            updateRoleChart();
                        } else {
                            loadUsers();
                            updateRoleChart();
                            alert('Пользователь удален, таблица обновлена');
                        }
                    } else {
                        alert(data.error || 'Ошибка удаления');
                    }
                })
                .catch(error => {
                    console.error('Ошибка:', error);
                    alert('Ошибка соединения: ' + error.message);
                });
            }
        };

        // Загрузка данных пользователя для редактирования
        window.loadUserData = function(userId) {
            $.ajax({
                url: '../process/get_user_data.php',
                method: 'GET',
                dataType: 'json',
                data: { user_id: userId },
                success: function(user) {
                    if (user.error) {
                        alert('Ошибка от сервера: ' + user.error);
                        return;
                    }
                    $('#edit_user_id').val(user.user_id || '');
                    $('#edit_username').val(user.username || '');
                    $('#edit_email').val(user.email || '');
                    $('#edit_full_name').val(user.full_name || '');
                    $('#edit_role_id').val(user.role_id || '');
                    $('#edit_position').val(user.position || '');
                    $('#edit_department').val(user.department || '');
                    $('#edit_password').val('');
                    if (user.avatar) {
                        $('#avatar-preview').attr('src', '../Uploads/' + user.avatar).show();
                    } else {
                        $('#avatar-preview').attr('src', '../Uploads/default_avatar.jpg').show();
                    }
                },
                error: function(xhr) {
                    console.error('Ошибка AJAX:', xhr.status, xhr.responseText);
                    alert('Ошибка загрузки данных: ' + (xhr.responseJSON?.error || 'Неизвестная ошибка'));
                }
            });
        };

        // Сохранение изменений пользователя
        window.saveUserChanges = function() {
            const form = document.getElementById('editUserForm');
            const formData = new FormData(form);

            // Валидация на стороне клиента
            const username = formData.get('username').trim();
            const email = formData.get('email').trim();
            const full_name = formData.get('full_name').trim();
            const password = formData.get('password').trim();
            const role_id = formData.get('role_id');

            if (!username) {
                alert('Имя пользователя обязательно');
                return;
            }
            if (!email) {
                alert('Электронная почта обязательна');
                return;
            }
            if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                alert('Недопустимый формат электронной почты');
                return;
            }
            if (!full_name) {
                alert('ФИО обязательно');
                return;
            }
            if (!/^[А-ЯЁа-яё\s-]+$/.test(full_name)) {
                alert('ФИО должно содержать только кириллические символы, пробелы или дефис');
                return;
            }
            if (password && password.length < 8) {
                alert('Пароль должен содержать минимум 8 символов');
                return;
            }
            if (password && !/^(?=.*[A-Za-z])(?=.*\d).+$/.test(password)) {
                alert('Пароль должен содержать как минимум одну букву и одну цифру');
                return;
            }
            if (!['1', '2', '3', '4'].includes(role_id)) {
                alert('Выберите действительную роль');
                return;
            }

            $.ajax({
                url: '../process/update_user.php',
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        $('#editUserModal').modal('hide');
                        alert('Данные пользователя обновлены');
                        loadUsers();
                        updateRoleChart();
                    } else {
                        alert('Ошибка: ' + (response.message || 'Неизвестная ошибка'));
                    }
                },
                error: function(xhr) {
                    console.error('Ошибка AJAX:', xhr.status, xhr.responseText);
                    alert('Ошибка соединения: ' + (xhr.responseJSON?.message || 'Неизвестная ошибка'));
                }
            });
        };

        // Предпросмотр аватара
        window.previewAvatar = function(event) {
            const file = event.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    $('#avatar-preview').attr('src', e.target.result).show();
                };
                reader.readAsDataURL(file);
            }
        };

        // Создание пользователя
        function setupCharCounter(inputSelector, spanId, maxLength) {
            const input = document.querySelector(inputSelector);
            if (!input) return;
            const span = document.getElementById(spanId);
            $span.textContent = maxLength;
            input.addEventListener('input', () => {
                span.textContent = maxLength - input.value.length;
            });
        }

        function generatePassword() {
            const characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
            let result = '';
            for (let i = 0; i < 12; i++) {
                result += characters.charAt(Math.floor(Math.random() * characters.length));
            }
            const passwordInput = document.getElementById('passwordInput');
            if (passwordInput) {
                passwordInput.value = result;
                document.getElementById('passwordChars').textContent = 255 - result.length;
            }
        }

        function togglePassword() {
            const passwordInput = document.getElementById('passwordInput');
            const toggleIcon = document.getElementById('passwordToggleIcon');
            if (passwordInput && toggleIcon) {
                if (passwordInput.type === 'password') {
                    passwordInput.type = 'text';
                    toggleIcon.classList.remove('bi-eye');
                    toggleIcon.classList.add('bi-eye-slash');
                } else {
                    passwordInput.type = 'password';
                    toggleIcon.classList.remove('bi-eye-slash');
                    toggleIcon.classList.add('bi-eye');
                }
            }
        }

        function copyCredentials() {
            const username = document.querySelector('input[name="username"]').value.trim();
            const password = document.querySelector('input[name="password"]').value.trim();
            if (!username || !password) {
                alert('Заполните имя пользователя и пароль перед копированием');
                return;
            }
            const text = `Username: ${username}\nPassword: ${password}`;
            navigator.clipboard.writeText(text).then(() => {
                alert('Учетные данные скопированы в буфер обмена!');
            }).catch(err => {
                console.error('Ошибка копирования:', err);
                alert('Не удалось скопировать учетные данные');
            });
        }

        // Привязка обработчиков для кнопок
        $('#generatePasswordBtn').on('click', function(e) {
            e.preventDefault();
            generatePassword();
        });

        $('#passwordToggleBtn').on('click', function(e) {
            e.preventDefault();
            togglePassword();
        });

        $('#copyCredentialsBtn').on('click', function(e) {
            e.preventDefault();
            copyCredentials();
        });

        $('#createUserForm').on('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);

            const username = formData.get('username').trim();
            const password = formData.get('password').trim();
            const email = formData.get('email').trim();
            const full_name = formData.get('full_name').trim();
            const role_id = formData.get('role_id');

            // Валидация на стороне клиента
            if (!username) {
                alert('Имя пользователя обязательно');
                return;
            }
            if (!password) {
                alert('Пароль обязателен');
                return;
            }
            if (password.length < 8) {
                alert('Пароль должен содержать минимум 8 символов');
                return;
            }
            if (!email) {
                alert('Электронная почта обязательна');
                return;
            }
            if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                alert('Недопустимый формат email');
                return;
            }
            if (!full_name) {
                alert('ФИО обязательно');
                return;
            }
            if (!/^[А-ЯЁа-яё\s-]+$/.test(full_name)) {
                alert('ФИО должно содержать только кириллические символы');
                return;
            }
            if (!['1', '2', '3', '3', '4'].includes(role_id)) {
                alert('Выберите действительную роль');
                return;
            }

            $.ajax({
                url: '../process/create_user.php',
                method: 'POST',
                data: formData,
                contentType: false,
                processData: false,
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        $('#createUserModal').modal('hide');
                        loadUsers();
                        updateRoleChart();
                        alert('Пользователь успешно создан!');
                    } else {
                        alert(response.error || 'Ошибка при создании пользователя');
                    }
                },
                error: function(xhr) {
                    console.error('Ошибка AJAX:', xhr.status, xhr.responseText);
                    const response = xhr.responseJSON || {};
                    let errorMessage = 'Ошибка при создании пользователя';
                    if (xhr.status === 400 && response.errors) {
                        errorMessage = response.errors.join('\n');
                    } else if (response.error) {
                        errorMessage = response.error;
                    } else if (xhr.status === 404) {
                        errorMessage = 'Файл create_user.php не найден';
                    } else if (xhr.status === 500) {
                        errorMessage = 'Внутренняя ошибка сервера';
                    }
                    alert(errorMessage);
                }
            });
        });

        // Инициализация счетчиков символов
        setupCharCounter('[name="username"]', 'usernameChars', 50);
        setupCharCounter('[name="password"]', 'passwordChars', 255);
        setupCharCounter('[name="email"]', 'emailChars', 100);
        setupCharCounter('[name="full_name"]', 'fullNameChars', 100);
        setupCharCounter('[name="position"]', 'positionChars', 100);
        setupCharCounter('[name="department"]', 'departmentChars', 100);
    });
</script>
</body>
</html>