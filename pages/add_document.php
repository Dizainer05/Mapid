<?php
// add_document.php
session_start();
require_once __DIR__ . '/../db.php';

// Проверяем авторизацию
if (!isset($_SESSION["user_id"])) {
    error_log("Ошибка: пользователь не авторизован.");
    header("Location: ../pages/auth.php");
    exit();
}

// Проверяем наличие project_id
if (!isset($_GET["project_id"]) || !is_numeric($_GET["project_id"])) {
    error_log("Ошибка: некорректный идентификатор проекта.");
    $_SESSION["error_msg"] = "Некорректный идентификатор проекта.";
    header("Location: ../index.php");
    exit();
}
$project_id = intval($_GET["project_id"]);
$user_id = $_SESSION["user_id"];
$role_id = $_SESSION["role_id"];

// Получаем проектную роль пользователя
$project_role = null;
$sql = "SELECT project_role FROM project_participants WHERE project_id = ? AND user_id = ?";
$stmt = $conn->prepare($sql);
if ($stmt === false) {
    error_log("Ошибка подготовки запроса (project_role): " . $conn->error_log);
    $_SESSION["error_msg"] = "Ошибка сервера: не удалось проверить доступ.";
    header("Location: ../pages/project_details.php?id=" . $project_id);
    exit();
}
$stmt->bind_param("ii", $project_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $project_role = $row["project_role"];
}
$stmt->close();

// Если пользователь не является участником проекта, проверяем его глобальную роль
if ($project_role === null && ($role_id == 1 || $role_id == 2)) {
    $project_role = ($role_id == 1) ? 'admin' : 'manager';
}

// Проверяем, имеет ли пользователь право загружать документы (должен быть admin, manager или employee)
if (!in_array($project_role, ['admin', 'manager', 'employee'])) {
    error_log("Ошибка: у пользователя нет прав для загрузки документов (project_role: " . ($project_role ?? 'не определена') . ")");
    $_SESSION["error_msg"] = "У вас нет прав для загрузки документов.";
    header("Location: ../pages/project_details.php?id=" . $project_id);
    exit();
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Добавить документ</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
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
            max-width: 700px;
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
        .form-control, .form-select {
            border: 2px solid #e0e0e0e0;
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
        .alert {
            border-radius: 10px;
            margin-bottom: 1.5rem;
        }
        @media (max-width: 768px) {
            .container {
                margin-top: 60px;
                padding: 1.5rem;
            }
            h2 {
                font-size: 1.5rem;
            }
            .form-control, .form-select {
                padding: 10px;
                font-size: 0.9rem;
            }
            .btn-primary, .btn-secondary {
                padding: 8px 20px;
                font-size: 0.9rem;
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
        <h2>Добавить документ в проект</h2>
        <?php if (isset($_SESSION["error_msg"])): ?>
            <div class="alert alert-danger">
                <?= htmlspecialchars($_SESSION["error_msg"]) ?>
                <?php unset($_SESSION["error_msg"]); ?>
            </div>
        <?php endif; ?>
        <form method="post" action="../process/process_add_document.php" enctype="multipart/form-data">
            <input type="hidden" name="project_id" value="<?= $project_id ?>">
            <div class="mb-3">
                <label for="documentFile" class="form-label">Выберите файл</label>
                <input type="file" class="form-control" id="documentFile" name="document_file" required>
            </div>
            <div class="mb-3">
                <label for="documentType" class="form-label">Тип документа</label>
                <select class="form-select" id="documentType" name="document_type" required>
                    <option value="drawing">Чертежи</option>
                    <option value="report">Отчеты</option>
                    <option value="photo">Фотографии</option>
                    <option value="document" selected>Документ</option>
                </select>
            </div>
            <div class="d-flex gap-3">
                <button type="submit" class="btn btn-primary">Добавить документ</button>
                <a href="../pages/project_details.php?id=<?= $project_id ?>" class="btn btn-secondary">Отмена</a>
            </div>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>