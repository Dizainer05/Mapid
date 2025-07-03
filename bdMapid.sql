-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Хост: MySQL-8.4
-- Время создания: Июл 01 2025 г., 01:18
-- Версия сервера: 8.4.4
-- Версия PHP: 8.4.1

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- База данных: `bdMapid`
--

DELIMITER $$
--
-- Процедуры
--
CREATE DEFINER=`root`@`%` PROCEDURE `GetProjectParticipants` (IN `p_project_id` INT)   BEGIN
    SELECT u.user_id, u.username, u.full_name, u.email, pp.project_role
    FROM users u
    INNER JOIN project_participants pp ON u.user_id = pp.user_id
    WHERE pp.project_id = p_project_id;
END$$

CREATE DEFINER=`root`@`%` PROCEDURE `sp_add_project_document_man` (IN `p_user_id` INT, IN `p_project_id` INT, IN `p_file_name` VARCHAR(255), IN `p_file_path` VARCHAR(255), IN `p_uploaded_by` INT, IN `p_document_type` VARCHAR(50))   BEGIN
    DECLARE v_project_role VARCHAR(50);
    DECLARE v_global_role_id INT;
    
    -- Получаем роль пользователя в проекте
    SELECT project_role INTO v_project_role
    FROM project_participants
    WHERE project_id = p_project_id AND user_id = p_user_id;
    
    -- Если пользователь не является участником проекта, проверяем его глобальную роль
    IF v_project_role IS NULL THEN
        SELECT role_id INTO v_global_role_id
        FROM users
        WHERE user_id = p_user_id;
        
        IF v_global_role_id IS NULL THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Пользователь не найден';
        END IF;
        
        IF v_global_role_id = 1 THEN
            SET v_project_role = 'admin';
        ELSEIF v_global_role_id = 2 THEN
            SET v_project_role = 'manager';
        END IF;
    END IF;
    
    -- Проверяем, имеет ли пользователь право загружать документы (должен быть admin, manager или employee)
    IF v_project_role NOT IN ('admin', 'manager', 'employee') THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Access Denied: Not authorized to add document';
    END IF;
    
    -- Добавляем документ
    INSERT INTO project_documents (project_id, file_name, file_path, uploaded_by, document_type, upload_date)
    VALUES (p_project_id, p_file_name, p_file_path, p_uploaded_by, p_document_type, NOW());
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_add_project_participant_man` (IN `p_user_id` INT, IN `p_project_id` INT, IN `p_target_user_id` INT, IN `p_project_role` ENUM('employee','manager','admin','user'))   BEGIN
    DECLARE v_user_role INT;
    DECLARE v_manager_role INT;
    DECLARE v_admin_role INT;
    
    -- Проверяем, что целевой пользователь существует
    IF NOT EXISTS (SELECT 1 FROM users WHERE user_id = p_target_user_id) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'User does not exist';
    ELSE
        SELECT role_id INTO v_user_role FROM users WHERE user_id = p_user_id;
        SELECT role_id INTO v_manager_role FROM roles WHERE role_name = 'manager';
        SELECT role_id INTO v_admin_role FROM roles WHERE role_name = 'admin';
    
        IF v_user_role != v_manager_role AND v_user_role != v_admin_role THEN
           SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Access Denied: Not authorized to add participant';
        ELSE
           INSERT INTO project_participants (project_id, user_id, project_role)
           VALUES (p_project_id, p_target_user_id, p_project_role);
        END IF;
    END IF;
END$$

CREATE DEFINER=`root`@`%` PROCEDURE `sp_add_project_task` (IN `p_user_id` INT, IN `p_project_id` INT, IN `p_task_name` VARCHAR(255), IN `p_responsible_id` INT, IN `p_assistants` JSON, IN `p_status` VARCHAR(50), IN `p_deadline` DATE)   BEGIN
    DECLARE v_project_role VARCHAR(50);
    DECLARE v_global_role_id INT;
    
    -- Получаем роль пользователя в проекте
    SELECT project_role INTO v_project_role
    FROM project_participants
    WHERE project_id = p_project_id AND user_id = p_user_id;
    
    -- Если пользователь не является участником проекта, проверяем его глобальную роль
    IF v_project_role IS NULL THEN
        SELECT role_id INTO v_global_role_id
        FROM users
        WHERE user_id = p_user_id;
        
        IF v_global_role_id IS NULL THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Пользователь не найден';
        END IF;
        
        IF v_global_role_id = 1 THEN
            SET v_project_role = 'admin';
        ELSEIF v_global_role_id = 2 THEN
            SET v_project_role = 'manager';
        END IF;
    END IF;
    
    -- Проверяем, имеет ли пользователь право добавлять задачи
    IF v_project_role NOT IN ('admin', 'manager') THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Access Denied: Not authorized to add task';
    END IF;
    
    -- Добавляем задачу
    INSERT INTO tasks (project_id, responsible_id, task_name, assistants, status, deadline, created_by)
    VALUES (p_project_id, p_responsible_id, p_task_name, p_assistants, p_status, p_deadline, p_user_id);
END$$

CREATE DEFINER=`root`@`%` PROCEDURE `sp_admin_delete_project` (IN `p_admin_id` INT, IN `p_project_id` INT)   BEGIN
    DECLARE v_admin_role INT;
    DECLARE v_required_admin_role INT;
    
    -- Проверяем, является ли пользователь администратором
    SELECT role_id INTO v_admin_role FROM users WHERE user_id = p_admin_id;
    SELECT role_id INTO v_required_admin_role FROM roles WHERE role_name = 'admin';
    
    IF v_admin_role IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Пользователь не найден';
    END IF;
    
    IF v_admin_role != v_required_admin_role THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Access Denied: Not an admin';
    END IF;
    
    -- Проверяем, существует ли проект
    IF NOT EXISTS (SELECT 1 FROM projects WHERE project_id = p_project_id) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Проект не найден';
    END IF;
    
    -- Удаляем связанные данные
    -- 1. Удаляем документы задач
    DELETE FROM task_documents
    WHERE task_id IN (SELECT task_id FROM tasks WHERE project_id = p_project_id);
    
    -- 2. Удаляем задачи проекта
    DELETE FROM tasks WHERE project_id = p_project_id;
    
    -- 3. Удаляем участников проекта
    DELETE FROM project_participants WHERE project_id = p_project_id;
    
    -- 4. Удаляем сам проект
    DELETE FROM projects WHERE project_id = p_project_id;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_admin_delete_project_participant` (IN `p_admin_id` INT, IN `p_participant_id` INT)   BEGIN
    DECLARE v_admin_role INT;
    DECLARE v_required_admin_role INT;
    
    SELECT role_id INTO v_admin_role FROM users WHERE user_id = p_admin_id;
    SELECT role_id INTO v_required_admin_role FROM roles WHERE role_name = 'admin';
    
    IF v_admin_role != v_required_admin_role THEN
       SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Access Denied: Not an admin';
    ELSE
       DELETE FROM project_participants WHERE participant_id = p_participant_id;
    END IF;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_admin_delete_user` (IN `p_admin_id` INT, IN `p_target_user_id` INT)   BEGIN
    DECLARE v_admin_role INT;
    DECLARE v_required_admin_role INT;
    
    SELECT role_id INTO v_admin_role FROM users WHERE user_id = p_admin_id;
    SELECT role_id INTO v_required_admin_role FROM roles WHERE role_name = 'admin';
    
    IF v_admin_role != v_required_admin_role THEN
       SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Access Denied: Not an admin';
    ELSE
       DELETE FROM users WHERE user_id = p_target_user_id;
    END IF;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_admin_update_project_document` (IN `p_admin_id` INT, IN `p_document_id` INT, IN `p_file_name` VARCHAR(255), IN `p_file_path` VARCHAR(255), IN `p_document_type` ENUM('document','image','drawing','photo'))   BEGIN
    DECLARE v_admin_role INT;
    DECLARE v_required_admin_role INT;
    
    SELECT role_id INTO v_admin_role FROM users WHERE user_id = p_admin_id;
    SELECT role_id INTO v_required_admin_role FROM roles WHERE role_name = 'admin';
    
    IF v_admin_role != v_required_admin_role THEN
       SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Access Denied: Not an admin';
    ELSE
       UPDATE project_documents
       SET file_name = p_file_name,
           file_path = p_file_path,
           document_type = p_document_type,
           upload_date = CURRENT_TIMESTAMP
       WHERE document_id = p_document_id;
    END IF;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_admin_update_project_stage` (IN `p_admin_id` INT, IN `p_stage_id` INT, IN `p_stage_name` VARCHAR(255), IN `p_description` TEXT, IN `p_start_date` DATE, IN `p_end_date` DATE, IN `p_status` ENUM('not_started','in_progress','completed'), IN `p_stage_order` INT)   BEGIN
    DECLARE v_admin_role INT;
    DECLARE v_required_admin_role INT;
    
    SELECT role_id INTO v_admin_role FROM users WHERE user_id = p_admin_id;
    SELECT role_id INTO v_required_admin_role FROM roles WHERE role_name = 'admin';
    
    IF v_admin_role != v_required_admin_role THEN
       SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Access Denied: Not an admin';
    ELSE
       UPDATE project_stages
       SET stage_name = p_stage_name,
           description = p_description,
           start_date = p_start_date,
           end_date = p_end_date,
           status = p_status,
           stage_order = p_stage_order
       WHERE stage_id = p_stage_id;
    END IF;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_admin_update_user` (IN `p_admin_id` INT, IN `p_target_user_id` INT, IN `p_username` VARCHAR(50), IN `p_password` VARCHAR(255), IN `p_email` VARCHAR(100), IN `p_full_name` VARCHAR(100), IN `p_role_id` INT, IN `p_position` VARCHAR(100), IN `p_department` VARCHAR(100), IN `p_avatar` VARCHAR(255))   BEGIN
    DECLARE v_admin_role INT;
    DECLARE v_required_admin_role INT;
    
    SELECT role_id INTO v_admin_role FROM users WHERE user_id = p_admin_id;
    SELECT role_id INTO v_required_admin_role FROM roles WHERE role_name = 'admin';
    
    IF v_admin_role != v_required_admin_role THEN
       SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Access Denied: Not an admin';
    ELSE
       UPDATE users
       SET username = p_username,
           password = p_password,
           email = p_email,
           full_name = p_full_name,
           role_id = p_role_id,
           position = p_position,
           department = p_department,
           avatar = p_avatar,
           updated_at = CURRENT_TIMESTAMP
       WHERE user_id = p_target_user_id;
    END IF;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_authenticate_user` (IN `p_username` VARCHAR(50))   BEGIN
    SELECT user_id, username, password, email, role_id 
    FROM users 
    WHERE username = p_username;
END$$

CREATE DEFINER=`root`@`%` PROCEDURE `sp_calculate_project_rating` (IN `p_project_id` INT)   BEGIN
    DECLARE v_planned_budget DECIMAL(12,2);
    DECLARE v_actual_budget DECIMAL(12,2);
    DECLARE v_planned_digitalization DECIMAL(5,2);
    DECLARE v_actual_digitalization DECIMAL(5,2);
    DECLARE v_planned_labor DECIMAL(12,2);
    DECLARE v_actual_labor DECIMAL(12,2);
    DECLARE ratio_budget DECIMAL(5,2);
    DECLARE ratio_digitalization DECIMAL(5,2);
    DECLARE ratio_labor DECIMAL(5,2);
    DECLARE v_rating DECIMAL(5,2);

    -- Получаем данные проекта
    SELECT 
        planned_budget, actual_budget, 
        planned_digitalization_level, actual_digitalization_level, 
        planned_labor_costs, actual_labor_costs
    INTO 
        v_planned_budget, v_actual_budget, 
        v_planned_digitalization, v_actual_digitalization, 
        v_planned_labor, v_actual_labor
    FROM projects
    WHERE project_id = p_project_id;

    -- Проверяем, существует ли проект
    IF v_planned_budget IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Проект не найден';
    END IF;

    -- Рассчитываем коэффициенты
    IF v_planned_budget > 0 THEN
        SET ratio_budget = LEAST(v_actual_budget / v_planned_budget, 1) * 100;
    ELSEIF v_actual_budget > 0 THEN
        SET ratio_budget = 0; -- Плановый бюджет 0, но есть фактический — перерасход
    ELSE
        SET ratio_budget = 0; -- Оба значения 0 — данные не заполнены
    END IF;

    IF v_planned_digitalization > 0 THEN
        SET ratio_digitalization = LEAST(v_actual_digitalization / v_planned_digitalization, 1) * 100;
    ELSEIF v_actual_digitalization > 0 THEN
        SET ratio_digitalization = 0; -- Плановый уровень 0, но есть фактический — перерасход
    ELSE
        SET ratio_digitalization = 0; -- Оба значения 0 — данные не заполнены
    END IF;

    IF v_planned_labor > 0 THEN
        SET ratio_labor = LEAST(v_actual_labor / v_planned_labor, 1) * 100;
    ELSEIF v_actual_labor > 0 THEN
        SET ratio_labor = 0; -- Плановые трудозатраты 0, но есть фактические — перерасход
    ELSE
        SET ratio_labor = 0; -- Оба значения 0 — данные не заполнены
    END IF;

    -- Итоговый рейтинг — среднее значение трёх коэффициентов
    SET v_rating = (ratio_budget + ratio_digitalization + ratio_labor) / 3;

    -- Обновляем рейтинг в таблице
    UPDATE projects
    SET rating = v_rating
    WHERE project_id = p_project_id;

    -- Возвращаем вычисленный рейтинг
    SELECT v_rating AS rating;
END$$

CREATE DEFINER=`root`@`%` PROCEDURE `sp_change_project_stage` (IN `p_user_id` INT, IN `p_project_id` INT, IN `p_new_stage` VARCHAR(50))   BEGIN
    DECLARE v_project_role VARCHAR(50);
    DECLARE v_global_role_id INT;
    
    -- Проверяем, существует ли проект
    IF NOT EXISTS (SELECT 1 FROM projects WHERE project_id = p_project_id) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Проект не найден';
    END IF;
    
    -- Получаем роль пользователя в проекте
    SELECT project_role INTO v_project_role
    FROM project_participants
    WHERE project_id = p_project_id AND user_id = p_user_id;
    
    -- Если пользователь не является участником проекта, проверяем его глобальную роль
    IF v_project_role IS NULL THEN
        SELECT role_id INTO v_global_role_id
        FROM users
        WHERE user_id = p_user_id;
        
        IF v_global_role_id IS NULL THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Пользователь не найден';
        END IF;
        
        IF v_global_role_id = 1 THEN
            SET v_project_role = 'admin';
        ELSEIF v_global_role_id = 2 THEN
            SET v_project_role = 'manager';
        END IF;
    END IF;
    
    -- Проверяем, имеет ли пользователь право изменять этапы
    IF v_project_role NOT IN ('admin', 'manager') THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Access Denied: Not authorized to change project stage';
    END IF;
    
    -- Проверяем допустимые значения этапа
    IF p_new_stage NOT IN ('initiation', 'planning', 'execution', 'monitoring', 'closure') THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Недопустимое значение этапа';
    END IF;
    
    -- Обновляем этап проекта
    UPDATE projects
    SET lifecycle_stage = p_new_stage
    WHERE project_id = p_project_id;
END$$

CREATE DEFINER=`root`@`%` PROCEDURE `sp_create_project` (IN `p_user_id` INT, IN `p_name` VARCHAR(255), IN `p_short_name` VARCHAR(50), IN `p_description` TEXT, IN `p_planned_start_date` DATE, IN `p_actual_start_date` DATE, IN `p_planned_end_date` DATE, IN `p_actual_end_date` DATE, IN `p_planned_budget` DECIMAL(10,2), IN `p_actual_budget` DECIMAL(10,2), IN `p_planned_digitalization` INT, IN `p_actual_digitalization` INT, IN `p_planned_labor` INT, IN `p_actual_labor` INT, IN `p_status` VARCHAR(20), IN `p_lifecycle_stage` VARCHAR(20), IN `p_scale` VARCHAR(20), IN `p_charter_file_path` VARCHAR(255) CHARSET utf8mb3, IN `p_expected_resources` TEXT, IN `p_access_code` VARCHAR(20))   BEGIN
    DECLARE v_user_role INT;
    DECLARE v_manager_role INT;
    DECLARE v_admin_role INT;

    -- Логирование входного статуса
    CREATE TEMPORARY TABLE IF NOT EXISTS temp_log (value VARCHAR(255), log_time DATETIME);
    INSERT INTO temp_log (value, log_time) VALUES (p_status, NOW());

    -- Проверка входного параметра status
    IF p_status IS NULL OR p_status NOT IN ('planning', 'in_progress', 'completed', 'on_hold') THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Недопустимое значение для статуса проекта';
    END IF;

    -- Проверка роли пользователя
    SELECT role_id INTO v_user_role FROM users WHERE user_id = p_user_id;
    SELECT role_id INTO v_manager_role FROM roles WHERE role_name = 'manager';
    SELECT role_id INTO v_admin_role FROM roles WHERE role_name = 'admin';

    IF v_user_role IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Пользователь не найден';
    END IF;

    IF v_user_role != v_manager_role AND v_user_role != v_admin_role THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Доступ запрещён: пользователь не имеет прав для создания проекта';
    END IF;

    -- Вставка данных
    INSERT INTO projects (
        name, short_name, description, planned_start_date, actual_start_date,
        planned_end_date, actual_end_date, planned_budget, actual_budget,
        planned_digitalization_level, actual_digitalization_level,
        planned_labor_costs, actual_labor_costs, status, lifecycle_stage, scale,
        charter_file_path, expected_resources, created_by, access_code
    ) VALUES (
        p_name, p_short_name, p_description, p_planned_start_date, p_actual_start_date,
        p_planned_end_date, p_actual_end_date, p_planned_budget, p_actual_budget,
        p_planned_digitalization, p_actual_digitalization, p_planned_labor, p_actual_labor,
        p_status, p_lifecycle_stage, p_scale, p_charter_file_path, p_expected_resources,
        p_user_id, p_access_code
    );
END$$

CREATE DEFINER=`root`@`%` PROCEDURE `sp_delete_project_document` (IN `p_user_id` INT, IN `p_project_id` INT, IN `p_document_id` INT)   BEGIN
    DECLARE v_project_role VARCHAR(50);
    DECLARE v_global_role_id INT;
    
    -- Получаем роль пользователя в проекте
    SELECT project_role INTO v_project_role
    FROM project_participants
    WHERE project_id = p_project_id AND user_id = p_user_id;
    
    -- Если пользователь не является участником проекта, проверяем его глобальную роль
    IF v_project_role IS NULL THEN
        SELECT role_id INTO v_global_role_id
        FROM users
        WHERE user_id = p_user_id;
        
        IF v_global_role_id IS NULL THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Пользователь не найден';
        END IF;
        
        IF v_global_role_id = 1 THEN
            SET v_project_role = 'admin';
        ELSEIF v_global_role_id = 2 THEN
            SET v_project_role = 'manager';
        END IF;
    END IF;
    
    -- Проверяем, имеет ли пользователь право удалять документы
    IF v_project_role NOT IN ('admin', 'manager', 'employee') THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Access Denied: Not authorized to delete document';
    END IF;
    
    -- Удаляем документ
    DELETE FROM project_documents
    WHERE document_id = p_document_id AND project_id = p_project_id;
END$$

CREATE DEFINER=`root`@`%` PROCEDURE `sp_delete_project_task` (IN `p_user_id` INT, IN `p_task_id` INT)   BEGIN
    DELETE FROM tasks WHERE task_id = p_task_id;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_delete_user` (IN `p_user_id` INT)   BEGIN
    DELETE FROM users
    WHERE user_id = p_user_id;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_employee_upload_document` (IN `p_employee_id` INT, IN `p_project_id` INT, IN `p_file_name` VARCHAR(255), IN `p_file_path` VARCHAR(255), IN `p_document_type` ENUM('document','image','drawing','photo'))   BEGIN
    DECLARE v_count INT DEFAULT 0;
    
    SELECT COUNT(*) INTO v_count
    FROM project_participants
    WHERE project_id = p_project_id AND user_id = p_employee_id;
    
    IF v_count = 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'User is not a participant in the project';
    ELSE
        INSERT INTO project_documents (project_id, file_name, file_path, uploaded_by, document_type)
        VALUES (p_project_id, p_file_name, p_file_path, p_employee_id, p_document_type);
    END IF;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_export_project_report` (IN `p_project_id` INT)   BEGIN
    SELECT 
        p.name AS project_name,
        p.status,
        p.planned_budget,
        p.actual_budget,
        COUNT(DISTINCT pp.user_id) AS participants_count,
        COUNT(DISTINCT pd.document_id) AS documents_count
    FROM projects p
    LEFT JOIN project_participants pp ON p.project_id = pp.project_id
    LEFT JOIN project_documents pd ON p.project_id = pd.project_id
    WHERE p.project_id = p_project_id
    GROUP BY p.project_id;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_export_users_report` (IN `p_search_term` VARCHAR(50), IN `p_role_id` INT, IN `p_department` VARCHAR(100), IN `p_position` VARCHAR(100))   BEGIN
    SELECT 
        u.user_id,
        u.username,
        u.email,
        u.full_name,
        r.role_name,
        u.position,
        u.department,
        u.created_at
    FROM users u
    LEFT JOIN roles r ON u.role_id = r.role_id
    WHERE 
      (p_search_term IS NULL OR p_search_term = '' OR u.username LIKE CONCAT('%', p_search_term, '%') OR u.full_name LIKE CONCAT('%', p_search_term, '%'))
      AND (p_role_id IS NULL OR p_role_id = 0 OR u.role_id = p_role_id)
      AND (p_department IS NULL OR p_department = '' OR u.department LIKE CONCAT('%', p_department, '%'))
      AND (p_position IS NULL OR p_position = '' OR u.position LIKE CONCAT('%', p_position, '%'))
    ORDER BY u.created_at DESC;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_get_employee_projects` (IN `p_employee_id` INT)   BEGIN
    SELECT p.*
    FROM projects p
    INNER JOIN project_participants pp ON p.project_id = pp.project_id
    WHERE pp.user_id = p_employee_id;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_get_project_details` (IN `p_project_id` INT)   BEGIN
    SELECT *
    FROM projects
    WHERE project_id = p_project_id;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_get_project_documents` (IN `p_project_id` INT)   BEGIN
    SELECT *
    FROM project_documents
    WHERE project_id = p_project_id;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_get_project_participants` (IN `p_project_id` INT)   BEGIN
    SELECT u.user_id, u.username, u.full_name, u.email, pp.project_role
    FROM users u
    INNER JOIN project_participants pp ON u.user_id = pp.user_id
    WHERE pp.project_id = p_project_id;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_get_project_stages` (IN `p_project_id` INT)   BEGIN
    SELECT *
    FROM project_stages
    WHERE project_id = p_project_id
    ORDER BY stage_order ASC;
END$$

CREATE DEFINER=`root`@`%` PROCEDURE `sp_get_project_tasks` (IN `p_project_id` INT)   BEGIN
    SELECT task_id, task_name, assigned_to, status, deadline
    FROM tasks
    WHERE project_id = p_project_id;
END$$

CREATE DEFINER=`root`@`%` PROCEDURE `sp_join_project` (IN `p_user_id` INT, IN `p_access_code` VARCHAR(255))   BEGIN
    DECLARE v_project_id INT;
    SELECT project_id INTO v_project_id 
    FROM projects 
    WHERE access_code = p_access_code 
    LIMIT 1;
    IF v_project_id IS NOT NULL THEN
        IF NOT EXISTS (
            SELECT 1 
            FROM project_participants 
            WHERE project_id = v_project_id AND user_id = p_user_id
        ) THEN
            INSERT INTO project_participants (project_id, user_id, project_role)
            VALUES (v_project_id, p_user_id, 'user');
        ELSE
            SIGNAL SQLSTATE '45000' 
            SET MESSAGE_TEXT = 'Пользователь уже является участником';
        END IF;
    ELSE
        SIGNAL SQLSTATE '45000' 
        SET MESSAGE_TEXT = 'Неверный код доступа';
    END IF;
END$$

CREATE DEFINER=`root`@`%` PROCEDURE `sp_register_user` (IN `p_username` VARCHAR(50), IN `p_password` VARCHAR(255), IN `p_email` VARCHAR(100), IN `p_full_name` VARCHAR(255), IN `p_role_id` INT, IN `p_position` VARCHAR(100), IN `p_department` VARCHAR(100), IN `p_avatar` VARCHAR(255))   BEGIN
    IF EXISTS (SELECT 1 FROM users WHERE username = p_username) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Логин уже занят';
    ELSEIF EXISTS (SELECT 1 FROM users WHERE email = p_email) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Электронная почта уже используется';
    ELSEIF EXISTS (SELECT 1 FROM users WHERE full_name = p_full_name) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'ФИО уже зарегистрировано';
    ELSE
        INSERT INTO users (username, password, email, full_name, role_id, position, department, avatar)
        VALUES (p_username, p_password, p_email, p_full_name, p_role_id, p_position, p_department, p_avatar);
    END IF;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_search_users` (IN `p_search_term` VARCHAR(50), IN `p_role_id` INT, IN `p_department` VARCHAR(100), IN `p_position` VARCHAR(100))   BEGIN
    SELECT 
        u.user_id,
        u.username,
        u.email,
        u.full_name,
        u.role_id,
        r.role_name,
        u.position,
        u.department,
        u.avatar,
        u.created_at,
        u.updated_at
    FROM users u
    LEFT JOIN roles r ON u.role_id = r.role_id
    WHERE 
      (p_search_term IS NULL OR p_search_term = '' OR u.username LIKE CONCAT('%', p_search_term, '%') OR u.full_name LIKE CONCAT('%', p_search_term, '%'))
      AND (p_role_id IS NULL OR p_role_id = 0 OR u.role_id = p_role_id)
      AND (p_department IS NULL OR p_department = '' OR u.department LIKE CONCAT('%', p_department, '%'))
      AND (p_position IS NULL OR p_position = '' OR u.position LIKE CONCAT('%', p_position, '%'));
END$$

CREATE DEFINER=`root`@`%` PROCEDURE `sp_update_project` (IN `p_user_id` INT, IN `p_project_id` INT, IN `p_name` VARCHAR(255), IN `p_short_name` VARCHAR(50), IN `p_description` TEXT, IN `p_planned_start_date` DATE, IN `p_actual_start_date` DATE, IN `p_planned_end_date` DATE, IN `p_actual_end_date` DATE, IN `p_planned_budget` DECIMAL(10,2), IN `p_actual_budget` DECIMAL(10,2), IN `p_planned_digitalization` DECIMAL(5,2), IN `p_actual_digitalization` DECIMAL(5,2), IN `p_planned_labor` DECIMAL(10,2), IN `p_actual_labor` DECIMAL(10,2), IN `p_status` VARCHAR(50), IN `p_lifecycle_stage` VARCHAR(50), IN `p_scale` VARCHAR(20), IN `p_charter_file_path` VARCHAR(255), IN `p_expected_resources` TEXT, IN `p_access_code` VARCHAR(50), IN `p_start_date` DATE)   BEGIN
    DECLARE v_project_role VARCHAR(50);
    DECLARE v_global_role_id INT;
    
    -- Получаем роль пользователя в проекте
    SELECT project_role INTO v_project_role
    FROM project_participants
    WHERE project_id = p_project_id AND user_id = p_user_id;
    
    -- Если пользователь не является участником проекта, проверяем его глобальную роль
    IF v_project_role IS NULL THEN
        SELECT role_id INTO v_global_role_id
        FROM users
        WHERE user_id = p_user_id;
        
        IF v_global_role_id = 1 THEN
            SET v_project_role = 'admin';
        ELSEIF v_global_role_id = 2 THEN
            SET v_project_role = 'manager';
        END IF;
    END IF;
    
    -- Проверяем, имеет ли пользователь право на обновление
    IF v_project_role != 'admin' AND v_project_role != 'manager' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Access Denied: Not authorized to update project';
    ELSE
        -- Проверяем допустимость значения scale
        IF p_scale NOT IN ('small', 'medium', 'large', 'megaproject') THEN
            SET p_scale = 'medium'; -- Устанавливаем значение по умолчанию, если недопустимо
        END IF;
        
        -- Обновляем проект
        UPDATE projects
        SET 
            name = p_name,
            short_name = p_short_name,
            description = p_description,
            planned_start_date = IFNULL(p_planned_start_date, planned_start_date),
            actual_start_date = IFNULL(p_actual_start_date, actual_start_date),
            planned_end_date = IFNULL(p_planned_end_date, planned_end_date),
            actual_end_date = IFNULL(p_actual_end_date, actual_end_date),
            planned_budget = p_planned_budget,
            actual_budget = p_actual_budget,
            planned_digitalization_level = p_planned_digitalization,
            actual_digitalization_level = p_actual_digitalization,
            planned_labor_costs = p_planned_labor,
            actual_labor_costs = p_actual_labor,
            status = p_status,
            lifecycle_stage = p_lifecycle_stage,
            scale = p_scale, -- Используем переданное значение напрямую
            charter_file_path = p_charter_file_path,
            expected_resources = p_expected_resources,
            access_code = p_access_code,
            start_date = IFNULL(p_start_date, start_date),
            updated_at = CURRENT_TIMESTAMP
        WHERE project_id = p_project_id;
    END IF;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_update_project_document` (IN `p_user_id` INT, IN `p_document_id` INT, IN `p_file_name` VARCHAR(255), IN `p_file_path` VARCHAR(255), IN `p_document_type` ENUM('document','image','drawing','photo'))   BEGIN
    DECLARE v_user_role INT;
    DECLARE v_manager_role INT;
    DECLARE v_admin_role INT;
    
    SELECT role_id INTO v_user_role FROM users WHERE user_id = p_user_id;
    SELECT role_id INTO v_manager_role FROM roles WHERE role_name = 'manager';
    SELECT role_id INTO v_admin_role FROM roles WHERE role_name = 'admin';
    
    IF v_user_role != v_manager_role AND v_user_role != v_admin_role THEN
       SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Access Denied: Not authorized to update document';
    ELSE
       UPDATE project_documents
       SET 
         file_name = p_file_name,
         file_path = p_file_path,
         document_type = p_document_type,
         upload_date = CURRENT_TIMESTAMP
       WHERE document_id = p_document_id;
    END IF;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_update_project_stage` (IN `p_user_id` INT, IN `p_stage_id` INT, IN `p_stage_name` VARCHAR(255), IN `p_description` TEXT, IN `p_start_date` DATE, IN `p_end_date` DATE, IN `p_status` ENUM('not_started','in_progress','completed'), IN `p_stage_order` INT)   BEGIN
    DECLARE v_user_role INT;
    DECLARE v_manager_role INT;
    DECLARE v_admin_role INT;
    
    SELECT role_id INTO v_user_role FROM users WHERE user_id = p_user_id;
    SELECT role_id INTO v_manager_role FROM roles WHERE role_name = 'manager';
    SELECT role_id INTO v_admin_role FROM roles WHERE role_name = 'admin';
    
    IF v_user_role != v_manager_role AND v_user_role != v_admin_role THEN
       SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Access Denied: Not authorized to update project stage';
    ELSE
       UPDATE project_stages
       SET 
         stage_name = p_stage_name,
         description = p_description,
         start_date = p_start_date,
         end_date = p_end_date,
         status = p_status,
         stage_order = p_stage_order
       WHERE stage_id = p_stage_id;
    END IF;
END$$

CREATE DEFINER=`root`@`%` PROCEDURE `sp_update_task` (IN `p_user_id` INT, IN `p_task_id` INT, IN `p_task_name` VARCHAR(255), IN `p_responsible_id` INT, IN `p_assistants` JSON, IN `p_status` VARCHAR(50), IN `p_deadline` DATE)   BEGIN
    DECLARE v_project_id INT;
    DECLARE v_project_role VARCHAR(50);
    DECLARE v_global_role_id INT;
    
    -- Получаем project_id задачи
    SELECT project_id INTO v_project_id
    FROM tasks
    WHERE task_id = p_task_id;
    
    IF v_project_id IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Задача не найдена';
    END IF;
    
    -- Получаем роль пользователя в проекте
    SELECT project_role INTO v_project_role
    FROM project_participants
    WHERE project_id = v_project_id AND user_id = p_user_id;
    
    -- Если пользователь не является участником проекта, проверяем его глобальную роль
    IF v_project_role IS NULL THEN
        SELECT role_id INTO v_global_role_id
        FROM users
        WHERE user_id = p_user_id;
        
        IF v_global_role_id IS NULL THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Пользователь не найден';
        END IF;
        
        IF v_global_role_id = 1 THEN
            SET v_project_role = 'admin';
        ELSEIF v_global_role_id = 2 THEN
            SET v_project_role = 'manager';
        END IF;
    END IF;
    
    -- Проверяем, имеет ли пользователь право редактировать задачи
    IF v_project_role NOT IN ('admin', 'manager') THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Access Denied: Not authorized to update task';
    END IF;
    
    -- Проверяем, что responsible_id существует
    IF NOT EXISTS (SELECT 1 FROM users WHERE user_id = p_responsible_id) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Ответственный пользователь не найден';
    END IF;
    
    -- Проверяем допустимые значения статуса
    IF p_status NOT IN ('pending', 'in_progress', 'completed') THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Недопустимое значение статуса';
    END IF;
    
    -- Обновляем задачу
    UPDATE tasks
    SET task_name = p_task_name,
        responsible_id = p_responsible_id,
        assistants = p_assistants,
        status = p_status,
        deadline = p_deadline
    WHERE task_id = p_task_id;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_update_user_data` (IN `p_user_id` INT, IN `p_full_name` VARCHAR(100), IN `p_email` VARCHAR(100), IN `p_position` VARCHAR(100), IN `p_department` VARCHAR(100), IN `p_avatar` VARCHAR(255))   BEGIN
    UPDATE users
    SET 
        username = IF(p_username IS NOT NULL, p_username, username),
        password = IF(p_password IS NOT NULL, p_password, password),
        email = IF(p_email IS NOT NULL, p_email, email),
        full_name = IF(p_full_name IS NOT NULL, p_full_name, full_name),
        role_id = IF(p_role_id IS NOT NULL, p_role_id, role_id),
        position = IF(p_position IS NOT NULL, p_position, position),
        department = IF(p_department IS NOT NULL, p_department, department),
        avatar = IF(p_avatar IS NOT NULL, p_avatar, avatar),
        updated_at = CURRENT_TIMESTAMP
    WHERE user_id = p_user_id;
END$$

CREATE DEFINER=`root`@`%` PROCEDURE `sp_update_user_full` (IN `p_user_id` INT, IN `p_username` VARCHAR(255), IN `p_password` VARCHAR(255), IN `p_email` VARCHAR(255), IN `p_full_name` VARCHAR(255), IN `p_role_id` INT, IN `p_position` VARCHAR(255), IN `p_department` VARCHAR(255), IN `p_avatar` VARCHAR(255))   BEGIN
    UPDATE users
    SET 
        username = IF(p_username IS NOT NULL AND p_username != '', p_username, username),
        password = IF(p_password IS NOT NULL AND p_password != '', p_password, password),
        email = IF(p_email IS NOT NULL AND p_email != '', p_email, email),
        full_name = IF(p_full_name IS NOT NULL AND p_full_name != '', p_full_name, full_name),
        role_id = IF(p_role_id IS NOT NULL AND p_role_id != 0, p_role_id, role_id),
        position = IF(p_position IS NOT NULL AND p_position != '', p_position, position),
        department = IF(p_department IS NOT NULL AND p_department != '', p_department, department),
        avatar = IF(p_avatar IS NOT NULL AND p_avatar != '', p_avatar, avatar),
        updated_at = CURRENT_TIMESTAMP
    WHERE user_id = p_user_id;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Структура таблицы `password_resets`
--

CREATE TABLE `password_resets` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `token` varchar(64) NOT NULL,
  `expires_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Дамп данных таблицы `password_resets`
--

INSERT INTO `password_resets` (`id`, `user_id`, `token`, `expires_at`) VALUES
(19, 18, '5d24ba3f68b6f59fecbe3047930fe19a19f34850edb0b1ef7644adc0fe2c68d7', '2025-06-15 15:39:03'),
(20, 18, '52d1b29da9bf4e5098d3fa265b8085a70790b8b10644ce3b59c1d26fc63a25d8', '2025-06-15 15:39:16');

-- --------------------------------------------------------

--
-- Структура таблицы `projects`
--

CREATE TABLE `projects` (
  `project_id` int NOT NULL,
  `name` varchar(255) NOT NULL,
  `short_name` varchar(50) DEFAULT NULL,
  `description` text,
  `planned_start_date` date NOT NULL,
  `actual_start_date` date DEFAULT NULL,
  `planned_end_date` date DEFAULT NULL,
  `actual_end_date` date DEFAULT NULL,
  `planned_budget` decimal(15,2) DEFAULT NULL,
  `actual_budget` decimal(15,2) DEFAULT NULL,
  `planned_digitalization_level` int DEFAULT NULL,
  `actual_digitalization_level` int DEFAULT NULL,
  `planned_labor_costs` int DEFAULT NULL,
  `actual_labor_costs` int DEFAULT NULL,
  `status` enum('planning','in_progress','completed','on_hold') NOT NULL,
  `lifecycle_stage` enum('initiation','planning','execution','monitoring','closure') DEFAULT NULL,
  `scale` enum('small','medium','large','megaproject') NOT NULL,
  `charter_file_path` varchar(255) DEFAULT NULL,
  `expected_resources` text,
  `created_by` int NOT NULL,
  `access_code` varchar(20) NOT NULL,
  `rating` decimal(5,2) NOT NULL DEFAULT '0.00',
  `start_date` date DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ;

--
-- Дамп данных таблицы `projects`
--

INSERT INTO `projects` (`project_id`, `name`, `short_name`, `description`, `planned_start_date`, `actual_start_date`, `planned_end_date`, `actual_end_date`, `planned_budget`, `actual_budget`, `planned_digitalization_level`, `actual_digitalization_level`, `planned_labor_costs`, `actual_labor_costs`, `status`, `lifecycle_stage`, `scale`, `charter_file_path`, `expected_resources`, `created_by`, `access_code`, `rating`, `start_date`, `created_at`, `updated_at`) VALUES
(29, 'Складской логистический комплекс «Быстрый поток»', '«СЛК Быстрый поток»', 'Строительство современного складского комплекса класса А площадью 30 000 м² с зонами хранения, таможенным терминалом и автоматизированной системой управления грузами.', '2024-02-02', '2025-03-28', '2025-10-24', '2025-06-19', 3500000.00, 4000000.00, 70, 70, 2000, 2000, 'completed', 'closure', 'medium', '../uploads/1750886595_уставБыстрыйПоток.doc', 'Материалы	Металлокаркас, сэндвич-панели, бетонные полы повышенной прочности\r\nТехника	Автокраны, бульдозеры, вибропрессы для фундамента\r\nПодрядчики	ООО «ЛогиСтрой» (генподряд), субподряд на системы безопасности', 18, '7', 100.00, NULL, '2025-05-26 03:52:36', '2025-06-26 00:23:15'),
(30, 'Строительство школы на 800 учащихся в микрорайоне Солнечный', 'Школа в Солнечном', 'Возведение 4-этажного образовательного учреждения с актовым залом, двумя спортзалами, столовой и пришкольной территорией.', '2024-01-01', '2024-01-01', '2025-01-01', '2025-01-01', 1000000.00, 1000000.00, 71, 71, 1000, 950, 'completed', 'execution', 'medium', '../uploads/1750886605_УставШколы800.doc', 'Материалы	Кирпич, железобетон, безопасные стеклопакеты\r\nТехника	Бетононасосы, строительные леса, экскаваторы\r\nПодрядчики	ООО «ОбразованиеСтрой» (по госзаказу)', 18, '8', 98.33, NULL, '2025-05-26 03:58:07', '2025-06-26 00:23:25'),
(73, 'ЖК \"Зеленый Бор-2\"', 'ЖК-ЗБ2', 'Строительство жилого комплекса комфорт-класса из 4 монолитно-кирпичных домов (9-16 этажей) с подземным паркингом и детской площадкой. Общая площадь 45 000 кв.м. Применяются энергосберегающие технологии: утепленный фасад, рекуперация воздуха, датчики движения в общественных зонах. Благоустройство территории включает велодорожки и зоны отдыха.', '2023-04-15', '2023-05-01', '2025-02-28', NULL, 12500000.00, 6800000.00, 60, 45, 850, 520, 'in_progress', 'execution', 'medium', '../uploads/1750886468_уставЗеленыйБор-2.doc', '3 строительные бригады, 2 крана, 5 инженеров, подрядчики по электрике и сантехнике', 18, '1', 63.53, '2023-05-01', '2023-03-10 09:00:00', '2025-06-26 00:21:08'),
(74, 'ТЦ \"Солнечный\"', 'ТЦ-СОЛ', 'Строительство 2-этажного торгового центра площадью 25 000 кв.м. с гипермаркетом, 50 магазинами и фуд-кортом. Особенности: панорамное остекление, система климат-контроля, \"умная\" парковка с распознаванием номеров. Проект реализуется в партнерстве с международной сетью ритейеров.', '2024-01-10', '2024-02-01', '2025-11-30', NULL, 18200000.00, 4200000.00, 75, 30, 1200, 380, 'in_progress', 'planning', 'medium', '../uploads/1750886493_уставТЦ_Солнечный.doc', 'Генподрядчик \"СтройГарант\", 4 субподрядчика, коммерческий директор проекта', 18, 'MAP-2024-003', 31.58, '2024-02-01', '2023-11-15 10:20:00', '2025-06-26 00:21:33'),
(75, 'КП \"У озера\"', 'КП-УОЗ', 'Строительство коттеджного поселка премиум-класса (40 участков по 20 соток). Каждый дом: 200-300 кв.м с камином, сауной и умными системами. Инфраструктура: частный пляж, теннисный корт, охрана 24/7. Используются экологичные материалы: газобетон, керамическая черепица, деревянные перекрытия.', '2024-07-01', NULL, '2028-12-31', NULL, 9500000.00, 0.00, 80, 0, 600, 0, 'planning', 'initiation', 'medium', '../uploads/1750886504_уставКП_У_озера.doc', 'Архитектурное бюро \"Домострой\", 5 строительных бригад, ландшафтный дизайнер', 18, 'MAP-2024-015', 0.00, NULL, '2024-04-10 08:45:00', '2025-06-26 00:21:44'),
(76, 'Реконструкция стадиона \"Труд\"', 'СТ-ТРУД', 'Модернизация городского стадиона: замена покрытия беговых дорожек, установка пластиковых сидений (5 000 мест), строительство крытой тренировочной арены. Проект финансируется из городского бюджета и спонсорских средств. После реконструкции стадион сможет принимать международные соревнования.', '2023-08-20', '2023-09-01', '2024-10-01', NULL, 7500000.00, 3100000.00, 50, 25, 700, 290, 'on_hold', 'monitoring', 'medium', '../uploads/1750886518_уставРеконстукции_Труд.doc', 'Специалисты по спортивным сооружениям, 2 подрядные организации', 18, 'MAP-2023-008', 44.25, '2023-09-01', '2023-06-01 14:00:00', '2025-06-26 00:21:58'),
(77, 'БЦ \"Европа\"', 'БЦ-ЕВР', 'Строительство 7-этажного бизнес-центра класса B+ с открытой террасой на крыше. Общая площадь 15 000 кв.м. Особенности: безрамное остекление, система \"умный офис\", конференц-залы с VR-оборудованием. Уже подписаны предварительные договоры с 3 якорными арендаторами.', '2022-11-01', '2022-11-05', '2024-05-31', '2024-04-15', 16800000.00, 15500000.00, 70, 68, 950, 880, 'completed', 'closure', 'medium', '../uploads/1750886642_уставБЦ_Европа.doc', 'Генподрядчик \"Еврострой\", инженеры-электрики, дизайнеры интерьеров', 18, 'MAP-2022-005', 94.01, '2022-11-05', '2022-09-10 12:30:00', '2025-06-26 00:24:02'),
(78, 'ЛК \"Восточный\"', 'ЛК-ВОСТ', 'Строительство логистического комплекса класса А площадью 60 000 кв.м. Включает: складские помещения с высотой потолков 12м, таможенный терминал, административный корпус. Особенности: система климат-контроля, полы с антипылевым покрытием, автоматизированная система учета товаров. Проект реализуется для международной логистической компании.', '2024-03-01', '2024-03-15', '2025-12-31', NULL, 22000000.00, 8500000.00, 85, 40, 1500, 600, 'in_progress', 'execution', 'medium', '../uploads/1750886529_устав_ЛК_Восточный.doc', 'Генподрядчик \"ИндустрСтрой\", 4 субподрядчика, инженеры по складскому оборудованию', 18, 'MAP-2024-021', 41.90, '2024-03-15', '2024-01-20 11:00:00', '2025-06-26 00:22:09'),
(79, 'ЖК \"Солнечный берег\"', 'ЖК-СБ', 'Строительство жилого комплекса эконом-класса из 5 панельных домов (9 этажей). Общая площадь 35 000 кв.м. Инфраструктура: детский сад, спортивная площадка, магазины шаговой доступности. Применены современные энергоэффективные панели с улучшенной шумоизоляцией.', '2023-10-01', '2023-10-10', '2025-05-31', NULL, 9800000.00, 5200000.00, 50, 35, 900, 480, 'in_progress', 'execution', 'medium', '../uploads/1750886546_устав_ЖК_солнечныйберег.doc', '2 строительные бригады, подрядчики по отделочным работам', 18, 'MAP-2023-017', 58.80, '2023-10-10', '2023-08-15 09:30:00', '2025-06-26 00:22:26'),
(80, 'Офисный центр \"Плаза\"', 'ОЦ-ПЛАЗА', 'Реконструкция исторического здания под современный офисный центр с сохранением фасада. Площадь 12 000 кв.м. Особенности: open-space зоны, коворкинг, кафе на крыше. Установлены \"умные\" системы освещения и кондиционирования.', '2024-05-01', NULL, '2025-08-31', NULL, 15000000.00, 0.00, 75, 0, 800, 0, 'planning', 'initiation', 'medium', '../uploads/1750886558_устав_офисныйцентр_плаза.doc', 'Реставраторы, дизайнеры интерьеров, 3 строительные бригады', 18, 'MAP-2024-028', 0.00, NULL, '2024-03-05 10:45:00', '2025-06-26 00:22:38'),
(81, 'Развязка на кольцевой автодороге', 'РАД-КОЛЬЦО', 'Строительство трехуровневой транспортной развязки протяженностью 2.5 км. Включает: 3 моста, 5 эстакад, систему водоотведения. Проект реализуется по государственному контракту с применением инновационных бетонных смесей.', '2023-06-01', '2023-06-20', '2024-11-30', '2024-09-15', 18500000.00, 17500000.00, 65, 60, 2000, 1850, 'completed', 'closure', 'medium', '../uploads/1750886622_Устав_развязки.doc', 'Дорожные строители, мостостроители, инженеры-проектировщики', 18, 'MAP-2023-009', 93.13, '2023-06-20', '2023-04-10 08:00:00', '2025-06-26 00:23:42'),
(82, 'Детская больница \"Смайл\"', 'БОЛЬНИЦА-СМ', 'Строительство современного медицинского центра для детей на 200 коек. Площадь 25 000 кв.м. Включает: диагностический корпус, стационар, реабилитационный центр. Особенности: система очистки воздуха, игровые зоны, \"дружелюбная\" к детям архитектура.', '2024-02-01', '2024-02-10', '2026-06-30', NULL, 32000000.00, 9500000.00, 90, 30, 2200, 700, 'in_progress', 'execution', 'medium', '../uploads/1750886581_уставБольница_смайл.doc', 'Медицинские планировщики, 6 строительных бригад, специалисты по вентиляции', 18, 'MAP-2024-035', 31.61, '2024-02-10', '2023-12-01 14:20:00', '2025-06-26 00:23:01');

-- --------------------------------------------------------

--
-- Структура таблицы `project_documents`
--

CREATE TABLE `project_documents` (
  `document_id` int NOT NULL,
  `project_id` int NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `uploaded_by` int NOT NULL,
  `upload_date` datetime DEFAULT CURRENT_TIMESTAMP,
  `document_type` enum('document','report','drawing','photo') CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT 'document',
  `uploaded_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Дамп данных таблицы `project_documents`
--

INSERT INTO `project_documents` (`document_id`, `project_id`, `file_name`, `file_path`, `uploaded_by`, `upload_date`, `document_type`, `uploaded_at`) VALUES
(48, 73, 'Вертикальный разрез здания на чертеже.jpg', '../uploads/drawings/1750887626_Вертикальный разрез здания на чертеже.jpg', 18, '2025-06-26 00:40:26', 'drawing', '2025-06-26 00:40:26'),
(49, 73, 'Горизонтальный разрез здания на чертеже.jpg', '../uploads/drawings/1750887646_Горизонтальный разрез здания на чертеже.jpg', 18, '2025-06-26 00:40:46', 'drawing', '2025-06-26 00:40:46'),
(50, 73, 'Компоновка чертежа.jpg', '../uploads/drawings/1750887660_Компоновка чертежа.jpg', 18, '2025-06-26 00:41:00', 'drawing', '2025-06-26 00:41:00'),
(51, 73, 'Основные элементы лестничной клетки.jpg', '../uploads/drawings/1750887668_Основные элементы лестничной клетки.jpg', 18, '2025-06-26 00:41:08', 'drawing', '2025-06-26 00:41:08'),
(52, 73, 'Должностная инструкция.docx', '../uploads/documents/1750887681_Должностная инструкция.docx', 18, '2025-06-26 00:41:21', 'document', '2025-06-26 00:41:21'),
(54, 73, 'Контрольная карта для количественного признака.docx', '../uploads/documents/1750887695_Контрольная карта для количественного признака.docx', 18, '2025-06-26 00:41:35', 'document', '2025-06-26 00:41:35'),
(55, 73, 'Операции с объектом.docx', '../uploads/documents/1750887707_Операции с объектом.docx', 18, '2025-06-26 00:41:47', 'document', '2025-06-26 00:41:47'),
(56, 73, 'Отчет по проведенному анализу несоответствия.pdf', '../uploads/reports/1750887716_Отчет по проведенному анализу несоответствия.pdf', 18, '2025-06-26 00:41:56', 'report', '2025-06-26 00:41:56'),
(57, 73, 'Отчет по временным ресурсам.pdf', '../uploads/reports/1750887727_Отчет по временным ресурсам.pdf', 18, '2025-06-26 00:42:07', 'report', '2025-06-26 00:42:07'),
(58, 73, 'Отчет по проекту.pdf', '../uploads/reports/1750887738_Отчет по проекту.pdf', 18, '2025-06-26 00:42:18', 'report', '2025-06-26 00:42:18'),
(59, 73, 'Вид.jpg', '../uploads/photoproject/1750888278_Вид.jpg', 18, '2025-06-26 00:51:18', 'photo', '2025-06-26 00:51:18'),
(60, 73, 'Схема.jpeg', '../uploads/photoproject/1750888285_Схема.jpeg', 18, '2025-06-26 00:51:25', 'photo', '2025-06-26 00:51:25'),
(61, 74, 'Вертикальный разрез здания на чертеже.jpg', '../uploads/drawings/1750888336_Вертикальный разрез здания на чертеже.jpg', 18, '2025-06-26 00:52:16', 'drawing', '2025-06-26 00:52:16'),
(62, 74, 'Конструктивный разрез здания на чертеже.jpg', '../uploads/drawings/1750888344_Конструктивный разрез здания на чертеже.jpg', 18, '2025-06-26 00:52:24', 'drawing', '2025-06-26 00:52:24'),
(63, 74, 'Основные элементы оконного проёма.jpg', '../uploads/drawings/1750888351_Основные элементы оконного проёма.jpg', 18, '2025-06-26 00:52:31', 'drawing', '2025-06-26 00:52:31'),
(64, 74, 'Маршруты документа.docx', '../uploads/documents/1750888363_Маршруты документа.docx', 18, '2025-06-26 00:52:43', 'document', '2025-06-26 00:52:43'),
(65, 74, 'Отчет по проведенному анализу несоответствия.pdf', '../uploads/reports/1750888371_Отчет по проведенному анализу несоответствия.pdf', 18, '2025-06-26 00:52:51', 'report', '2025-06-26 00:52:51'),
(66, 74, 'ТЦ_Солнечный.jpg', '../uploads/photoproject/1750888400_ТЦ_Солнечный.jpg', 18, '2025-06-26 00:53:20', 'photo', '2025-06-26 00:53:20'),
(67, 75, 'ЧертежПоселка.jpg', '../uploads/drawings/1750888777_ЧертежПоселка.jpg', 18, '2025-06-26 00:59:37', 'drawing', '2025-06-26 00:59:37'),
(68, 75, 'Схема.jpeg', '../uploads/documents/1750888791_Схема.jpeg', 18, '2025-06-26 00:59:51', 'document', '2025-06-26 00:59:51'),
(69, 75, 'Жизненный цикл объекта.docx', '../uploads/documents/1750889066_Жизненный цикл объекта.docx', 18, '2025-06-26 01:04:26', 'document', '2025-06-26 01:04:26'),
(70, 75, 'Отчет по проекту.pdf', '../uploads/reports/1750889076_Отчет по проекту.pdf', 18, '2025-06-26 01:04:36', 'report', '2025-06-26 01:04:36'),
(71, 75, 'Регламент процесса IDEF0.docx', '../uploads/documents/1750889085_Регламент процесса IDEF0.docx', 18, '2025-06-26 01:04:45', 'document', '2025-06-26 01:04:45'),
(72, 76, 'Маршруты документа.docx', '../uploads/documents/1750889113_Маршруты документа.docx', 18, '2025-06-26 01:05:13', 'document', '2025-06-26 01:05:13'),
(73, 76, 'Диаграмма Парето.pdf', '../uploads/documents/1750889120_Диаграмма Парето.pdf', 18, '2025-06-26 01:05:20', 'document', '2025-06-26 01:05:20'),
(74, 76, 'Регламент Процедуры.docx', '../uploads/documents/1750889127_Регламент Процедуры.docx', 18, '2025-06-26 01:05:27', 'document', '2025-06-26 01:05:27'),
(75, 76, 'Горизонтальный разрез здания на чертеже.jpg', '../uploads/drawings/1750889138_Горизонтальный разрез здания на чертеже.jpg', 18, '2025-06-26 01:05:38', 'drawing', '2025-06-26 01:05:38'),
(76, 76, 'Компоновка чертежа.jpg', '../uploads/drawings/1750889152_Компоновка чертежа.jpg', 18, '2025-06-26 01:05:52', 'drawing', '2025-06-26 01:05:52'),
(77, 76, 'Основные элементы лестничной клетки.jpg', '../uploads/drawings/1750889163_Основные элементы лестничной клетки.jpg', 18, '2025-06-26 01:06:03', 'drawing', '2025-06-26 01:06:03'),
(78, 78, 'Диаграмма Парето.pdf', '../uploads/documents/1750889484_Диаграмма Парето.pdf', 18, '2025-06-26 01:11:24', 'document', '2025-06-26 01:11:24'),
(79, 78, 'Должностная инструкция.docx', '../uploads/documents/1750889491_Должностная инструкция.docx', 18, '2025-06-26 01:11:31', 'document', '2025-06-26 01:11:31'),
(80, 78, 'Операции с объектом.docx', '../uploads/documents/1750889498_Операции с объектом.docx', 18, '2025-06-26 01:11:38', 'document', '2025-06-26 01:11:38'),
(81, 78, 'Руководство по качеству СМК.pdf', '../uploads/documents/1750889509_Руководство по качеству СМК.pdf', 18, '2025-06-26 01:11:49', 'document', '2025-06-26 01:11:49'),
(82, 78, 'схемаПомещения.jpg', '../uploads/photoproject/1750889525_схемаПомещения.jpg', 18, '2025-06-26 01:12:05', 'photo', '2025-06-26 01:12:05'),
(83, 78, 'Схема.jpeg', '../uploads/reports/1750889539_Схема.jpeg', 18, '2025-06-26 01:12:19', 'report', '2025-06-26 01:12:19'),
(84, 79, 'Жизненный цикл объекта.docx', '../uploads/documents/1750889903_Жизненный цикл объекта.docx', 18, '2025-06-26 01:18:23', 'document', '2025-06-26 01:18:23'),
(85, 79, 'Отчет по проекту.pdf', '../uploads/documents/1750889912_Отчет по проекту.pdf', 18, '2025-06-26 01:18:32', 'document', '2025-06-26 01:18:32'),
(86, 79, 'Регламент процесса IDEF0.docx', '../uploads/documents/1750889920_Регламент процесса IDEF0.docx', 18, '2025-06-26 01:18:40', 'document', '2025-06-26 01:18:40'),
(87, 79, 'Операции с объектом.docx', '../uploads/documents/1750889929_Операции с объектом.docx', 18, '2025-06-26 01:18:49', 'document', '2025-06-26 01:18:49'),
(88, 79, 'Диаграмма Парето.pdf', '../uploads/reports/1750889949_Диаграмма Парето.pdf', 18, '2025-06-26 01:19:09', 'report', '2025-06-26 01:19:09'),
(89, 79, 'ЖК Солнечный берег.jpg', '../uploads/photoproject/1750889995_ЖК Солнечный берег.jpg', 18, '2025-06-26 01:19:55', 'photo', '2025-06-26 01:19:55'),
(90, 79, 'ЖК Солнечный берег2.jpg', '../uploads/photoproject/1750890003_ЖК Солнечный берег2.jpg', 18, '2025-06-26 01:20:03', 'photo', '2025-06-26 01:20:03'),
(91, 82, 'Должностная инструкция.docx', '../uploads/documents/1750890240_Должностная инструкция.docx', 18, '2025-06-26 01:24:00', 'document', '2025-06-26 01:24:00'),
(92, 82, 'Операции с атрибутами объекта.docx', '../uploads/documents/1750890247_Операции с атрибутами объекта.docx', 18, '2025-06-26 01:24:07', 'document', '2025-06-26 01:24:07'),
(93, 82, 'Регламент Процедуры.docx', '../uploads/documents/1750890255_Регламент Процедуры.docx', 18, '2025-06-26 01:24:15', 'document', '2025-06-26 01:24:15'),
(94, 82, 'Вертикальный разрез здания на чертеже.jpg', '../uploads/drawings/1750890267_Вертикальный разрез здания на чертеже.jpg', 18, '2025-06-26 01:24:27', 'drawing', '2025-06-26 01:24:27'),
(95, 82, 'Горизонтальный разрез здания на чертеже.jpg', '../uploads/drawings/1750890274_Горизонтальный разрез здания на чертеже.jpg', 18, '2025-06-26 01:24:34', 'drawing', '2025-06-26 01:24:34'),
(96, 82, 'Компоновка чертежа.jpg', '../uploads/drawings/1750890285_Компоновка чертежа.jpg', 18, '2025-06-26 01:24:45', 'drawing', '2025-06-26 01:24:45'),
(97, 82, 'Конструктивный разрез здания на чертеже.jpg', '../uploads/drawings/1750890294_Конструктивный разрез здания на чертеже.jpg', 18, '2025-06-26 01:24:54', 'drawing', '2025-06-26 01:24:54'),
(98, 82, 'Основные части здания.jpg', '../uploads/drawings/1750890302_Основные части здания.jpg', 18, '2025-06-26 01:25:02', 'drawing', '2025-06-26 01:25:02'),
(99, 82, 'Детская больница Смайл.jpg', '../uploads/photoproject/1750890333_Детская больница Смайл.jpg', 18, '2025-06-26 01:25:33', 'photo', '2025-06-26 01:25:33'),
(100, 29, 'Вертикальный разрез здания на чертеже.jpg', '../uploads/drawings/1750937747_Вертикальный разрез здания на чертеже.jpg', 18, '2025-06-26 14:35:47', 'drawing', '2025-06-26 14:35:47'),
(101, 29, 'Горизонтальный разрез здания на чертеже.jpg', '../uploads/drawings/1750937755_Горизонтальный разрез здания на чертеже.jpg', 18, '2025-06-26 14:35:55', 'drawing', '2025-06-26 14:35:55'),
(102, 29, 'Дверной проем.jpg', '../uploads/drawings/1750937764_Дверной проем.jpg', 18, '2025-06-26 14:36:04', 'drawing', '2025-06-26 14:36:04'),
(103, 29, 'Компоновка чертежа.jpg', '../uploads/drawings/1750937776_Компоновка чертежа.jpg', 18, '2025-06-26 14:36:16', 'drawing', '2025-06-26 14:36:16'),
(104, 29, 'Конструктивный разрез здания на чертеже.jpg', '../uploads/drawings/1750937786_Конструктивный разрез здания на чертеже.jpg', 18, '2025-06-26 14:36:26', 'drawing', '2025-06-26 14:36:26'),
(105, 29, 'Основные части здания.jpg', '../uploads/drawings/1750937796_Основные части здания.jpg', 18, '2025-06-26 14:36:36', 'drawing', '2025-06-26 14:36:36'),
(106, 29, 'Основные элементы лестницы.jpg', '../uploads/drawings/1750937805_Основные элементы лестницы.jpg', 18, '2025-06-26 14:36:45', 'drawing', '2025-06-26 14:36:45'),
(107, 29, 'Основные элементы оконного проёма.jpg', '../uploads/drawings/1750937815_Основные элементы оконного проёма.jpg', 18, '2025-06-26 14:36:55', 'drawing', '2025-06-26 14:36:55'),
(108, 29, 'Отчет по временным ресурсам.pdf', '../uploads/reports/1750937844_Отчет по временным ресурсам.pdf', 18, '2025-06-26 14:37:24', 'report', '2025-06-26 14:37:24'),
(109, 29, 'Жизненный цикл объекта.docx', '../uploads/documents/1750937853_Жизненный цикл объекта.docx', 18, '2025-06-26 14:37:33', 'document', '2025-06-26 14:37:33'),
(111, 29, 'Отчет по проекту.pdf', '../uploads/reports/1750937885_Отчет по проекту.pdf', 18, '2025-06-26 14:38:05', 'report', '2025-06-26 14:38:05'),
(112, 29, 'Маршруты документа.docx', '../uploads/documents/1750937899_Маршруты документа.docx', 18, '2025-06-26 14:38:19', 'document', '2025-06-26 14:38:19'),
(113, 29, 'Операции с объектом.docx', '../uploads/documents/1750937907_Операции с объектом.docx', 18, '2025-06-26 14:38:27', 'document', '2025-06-26 14:38:27'),
(114, 29, 'Складской логистический комплекс «Быстрый поток».jpg', '../uploads/photoproject/1750937974_Складской логистический комплекс «Быстрый поток».jpg', 18, '2025-06-26 14:39:34', 'photo', '2025-06-26 14:39:34'),
(115, 29, 'фото внутри.jpg', '../uploads/photoproject/1750937979_фото внутри.jpg', 18, '2025-06-26 14:39:39', 'photo', '2025-06-26 14:39:39'),
(116, 29, 'Фото здания.jpg', '../uploads/photoproject/1750937996_Фото здания.jpg', 18, '2025-06-26 14:39:56', 'photo', '2025-06-26 14:39:56'),
(117, 30, 'четрежШколы.png', '../uploads/drawings/1750938780_четрежШколы.png', 18, '2025-06-26 14:53:00', 'drawing', '2025-06-26 14:53:00'),
(118, 30, 'ВидШколы.jpg', '../uploads/photoproject/1750938785_ВидШколы.jpg', 18, '2025-06-26 14:53:05', 'photo', '2025-06-26 14:53:05'),
(119, 30, 'Жизненный цикл объекта.docx', '../uploads/documents/1750938792_Жизненный цикл объекта.docx', 18, '2025-06-26 14:53:12', 'document', '2025-06-26 14:53:12'),
(120, 30, 'Отчет по проекту.pdf', '../uploads/reports/1750938805_Отчет по проекту.pdf', 18, '2025-06-26 14:53:25', 'report', '2025-06-26 14:53:25'),
(121, 77, 'Вертикальный разрез здания на чертеже.jpg', '../uploads/drawings/1750939000_Вертикальный разрез здания на чертеже.jpg', 18, '2025-06-26 14:56:40', 'drawing', '2025-06-26 14:56:40'),
(122, 77, 'Конструктивный разрез здания на чертеже.jpg', '../uploads/drawings/1750939010_Конструктивный разрез здания на чертеже.jpg', 18, '2025-06-26 14:56:50', 'drawing', '2025-06-26 14:56:50'),
(123, 77, 'Основные элементы оконного проёма.jpg', '../uploads/drawings/1750939020_Основные элементы оконного проёма.jpg', 18, '2025-06-26 14:57:00', 'drawing', '2025-06-26 14:57:00'),
(124, 77, 'Операции с атрибутами объекта.docx', '../uploads/documents/1750939032_Операции с атрибутами объекта.docx', 18, '2025-06-26 14:57:12', 'document', '2025-06-26 14:57:12'),
(125, 77, 'План корректирующих и предупреждающих действий несоответствия.pdf', '../uploads/reports/1750939041_План корректирующих и предупреждающих действий несоответствия.pdf', 18, '2025-06-26 14:57:21', 'report', '2025-06-26 14:57:21'),
(126, 81, 'Операции с объектом.docx', '../uploads/documents/1750939275_Операции с объектом.docx', 18, '2025-06-26 15:01:15', 'document', '2025-06-26 15:01:15'),
(127, 81, 'Операции с атрибутами объекта.docx', '../uploads/documents/1750939282_Операции с атрибутами объекта.docx', 18, '2025-06-26 15:01:22', 'document', '2025-06-26 15:01:22'),
(130, 81, 'КольцоДорогиЧертеж.png', '../uploads/drawings/1750939362_КольцоДорогиЧертеж.png', 18, '2025-06-26 15:02:42', 'drawing', '2025-06-26 15:02:42'),
(131, 81, 'видКольцаСверху.jpg', '../uploads/photoproject/1750939398_видКольцаСверху.jpg', 18, '2025-06-26 15:03:18', 'photo', '2025-06-26 15:03:18');

-- --------------------------------------------------------

--
-- Структура таблицы `project_participants`
--

CREATE TABLE `project_participants` (
  `participant_id` int NOT NULL,
  `project_id` int NOT NULL,
  `user_id` int NOT NULL,
  `project_role` enum('employee','manager','admin','user') NOT NULL DEFAULT 'employee',
  `joined_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Дамп данных таблицы `project_participants`
--

INSERT INTO `project_participants` (`participant_id`, `project_id`, `user_id`, `project_role`, `joined_at`) VALUES
(75, 30, 20, 'employee', '2025-06-12 03:29:45'),
(76, 30, 50, 'employee', '2025-06-12 03:29:45'),
(78, 30, 61, 'employee', '2025-06-12 03:29:45'),
(79, 30, 62, 'employee', '2025-06-12 03:29:45'),
(80, 30, 45, 'manager', '2025-06-12 03:29:51'),
(81, 30, 58, 'manager', '2025-06-12 03:29:51'),
(83, 30, 47, 'user', '2025-06-12 03:29:57'),
(84, 30, 49, 'user', '2025-06-12 03:29:57'),
(127, 73, 20, 'manager', '2025-06-25 15:19:36'),
(130, 73, 45, 'manager', '2025-06-26 00:08:02'),
(131, 73, 46, 'manager', '2025-06-26 00:08:02'),
(132, 73, 47, 'employee', '2025-06-26 00:08:09'),
(133, 73, 48, 'employee', '2025-06-26 00:08:09'),
(134, 73, 49, 'user', '2025-06-26 00:08:14'),
(135, 73, 50, 'user', '2025-06-26 00:08:14'),
(136, 74, 47, 'manager', '2025-06-26 00:08:47'),
(137, 74, 49, 'manager', '2025-06-26 00:08:47'),
(138, 74, 50, 'manager', '2025-06-26 00:08:47'),
(139, 74, 45, 'employee', '2025-06-26 00:08:52'),
(140, 74, 60, 'employee', '2025-06-26 00:08:52'),
(141, 74, 61, 'employee', '2025-06-26 00:08:52'),
(142, 74, 46, 'user', '2025-06-26 00:08:58'),
(143, 74, 48, 'user', '2025-06-26 00:08:58'),
(144, 74, 62, 'user', '2025-06-26 00:08:58'),
(145, 75, 47, 'employee', '2025-06-26 00:09:14'),
(146, 75, 49, 'employee', '2025-06-26 00:09:14'),
(147, 75, 50, 'user', '2025-06-26 00:09:14'),
(148, 75, 62, 'employee', '2025-06-26 00:09:14'),
(149, 75, 58, 'manager', '2025-06-26 00:09:19'),
(150, 75, 60, 'manager', '2025-06-26 00:09:19'),
(151, 75, 45, 'employee', '2025-06-26 00:09:26'),
(152, 75, 46, 'employee', '2025-06-26 00:09:26'),
(153, 75, 48, 'employee', '2025-06-26 00:09:26'),
(154, 76, 20, 'user', '2025-06-26 00:09:52'),
(155, 76, 45, 'user', '2025-06-26 00:09:52'),
(156, 76, 61, 'user', '2025-06-26 00:09:52'),
(157, 76, 62, 'user', '2025-06-26 00:09:52'),
(158, 76, 46, 'manager', '2025-06-26 00:09:57'),
(159, 76, 47, 'manager', '2025-06-26 00:09:57'),
(160, 76, 48, 'employee', '2025-06-26 00:10:06'),
(161, 76, 49, 'employee', '2025-06-26 00:10:06'),
(162, 76, 50, 'employee', '2025-06-26 00:10:06'),
(163, 76, 57, 'employee', '2025-06-26 00:10:06'),
(164, 76, 58, 'employee', '2025-06-26 00:10:06'),
(165, 76, 60, 'employee', '2025-06-26 00:10:06'),
(166, 78, 49, 'employee', '2025-06-26 00:10:15'),
(167, 78, 50, 'employee', '2025-06-26 00:10:15'),
(168, 78, 58, 'manager', '2025-06-26 00:10:19'),
(169, 78, 60, 'manager', '2025-06-26 00:10:19'),
(170, 78, 57, 'user', '2025-06-26 00:10:24'),
(171, 78, 61, 'user', '2025-06-26 00:10:24'),
(172, 79, 46, 'employee', '2025-06-26 00:10:43'),
(173, 79, 47, 'employee', '2025-06-26 00:10:43'),
(174, 79, 48, 'employee', '2025-06-26 00:10:43'),
(175, 79, 49, 'employee', '2025-06-26 00:10:43'),
(176, 79, 50, 'employee', '2025-06-26 00:10:43'),
(177, 79, 57, 'employee', '2025-06-26 00:10:43'),
(178, 79, 58, 'employee', '2025-06-26 00:10:43'),
(179, 79, 60, 'employee', '2025-06-26 00:10:43'),
(180, 79, 61, 'employee', '2025-06-26 00:10:43'),
(181, 79, 62, 'employee', '2025-06-26 00:10:43'),
(182, 79, 20, 'manager', '2025-06-26 00:10:47'),
(183, 79, 45, 'manager', '2025-06-26 00:10:47'),
(184, 80, 20, 'manager', '2025-06-26 00:10:59'),
(185, 80, 45, 'manager', '2025-06-26 00:10:59'),
(186, 82, 50, 'employee', '2025-06-26 00:11:22'),
(187, 82, 57, 'employee', '2025-06-26 00:11:22'),
(188, 82, 58, 'employee', '2025-06-26 00:11:22'),
(189, 82, 60, 'employee', '2025-06-26 00:11:22'),
(190, 82, 62, 'employee', '2025-06-26 00:11:22'),
(191, 82, 20, 'manager', '2025-06-26 00:11:27'),
(192, 29, 20, 'manager', '2025-06-26 14:33:18'),
(193, 29, 45, 'manager', '2025-06-26 14:33:18'),
(194, 29, 46, 'manager', '2025-06-26 14:33:18'),
(195, 29, 47, 'employee', '2025-06-26 14:33:25'),
(196, 29, 48, 'employee', '2025-06-26 14:33:25'),
(197, 29, 49, 'employee', '2025-06-26 14:33:25'),
(198, 29, 50, 'employee', '2025-06-26 14:33:25'),
(199, 29, 57, 'employee', '2025-06-26 14:33:25'),
(200, 29, 58, 'employee', '2025-06-26 14:33:32'),
(201, 29, 60, 'employee', '2025-06-26 14:33:32'),
(202, 29, 61, 'employee', '2025-06-26 14:33:32'),
(203, 29, 62, 'employee', '2025-06-26 14:33:32'),
(204, 77, 49, 'manager', '2025-06-26 14:34:03'),
(205, 77, 50, 'manager', '2025-06-26 14:34:03'),
(206, 77, 57, 'employee', '2025-06-26 14:34:10'),
(207, 77, 58, 'employee', '2025-06-26 14:34:10'),
(208, 77, 20, 'user', '2025-06-26 14:34:15'),
(209, 81, 50, 'user', '2025-06-26 14:34:53');

-- --------------------------------------------------------

--
-- Структура таблицы `project_stages`
--

CREATE TABLE `project_stages` (
  `stage_id` int NOT NULL,
  `project_id` int NOT NULL,
  `stage_name` varchar(255) NOT NULL DEFAULT '',
  `description` text,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `status` enum('not_started','in_progress','completed') NOT NULL DEFAULT 'not_started',
  `stage_order` int NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `roles`
--

CREATE TABLE `roles` (
  `role_id` int NOT NULL,
  `role_name` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Дамп данных таблицы `roles`
--

INSERT INTO `roles` (`role_id`, `role_name`) VALUES
(1, 'admin'),
(3, 'employee'),
(2, 'manager'),
(4, 'user');

-- --------------------------------------------------------

--
-- Структура таблицы `tasks`
--

CREATE TABLE `tasks` (
  `task_id` int NOT NULL,
  `project_id` int DEFAULT NULL,
  `responsible_id` int NOT NULL,
  `task_name` varchar(255) DEFAULT NULL,
  `assistants` json NOT NULL,
  `status` varchar(50) DEFAULT NULL,
  `deadline` date DEFAULT NULL,
  `created_by` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Дамп данных таблицы `tasks`
--

INSERT INTO `tasks` (`task_id`, `project_id`, `responsible_id`, `task_name`, `assistants`, `status`, `deadline`, `created_by`) VALUES
(28, 73, 20, 'Разработка лестницы', '[\"45\", \"46\", \"47\"]', 'in_progress', '2025-07-18', 18),
(29, 73, 20, 'Установка окн', '[\"46\", \"47\", \"49\"]', 'in_progress', '2025-06-27', 18),
(30, 73, 45, 'Подключить воду в ЖК', '[\"20\", \"47\", \"48\"]', 'completed', '2025-06-12', 18),
(31, 74, 48, 'Проанализировать схему помещения', '[\"49\", \"47\", \"62\"]', 'pending', '2025-07-11', 18),
(32, 74, 47, 'Залить цемент в отверстия для цемента в пункте №3', '[\"62\", \"48\", \"46\"]', 'completed', '2025-06-19', 18),
(35, 76, 20, 'Снос старых стен', '[\"61\", \"46\", \"60\"]', 'pending', '2024-06-14', 18),
(36, 76, 45, 'Установка новых стен', '[\"61\", \"62\", \"58\"]', 'in_progress', '2024-09-27', 18),
(37, 78, 49, 'выравнивание площади под логистического комплекс', '[\"58\", \"60\", \"61\"]', 'completed', '2024-07-26', 18),
(38, 79, 46, 'выравнивание площади под ЖК', '[\"48\", \"49\", \"62\"]', 'in_progress', '2024-07-26', 18),
(39, 79, 48, 'Залить цемент в отверстия для цемента в пункте №2', '[\"49\", \"20\", \"45\"]', 'in_progress', '2025-04-18', 18),
(40, 82, 50, 'Установка дверных проемов', '[\"58\", \"60\", \"62\"]', 'in_progress', '2025-05-08', 18),
(41, 29, 20, 'Выравнивание площади', '[\"47\", \"61\", \"62\", \"60\"]', 'completed', '2024-12-21', 18),
(42, 29, 45, 'Сдача проекта', '[\"46\", \"47\"]', 'completed', '2025-02-15', 18),
(43, 30, 50, 'Залить цемент в пол', '[\"47\", \"49\"]', 'completed', '2024-10-19', 18),
(44, 30, 62, 'Сдача проекта', '[\"47\", \"49\"]', 'completed', '2024-12-26', 18),
(45, 77, 57, 'Возведение Стен', '[\"57\", \"58\", \"50\"]', 'completed', '2024-04-26', 18),
(47, 81, 20, 'Выравнивание площади', '[\"49\", \"60\", \"61\", \"62\"]', 'completed', '2024-08-13', 18),
(48, 81, 20, 'Сдача проекта', '[\"49\", \"60\", \"62\"]', 'completed', '2024-10-26', 18);

-- --------------------------------------------------------

--
-- Структура таблицы `task_documents`
--

CREATE TABLE `task_documents` (
  `document_id` int NOT NULL,
  `task_id` int DEFAULT NULL,
  `file_name` varchar(255) DEFAULT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `document_type` varchar(50) NOT NULL DEFAULT 'document',
  `uploaded_by` int DEFAULT NULL,
  `upload_date` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Дамп данных таблицы `task_documents`
--

INSERT INTO `task_documents` (`document_id`, `task_id`, `file_name`, `file_path`, `document_type`, `uploaded_by`, `upload_date`) VALUES
(27, 28, 'Основные элементы лестничной клетки.jpg', '../uploads/task_documents/drawings/1750887899_Основные элементы лестничной клетки.jpg', 'drawing', 18, '2025-06-25 21:44:59'),
(28, 28, 'Основные элементы лестницы.jpg', '../uploads/task_documents/documents/1750887903_Основные элементы лестницы.jpg', 'document', 18, '2025-06-25 21:45:03'),
(29, 28, 'Регламент процесса IDEF0.docx', '../uploads/task_documents/documents/1750887930_Регламент процесса IDEF0.docx', 'document', 18, '2025-06-25 21:45:30'),
(30, 28, 'Должностная инструкция.docx', '../uploads/task_documents/documents/1750887937_Должностная инструкция.docx', 'document', 18, '2025-06-25 21:45:37'),
(31, 28, 'Отчет по проекту.pdf', '../uploads/task_documents/reports/1750887948_Отчет по проекту.pdf', 'report', 18, '2025-06-25 21:45:48'),
(32, 29, 'Ранжирование причин несоответствия.pdf', '../uploads/task_documents/documents/1750887964_Ранжирование причин несоответствия.pdf', 'document', 18, '2025-06-25 21:46:04'),
(33, 29, 'Регламент процесса BPMN.docx', '../uploads/task_documents/documents/1750887969_Регламент процесса BPMN.docx', 'document', 18, '2025-06-25 21:46:09'),
(34, 29, 'Отчет по временным ресурсам.pdf', '../uploads/task_documents/reports/1750887977_Отчет по временным ресурсам.pdf', 'report', 18, '2025-06-25 21:46:17'),
(35, 30, 'Вертикальный разрез здания на чертеже.jpg', '../uploads/task_documents/drawings/1750888010_Вертикальный разрез здания на чертеже.jpg', 'drawing', 18, '2025-06-25 21:46:50'),
(36, 30, 'Горизонтальный разрез здания на чертеже.jpg', '../uploads/task_documents/drawings/1750888014_Горизонтальный разрез здания на чертеже.jpg', 'drawing', 18, '2025-06-25 21:46:54'),
(37, 30, 'Руководство по качеству СМК.pdf', '../uploads/task_documents/documents/1750888028_Руководство по качеству СМК.pdf', 'document', 18, '2025-06-25 21:47:08'),
(38, 31, 'схемаПомещения.jpg', '../uploads/task_documents/photoproject/1750888582_схемаПомещения.jpg', 'photo', 18, '2025-06-25 21:56:22'),
(39, 31, 'План корректирующих и предупреждающих действий несоответствия.pdf', '../uploads/task_documents/documents/1750888596_План корректирующих и предупреждающих действий несоответствия.pdf', 'document', 18, '2025-06-25 21:56:36'),
(40, 32, 'цемет№3 пункт.jpg', '../uploads/task_documents/photoproject/1750888655_цемет№3 пункт.jpg', 'photo', 18, '2025-06-25 21:57:35'),
(41, 32, 'Регламент Процедуры.docx', '../uploads/task_documents/documents/1750888665_Регламент Процедуры.docx', 'document', 18, '2025-06-25 21:57:45'),
(48, 35, 'Снос_стен.jpeg', '../uploads/task_documents/photoproject/1750889316_Снос_стен.jpeg', 'photo', 18, '2025-06-25 22:08:36'),
(49, 35, 'Снос_стен2.jpeg', '../uploads/task_documents/photoproject/1750889321_Снос_стен2.jpeg', 'photo', 18, '2025-06-25 22:08:41'),
(50, 35, 'схемаПомещения.jpg', '../uploads/task_documents/drawings/1750889329_схемаПомещения.jpg', 'drawing', 18, '2025-06-25 22:08:49'),
(51, 35, 'Должностная инструкция.docx', '../uploads/task_documents/documents/1750889342_Должностная инструкция.docx', 'document', 18, '2025-06-25 22:09:02'),
(52, 36, 'установкаСтен.png', '../uploads/task_documents/photoproject/1750889403_установкаСтен.png', 'photo', 18, '2025-06-25 22:10:03'),
(53, 36, 'УстановкаНовыхСтен.jpg', '../uploads/task_documents/photoproject/1750889412_УстановкаНовыхСтен.jpg', 'photo', 18, '2025-06-25 22:10:12'),
(54, 36, 'Схема.jpeg', '../uploads/task_documents/drawings/1750889419_Схема.jpeg', 'drawing', 18, '2025-06-25 22:10:19'),
(55, 36, 'Отчет по проекту.pdf', '../uploads/task_documents/reports/1750889435_Отчет по проекту.pdf', 'report', 18, '2025-06-25 22:10:35'),
(56, 37, 'выравниваниеТрактором.jpg', '../uploads/task_documents/photoproject/1750889845_выравниваниеТрактором.jpg', 'photo', 18, '2025-06-25 22:17:25'),
(57, 37, 'Выравнивание.jpeg', '../uploads/task_documents/photoproject/1750889852_Выравнивание.jpeg', 'photo', 18, '2025-06-25 22:17:32'),
(58, 37, 'Схема.jpeg', '../uploads/task_documents/reports/1750889860_Схема.jpeg', 'report', 18, '2025-06-25 22:17:40'),
(59, 37, 'Маршруты документа.docx', '../uploads/task_documents/documents/1750889870_Маршруты документа.docx', 'document', 18, '2025-06-25 22:17:50'),
(60, 38, 'выравниваниеТрактором.jpg', '../uploads/task_documents/photoproject/1750890080_выравниваниеТрактором.jpg', 'photo', 18, '2025-06-25 22:21:20'),
(61, 38, 'Выравнивание.jpeg', '../uploads/task_documents/photoproject/1750890085_Выравнивание.jpeg', 'photo', 18, '2025-06-25 22:21:25'),
(62, 38, 'Подсыпка песка.jpg', '../uploads/task_documents/photoproject/1750890093_Подсыпка песка.jpg', 'photo', 18, '2025-06-25 22:21:33'),
(63, 38, 'Операции с объектом.docx', '../uploads/task_documents/documents/1750890103_Операции с объектом.docx', 'document', 18, '2025-06-25 22:21:43'),
(64, 38, 'Основные части здания.jpg', '../uploads/task_documents/drawings/1750890133_Основные части здания.jpg', 'drawing', 18, '2025-06-25 22:22:13'),
(65, 39, 'цемет№2 пункт.jpg', '../uploads/task_documents/photoproject/1750890155_цемет№2 пункт.jpg', 'photo', 18, '2025-06-25 22:22:35'),
(66, 39, 'установкаСтен.png', '../uploads/task_documents/photoproject/1750890166_установкаСтен.png', 'photo', 18, '2025-06-25 22:22:46'),
(67, 39, 'Регламент Процедуры.docx', '../uploads/task_documents/documents/1750890180_Регламент Процедуры.docx', 'document', 18, '2025-06-25 22:23:00'),
(68, 40, 'Дверной проем.jpg', '../uploads/task_documents/drawings/1750890410_Дверной проем.jpg', 'drawing', 18, '2025-06-25 22:26:50'),
(69, 40, 'Основные элементы оконного проёма.jpg', '../uploads/task_documents/drawings/1750890417_Основные элементы оконного проёма.jpg', 'drawing', 18, '2025-06-25 22:26:57'),
(70, 40, 'дверной проем.png', '../uploads/task_documents/photoproject/1750890441_дверной проем.png', 'photo', 18, '2025-06-25 22:27:21'),
(71, 40, 'Операции с атрибутами объекта.docx', '../uploads/task_documents/documents/1750890454_Операции с атрибутами объекта.docx', 'document', 18, '2025-06-25 22:27:34'),
(72, 41, 'выравниваниеТрактором.jpg', '../uploads/task_documents/photoproject/1750938089_выравниваниеТрактором.jpg', 'photo', 18, '2025-06-26 11:41:29'),
(73, 41, 'Подсыпка песка.jpg', '../uploads/task_documents/photoproject/1750938095_Подсыпка песка.jpg', 'photo', 18, '2025-06-26 11:41:35'),
(74, 41, 'Жизненный цикл объекта.docx', '../uploads/task_documents/documents/1750938617_Жизненный цикл объекта.docx', 'document', 18, '2025-06-26 11:50:17'),
(75, 42, 'Регламент Процедуры.docx', '../uploads/task_documents/documents/1750938659_Регламент Процедуры.docx', 'document', 18, '2025-06-26 11:50:59'),
(76, 43, 'Выравнивание.jpeg', '../uploads/task_documents/photoproject/1750938916_Выравнивание.jpeg', 'photo', 18, '2025-06-26 11:55:16'),
(77, 43, 'ЦементВпол.jpg', '../uploads/task_documents/photoproject/1750938944_ЦементВпол.jpg', 'photo', 18, '2025-06-26 11:55:44'),
(78, 43, 'четрежШколы.png', '../uploads/task_documents/drawings/1750938952_четрежШколы.png', 'drawing', 18, '2025-06-26 11:55:52'),
(79, 43, 'Операции с объектом.docx', '../uploads/task_documents/documents/1750938961_Операции с объектом.docx', 'document', 18, '2025-06-26 11:56:01'),
(80, 44, 'Отчет по проекту.pdf', '../uploads/task_documents/documents/1750938972_Отчет по проекту.pdf', 'document', 18, '2025-06-26 11:56:12'),
(81, 45, 'УстановкаНовыхСтен.jpg', '../uploads/task_documents/photoproject/1750939147_УстановкаНовыхСтен.jpg', 'photo', 18, '2025-06-26 11:59:07'),
(82, 45, 'Операции с объектом.docx', '../uploads/task_documents/documents/1750939155_Операции с объектом.docx', 'document', 18, '2025-06-26 11:59:15'),
(83, 47, 'Подсыпка песка.jpg', '../uploads/task_documents/photoproject/1750939532_Подсыпка песка.jpg', 'photo', 18, '2025-06-26 12:05:32'),
(84, 47, 'укладкаАсфальта.jpg', '../uploads/task_documents/photoproject/1750939544_укладкаАсфальта.jpg', 'photo', 18, '2025-06-26 12:05:44'),
(85, 48, 'Отчет по проекту.pdf', '../uploads/task_documents/reports/1750939648_Отчет по проекту.pdf', 'report', 18, '2025-06-26 12:07:28'),
(86, 48, 'Должностная инструкция.docx', '../uploads/task_documents/documents/1750939658_Должностная инструкция.docx', 'document', 18, '2025-06-26 12:07:38');

-- --------------------------------------------------------

--
-- Структура таблицы `users`
--

CREATE TABLE `users` (
  `user_id` int NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(100) NOT NULL,
  `full_name` varchar(100) NOT NULL DEFAULT '',
  `role_id` int NOT NULL,
  `position` varchar(100) NOT NULL DEFAULT '',
  `department` varchar(100) NOT NULL DEFAULT '',
  `avatar` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Дамп данных таблицы `users`
--

INSERT INTO `users` (`user_id`, `username`, `password`, `email`, `full_name`, `role_id`, `position`, `department`, `avatar`, `created_at`, `updated_at`) VALUES
(18, 'admin', '$2y$12$Xb5Hu3mDX0k5azbg7wxcr.pH/ghmcYJV.nrhJHinPYwQJWqRXo8VG', 'nosko.adrian2005@gmail.com', 'Адриан Александрович Носко', 1, 'Админ', 'Энергетика', '684d590715e3a_ава.jpg', '2025-03-25 13:43:03', '2025-06-14 16:56:21'),
(20, 'empl1', '$2y$10$q2HNNFrJWrBhKvLC4zb8I.bUsH0L5ZXuEcgaewpG2IG6JKPTfqJoC', 'empl1@mail.ru', 'Яковчик Илья Сергеевич', 3, 'Инженер-Программист', 'Энергетика', '6833bd127841e_portrait-interesting-young-man-winter-clothes_158595-914.avif', '2025-03-25 14:01:32', '2025-05-26 04:00:02'),
(44, 'admin1', '$2y$12$.0g9pdydSoKE32eA9dbnie33vwWMMqbnmynVysQBacApG9pK4JjZG', 'admin1@mail.ru', 'Фомина Милана Романовна', 1, 'Админ', 'Админ', 'default_avatar.jpg', '2025-05-26 03:27:27', '2025-05-26 03:27:27'),
(45, 'empl', '$2y$12$oOJKqDaf8KR2X2R3m7SV9.33b0UI3AYmeRwQbvD6K//icDY1P6hIy', 'empl@mail.ru', 'Иванов Алексей Елисеевич', 3, 'Техник-Программист', 'Энергетики', '6833bcf49efcd_smiley-man-relaxing-outdoors_23-2148739334.avif', '2025-05-26 03:28:13', '2025-05-26 03:59:32'),
(46, 'empl2', '$2y$12$SVjwFV3WARAShn0hyU3m5eO5pvWSrKjRl0tJzXh2E9/AE8x9dwFmy', 'empl2@mail.ru', 'Никифорова Ксения Петровна', 3, 'Техник-Программист', 'Энергетики', '685c63f652f38_iU4RslZhmcj0B4PNNsQbYVf46feRA3kGXHZjoYlai1Pxna-0RRpJ8flfRxVnnKZtEfjtWFRj1oyf0AyvBF9mB8sd.jpg', '2025-05-26 03:29:07', '2025-06-26 00:02:46'),
(47, 'empl3', '$2y$12$Q4ndZCGQe7zdDbxcB232YO8vaRjSUldYyVAnwweiD8XlNG59ewwTW', 'empl3@mail.ru', 'Андреева Екатерина Макаровна', 3, 'Маляр', 'Монтажная группа', '685c643f6aac8_tFGqAIT-A_5sTNAE4enEC5_9t723ZcyehHt6L8n803EQg19D4_Iw_S-IEh3Usbuf9XgxrNrRjYatRYZHvOrKM0kb.jpg', '2025-05-26 03:31:01', '2025-06-26 00:03:59'),
(48, 'empl4', '$2y$12$uUfcJo/CcvK20JrhdWl9Re.hSKNWBUHqLMVxDQLlvW1Dw9bV5VCYu', 'empl4@mail.ru', 'Жданов Ярослав Владимирович', 3, 'Изолировщик на термоизоляции', 'Монтажная группа', 'default_avatar.jpg', '2025-05-26 03:31:42', '2025-05-26 03:31:42'),
(49, 'empl5', '$2y$12$yO8XoymuprA/L5XgbvE1gOa0yN9hV3ToLDGDMiQitZyCjVYFSzvMy', 'empl5@mail.ru', 'Быков Леонид Егорович', 3, 'Штукатур', 'Монтажная группа', '684d61d7323b4_ава.jpg', '2025-05-26 03:32:35', '2025-06-14 14:49:43'),
(50, 'empl6', '$2y$12$.RwnlNjVRjPauZCFmjYWWeCj.obJEWRT6Eny7rIliK/KhwOiCN71K', 'empl6@mail.ru', 'Орлова Полина Максимовна', 3, 'Электромеханик по лифтам', 'Монтажная группа', 'default_avatar.jpg', '2025-05-26 03:32:55', '2025-05-26 03:32:55'),
(51, 'mang', '$2y$12$PrGSQklUGdN9CF2jwKk8nOK4Lflo6x0te2rEp8RsCUveFA.sVpBb6', 'mang@mail.ru', 'Попов Марк Арсентьевич', 2, 'Заместитель начальника конструкторского отдела', 'Проектное управление', '6833bd6745293_5ceeba27584867ef5059f439b1ebb43e.jpg', '2025-05-26 03:34:15', '2025-06-15 14:28:53'),
(52, 'mang1', '$2y$12$.eDthX..few8Uhv9xZbr/e8D4vG38yg1fJ.pbQND9wi3mfeZRfTwO', 'mang1@mail.ru', 'Цветкова Мария Ивановна', 2, 'Заместитель начальника отдела технического контроля', 'Проектное управление', '6833bd7c263af_2af1c4e727bb59f7b8ac632cd343d594.jpg', '2025-05-26 03:34:37', '2025-05-26 04:01:48'),
(54, 'mang2', '$2y$12$LHy3bGK8yoeowaUMf8XyHe6N2LAfs/jP7hBpFK8WiWTcX8893QE1.', 'mang2@mail.ru', 'Тимофеева Алиса Егоровна', 2, 'Заместитель начальника отдела технического контроля', 'Проектное управление', '6833bd96103a4_images.jpg', '2025-05-26 03:35:51', '2025-06-15 14:23:17'),
(55, 'mang3', '$2y$12$pKNvFQHaB6Tys9rm4meZ6eniHXG325hMVfhf9iK0Un5HNgX1rpg1a', 'mang3@mail.ru', 'Сидорова Василиса Дмитриевна', 2, 'Заместитель начальника производственного отдела', 'Проектное управление', 'default_avatar.jpg', '2025-05-26 03:36:46', '2025-05-26 03:36:46'),
(56, 'mang4', '$2y$12$DNx7fqc2rLrji8mngiaZjO8rNQPsBRl54S6VqWGuUpiXlgrEP2Rqe', 'mang4@mail.ru', 'Крылов Матвей Маркович', 2, 'Заместитель начальника формовочного цеха', 'Проектное управление', 'default_avatar.jpg', '2025-05-26 03:37:26', '2025-05-26 03:37:26'),
(57, 'user', '$2y$12$f47xQWuMvVLTT6WwZYhIju4CEEUs49mTzBGs7TdzZokIkTg07HHcu', 'user@mail.ru', 'Соловьева Софья Юрьевна', 4, '', '', '685c64c6d9f88_JhZ-vtMOSNDV3Hfn0jD73MwLHJs6VqFs3acqnqYBmbC8B-aTIs_-ym0iC9snSUEfzGAimiM8V4tve8NEnj55TchP.jpg', '2025-05-26 03:38:09', '2025-06-26 00:06:14'),
(58, 'user1', '$2y$12$DRvqJYtcpNIJGchkhc/dfOzBOhm2IKTy/pjUDtVvGLP0gws2faUcu', 'user1@mail.ru', 'Беляков Арсений Сергеевич', 4, '', '', 'default_avatar.jpg', '2025-05-26 03:38:19', '2025-05-26 03:38:19'),
(60, 'user3', '$2y$12$bpffUqQXrMezK6RmOdaO2OOnyUn/zF87lphM0GfvW6pL35HXoHnvK', 'user3@mail.ru', 'Пономарев Виктор Дмитриевич', 4, '', '', 'default_avatar.jpg', '2025-05-26 03:38:40', '2025-05-26 03:38:40'),
(61, 'user4', '$2y$12$lXn9LmoxiAEyidhbIcSo0.OUmDB0izF3hkRbgudJpsYl.v52f1gJG', 'user4@mail.ru', 'Крылов Ярослав Михайлович', 4, '', '', '685c64fe27376_9uOk98Oz1KDH1GCKDTihYdA2ALzxSuPML7GwfrDGmY1pnqQxsu-R-Fz6o-_DyC7Qe3MZZOP6Uc0JW7UIRMkH_zJ4.jpg', '2025-05-26 03:38:50', '2025-06-26 00:07:10'),
(62, 'user5', '$2y$12$uAgQRQm8k6brbeIm6M9iiuVMn0WRVGtGS9rjTYFx0WTq4my0OavIO', 'user5@mail.ru', 'Гончаров Ярослав Тимурович', 4, '', '', 'default_avatar.jpg', '2025-05-26 03:39:03', '2025-05-26 03:39:03');

--
-- Индексы сохранённых таблиц
--

--
-- Индексы таблицы `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Индексы таблицы `projects`
--
ALTER TABLE `projects`
  ADD PRIMARY KEY (`project_id`),
  ADD UNIQUE KEY `access_code` (`access_code`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_lifecycle_stage` (`lifecycle_stage`);

--
-- Индексы таблицы `project_documents`
--
ALTER TABLE `project_documents`
  ADD PRIMARY KEY (`document_id`),
  ADD KEY `project_id` (`project_id`),
  ADD KEY `uploaded_by` (`uploaded_by`);

--
-- Индексы таблицы `project_participants`
--
ALTER TABLE `project_participants`
  ADD PRIMARY KEY (`participant_id`),
  ADD UNIQUE KEY `unique_user_project` (`user_id`,`project_id`),
  ADD KEY `project_id` (`project_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Индексы таблицы `project_stages`
--
ALTER TABLE `project_stages`
  ADD PRIMARY KEY (`stage_id`),
  ADD KEY `project_id` (`project_id`);

--
-- Индексы таблицы `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`role_id`),
  ADD UNIQUE KEY `role_name` (`role_name`);

--
-- Индексы таблицы `tasks`
--
ALTER TABLE `tasks`
  ADD PRIMARY KEY (`task_id`),
  ADD KEY `project_id` (`project_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Индексы таблицы `task_documents`
--
ALTER TABLE `task_documents`
  ADD PRIMARY KEY (`document_id`),
  ADD KEY `task_id` (`task_id`),
  ADD KEY `uploaded_by` (`uploaded_by`);

--
-- Индексы таблицы `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `username_2` (`username`),
  ADD UNIQUE KEY `email_2` (`email`),
  ADD UNIQUE KEY `full_name` (`full_name`),
  ADD KEY `role_id` (`role_id`);

--
-- AUTO_INCREMENT для сохранённых таблиц
--

--
-- AUTO_INCREMENT для таблицы `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT для таблицы `projects`
--
ALTER TABLE `projects`
  MODIFY `project_id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `project_documents`
--
ALTER TABLE `project_documents`
  MODIFY `document_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=132;

--
-- AUTO_INCREMENT для таблицы `project_participants`
--
ALTER TABLE `project_participants`
  MODIFY `participant_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=216;

--
-- AUTO_INCREMENT для таблицы `project_stages`
--
ALTER TABLE `project_stages`
  MODIFY `stage_id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `roles`
--
ALTER TABLE `roles`
  MODIFY `role_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT для таблицы `tasks`
--
ALTER TABLE `tasks`
  MODIFY `task_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=49;

--
-- AUTO_INCREMENT для таблицы `task_documents`
--
ALTER TABLE `task_documents`
  MODIFY `document_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=87;

--
-- AUTO_INCREMENT для таблицы `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=79;

--
-- Ограничения внешнего ключа сохраненных таблиц
--

--
-- Ограничения внешнего ключа таблицы `password_resets`
--
ALTER TABLE `password_resets`
  ADD CONSTRAINT `password_resets_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);

--
-- Ограничения внешнего ключа таблицы `projects`
--
ALTER TABLE `projects`
  ADD CONSTRAINT `projects_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`);

--
-- Ограничения внешнего ключа таблицы `project_documents`
--
ALTER TABLE `project_documents`
  ADD CONSTRAINT `project_documents_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`project_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `project_documents_ibfk_2` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `project_participants`
--
ALTER TABLE `project_participants`
  ADD CONSTRAINT `project_participants_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`project_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `project_participants_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `project_stages`
--
ALTER TABLE `project_stages`
  ADD CONSTRAINT `project_stages_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`project_id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `tasks`
--
ALTER TABLE `tasks`
  ADD CONSTRAINT `tasks_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`project_id`),
  ADD CONSTRAINT `tasks_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`);

--
-- Ограничения внешнего ключа таблицы `task_documents`
--
ALTER TABLE `task_documents`
  ADD CONSTRAINT `task_documents_ibfk_1` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`task_id`),
  ADD CONSTRAINT `task_documents_ibfk_2` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`user_id`);

--
-- Ограничения внешнего ключа таблицы `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`role_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
