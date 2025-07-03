<?php
// edit_task.php
session_start();
require_once __DIR__ . '/../db.php';

// Включаем логирование
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

// Проверяем авторизацию
if (!isset($_SESSION["user_id"])) {
    error_log("Ошибка: пользователь не авторизован.");
    header("Location: ../pages/auth.php");
    exit();
}

$user_id = $_SESSION["user_id"];
$role_id = $_SESSION["role_id"];
$task_id = intval($_GET["task_id"]);

// Получаем задачу
$task = getTaskDetails($conn, $task_id);
if (!$task) {
    error_log("Ошибка: задача не найдена, task_id=$task_id");
    $_SESSION["error_msg"] = "Задача не найдена.";
    header("Location: ../index.php");
    exit();
}

$project_id = $task["project_id"];

// Проверяем права на редактирование
$project_role = null;
$sql = "SELECT project_role FROM project_participants WHERE project_id = ? AND user_id = ?";
$stmt = $conn->prepare($sql);
if ($stmt === false) {
    error_log("Ошибка подготовки запроса (project_role): " . $conn->error);
    die("Ошибка сервера: не удалось проверить доступ.");
}
$stmt->bind_param("ii", $project_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $project_role = $row["project_role"];
}
$stmt->close();

// Если пользователь не участник, проверяем глобальную роль
if ($project_role === null && ($role_id == 1 || $role_id == 2)) {
    $project_role = ($role_id == 1) ? 'admin' : 'manager';
}

// Проверяем права
if (!in_array($project_role, ['admin', 'manager'])) {
    error_log("Ошибка: у пользователя нет прав для редактирования задачи (project_role: " . ($project_role ?? 'не определена') . ").");
    $_SESSION["error_msg"] = "У вас нет прав для редактирования задачи.";
    header("Location: ../pages/task_details.php?task_id=" . $task_id);
    exit();
}

// Получаем участников проекта
$participants = getProjectParticipants($conn, $project_id);
if (empty($participants)) {
    error_log("Ошибка: участники проекта не найдены, project_id=$project_id");
    $_SESSION["error_msg"] = "Не удалось загрузить участников проекта.";
    header("Location: ../pages/task_details.php?task_id=" . $task_id);
    exit();
}

// Получаем текущих помощников
$current_assistants = json_decode($task["assistants"], true) ?? [];
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Редактировать задачу</title>
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
        .searchable-select {
            position: relative;
        }
        .searchable-select input {
            position: relative;
            padding-right: 40px;
        }
        .searchable-select::before {
            content: '🔍';
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 1.1rem;
            color: #6c757d;
            z-index: 1;
        }
        .searchable-select .options {
            max-height: 200px;
            overflow-y: auto;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            background: #ffffff;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            position: absolute;
            z-index: 1000;
            width: 100%;
            margin-top: 0.25rem;
            display: none;
        }
        .searchable-select .options div {
            padding: 12px 16px;
            font-size: 0.95rem;
            cursor: pointer;
            transition: background 0.2s ease;
        }
        .searchable-select .options div:hover {
            background: #f0f0f0;
        }
        .selected-assistants {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-top: 0.5rem;
            min-height: 38px;
            padding: 8px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            background: #f8f9fa;
        }
        .assistant-tag {
            display: inline-flex;
            align-items: center;
            background: #007bff;
            color: #fff;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
            cursor: pointer;
            transition: background 0.2s ease;
        }
        .assistant-tag:hover {
            background: #0056b3;
        }
        .assistant-tag .remove {
            margin-left: 8px;
            font-size: 1rem;
            line-height: 1;
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
                <a href="../pages/task_details.php?task_id=<?= $task_id ?>" class="btn btn-secondary">Назад</a>
            </div>
        </div>
    </nav>

    <div class="container">
        <h2>Редактировать задачу</h2>
        <?php if (isset($_SESSION["error_msg"])): ?>
            <div class="alert alert-danger">
                <?= htmlspecialchars($_SESSION["error_msg"]) ?>
                <?php unset($_SESSION["error_msg"]); ?>
            </div>
        <?php endif; ?>
        <form method="post" action="../process/process_edit_task.php">
            <input type="hidden" name="task_id" value="<?= $task_id ?>">
            <input type="hidden" name="project_id" value="<?= $project_id ?>">
            <div class="mb-3">
                <label for="task_name" class="form-label">Название задачи</label>
                <input type="text" class="form-control" id="task_name" name="task_name" value="<?= htmlspecialchars($task["task_name"] ?? '') ?>" required>
            </div>
            <div class="mb-3">
                <label for="responsible_search" class="form-label">Ответственный</label>
                <div class="searchable-select">
                    <input type="text" class="form-control" id="responsible_search" placeholder="Поиск ответственного..." autocomplete="off" value="<?php
                        $responsible = getUserById($conn, $task["responsible_id"]);
                        if ($responsible) {
                            // Ищем полное имя в $participants
                            $full_name = '';
                            foreach ($participants as $participant) {
                                if ($participant["user_id"] == $task["responsible_id"]) {
                                    $full_name = $participant["full_name"];
                                    break;
                                }
                            }
                            echo htmlspecialchars($responsible["username"] . ($full_name ? " ($full_name)" : ""));
                        } else {
                            echo '';
                            error_log("Ответственный не найден, responsible_id=" . $task["responsible_id"]);
                        }
                    ?>">
                    <input type="hidden" id="responsible" name="responsible" value="<?= $task["responsible_id"] ?>" required>
                    <div class="options" id="responsible_options">
                        <?php foreach ($participants as $participant): ?>
                            <div data-value="<?= $participant["user_id"] ?>">
                                <?= htmlspecialchars($participant["username"] . " (" . $participant["full_name"] . ")") ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <div class="mb-3">
                <label for="assistants_search" class="form-label">Помощники</label>
                <div class="searchable-select">
                    <input type="text" class="form-control" id="assistants_search" placeholder="Поиск помощников..." autocomplete="off">
                    <div class="options" id="assistants_options">
                        <?php foreach ($participants as $participant): ?>
                            <div data-value="<?= $participant["user_id"] ?>">
                                <?= htmlspecialchars($participant["username"] . " (" . $participant["full_name"] . ")") ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="selected-assistants" id="selected_assistants">
                    <?php foreach ($current_assistants as $assistant_id): ?>
                        <?php
                        $assistant = getUserById($conn, $assistant_id);
                        if ($assistant):
                            // Ищем полное имя в $participants
                            $full_name = '';
                            foreach ($participants as $participant) {
                                if ($participant["user_id"] == $assistant_id) {
                                    $full_name = $participant["full_name"];
                                    break;
                                }
                            }
                        ?>
                            <span class="assistant-tag" data-value="<?= $assistant_id ?>">
                                <?= htmlspecialchars($assistant["username"] . ($full_name ? " ($full_name)" : "")) ?>
                                <span class="remove">×</span>
                            </span>
                        <?php else: ?>
                            <?php error_log("Помощник не найден, assistant_id=$assistant_id"); ?>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
                <select class="form-select d-none" id="assistants" name="assistants[]" multiple>
                    <?php foreach ($current_assistants as $assistant_id): ?>
                        <?php
                        $assistant = getUserById($conn, $assistant_id);
                        if ($assistant):
                            $full_name = '';
                            foreach ($participants as $participant) {
                                if ($participant["user_id"] == $assistant_id) {
                                    $full_name = $participant["full_name"];
                                    break;
                                }
                            }
                        ?>
                            <option value="<?= $assistant_id ?>" selected>
                                <?= htmlspecialchars($assistant["username"] . ($full_name ? " ($full_name)" : "")) ?>
                            </option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-3">
                <label for="status" class="form-label">Статус</label>
                <select class="form-select" id="status" name="status" required>
                    <option value="pending" <?= $task["status"] === 'pending' ? 'selected' : '' ?>>В ожидании</option>
                    <option value="in_progress" <?= $task["status"] === 'in_progress' ? 'selected' : '' ?>>В процессе</option>
                    <option value="completed" <?= $task["status"] === 'completed' ? 'selected' : '' ?>>Завершена</option>
                </select>
            </div>
            <div class="mb-4">
                <label for="deadline" class="form-label">Срок</label>
                <input type="date" class="form-control" id="deadline" name="deadline" value="<?= htmlspecialchars($task["deadline"] ?? '') ?>" required>
            </div>
            <div class="d-flex gap-3">
                <button type="submit" class="btn btn-primary">Сохранить</button>
                <a href="../pages/task_details.php?task_id=<?= $task_id ?>" class="btn btn-secondary">Отмена</a>
            </div>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Поиск для ответственного
        const responsibleSearch = document.getElementById('responsible_search');
        const responsibleOptions = document.getElementById('responsible_options');
        const responsibleInput = document.getElementById('responsible');

        responsibleSearch.addEventListener('focus', () => {
            responsibleOptions.style.display = 'block';
        });

        responsibleSearch.addEventListener('input', () => {
            const filter = responsibleSearch.value.toLowerCase();
            const options = responsibleOptions.querySelectorAll('div');
            options.forEach(option => {
                const text = option.textContent.toLowerCase();
                option.style.display = text.includes(filter) ? 'block' : 'none';
            });
        });

        responsibleOptions.addEventListener('click', (e) => {
            if (e.target.tagName === 'DIV') {
                const value = e.target.getAttribute('data-value');
                responsibleInput.value = value;
                responsibleSearch.value = e.target.textContent;
                responsibleOptions.style.display = 'none';
                console.log('Выбран ответственный:', value);
            }
        });

        document.addEventListener('click', (e) => {
            if (!responsibleSearch.contains(e.target) && !responsibleOptions.contains(e.target)) {
                responsibleOptions.style.display = 'none';
            }
        });

        // Поиск для помощников
        const assistantsSearch = document.getElementById('assistants_search');
        const assistantsOptions = document.getElementById('assistants_options');
        const assistantsSelect = document.getElementById('assistants');
        const selectedAssistants = document.getElementById('selected_assistants');

        assistantsSearch.addEventListener('focus', () => {
            assistantsOptions.style.display = 'block';
        });

        assistantsSearch.addEventListener('input', () => {
            const filter = assistantsSearch.value.toLowerCase();
            const options = assistantsOptions.querySelectorAll('div');
            options.forEach(option => {
                const text = option.textContent.toLowerCase();
                option.style.display = text.includes(filter) ? 'block' : 'none';
            });
        });

        assistantsOptions.addEventListener('click', (e) => {
            if (e.target.tagName === 'DIV') {
                const value = e.target.getAttribute('data-value');
                const text = e.target.textContent;
                if (value === responsibleInput.value) {
                    alert('Этот пользователь уже выбран как ответственный.');
                    return;
                }
                if (!Array.from(assistantsSelect.options).some(opt => opt.value === value)) {
                    // Добавляем в скрытый select
                    const option = new Option(text, value);
                    assistantsSelect.add(option);
                    // Добавляем тег
                    const tag = document.createElement('span');
                    tag.className = 'assistant-tag';
                    tag.dataset.value = value;
                    tag.innerHTML = `${text}<span class="remove">×</span>`;
                    selectedAssistants.appendChild(tag);
                    console.log('Добавлен помощник:', value);
                }
                assistantsSearch.value = '';
                assistantsOptions.style.display = 'none';
                console.log('Текущие помощники:', Array.from(assistantsSelect.options).map(opt => opt.value));
            }
        });

        document.addEventListener('click', (e) => {
            if (!assistantsSearch.contains(e.target) && !assistantsOptions.contains(e.target)) {
                assistantsOptions.style.display = 'none';
            }
        });

        // Удаление помощника
        selectedAssistants.addEventListener('click', (e) => {
            if (e.target.classList.contains('remove') || e.target.parentElement.classList.contains('assistant-tag')) {
                const tag = e.target.classList.contains('remove') ? e.target.parentElement : e.target;
                const value = tag.dataset.value;
                const option = Array.from(assistantsSelect.options).find(opt => opt.value === value);
                if (option) {
                    assistantsSelect.remove(option.index);
                    tag.remove();
                    console.log('Удалён помощник:', value);
                    console.log('Текущие помощники:', Array.from(assistantsSelect.options).map(opt => opt.value));
                }
            }
        });

        // При отправке формы
        document.querySelector('form').addEventListener('submit', (e) => {
            const deadline = document.getElementById('deadline').value;
            console.log('Значение deadline перед отправкой:', deadline);
            if (!/^\d{4}-\d{2}-\d{2}$/.test(deadline)) {
                e.preventDefault();
                alert('Пожалуйста, введите дату в формате YYYY-MM-DD.');
                return;
            }
            Array.from(assistantsSelect.options).forEach(option => {
                option.selected = true;
            });
            console.log('Отправка формы, выбранные помощники:', Array.from(assistantsSelect.options).map(opt => opt.value));
        });
    </script>
</body>
</html>