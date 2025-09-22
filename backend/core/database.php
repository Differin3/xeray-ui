<?php
// Xeray UI - Работа с базой данных

require_once 'config.php';

class Database {
    private static $instance = null;
    private $pdo;

    private function __construct() {
        try {
            $this->pdo = new PDO('sqlite:' . DB_PATH);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo->exec('PRAGMA foreign_keys = ON');
        } catch (PDOException $e) {
            logMessage('ERROR', 'Ошибка подключения к базе данных: ' . $e->getMessage());
            die("Ошибка подключения к базе данных");
        }
    }

    // Получение экземпляра класса (Singleton)
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    // Получение PDO объекта
    public function getConnection() {
        return $this->pdo;
    }

    // Выполнение SQL запроса
    public function query($sql, $params = []) {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            logMessage('ERROR', 'Ошибка выполнения запроса: ' . $e->getMessage());
            throw $e;
        }
    }

    // Получение одной записи
    public function fetchOne($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Получение всех записей
    public function fetchAll($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Получение значения
    public function fetchColumn($sql, $params = [], $column = 0) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchColumn($column);
    }

    // Вставка данных
    public function insert($table, $data) {
        $fields = array_keys($data);
        $values = array_fill(0, count($fields), '?');

        $sql = "INSERT INTO $table (" . implode(', ', $fields) . ") 
                VALUES (" . implode(', ', $values) . ")";

        $this->query($sql, array_values($data));

        return $this->pdo->lastInsertId();
    }

    // Обновление данных
    public function update($table, $data, $where, $whereParams = []) {
        $fields = [];
        foreach ($data as $key => $value) {
            $fields[] = "$key = ?";
        }

        $sql = "UPDATE $table SET " . implode(', ', $fields) . " WHERE $where";

        $params = array_merge(array_values($data), $whereParams);

        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }

    // Удаление данных
    public function delete($table, $where, $params = []) {
        $sql = "DELETE FROM $table WHERE $where";

        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }

    // Начало транзакции
    public function beginTransaction() {
        return $this->pdo->beginTransaction();
    }

    // Подтверждение транзакции
    public function commit() {
        return $this->pdo->commit();
    }

    // Откат транзакции
    public function rollBack() {
        return $this->pdo->rollBack();
    }

    // Экранирование имени таблицы или поля
    public function quoteIdentifier($identifier) {
        return '"' . str_replace('"', '""', $identifier) . '"';
    }
}

// Инициализация базы данных (пропускаем в статическом режиме)
$db = Database::getInstance();

// Функция для создания таблиц при первом запуске
function createTables() {
    global $db;

    try {
        // Таблица пользователей
        $db->query("
            CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT NOT NULL UNIQUE,
                email TEXT NOT NULL UNIQUE,
                password_hash TEXT NOT NULL,
                full_name TEXT,
                is_admin BOOLEAN DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                last_login DATETIME,
                is_active BOOLEAN DEFAULT 1
            )
        ");

        // Таблица серверов
        $db->query("
            CREATE TABLE IF NOT EXISTS servers (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                location TEXT NOT NULL,
                ip_address TEXT NOT NULL,
                port INTEGER NOT NULL,
                protocol TEXT NOT NULL,
                config_path TEXT NOT NULL,
                status TEXT DEFAULT 'offline',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");

        // Таблица inbounds
        $db->query("
            CREATE TABLE IF NOT EXISTS inbounds (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                server_id INTEGER NOT NULL,
                name TEXT NOT NULL,
                type TEXT NOT NULL,
                port INTEGER NOT NULL,
                settings TEXT NOT NULL,
                enabled BOOLEAN DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (server_id) REFERENCES servers(id) ON DELETE CASCADE
            )
        ");

        // Таблица outbounds
        $db->query("
            CREATE TABLE IF NOT EXISTS outbounds (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                server_id INTEGER NOT NULL,
                name TEXT NOT NULL,
                type TEXT NOT NULL,
                settings TEXT NOT NULL,
                enabled BOOLEAN DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (server_id) REFERENCES servers(id) ON DELETE CASCADE
            )
        ");

        // Таблица пользователей-серверов (связь многие-ко-многим)
        $db->query("
            CREATE TABLE IF NOT EXISTS user_servers (
                user_id INTEGER NOT NULL,
                server_id INTEGER NOT NULL,
                PRIMARY KEY (user_id, server_id),
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (server_id) REFERENCES servers(id) ON DELETE CASCADE
            )
        ");

        // Таблица трафика
        $db->query("
            CREATE TABLE IF NOT EXISTS traffic (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                server_id INTEGER NOT NULL,
                inbound_id INTEGER NOT NULL,
                bytes_up INTEGER NOT NULL DEFAULT 0,
                bytes_down INTEGER NOT NULL DEFAULT 0,
                start_time DATETIME DEFAULT CURRENT_TIMESTAMP,
                end_time DATETIME,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (server_id) REFERENCES servers(id) ON DELETE CASCADE,
                FOREIGN KEY (inbound_id) REFERENCES inbounds(id) ON DELETE CASCADE
            )
        ");

        // Таблица квот пользователей
        $db->query("
            CREATE TABLE IF NOT EXISTS user_quotas (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL UNIQUE,
                data_limit_gb INTEGER NOT NULL DEFAULT 100,
                reset_strategy TEXT DEFAULT 'no-reset',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )
        ");

        // Таблица логов
        $db->query("
            CREATE TABLE IF NOT EXISTS logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER,
                server_id INTEGER,
                action TEXT NOT NULL,
                details TEXT,
                ip_address TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
                FOREIGN KEY (server_id) REFERENCES servers(id) ON DELETE SET NULL
            )
        ");

        // Таблица настроек
        $db->query("
            CREATE TABLE IF NOT EXISTS settings (
                key TEXT PRIMARY KEY,
                value TEXT,
                description TEXT,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");

        // Создание индексов для ускорения запросов
        $db->query("CREATE INDEX IF NOT EXISTS idx_users_email ON users(email)");
        $db->query("CREATE INDEX IF NOT EXISTS idx_servers_ip ON servers(ip_address)");
        $db->query("CREATE INDEX IF NOT EXISTS idx_servers_status ON servers(status)");
        $db->query("CREATE INDEX IF NOT EXISTS idx_traffic_user ON traffic(user_id)");
        $db->query("CREATE INDEX IF NOT EXISTS idx_traffic_server ON traffic(server_id)");
        $db->query("CREATE INDEX IF NOT EXISTS idx_logs_user ON logs(user_id)");
        $db->query("CREATE INDEX IF NOT EXISTS idx_logs_created ON logs(created_at)");

        // Вставка начальных данных, если таблица пользователей пуста
        if (!$db->fetchOne("SELECT id FROM users LIMIT 1")) {
            // Создание администратора по умолчанию
            $adminPassword = hashPassword('admin123');
            $db->insert('users', [
                'username' => 'admin',
                'email' => 'admin@xeray.local',
                'password_hash' => $adminPassword,
                'full_name' => 'Administrator',
                'is_admin' => 1
            ]);

            // Вставка начальных настроек
            $defaultSettings = [
                ['site_name', 'Xeray UI', 'Название сайта'],
                ['site_description', 'Панель управления для XRay', 'Описание сайта'],
                ['theme', 'default', 'Тема оформления'],
                ['language', 'ru', 'Язык интерфейса'],
                ['timezone', 'Europe/Moscow', 'Часовой пояс'],
                ['max_users_per_server', '100', 'Максимальное количество пользователей на сервер'],
                ['traffic_reset_interval', 'monthly', 'Интервал сброса трафика'],
                ['enable_registration', '0', 'Разрешить регистрацию пользователей'],
                ['default_user_quota', '100', 'Квота пользователя по умолчанию (ГБ)'],
                ['enable_api', '0', 'Включить API'],
                ['api_token', generateApiToken(), 'Токен API']
            ];

            foreach ($defaultSettings as $setting) {
                $db->insert('settings', [
                    'key' => $setting[0],
                    'value' => $setting[1],
                    'description' => $setting[2]
                ]);
            }

            logMessage('INFO', 'База данных инициализирована, создан администратор по умолчанию (admin/admin123)');
        }

        return true;
    } catch (Exception $e) {
        logMessage('ERROR', 'Ошибка создания таблиц: ' . $e->getMessage());
        return false;
    }
}

// Вызов функции создания таблиц
createTables();
