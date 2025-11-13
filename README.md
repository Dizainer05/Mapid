<div align="center">
  <img src="assets/banner.png" width="80" alt="Construction Management System"/>
  
  <h1>🚧 Система управления строительными проектами</h1>
  <h3>Веб-приложение для ОАО «МАПИД» | Дипломный проект</h3>
  
  <p>
    <img src="https://img.shields.io/badge/Status-Released-brightgreen?style=for-the-badge" alt="Status">
    <img src="https://img.shields.io/badge/Version-1.0.0-blue?style=for-the-badge" alt="Version">
  </p>
  
  <p>
    <img src="https://img.shields.io/badge/PHP-777BB4?style=flat-square&logo=php&logoColor=white" alt="PHP">
    <img src="https://img.shields.io/badge/MySQL-4479A1?style=flat-square&logo=mysql&logoColor=white" alt="MySQL">
    <img src="https://img.shields.io/badge/JavaScript-F7DF1E?style=flat-square&logo=javascript&logoColor=black" alt="JavaScript">
    <img src="https://img.shields.io/badge/Bootstrap-7952B3?style=flat-square&logo=bootstrap&logoColor=white" alt="Bootstrap">
    <img src="https://img.shields.io/badge/License-MIT-blue?style=flat-square" alt="License">
  </p>
</div>

---


## 📝 Оглавление
- [📌 О проекте](#-о-проекте)
- [🚀 Быстрый старт](#-быстрый-старт)
- [🛠️ Функционал](#️-функционал)
- [🖥️ Скриншоты](#️-скриншоты)

---

## 📌 О проекте

Веб-приложение для автоматизации управления строительными проектами в ОАО «МАПИД», разработанное в рамках дипломного проекта Колледжа бизнеса и права.

**Основные цели:**
- ✅ Автоматизация управления проектами
- ✅ Оптимизация документооборота
- ✅ Улучшение взаимодействия между отделами
- ✅ Генерация аналитических отчетов

### 👥 Роли пользователей
| Роль | Иконка | Доступ |
|------|--------|--------|
| **Администратор** | 👨‍💼 | Полный доступ к системе |
| **Менеджер проекта** | 👷 | Управление проектами |
| **Сотрудник** | 🛠️ | Работа с задачами |
| **Клиент** | 👤 | Просмотр информации |

---

## 🚀 Быстрый старт

### 📋 Системные требования
| Компонент | Версия |
|-----------|--------|
| PHP | 8.0+ |
| MySQL | 5.7+ |
| Apache/Nginx | 2.4+ |
| Composer | 2.0+ |

### ⚙️ Установка
```bash
# 1. Клонирование репозитория
git clone https://github.com/Dizainer05/construction-management-system.git
cd construction-management-system

# 2. Установка зависимостей
composer install
npm install

# 3. Настройка окружения
cp .env.example .env
php artisan key:generate

# 4. Импорт базы данных
mysql -u [user] -p [database] < database/dump.sql

# 5. Запуск сервера
php artisan serve

```
## 🛠️ Функционал

<div align="center">

| Модуль           | Возможности                                                                 |
|------------------|-----------------------------------------------------------------------------|
| **Проекты**      | Создание, календарное планирование, контроль сроков, назначение ответственных |
| **Документы**    | Загрузка чертежей, версионность, поиск, экспорт в Excel/Word      |                 |
| **Отчетность**   | Автогенерация отчетов (PDF/Excel)         |


</div>


### Скриншоты интерфейса (`## 🖥️ Скриншоты интерфейса`)

<div align="center">
  <h3>Страница Авторизации</h3>
  <img src="assets/auth.png" width="2000" alt="Construction Management System"/>
  <h3>Страница Регистрации</h3>
  <img src="assets/reg.png" width="2000" alt="Construction Management System"/>
  <h3>Главная страница</h3>
  <img src="assets/dashbord.png" width="2000" alt="Construction Management System"/>
  <h3>Страница Проектов</h3>
  <img src="assets/project.png" width="2000" alt="Construction Management System"/>
  <h3>Страница Профиля</h3>
  <img src="assets/avtar.png" width="2000" alt="Construction Management System"/>
  <h3>Страница Админ-панель</h3>
  <img src="assets/admin.png" width="2000" alt="Construction Management System"/>

</div>
