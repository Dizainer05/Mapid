<?php
// db.php
// Настройки подключения к базе данных
$servername  = "MySQL-8.4";
$dbUsername  = "root";
$dbPassword  = "";
$dbName      = "bdMapid"; // Имя вашей базы данных

// Устанавливаем соединение с базой данных
$conn = new mysqli($servername, $dbUsername, $dbPassword, $dbName);
if ($conn->connect_error) {
    die("Ошибка подключения: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");
/* 
Функции для вызова хранимых процедур.
Ниже приведён список функций, соответствующих процедурам:
  1. registerUser                      - sp_register_user
  2. authenticateUser                  - sp_authenticate_user
  3. updateUserData                    - sp_update_user_data
  4. deleteUser                        - sp_delete_user
  5. joinProject                       - sp_join_project
  6. createProject                     - sp_create_project
  7. updateProject                     - sp_update_project
  8. addProjectParticipantMan          - sp_add_project_participant_man
  9. updateProjectStage                - sp_update_project_stage
 10. addProjectDocumentMan             - sp_add_project_document_man
 11. updateProjectDocument             - sp_update_project_document
 12. adminDeleteProject                - sp_admin_delete_project
 13. adminDeleteUser                   - sp_admin_delete_user
 14. adminUpdateUser                   - sp_admin_update_user
 15. adminDeleteProjectParticipant     - sp_admin_delete_project_participant
 16. adminUpdateProjectStage           - sp_admin_update_project_stage
 17. adminUpdateProjectDocument        - sp_admin_update_project_document
 18. getEmployeeProjects               - sp_get_employee_projects
 19. getProjectDetails                 - sp_get_project_details
 20. getProjectParticipants            - sp_get_project_participants
 21. getProjectStages                  - sp_get_project_stages
 22. getProjectDocuments               - sp_get_project_documents
 23. employeeUploadDocument            - sp_employee_upload_document
 24. calculateProjectRating            - sp_calculate_project_rating
 25. exportProjectReport               - sp_export_project_report
 26. searchUsers                       - sp_search_users
 27. exportUsersReport                 - sp_export_users_report
*/

// 1. Регистрация пользователя
function registerUser($conn, $username, $password, $email, $full_name, $role_id, $position, $department, $avatar) {
    try {
        $sql = "CALL sp_register_user(?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        if ($stmt === false) {
            error_log("Ошибка подготовки запроса в registerUser: " . $conn->error);
            throw new Exception("Ошибка подготовки запроса.");
        }
        $stmt->bind_param("ssssisss", $username, $password, $email, $full_name, $role_id, $position, $department, $avatar);
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    } catch (mysqli_sql_exception $e) {
        $errorMessage = $e->getMessage();
        error_log("Ошибка в registerUser: " . $errorMessage);
        
        // Обработка пользовательских ошибок из хранимой процедуры (SQLSTATE 45000)
        if ($e->getCode() == 1644) { // Код для SIGNAL SQLSTATE '45000'
            if (strpos($errorMessage, 'Логин уже занят') !== false) {
                throw new Exception("Логин '$username' уже занят.");
            } elseif (strpos($errorMessage, 'Электронная почта уже используется') !== false) {
                throw new Exception("Электронная почта '$email' уже используется.");
            } elseif (strpos($errorMessage, 'ФИО уже зарегистрировано') !== false) {
                throw new Exception("ФИО '$full_name' уже зарегистрировано.");
            } else {
                throw new Exception("Ошибка: данные уже существуют.");
            }
        }
        // Обработка дубликатов (на случай прямого нарушения уникального индекса)
        elseif ($e->getCode() == 1062) { // Код ошибки MySQL для дублирования
            if (strpos($errorMessage, 'users.username') !== false) {
                throw new Exception("Логин '$username' уже занят.");
            } elseif (strpos($errorMessage, 'users.email') !== false) {
                throw new Exception("Электронная почта '$email' уже используется.");
            } elseif (strpos($errorMessage, 'users.full_name') !== false) {
                throw new Exception("ФИО '$full_name' уже зарегистрировано.");
            } else {
                throw new Exception("Ошибка: данные уже существуют.");
            }
        } else {
            throw new Exception("Ошибка базы данных: " . $errorMessage);
        }
    }
}

// 2. Аутентификация пользователя (возвращает данные, включая хэш пароля)
function authenticateUser($conn, $username) {
    $stmt = $conn->prepare("CALL sp_authenticate_user(?)");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    return $user;
}

// 3. Обновление личных данных пользователя
function updateUserData($conn, $user_id, $full_name, $email, $position, $department, $avatar) {
    $stmt = $conn->prepare("CALL sp_update_user_data(?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("isssss", $user_id, $full_name, $email, $position, $department, $avatar);
    $result = $stmt->execute();
    $stmt->close();
    return $result;
}

// 4. Удаление пользователя
function deleteUser($conn, $user_id) {
    $stmt = $conn->prepare("CALL sp_delete_user(?)");
    $stmt->bind_param("i", $user_id);
    $result = $stmt->execute();
    $stmt->close();
    return $result;
}

// 5. Присоединение к проекту по коду доступа
function joinProject($conn, $user_id, $access_code) {
    $stmt = $conn->prepare("CALL sp_join_project(?, ?)");
    $stmt->bind_param("is", $user_id, $access_code);
    $result = $stmt->execute();
    $stmt->close();
    return $result;
}

// 6. Создание проекта (доступно менеджеру и администратору)
// function createProject($conn, $user_id, $name, $short_name, $description, $planned_start_date, $actual_start_date, $planned_end_date, $actual_end_date, $planned_budget, $actual_budget, $planned_digitalization, $actual_digitalization, $planned_labor, $actual_labor, $status, $lifecycle_stage, $scale, $charter_file_path, $expected_resources, $access_code) {
//     $stmt = $conn->prepare("CALL sp_create_project(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
//     $stmt->bind_param("issssssddddddssisss", $user_id, $name, $short_name, $description, $planned_start_date, $actual_start_date, $planned_end_date, $actual_end_date, $planned_budget, $actual_budget, $planned_digitalization, $actual_digitalization, $planned_labor, $actual_labor, $status, $lifecycle_stage, $scale, $charter_file_path, $expected_resources, $access_code);
//     $result = $stmt->execute();
//     $stmt->close();
//     return $result;
// }

// 6. Создание проекта (доступно менеджеру и администратору)
function createProject(
    $conn,
    $user_id,
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
    $access_code
) {
    // Логирование всех параметров для отладки
    error_log("db.php: Параметры перед привязкой: user_id=$user_id, name='$name', charter_file_path='$charter_file_path', status='$status'");

    // Подготовка запроса
    $stmt = $conn->prepare("CALL sp_create_project(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    if (!$stmt) {
        error_log("db.php: Ошибка подготовки запроса sp_create_project: " . $conn->error);
        return false;
    }

    // Приведение типов для корректной привязки
    $user_id = (int)$user_id;
    $name = (string)$name;
    $short_name = $short_name ? (string)$short_name : null;
    $description = $description ? (string)$description : null;
    $planned_start_date = $planned_start_date ? (string)$planned_start_date : null;
    $actual_start_date = $actual_start_date ? (string)$actual_start_date : null;
    $planned_end_date = $planned_end_date ? (string)$planned_end_date : null;
    $actual_end_date = $actual_end_date ? (string)$actual_end_date : null;
    $planned_budget = $planned_budget !== null ? (float)$planned_budget : null;
    $actual_budget = $actual_budget !== null ? (float)$actual_budget : null;
    $planned_digitalization = $planned_digitalization !== null ? (int)$planned_digitalization : null;
    $actual_digitalization = $actual_digitalization !== null ? (int)$actual_digitalization : null;
    $planned_labor = $planned_labor !== null ? (int)$planned_labor : null;
    $actual_labor = $actual_labor !== null ? (int)$actual_labor : null;
    $status = (string)$status;
    $lifecycle_stage = $lifecycle_stage ? (string)$lifecycle_stage : null;
    $scale = (string)$scale;
    $charter_file_path = empty($charter_file_path) ? null : (string)$charter_file_path; // Убедимся, что пустая строка не станет проблемой
    $expected_resources = $expected_resources ? (string)$expected_resources : null;
    $access_code = (string)$access_code;

    // Дополнительное логирование значения charter_file_path
    error_log("db.php: Значение charter_file_path перед привязкой: " . ($charter_file_path ?? 'NULL'));

    // Привязка параметров
    $stmt->bind_param(
        "isssssssddiiiissssss",
        $user_id,
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
        $access_code
    );

    // Выполнение запроса
    $result = $stmt->execute();
    if (!$result) {
        error_log("db.php: Ошибка выполнения sp_create_project: " . $stmt->error);
    } else {
        // Проверка значения после вставки
        $project_id = $conn->insert_id;
        $check_stmt = $conn->prepare("SELECT charter_file_path FROM projects WHERE project_id = ?");
        $check_stmt->bind_param("i", $project_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result()->fetch_assoc();
        error_log("db.php: Путь к файлу после вставки: " . ($check_result['charter_file_path'] ?? 'NULL'));
        $check_stmt->close();
    }

    $stmt->close();
    return $result;
}
//TODO Нужно исправть ошибку когда создаешь проект не заполняя все поля, ошибку будет с рейтингом проекта 


// 7. Редактирование проекта (manager/admin)
// Формат: i (user_id), i (project_id), s (name), s (short_name), s (description),
// s (planned_start_date), s (actual_start_date), s (planned_end_date), s (actual_end_date),
// d (planned_budget), d (actual_budget), d (planned_digitalization), d (actual_digitalization),
// d (planned_labor), d (actual_labor), s (status), s (lifecycle_stage), i (scale),
// s (charter_file_path), s (expected_resources), s (access_code), d (rating), s (start_date)
function updateProject($conn, $user_id, $project_id, $name, $short_name, $description, 
                     $planned_start_date, $actual_start_date, $planned_end_date, $actual_end_date,
                     $planned_budget, $actual_budget, $planned_digitalization, $actual_digitalization,
                     $planned_labor, $actual_labor, $status, $lifecycle_stage, $scale,
                     $charter_file_path, $expected_resources, $access_code, $start_date) {
    
    // Логируем параметры перед вызовом процедуры
    error_log("Вызов sp_update_project с параметрами: " . json_encode([
        'user_id' => $user_id,
        'project_id' => $project_id,
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

    $stmt = $conn->prepare("CALL sp_update_project(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    if ($stmt === false) {
        error_log("Ошибка подготовки запроса sp_update_project: " . $conn->error);
        return false;
    }
    
    // Убрали параметр rating, обновили строку формата
    $stmt->bind_param("iisssssssddddddssissss", 
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
    
    $result = $stmt->execute();
    if (!$result) {
        error_log("Ошибка выполнения sp_update_project: " . $stmt->error);
    } else {
        error_log("sp_update_project успешно выполнена для project_id=$project_id");
    }
    $stmt->close();
    return $result;
}

// 8. Добавление участника в проект (manager/admin)
// Формат: i (user_id), i (project_id), i (target_user_id), s (project_role)
function addProjectParticipantMan($conn, $user_id, $project_id, $target_user_id, $project_role) {
    $stmt = $conn->prepare("CALL sp_add_project_participant_man(?, ?, ?, ?)");
    $stmt->bind_param("iiis", $user_id, $project_id, $target_user_id, $project_role);
    $result = $stmt->execute();
    $stmt->close();
    return $result;
}

// 9. Редактирование этапа проекта (manager/admin)
// Формат: i (user_id), i (stage_id), s (stage_name), s (description), s (start_date), s (end_date), s (status), i (stage_order)
function updateProjectStage($conn, $user_id, $stage_id, $stage_name, $description, $start_date, $end_date, $status, $stage_order) {
    $stmt = $conn->prepare("CALL sp_update_project_stage(?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("iisssssi", $user_id, $stage_id, $stage_name, $description, $start_date, $end_date, $status, $stage_order);
    $result = $stmt->execute();
    $stmt->close();
    return $result;
}

// 10. Добавление документа к проекту (manager/admin)
// Формат: i (user_id), i (project_id), s (file_name), s (file_path), i (uploaded_by), s (document_type)
function addProjectDocumentMan($conn, $user_id, $project_id, $file_name, $file_path, $uploaded_by, $document_type) {
    $stmt = $conn->prepare("CALL sp_add_project_document_man(?, ?, ?, ?, ?, ?)");
    if ($stmt === false) {
        error_log("Ошибка подготовки запроса sp_add_project_document_man: " . $conn->error);
        return false;
    }
    
    $stmt->bind_param("iissis", $user_id, $project_id, $file_name, $file_path, $uploaded_by, $document_type);
    
    $result = $stmt->execute();
    if (!$result) {
        error_log("Ошибка выполнения sp_add_project_document_man: " . $stmt->error);
    } else {
        error_log("sp_add_project_document_man успешно выполнена: project_id=$project_id, file_name=$file_name");
    }
    
    $stmt->close();
    return $result;
}

// 11. Редактирование документа проекта (manager/admin)
// Формат: i (user_id), i (document_id), s (file_name), s (file_path), s (document_type)
function updateProjectDocument($conn, $user_id, $document_id, $file_name, $file_path, $document_type) {
    $stmt = $conn->prepare("CALL sp_update_project_document(?, ?, ?, ?, ?)");
    $stmt->bind_param("iisss", $user_id, $document_id, $file_name, $file_path, $document_type);
    $result = $stmt->execute();
    $stmt->close();
    return $result;
}

// 12. Удаление проекта (admin)
// Формат: i (admin_id), i (project_id)
function adminDeleteProject($conn, $admin_id, $project_id) {
    $stmt = $conn->prepare("CALL sp_admin_delete_project(?, ?)");
    $stmt->bind_param("ii", $admin_id, $project_id);
    $result = $stmt->execute();
    $stmt->close();
    return $result;
}

// 13. Удаление пользователя (admin)
// Формат: i (admin_id), i (target_user_id)
function adminDeleteUser($conn, $admin_id, $target_user_id) {
    $stmt = $conn->prepare("CALL sp_admin_delete_user(?, ?)");
    $stmt->bind_param("ii", $admin_id, $target_user_id);
    $result = $stmt->execute();
    $stmt->close();
    return $result;
}

// 14. Редактирование данных пользователя (admin)
// Формат: i (admin_id), i (target_user_id), s (username), s (password), s (email), s (full_name), i (role_id), s (position), s (department), s (avatar)
function adminUpdateUser($conn, $admin_id, $target_user_id, $username, $password, $email, $full_name, $role_id, $position, $department, $avatar) {
    $stmt = $conn->prepare("CALL sp_admin_update_user(?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("iissssisss", $admin_id, $target_user_id, $username, $password, $email, $full_name, $role_id, $position, $department, $avatar);
    $result = $stmt->execute();
    $stmt->close();
    return $result;
}

// 15. Удаление участника проекта (admin)
// Формат: i (admin_id), i (participant_id)
function adminDeleteProjectParticipant($conn, $admin_id, $participant_id) {
    $stmt = $conn->prepare("CALL sp_admin_delete_project_participant(?, ?)");
    $stmt->bind_param("ii", $admin_id, $participant_id);
    $result = $stmt->execute();
    $stmt->close();
    return $result;
}

// 16. Редактирование этапа проекта (admin)
// Формат: i (admin_id), i (stage_id), s (stage_name), s (description), s (start_date), s (end_date), s (status), i (stage_order)
function adminUpdateProjectStage($conn, $admin_id, $stage_id, $stage_name, $description, $start_date, $end_date, $status, $stage_order) {
    $stmt = $conn->prepare("CALL sp_admin_update_project_stage(?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("iisssssi", $admin_id, $stage_id, $stage_name, $description, $start_date, $end_date, $status, $stage_order);
    $result = $stmt->execute();
    $stmt->close();
    return $result;
}

// 17. Редактирование документа проекта (admin)
// Формат: i (admin_id), i (document_id), s (file_name), s (file_path), s (document_type)
function adminUpdateProjectDocument($conn, $admin_id, $document_id, $file_name, $file_path, $document_type) {
    $stmt = $conn->prepare("CALL sp_admin_update_project_document(?, ?, ?, ?, ?)");
    $stmt->bind_param("iisss", $admin_id, $document_id, $file_name, $file_path, $document_type);
    $result = $stmt->execute();
    $stmt->close();
    return $result;
}

// 18. Получение списка проектов сотрудника
function getEmployeeProjects($conn, $employee_id) {
    $stmt = $conn->prepare("CALL sp_get_employee_projects(?)");
    $stmt->bind_param("i", $employee_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $projects = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $projects;
}

// 19. Получение подробной информации о проекте
function getProjectDetails($conn, $project_id) {
    try {
        $stmt = $conn->prepare("CALL sp_get_project_details(?)");
        $stmt->bind_param("i", $project_id);
        
        if (!$stmt->execute()) {
            throw new Exception("Ошибка выполнения процедуры: " . $stmt->error);
        }
        
        $result = $stmt->get_result();
        $project = $result->fetch_assoc();
        
        // Важно: сбросить все результаты процедуры
        while ($conn->more_results()) {
            $conn->next_result();
            $conn->store_result();
        }
        
        $stmt->close();
        
        if (!$project) {
            throw new Exception("Проект не найден");
        }
        
        return $project;
        
    } catch (Exception $e) {
        error_log($e->getMessage());
        return null;
    }
}

// 20. Получение списка участников проекта
function getProjectParticipants($conn, $project_id) {
    // Вызываем хранимую процедуру
    $sql = "CALL GetProjectParticipants(?)";
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        die("Ошибка подготовки запроса (GetProjectParticipants): " . $conn->error);
    }
    $stmt->bind_param("i", $project_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $participants = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $participants;
}

// 21. Получение списка этапов проекта
function getProjectStages($conn, $project_id) {
    $stmt = $conn->prepare("CALL sp_get_project_stages(?)");
    $stmt->bind_param("i", $project_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $stages = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $stages;
}

// 22. Получение документов проекта через процедуру
function getProjectDocuments($conn, $project_id) {
    $stmt = $conn->prepare("SELECT document_id, project_id, file_name, file_path, document_type, upload_date FROM project_documents WHERE project_id = ?");
    if ($stmt === false) {
        error_log("Ошибка подготовки запроса getProjectDocuments: " . $conn->error);
        return [];
    }
    
    $stmt->bind_param("i", $project_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $documents = [];
    while ($row = $result->fetch_assoc()) {
        $documents[] = $row;
    }
    
    $stmt->close();
    return $documents;
}

// 23. Загрузка документа сотрудником
function employeeUploadDocument($conn, $employee_id, $project_id, $file_name, $file_path, $document_type) {
    $stmt = $conn->prepare("CALL sp_employee_upload_document(?, ?, ?, ?, ?)");
    $stmt->bind_param("iisss", $employee_id, $project_id, $file_name, $file_path, $document_type);
    $result = $stmt->execute();
    $stmt->close();
    return $result;
}

// 24. Расчёт рейтинга проекта
function calculateProjectRating($conn, $project_id) {
    $stmt = $conn->prepare("CALL sp_calculate_project_rating(?)");
    $stmt->bind_param("i", $project_id);
    $result = $stmt->execute();
    $stmt->close();
    return $result;
}

function getProjectRating($conn, $project_id) {
    // Подготавливаем вызов хранимой процедуры
    $stmt = $conn->prepare("CALL sp_calculate_project_rating(?)");
    
    // Проверка подготовки запроса
    if (!$stmt) {
        error_log("Ошибка подготовки запроса sp_calculate_project_rating: " . $conn->error);
        return 'Ошибка сервера';
    }
    
    $stmt->bind_param("i", $project_id);
    
    try {
        // Выполняем запрос
        if (!$stmt->execute()) {
            throw new Exception("Ошибка выполнения процедуры: " . $stmt->error);
        }
        
        // Получаем результат
        $result = $stmt->get_result();
        
        // Проверка наличия результата
        if (!$result) {
            throw new Exception("Ошибка получения результата: " . $conn->error);
        }
        
        // Извлекаем данные
        $row = $result->fetch_assoc();
        $rating = $row['rating'] ?? 'Не рассчитан';
        
        // Закрываем результат
        $result->close();
    } catch (Exception $e) {
        // Логируем ошибку и возвращаем сообщение
        error_log("Ошибка в getProjectRating для project_id $project_id: " . $e->getMessage());
        $rating = 'Не рассчитан';
    } finally {
        // Закрываем statement
        $stmt->close();
        
        // Очищаем дополнительные результаты (если есть)
        while ($conn->more_results()) {
            $conn->next_result();
            if ($conn->more_results()) {
                $conn->store_result();
            }
        }
    }
    
    return $rating;
}

// 25. Экспорт отчёта по проекту
function exportProjectReport($conn, $project_id) {
    $stmt = $conn->prepare("CALL sp_export_project_report(?)");
    $stmt->bind_param("i", $project_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $report = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $report;
}

// 26. Поиск пользователей с фильтрами (admin)
// Формат: s (search_term), i (role_id), s (department), s (position)
// function searchUsers($conn, $search_term, $role_id, $department, $position) {
//     $stmt = $conn->prepare("CALL sp_search_users(?, ?, ?, ?)");
//     $stmt->bind_param("siss", $search_term, $role_id, $department, $position);
//     $stmt->execute();
//     $result = $stmt->get_result();
//     $users = $result->fetch_all(MYSQLI_ASSOC);
//     $stmt->close();
//     return $users;
// }

// 27. Экспорт отчёта по пользователям (admin)
// function exportUsersReport($conn, $search_term, $role_id, $department, $position) {
//     $stmt = $conn->prepare("CALL sp_export_users_report(?, ?, ?, ?)");
//     $stmt->bind_param("siss", $search_term, $role_id, $department, $position);
//     $stmt->execute();
//     $result = $stmt->get_result();
//     $report = $result->fetch_all(MYSQLI_ASSOC);
//     $stmt->close();
//     return $report;
// }


function getAllProjects($conn) {
    $stmt = $conn->prepare("SELECT * FROM projects");
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// function getRoleName($role_id) {
//     $roles = [
//         1 => 'Администратор',
//         2 => 'Менеджер',
//         3 => 'Сотрудник',
//         4 => 'Пользователь'
//     ];
//     return $roles[$role_id] ?? 'Неизвестно';
// }

function getStatusBadge($status) {
    $statuses = [
        'planning' => 'secondary',
        'active' => 'success',
        'completed' => 'primary',
        'on_hold' => 'warning'
    ];
    return $statuses[$status] ?? 'dark';
}

function getAdminStatistics($conn) {
    return [
        'total_projects' => $conn->query("SELECT COUNT(*) FROM projects")->fetch_row()[0],
        'active_users' => $conn->query("SELECT COUNT(*) FROM users")->fetch_row()[0],
        'status_distribution' => $conn->query("SELECT status, COUNT(*) as count FROM projects GROUP BY status")->fetch_all(MYSQLI_ASSOC)
    ];
}

function getAllUsersWithRoles($conn) {
    $stmt = $conn->prepare("
        SELECT 
            u.user_id,
            u.username,
            u.email,
            u.full_name,
            u.role_id,
            r.role_name
        FROM users u
        LEFT JOIN roles r ON u.role_id = r.role_id
        ORDER BY u.user_id ASC
    ");
    
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}
// Получение списка задач проекта
function getProjectTasks($conn, $project_id) {
    $stmt = $conn->prepare("SELECT task_id, project_id, responsible_id, task_name, assistants, status, deadline FROM tasks WHERE project_id = ?");
    if ($stmt === false) {
        error_log("Ошибка подготовки запроса getProjectTasks: " . $conn->error);
        return [];
    }
    
    $stmt->bind_param("i", $project_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $tasks = [];
    while ($row = $result->fetch_assoc()) {
        $tasks[] = $row;
    }
    
    $stmt->close();
    return $tasks;
}

// Добавление задачи
function addProjectTask($conn, $created_by, $project_id, $task_name, $responsible_id, $assistants_json, $status, $deadline) {
    $stmt = $conn->prepare("CALL sp_add_project_task(?, ?, ?, ?, ?, ?, ?)");
    if ($stmt === false) {
        error_log("Ошибка подготовки запроса sp_add_project_task: " . $conn->error);
        return false;
    }
    
    $stmt->bind_param("iisisss", $created_by, $project_id, $task_name, $responsible_id, $assistants_json, $status, $deadline);
    
    $result = $stmt->execute();
    if (!$result) {
        error_log("Ошибка выполнения sp_add_project_task: " . $stmt->error);
    } else {
        error_log("sp_add_project_task успешно выполнена: project_id=$project_id, task_name=$task_name");
    }
    
    $stmt->close();
    return $result;
}

// Удаление задачи (manager/admin)
function deleteProjectTask($conn, $user_id, $task_id) {
    $stmt = $conn->prepare("CALL sp_delete_project_task(?, ?)");
    $stmt->bind_param("ii", $user_id, $task_id);
    $result = $stmt->execute();
    $stmt->close();
    return $result;
}

//Получение деталей задачи
function getTaskDetails($conn, $task_id) {
    $stmt = $conn->prepare("SELECT * FROM tasks WHERE task_id = ?");
    $stmt->bind_param("i", $task_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $task = $result->fetch_assoc();
    $stmt->close();
    return $task;
}

// Получение документов задачи
function getTaskDocuments($conn, $task_id) {
    $stmt = $conn->prepare("SELECT document_id, task_id, file_name, file_path, document_type, upload_date, uploaded_by FROM task_documents WHERE task_id = ?");
    if ($stmt === false) {
        error_log("Ошибка подготовки запроса getTaskDocuments: " . $conn->error);
        return [];
    }
    
    $stmt->bind_param("i", $task_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $documents = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $documents;
}

// Проверка доступа к задаче
// Проверка, является ли пользователь участником проекта
// Проверка, является ли пользователь участником проекта
function isProjectParticipant($conn, $user_id, $project_id) {
    $stmt = $conn->prepare("SELECT project_role FROM project_participants WHERE project_id = ? AND user_id = ?");
    if ($stmt === false) {
        error_log("Ошибка подготовки запроса isProjectParticipant: " . $conn->error);
        return false;
    }
    
    $stmt->bind_param("ii", $project_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    
    return $row ? $row["project_role"] : false;
}

// Проверка доступа к задаче (просмотр)
function canViewTask($conn, $user_id, $task_id) {
    $task = getTaskDetails($conn, $task_id);
    if (!$task) {
        error_log("Задача не найдена: task_id=$task_id");
        return false;
    }
    
    $project_id = $task["project_id"];
    $responsible_id = $task["responsible_id"];
    $assistants = json_decode($task["assistants"], true) ?? [];
    
    // 1. Проверяем глобальную роль (admin или manager)
    $role_id = $_SESSION["role_id"];
    if ($role_id == 1 || $role_id == 2) {
        error_log("Доступ разрешён: пользователь имеет глобальную роль admin/manager (role_id=$role_id)");
        return true;
    }
    
    // 2. Проверяем, является ли пользователь ответственным
    if ($user_id == $responsible_id) {
        error_log("Доступ разрешён: пользователь является ответственным (user_id=$user_id)");
        return true;
    }
    
    // 3. Проверяем, является ли пользователь помощником
    if (in_array($user_id, $assistants)) {
        error_log("Доступ разрешён: пользователь является помощником (user_id=$user_id)");
        return true;
    }
    
    // 4. Проверяем, является ли пользователь участником проекта с ролью admin или manager
    $project_role = isProjectParticipant($conn, $user_id, $project_id);
    if ($project_role && in_array($project_role, ['admin', 'manager'])) {
        error_log("Доступ разрешён: пользователь имеет проектную роль $project_role");
        return true;
    }
    
    error_log("Доступ запрещён: user_id=$user_id, project_role=" . ($project_role ?: 'не определена'));
    return false;
}

// Проверка, может ли пользователь загружать документы для задачи
// Проверка, может ли пользователь загружать документы для задачи
function canUploadTaskDocument($conn, $user_id, $task_id) {
    $task = getTaskDetails($conn, $task_id);
    if (!$task) {
        error_log("Задача не найдена: task_id=$task_id");
        return false;
    }
    
    $project_id = $task["project_id"];
    $responsible_id = $task["responsible_id"];
    $assistants = json_decode($task["assistants"], true) ?? [];
    
    // 1. Проверяем глобальную роль (admin или manager)
    $role_id = $_SESSION["role_id"];
    if ($role_id == 1 || $role_id == 2) {
        error_log("Разрешена загрузка: пользователь имеет глобальную роль admin/manager (role_id=$role_id)");
        return true;
    }
    
    // 2. Проверяем, является ли пользователь ответственным
    if ($user_id == $responsible_id) {
        error_log("Разрешена загрузка: пользователь является ответственным (user_id=$user_id)");
        return true;
    }
    
    // 3. Проверяем, является ли пользователь помощником
    if (in_array($user_id, $assistants)) {
        error_log("Разрешена загрузка: пользователь является помощником (user_id=$user_id)");
        return true;
    }
    
    error_log("Загрузка запрещена: user_id=$user_id");
    return false;
}

// Загрузка документа для задачи
function uploadTaskDocument($conn, $task_id, $file_name, $file_path, $document_type, $uploaded_by) {
    $stmt = $conn->prepare("INSERT INTO task_documents (task_id, file_name, file_path, document_type, uploaded_by, upload_date) VALUES (?, ?, ?, ?, ?, NOW())");
    if ($stmt === false) {
        error_log("Ошибка подготовки запроса uploadTaskDocument: " . $conn->error);
        return false;
    }
    
    $stmt->bind_param("isssi", $task_id, $file_name, $file_path, $document_type, $uploaded_by);
    $result = $stmt->execute();
    if (!$result) {
        error_log("Ошибка выполнения uploadTaskDocument: " . $stmt->error);
    }
    
    $stmt->close();
    return $result;
}
// Получение пользователя по ID
function getUserById($conn, $user_id) {
    $stmt = $conn->prepare("SELECT username FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    return $user;
}

// function getAllProjectsWithDetails($conn) {
//     $stmt = $conn->prepare("
//         SELECT p.*, COUNT(pp.user_id) as participants 
//         FROM projects p
//         LEFT JOIN project_participants pp ON p.project_id = pp.project_id
//         GROUP BY p.project_id
//     ");
//     $stmt->execute();
//     return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
// }

function createPasswordResetToken($conn, $email) {
    // Проверяем, существует ли пользователь
    $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        return false; // Email не найден
    }

    $userId = $result->fetch_assoc()['user_id'];
    $token = bin2hex(random_bytes(32));
    $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));

    // Сохраняем токен в таблице password_resets
    $stmt = $conn->prepare("INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?)");
    $stmt->bind_param("iss", $userId, $token, $expiresAt);
    $stmt->execute();

    return $token;
}

// Функция для проверки токена
function verifyPasswordResetToken($conn, $token) {
    $stmt = $conn->prepare("SELECT user_id FROM password_resets WHERE token = ? AND expires_at > NOW()");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        return false; // Токен недействителен или истек
    }

    return $result->fetch_assoc()['user_id'];
}

// Функция для обновления пароля
function updateUserPassword($conn, $userId, $newPassword) {
    $passwordHash = password_hash($newPassword, PASSWORD_BCRYPT);
    $stmt = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
    $stmt->bind_param("si", $passwordHash, $userId);
    $stmt->execute();

    // Удаляем использованный токен
    $stmt = $conn->prepare("DELETE FROM password_resets WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
}

?>
