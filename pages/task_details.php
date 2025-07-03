<?php
// task_details.php
session_start();
require_once __DIR__ . '/../db.php';

if (!isset($_SESSION["user_id"])) {
    error_log("Ошибка: пользователь не авторизован.");
    header("Location: ../pages/auth.php");
    exit();
}

$task_id = intval($_GET["task_id"]);
$task = getTaskDetails($conn, $task_id);
if (!$task) {
    error_log("Ошибка: задача не найдена, task_id=$task_id");
    $_SESSION["error_msg"] = "Задача не найдена.";
    header("Location: ../index.php");
    exit();
}

$project_id = $task["project_id"];
$documents = getTaskDocuments($conn, $task_id);

// Проверяем доступ к просмотру
if (!canViewTask($conn, $_SESSION["user_id"], $task_id)) {
    error_log("Ошибка: нет доступа к задаче, task_id=$task_id, user_id=" . $_SESSION["user_id"]);
    $_SESSION["error_msg"] = "У вас нет доступа к этой задаче.";
    header("Location: ../index.php");
    exit();
}

// Получаем участников проекта для full_name
$participants = getProjectParticipants($conn, $project_id);

// Получаем основного ответственного
$responsible = getUserById($conn, $task["responsible_id"]);
$responsible_name = 'Не указан';
if ($responsible) {
    $full_name = '';
    foreach ($participants as $participant) {
        if ($participant["user_id"] == $task["responsible_id"]) {
            $full_name = $participant["full_name"];
            break;
        }
    }
    $responsible_name = $responsible["username"] . ($full_name ? " ($full_name)" : "");
}

// Получаем помощников
$assistants = json_decode($task["assistants"], true) ?? [];
$assistant_names = array_map(function($id) use ($conn, $participants) {
    $user = getUserById($conn, $id);
    if ($user) {
        $full_name = '';
        foreach ($participants as $participant) {
            if ($participant["user_id"] == $id) {
                $full_name = $participant["full_name"];
                break;
            }
        }
        return $user["username"] . ($full_name ? " ($full_name)" : "");
    }
    error_log("Помощник не найден, assistant_id=$id");
    return 'Не указан';
}, $assistants);

// Проверяем, может ли пользователь загружать документы
$can_upload = canUploadTaskDocument($conn, $_SESSION["user_id"], $task_id);

// Маппинг статусов для перевода (обновлено для "pending")
$statusTranslations = [
    'pending' => 'Ожидание',
    'in_progress' => 'В процессе',
    'completed' => 'Завершено'
];

// Маппинг типов документов для перевода
$documentTypeTranslations = [
    'drawing' => 'Чертежи',
    'report' => 'Отчеты',
    'photo' => 'Фотографии',
    'document' => 'Документ'
];

// Перевод статуса
$translatedStatus = $statusTranslations[$task["status"]] ?? $task["status"];
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Детали задачи – <?= htmlspecialchars($task["task_name"] ?? '') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background-color: #f4f7fa;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, 'Open Sans', 'Helvetica Neue', sans-serif;
        }
        .navbar {
            background-color: #ffffff;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 0.75rem 1rem;
        }
        .navbar-brand {
            font-weight: 600;
            color: #333;
        }
        .container {
            max-width: 800px;
            margin-top: 80px;
            padding: 2rem;
            background: #ffffff;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        }
        h2 {
            font-size: 1.8rem;
            font-weight: 600;
            color: #1a1a1a;
            margin-bottom: 1.5rem;
        }
        h4 {
            font-size: 1.2rem;
            font-weight: 500;
            color: #333;
            margin-top: 2rem;
            margin-bottom: 1rem;
        }
        .form-control, .form-select {
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            padding: 12px;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            background: #f8f9fa;
        }
        .form-control:focus, .form-select:focus {
            border-color: #007bff;
            box-shadow: 0 0 8px rgba(0,123,255,0.2);
            outline: none;
        }
        .form-label {
            font-weight: 500;
            color: #333;
            margin-bottom: 0.5rem;
        }
        .searchable-filter {
            position: relative;
            max-width: 300px;
        }
        .table {
            border-radius: 10px;
            overflow: hidden;
            background: #ffffff;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        .table th {
            background-color: #f8f9fa;
            font-weight: 500;
            color: #333;
            padding: 12px;
        }
        .table td {
            padding: 12px;
            vertical-align: middle;
            font-size: 0.95rem;
            color: #555;
        }
        .table-hover tbody tr:hover {
            background-color: #f0f0f0;
        }
        .table a {
            color: #007bff;
            text-decoration: none;
            transition: color 0.2s ease;
        }
        .table a:hover {
            color: #0056b3;
            text-decoration: underline;
        }
        .btn-primary {
            background-color: #007bff;
            border: none;
            padding: 10px 24px;
            border-radius: 8px;
            font-weight: 500;
            transition: background-color 0.3s ease, transform 0.2s ease;
        }
        .btn-primary:hover {
            background-color: #0056b3;
            transform: translateY(-2px);
        }
        .btn-secondary {
            background-color: #6c757d;
            border: none;
            padding: 10px 24px;
            border-radius: 8px;
            font-weight: 500;
            transition: background-color 0.3s ease, transform 0.2s ease;
        }
        .btn-secondary:hover {
            background-color: #5a6268;
            transform: translateY(-2px);
        }
        .btn-warning {
            background-color: #ffc107;
            border: none;
            padding: 10px 24px;
            border-radius: 8px;
            font-weight: 500;
            color: #333;
            transition: background-color 0.3s ease, transform 0.2s ease;
        }
        .btn-warning:hover {
            background-color: #e0a800;
            transform: translateY(-2px);
        }
        .alert {
            border-radius: 10px;
            margin-bottom: 1.5rem;
        }
        .task-info p {
            font-size: 0.95rem;
            color: #555;
            margin-bottom: 0.75rem;
        }
        .task-info strong {
            color: #333;
            font-weight: 500;
        }
        @media (max-width: 768px) {
            .container {
                margin-top: 60px;
                padding: 1.5rem;
            }
            h2 {
                font-size: 1.5rem;
            }
            h4 {
                font-size: 1.1rem;
            }
            .form-control, .form-select {
                padding: 10px;
                font-size: 0.9rem;
            }
            .btn-primary, .btn-secondary, .btn-warning {
                padding: 8px 20px;
                font-size: 0.9rem;
            }
            .table td, .table th {
                font-size: 0.9rem;
                padding: 10px;
            }
            .searchable-filter {
                max-width: 100%;
            }
        }
    </style>
</head>
<body>
    <!-- Навигационное меню -->
    <nav class="navbar fixed-top">
        <div class="container-fluid">
            <a class="navbar-brand" href="../index.php">Панель управления</a>
            <div class="d-flex">
                <a href="../pages/project_details.php?id=<?= $project_id ?>" class="btn btn-secondary">Назад</a>
            </div>
        </div>
    </nav>

    <div class="container">
        <h2>Детали задачи</h2>
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
        <div class="task-info">
            <p><strong>Название:</strong> <?= htmlspecialchars($task["task_name"] ?? '') ?></p>
            <p><strong>Ответственный:</strong> <?= htmlspecialchars($responsible_name) ?></p>
            <p><strong>Помощники:</strong> <?= implode(', ', array_map('htmlspecialchars', $assistant_names)) ?: 'Нет помощников' ?></p>
            <p><strong>Статус:</strong> <?= htmlspecialchars($translatedStatus) ?></p>
            <p><strong>Срок:</strong> <?= htmlspecialchars($task["deadline"] ?? '') ?></p>
        </div>
        <?php if ($_SESSION["role_id"] == 1 || $_SESSION["role_id"] == 2): ?>
            <a href="../pages/edit_task.php?task_id=<?= $task_id ?>" class="btn btn-warning mb-3"><i class="bi bi-pencil"></i> Редактировать</a>
        <?php endif; ?>
        <h4>Документы задачи</h4>
        <div class="mb-3 d-flex justify-content-between align-items-end">
            <div class="searchable-filter">
                <input type="text" id="documentSearch" class="form-control" placeholder="Поиск по названию" autocomplete="off">
                <select id="typeFilter" class="form-select mt-2">
                    <option value="">Все типы</option>
                    <option value="drawing">Чертежи</option>
                    <option value="report">Отчеты</option>
                    <option value="photo">Фотографии</option>
                    <option value="document">Документ</option>
                </select>
            </div>
        </div>
        <table class="table table-hover" id="documentsTable">
            <thead>
                <tr>
                    <th>Название файла</th>
                    <th>Тип</th>
                    <th>Дата загрузки</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($documents as $doc): ?>
                    <tr>
                        <td><a href="<?= htmlspecialchars($doc["file_path"] ?? '') ?>" target="_blank" download="<?= htmlspecialchars($doc["file_name"] ?? '') ?>"><?= htmlspecialchars($doc["file_name"] ?? '') ?></a></td>
                        <td data-type="<?= htmlspecialchars($doc["document_type"] ?? '') ?>"><?= htmlspecialchars($documentTypeTranslations[$doc["document_type"]] ?? $doc["document_type"]) ?></td>
                        <td><?= htmlspecialchars($doc["upload_date"] ?? '') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php if ($can_upload): ?>
            <form action="upload_task_document.php" method="post" enctype="multipart/form-data" class="mt-4">
                <input type="hidden" name="task_id" value="<?= $task_id ?>">
                <div class="mb-3">
                    <label for="document_type" class="form-label">Тип документа</label>
                    <select class="form-select" id="document_type" name="document_type" required>
                        <option value="drawing">Чертежи</option>
                        <option value="report">Отчеты</option>
                        <option value="photo">Фотографии</option>
                        <option value="document" selected>Документ</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label for="document_file" class="form-label">Загрузить документ</label>
                    <input type="file" class="form-control" id="document_file" name="document_file" required>
                </div>
                <button type="submit" class="btn btn-primary">Загрузить</button>
            </form>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Фильтрация документов
        document.getElementById('documentSearch').addEventListener('input', filterDocuments);
        document.getElementById('typeFilter').addEventListener('change', filterDocuments);

        function filterDocuments() {
            const searchVal = document.getElementById('documentSearch').value.toLowerCase();
            const typeVal = document.getElementById('typeFilter').value;

            const rows = document.querySelectorAll('#documentsTable tbody tr');
            rows.forEach(row => {
                const name = row.cells[0].textContent.toLowerCase();
                const type = row.cells[1].dataset.type;
                const matchName = name.includes(searchVal);
                const matchType = typeVal === '' || type === typeVal;
                row.style.display = matchName && matchType ? '' : 'none';
            });
        }
    </script>
</body>
</html>