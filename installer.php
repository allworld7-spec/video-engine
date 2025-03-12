<?php
/**
 * Установщик системы видеосайтов
 * Выполняет автоматизированную установку и настройку сайта
 */

// Запрещаем прямой доступ к файлу после установки
if (file_exists('config.php') && file_exists('installed.lock')) {
    die('Система уже установлена. Удалите файл installed.lock для повторной установки.');
}

// Инициализация установщика
session_start();

// Шаги установки
$steps = [
    1 => 'Проверка требований',
    2 => 'Настройка базы данных',
    3 => 'Настройка сайта',
    4 => 'Настройка API',
    5 => 'Настройка CloudFlare',
    6 => 'Генерация стилей',
    7 => 'Завершение'
];

// Текущий шаг
$currentStep = isset($_GET['step']) ? (int)$_GET['step'] : 1;

if ($currentStep < 1 || $currentStep > count($steps)) {
    $currentStep = 1;
}

// Обработка формы
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch ($currentStep) {
        case 2:
            // Настройка базы данных
            $dbHost = $_POST['db_host'] ?? 'localhost';
            $dbName = $_POST['db_name'] ?? '';
            $dbUser = $_POST['db_user'] ?? '';
            $dbPass = $_POST['db_pass'] ?? '';
            $dbPrefix = $_POST['db_prefix'] ?? 'vs_';
            
            if (empty($dbName) || empty($dbUser)) {
                $error = 'Пожалуйста, заполните все поля';
            } else {
                // Проверяем подключение к БД
                try {
                    $dsn = "mysql:host={$dbHost};charset=utf8mb4";
                    $options = [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES => false
                    ];
                    
                    $pdo = new PDO($dsn, $dbUser, $dbPass, $options);
                    
                    // Пробуем создать базу данных, если ее нет
                    $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                    
                    // Сохраняем данные в сессию
                    $_SESSION['install_db'] = [
                        'host' => $dbHost,
                        'name' => $dbName,
                        'user' => $dbUser,
                        'pass' => $dbPass,
                        'prefix' => $dbPrefix
                    ];
                    
                    // Переходим к следующему шагу
                    header('Location: installer.php?step=3');
                    exit;
                } catch (PDOException $e) {
                    $error = 'Ошибка подключения к базе данных: ' . $e->getMessage();
                }
            }
            break;
            
        case 3:
            // Настройка сайта
            $siteName = $_POST['site_name'] ?? '';
            $adminEmail = $_POST['admin_email'] ?? '';
            $adminUsername = $_POST['admin_username'] ?? '';
            $adminPassword = $_POST['admin_password'] ?? '';
            $adminPasswordConfirm = $_POST['admin_password_confirm'] ?? '';
            
            if (empty($siteName) || empty($adminEmail) || empty($adminUsername) || empty($adminPassword)) {
                $error = 'Пожалуйста, заполните все поля';
            } elseif ($adminPassword !== $adminPasswordConfirm) {
                $error = 'Пароли не совпадают';
            } elseif (strlen($adminPassword) < 6) {
                $error = 'Пароль должен содержать не менее 6 символов';
            } elseif (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
                $error = 'Укажите корректный email';
            } else {
                // Сохраняем данные в сессию
                $_SESSION['install_site'] = [
                    'name' => $siteName,
                    'admin_email' => $adminEmail,
                    'admin_username' => $adminUsername,
                    'admin_password' => $adminPassword
                ];
                
                // Переходим к следующему шагу
                header('Location: installer.php?step=4');
                exit;
            }
            break;
            
        case 4:
            // Настройка API
            $chatgptEnabled = isset($_POST['chatgpt_enabled']) && $_POST['chatgpt_enabled'] == 1;
            $chatgptApiKey = $_POST['chatgpt_api_key'] ?? '';
            
            if ($chatgptEnabled && empty($chatgptApiKey)) {
                $error = 'Пожалуйста, укажите API ключ ChatGPT';
            } else {
                // Сохраняем данные в сессию
                $_SESSION['install_api'] = [
                    'chatgpt_enabled' => $chatgptEnabled,
                    'chatgpt_api_key' => $chatgptApiKey
                ];
                
                // Переходим к следующему шагу
                header('Location: installer.php?step=5');
                exit;
            }
            break;
            
        case 5:
            // Настройка CloudFlare
            $cloudflareEnabled = isset($_POST['cloudflare_enabled']) && $_POST['cloudflare_enabled'] == 1;
            $cloudflareEmail = $_POST['cloudflare_email'] ?? '';
            $cloudflareApiKey = $_POST['cloudflare_api_key'] ?? '';
            $cloudflareZoneId = $_POST['cloudflare_zone_id'] ?? '';
            
            if ($cloudflareEnabled && (empty($cloudflareEmail) || empty($cloudflareApiKey))) {
                $error = 'Пожалуйста, заполните все поля CloudFlare';
            } else {
                // Сохраняем данные в сессию
                $_SESSION['install_cloudflare'] = [
                    'enabled' => $cloudflareEnabled,
                    'email' => $cloudflareEmail,
                    'api_key' => $cloudflareApiKey,
                    'zone_id' => $cloudflareZoneId
                ];
                
                // Переходим к следующему шагу
                header('Location: installer.php?step=6');
                exit;
            }
            break;
            
        case 6:
            // Генерация стилей
            $templateVariant = $_POST['template_variant'] ?? 'random';
            
            // Сохраняем данные в сессию
            $_SESSION['install_styles'] = [
                'template_variant' => $templateVariant
            ];
            
            // Переходим к следующему шагу
            header('Location: installer.php?step=7');
            exit;
            break;
            
        case 7:
            // Завершение установки
            $installResult = performInstallation();
            
            if ($installResult['success']) {
                $success = $installResult['message'];
                
                // Очищаем сессию
                session_destroy();
            } else {
                $error = $installResult['message'];
            }
            break;
    }
}

/**
 * Выполнение установки системы
 * @return array Результат установки
 */
function performInstallation() {
    try {
        // Получаем данные из сессии
        $dbConfig = isset($_SESSION['install_db']) ? $_SESSION['install_db'] : null;
        $siteConfig = isset($_SESSION['install_site']) ? $_SESSION['install_site'] : null;
        $apiConfig = isset($_SESSION['install_api']) ? $_SESSION['install_api'] : null;
        $cloudflareConfig = isset($_SESSION['install_cloudflare']) ? $_SESSION['install_cloudflare'] : null;
        $stylesConfig = isset($_SESSION['install_styles']) ? $_SESSION['install_styles'] : null;
        
        if (!$dbConfig || !$siteConfig) {
            return [
                'success' => false,
                'message' => 'Ошибка: отсутствуют данные конфигурации'
            ];
        }
        
        // 1. Подключаемся к базе данных
        $dsn = "mysql:host={$dbConfig['host']};dbname={$dbConfig['name']};charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ];
        
        $pdo = new PDO($dsn, $dbConfig['user'], $dbConfig['pass'], $options);
        
        // 2. Создаем таблицы в БД
        createDatabaseTables($pdo, $dbConfig['prefix']);
        
        // 3. Создаем конфигурационный файл
        createConfigFile($dbConfig, $siteConfig, $apiConfig, $cloudflareConfig);
        
        // 4. Создаем администратора
        createAdminUser($pdo, $dbConfig['prefix'], $siteConfig['admin_username'], $siteConfig['admin_password'], $siteConfig['admin_email']);
        
        // 5. Генерируем стили и ресурсы
        generateStyles($stylesConfig);
        
        // 6. Настройка CloudFlare (если включено)
        if (isset($cloudflareConfig['enabled']) && $cloudflareConfig['enabled']) {
            setupCloudflare($cloudflareConfig);
        }
        
        // 7. Создаем директории и устанавливаем права
        createDirectories();
        
        // 8. Создаем файл блокировки установки
        file_put_contents('installed.lock', date('Y-m-d H:i:s'));
        
        return [
            'success' => true,
            'message' => 'Установка успешно завершена!'
        ];
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Ошибка установки: ' . $e->getMessage()
        ];
    }
}

/**
 * Создание таблиц в базе данных
 * @param PDO $pdo Объект подключения к БД
 * @param string $prefix Префикс таблиц
 */
function createDatabaseTables($pdo, $prefix) {
    // Массив с SQL запросами для создания таблиц
    $tables = [
        // Таблица видео
        "CREATE TABLE IF NOT EXISTS `{$prefix}videos` (
            `id` INT UNSIGNED NOT NULL,
            `title` VARCHAR(255) NOT NULL,
            `slug` VARCHAR(255) NOT NULL,
            `description` TEXT NULL,
            `original_link` VARCHAR(255) NULL,
            `embed_code` TEXT NULL,
            `thumb_url` VARCHAR(255) NULL,
            `thumb_processed` VARCHAR(255) NULL,
            `file_url` VARCHAR(255) NULL,
            `secure_url` VARCHAR(255) NULL,
            `duration` INT UNSIGNED NOT NULL DEFAULT 0,
            `width` INT UNSIGNED NOT NULL DEFAULT 0,
            `height` INT UNSIGNED NOT NULL DEFAULT 0,
            `filesize` BIGINT UNSIGNED NOT NULL DEFAULT 0,
            `rating` FLOAT NOT NULL DEFAULT 0,
            `votes` INT UNSIGNED NOT NULL DEFAULT 0,
            `popularity` INT UNSIGNED NOT NULL DEFAULT 0,
            `post_date` DATETIME NOT NULL,
            `added_date` DATETIME NOT NULL,
            `processed` TINYINT NOT NULL DEFAULT 0,
            PRIMARY KEY (`id`),
            UNIQUE INDEX `slug_UNIQUE` (`slug` ASC),
            FULLTEXT INDEX `title_description` (`title`, `description`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        // Таблица категорий
        "CREATE TABLE IF NOT EXISTS `{$prefix}categories` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `name` VARCHAR(255) NOT NULL,
            `slug` VARCHAR(255) NOT NULL,
            `description` TEXT NULL,
            `meta_title` VARCHAR(255) NULL,
            `meta_description` TEXT NULL,
            `meta_keywords` TEXT NULL,
            `keywords` TEXT NULL,
            `sorting` INT UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY (`id`),
            UNIQUE INDEX `slug_UNIQUE` (`slug` ASC)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        // Таблица связей видео и категорий
        "CREATE TABLE IF NOT EXISTS `{$prefix}video_categories` (
            `video_id` INT UNSIGNED NOT NULL,
            `category_id` INT UNSIGNED NOT NULL,
            PRIMARY KEY (`video_id`, `category_id`),
            INDEX `fk_video_categories_category_id_idx` (`category_id` ASC),
            CONSTRAINT `fk_video_categories_video_id`
                FOREIGN KEY (`video_id`)
                REFERENCES `{$prefix}videos` (`id`)
                ON DELETE CASCADE
                ON UPDATE CASCADE,
            CONSTRAINT `fk_video_categories_category_id`
                FOREIGN KEY (`category_id`)
                REFERENCES `{$prefix}categories` (`id`)
                ON DELETE CASCADE
                ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        // Таблица тегов
        "CREATE TABLE IF NOT EXISTS `{$prefix}tags` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `name` VARCHAR(255) NOT NULL,
            `slug` VARCHAR(255) NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE INDEX `slug_UNIQUE` (`slug` ASC)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        // Таблица связей видео и тегов
        "CREATE TABLE IF NOT EXISTS `{$prefix}video_tags` (
            `video_id` INT UNSIGNED NOT NULL,
            `tag_id` INT UNSIGNED NOT NULL,
            PRIMARY KEY (`video_id`, `tag_id`),
            INDEX `fk_video_tags_tag_id_idx` (`tag_id` ASC),
            CONSTRAINT `fk_video_tags_video_id`
                FOREIGN KEY (`video_id`)
                REFERENCES `{$prefix}videos` (`id`)
                ON DELETE CASCADE
                ON UPDATE CASCADE,
            CONSTRAINT `fk_video_tags_tag_id`
                FOREIGN KEY (`tag_id`)
                REFERENCES `{$prefix}tags` (`id`)
                ON DELETE CASCADE
                ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        // Таблица пользователей
        "CREATE TABLE IF NOT EXISTS `{$prefix}users` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `username` VARCHAR(255) NOT NULL,
            `password` VARCHAR(255) NOT NULL,
            `email` VARCHAR(255) NOT NULL,
            `role` ENUM('admin', 'editor', 'user') NOT NULL DEFAULT 'user',
            `created_at` DATETIME NOT NULL,
            `last_login` DATETIME NULL,
            PRIMARY KEY (`id`),
            UNIQUE INDEX `username_UNIQUE` (`username` ASC),
            UNIQUE INDEX `email_UNIQUE` (`email` ASC)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        // Таблица настроек
        "CREATE TABLE IF NOT EXISTS `{$prefix}settings` (
            `name` VARCHAR(255) NOT NULL,
            `value` TEXT NULL,
            PRIMARY KEY (`name`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        // Таблица сетевых сайтов
        "CREATE TABLE IF NOT EXISTS `{$prefix}network_sites` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `name` VARCHAR(255) NOT NULL,
            `url` VARCHAR(255) NOT NULL,
            `api_key` VARCHAR(255) NULL,
            `description` TEXT NULL,
            `active` TINYINT NOT NULL DEFAULT 1,
            PRIMARY KEY (`id`),
            UNIQUE INDEX `url_UNIQUE` (`url` ASC)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        // Таблица поисковых запросов
        "CREATE TABLE IF NOT EXISTS `{$prefix}search_queries` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `query` VARCHAR(255) NOT NULL,
            `count` INT UNSIGNED NOT NULL DEFAULT 1,
            `last_search` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE INDEX `query_UNIQUE` (`query` ASC)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    ];
    
    // Выполняем запросы
    foreach ($tables as $sql) {
        $pdo->exec($sql);
    }
    
    // Добавляем стандартные категории
    $defaultCategories = [
        ['name' => 'Анальное', 'slug' => 'anal', 'keywords' => 'анал,анальный,анальное,в попу,в задницу'],
        ['name' => 'Блондинки', 'slug' => 'blonde', 'keywords' => 'блондинка,блондинки'],
        ['name' => 'Большие члены', 'slug' => 'big-dick', 'keywords' => 'большой член,огромный член,большой хуй,огромный хуй'],
        ['name' => 'Брюнетки', 'slug' => 'brunette', 'keywords' => 'брюнетка,брюнетки'],
        ['name' => 'Групповое', 'slug' => 'group', 'keywords' => 'группа,групповое,групповуха,толпой'],
        ['name' => 'Жесткое', 'slug' => 'hardcore', 'keywords' => 'жестко,жесткое,жесткий,хардкор,грубо'],
        ['name' => 'Зрелые', 'slug' => 'mature', 'keywords' => 'зрелая,зрелые,милф,милфа,мамочка'],
        ['name' => 'Игрушки', 'slug' => 'toys', 'keywords' => 'игрушки,дилдо,вибратор,самотык'],
        ['name' => 'Лесбиянки', 'slug' => 'lesbian', 'keywords' => 'лесби,лесбиянки,лесбийское,лесбиянка'],
        ['name' => 'Минет', 'slug' => 'blowjob', 'keywords' => 'минет,отсос,сосет,сосут,оральный,орал'],
        ['name' => 'Молодые', 'slug' => 'teen', 'keywords' => 'молодая,молодые,18,19,подростки,юные'],
        ['name' => 'Мастурбация', 'slug' => 'masturbation', 'keywords' => 'мастурбация,мастурбирует,дрочит,дрочка']
    ];
    
    $stmt = $pdo->prepare("INSERT INTO {$prefix}categories (name, slug, description, keywords) VALUES (?, ?, ?, ?)");
    
    foreach ($defaultCategories as $category) {
        $stmt->execute([
            $category['name'],
            $category['slug'],
            "Лучшие видео категории {$category['name']}",
            $category['keywords']
        ]);
    }
    
    // Добавляем настройки по умолчанию
    $defaultSettings = [
        'site_name' => 'VideoSite',
        'site_description' => 'Лучший сайт с видео',
        'videos_per_page' => '24',
        'related_count' => '12',
        'network_enabled' => '0',
        'cross_related' => '0',
        'template' => 'default'
    ];
    
    $stmt = $pdo->prepare("INSERT INTO {$prefix}settings (name, value) VALUES (?, ?)");
    
    foreach ($defaultSettings as $name => $value) {
        $stmt->execute([$name, $value]);
    }
}

/**
 * Создание конфигурационного файла
 * @param array $dbConfig Настройки базы данных
 * @param array $siteConfig Настройки сайта
 * @param array $apiConfig Настройки API
 * @param array $cloudflareConfig Настройки CloudFlare
 */
function createConfigFile($dbConfig, $siteConfig, $apiConfig, $cloudflareConfig) {
    // Генерируем соль для безопасности
    $salt = bin2hex(random_bytes(16));
    $hotlinkSalt = bin2hex(random_bytes(16));
    
    // Преобразуем boolean значения в строки 'true' и 'false'
    $cloudflareEnabledStr = isset($cloudflareConfig['enabled']) && $cloudflareConfig['enabled'] ? 'true' : 'false';
    $chatgptEnabledStr = isset($apiConfig['chatgpt_enabled']) && $apiConfig['chatgpt_enabled'] ? 'true' : 'false';
    
    // Шаблон конфигурационного файла
    $configTemplate = <<<EOT
<?php
/**
 * Конфигурационный файл сайта
 * Создан автоматически установщиком
 */

// Определяем константу для доступа к файлам
define('VIDEOSYSTEM', true);

// Основные настройки сайта
\$config = [
    // Информация о сайте
    'site' => [
        'name' => '{$siteConfig['name']}',
        'domain' => \$_SERVER['HTTP_HOST'] ?? 'localhost',
        'protocol' => (!empty(\$_SERVER['HTTPS']) && \$_SERVER['HTTPS'] !== 'off') ? 'https' : 'http',
        'admin_email' => '{$siteConfig['admin_email']}',
        'timezone' => 'Europe/Moscow',
        'charset' => 'utf-8',
        'language' => 'ru',
        'template' => 'default'
    ],
    
    // Настройки базы данных MySQL
    'db' => [
        'host' => '{$dbConfig['host']}',
        'name' => '{$dbConfig['name']}',
        'user' => '{$dbConfig['user']}',
        'pass' => '{$dbConfig['pass']}',
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'prefix' => '{$dbConfig['prefix']}',
        'port' => 3306
    ],
    
    // Настройки Redis для кэширования
    'redis' => [
        'enabled' => false,
        'host' => '127.0.0.1',
        'port' => 6379,
        'password' => null,
        'database' => 0,
        'prefix' => 'videosite_'
    ],
    
    // Настройки Elasticsearch для полнотекстового поиска
    'elasticsearch' => [
        'enabled' => false,
        'hosts' => ['localhost:9200'],
        'index_prefix' => 'videosite_',
        'settings' => [
            'number_of_shards' => 1,
            'number_of_replicas' => 0
        ]
    ],
    
    // Настройки кэширования
    'cache' => [
        'enabled' => true,
        'method' => 'file',
        'expire' => 3600,
        'clear_on_update' => true,
        'directory' => 'cache'
    ],
    
    // Настройки CloudFlare
    'cloudflare' => [
        'enabled' => $cloudflareEnabledStr,
        'email' => '{$cloudflareConfig['email']}',
        'api_key' => '{$cloudflareConfig['api_key']}',
        'zone_id' => '{$cloudflareConfig['zone_id']}',
        'disable_tls13' => true
    ],
    
    // Настройки API регистратора доменов
    'registrar' => [
        'api_type' => '',
        'api_key' => '',
        'api_secret' => ''
    ],
    
    // Настройки ChatGPT API
    'chatgpt' => [
        'enabled' => $chatgptEnabledStr,
        'api_key' => '{$apiConfig['chatgpt_api_key']}',
        'model' => 'gpt-3.5-turbo',
        'prompts' => [
            'site_description' => 'Напиши уникальное SEO-оптимизированное описание для сайта с видео контентом для взрослых. Две абзаца, не более 300 символов. Название сайта: {{site_name}}',
            'category_description' => 'Напиши уникальное SEO-оптимизированное описание категории видео для взрослых: {{category_name}}. Один абзац, не более 150 символов.',
            'video_title_rewrite' => 'Перепиши название видео для взрослых, сохранив ключевые слова, но сделав его более уникальным: {{video_title}}',
            'video_description' => 'Создай подробное описание для видео для взрослых на основе названия. Добавь больше деталей и ключевых слов. Название: {{video_title}}'
        ]
    ],
    
    // Настройки видеоплеера
    'player' => [
        'type' => 'playerjs',
        'width' => '100%',
        'height' => '400px',
        'autoplay' => false,
        'preload' => 'metadata'
    ],
    
    // Настройки SEO
    'seo' => [
        'title_length' => 60,
        'description_length' => 160,
        'keywords_count' => 10,
        'generate_sitemap' => true,
        'sitemap_frequency' => 'daily',
        'sitemap_priority' => 0.7,
        'generate_robots' => true,
        'noindex_pages' => ['admin.php', 'login.php', 'register.php']
    ],
    
    // Настройки отображения видео
    'videos' => [
        'per_page' => 24,
        'related_count' => 12,
        'thumb_width' => 320,
        'thumb_height' => 180,
        'default_sorting' => 'date',
        'default_order' => 'desc',
        'use_hotlink' => true,
        'hotlink_expire' => 3600,
        'hotlink_token_salt' => '{$hotlinkSalt}'
    ],
    
    // Настройки сети
    'network' => [
        'enabled' => false,
        'sites' => [],
        'cross_related' => true,
        'cross_related_ratio' => 30,
        'cross_footer_links' => true,
        'max_footer_links' => 10
    ],
    
    // Пути к директориям
    'paths' => [
        'root' => dirname(__FILE__),
        'cache' => dirname(__FILE__) . '/cache',
        'logs' => dirname(__FILE__) . '/logs',
        'thumbs' => dirname(__FILE__) . '/thumbs',
        'templates' => dirname(__FILE__) . '/templates',
        'uploads' => dirname(__FILE__) . '/uploads'
    ],
    
    // Настройки безопасности
    'security' => [
        'admin_cookie_name' => 'videosite_admin',
        'admin_cookie_expire' => 86400,
        'admin_session_name' => 'videosite_admin_session',
        'salt' => '{$salt}',
        'allowed_ip' => []
    ],
    
    // Настройки журналирования
    'logging' => [
        'enabled' => true,
        'level' => 'error',
        'rotate' => true,
        'max_files' => 7
    ]
];

// Определение базового URL
\$config['site']['url'] = \$config['site']['protocol'] . '://' . \$config['site']['domain'];

// Устанавливаем часовой пояс
date_default_timezone_set(\$config['site']['timezone']);

// Функции для работы с конфигом
function getConfig(\$path = null, \$default = null) {
    global \$config;
    
    if (\$path === null) {
        return \$config;
    }
    
    \$keys = explode('.', \$path);
    \$value = \$config;
    
    foreach (\$keys as \$key) {
        if (!isset(\$value[\$key])) {
            return \$default;
        }
        \$value = \$value[\$key];
    }
    
    return \$value;
}

function setConfig(\$path, \$value) {
    global \$config;
    
    \$keys = explode('.', \$path);
    \$lastKey = array_pop(\$keys);
    \$current = &\$config;
    
    foreach (\$keys as \$key) {
        if (!isset(\$current[\$key]) || !is_array(\$current[\$key])) {
            \$current[\$key] = [];
        }
        \$current = &\$current[\$key];
    }
    
    \$current[\$lastKey] = \$value;
    return true;
}

// Подключаем основные файлы
require_once 'functions.php';
EOT;

    // Сохраняем конфигурационный файл
    file_put_contents('config.php', $configTemplate);
}

/**
 * Создание администратора
 * @param PDO $pdo Объект подключения к БД
 * @param string $prefix Префикс таблиц
 * @param string $username Имя пользователя
 * @param string $password Пароль
 * @param string $email Email
 */
function createAdminUser($pdo, $prefix, $username, $password, $email) {
    // Хешируем пароль
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    
    // Добавляем пользователя
    $sql = "INSERT INTO {$prefix}users (username, password, email, role, created_at) VALUES (?, ?, ?, 'admin', NOW())";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$username, $passwordHash, $email]);
}

/**
 * Генерация стилей и ресурсов
 * @param array $config Настройки стилей
 */
function generateStyles($config) {
    // Создаем директории для шаблонов
    $templatesDir = 'templates/default';
    
    if (!is_dir($templatesDir)) {
        mkdir($templatesDir, 0755, true);
    }
    
    // Генерируем цветовую схему
    $colors = generate_color_scheme();
    
    // Генерируем CSS
    $css = generate_custom_css($colors);
    
    // Сохраняем CSS
    file_put_contents($templatesDir . '/style.css', $css);
    
    // Генерируем favicon
    if (isset($_SESSION['install_site']) && isset($_SESSION['install_site']['name'])) {
        generate_favicon($_SESSION['install_site']['name']);
    
        // Генерируем логотип
        generate_logo($_SESSION['install_site']['name']);
    }
}

/**
 * Настройка CloudFlare
 * @param array $config Настройки CloudFlare
 */
function setupCloudflare($config) {
    if (!isset($config['enabled']) || !$config['enabled'] || empty($config['email']) || empty($config['api_key'])) {
        return;
    }
    
    // Здесь можно добавить код для работы с API CloudFlare
    // Например, добавление сайта, отключение TLS 1.3, получение NS-записей
}

/**
 * Создание необходимых директорий
 */
function createDirectories() {
    $directories = [
        'cache',
        'logs',
        'thumbs',
        'uploads',
        'templates/default'
    ];
    
    foreach ($directories as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }
}

/**
 * Генерация цветовой схемы
 * @return array Массив с цветами
 */
function generate_color_scheme() {
    // Генерируем основной цвет (HSL)
    $hue = mt_rand(0, 360);
    $saturation = mt_rand(30, 70);
    $lightness = mt_rand(30, 60);
    
    // Создаем дополнительные цвета
    $colors = [
        'primary' => "hsl({$hue}, {$saturation}%, {$lightness}%)",
        'secondary' => "hsl(" . ($hue + 30) % 360 . ", {$saturation}%, " . ($lightness - 10) . "%)",
        'accent' => "hsl(" . ($hue + 180) % 360 . ", {$saturation}%, {$lightness}%)",
        'background' => "hsl({$hue}, " . ($saturation / 4) . "%, " . ($lightness + 30) . "%)",
        'text' => "hsl({$hue}, 10%, " . ($lightness - 30) . "%)",
        'link' => "hsl(" . ($hue + 200) % 360 . ", {$saturation}%, {$lightness}%)"
    ];
    
    return $colors;
}

/**
 * Генерация уникального CSS
 * @param array $colors Цветовая схема
 * @return string CSS код
 */
function generate_custom_css($colors) {
    // Выбор случайного варианта шаблона
    $templateVariant = mt_rand(1, 3);
    
    $css = "/* Автоматически сгенерированный CSS для шаблона вариант {$templateVariant} */\n\n";
    
    // Общие стили
    $css .= ":root {\n";
    foreach ($colors as $name => $color) {
        $css .= "  --color-{$name}: {$color};\n";
    }
    $css .= "}\n\n";
    
    $css .= "body {\n";
    $css .= "  background-color: var(--color-background);\n";
    $css .= "  color: var(--color-text);\n";
    $css .= "  font-family: " . ['Arial, sans-serif', 'Roboto, sans-serif', 'Helvetica, sans-serif'][mt_rand(0, 2)] . ";\n";
    $css .= "  line-height: " . (mt_rand(140, 170) / 100) . ";\n";
    $css .= "  margin: 0;\n";
    $css .= "  padding: 0;\n";
    $css .= "}\n\n";
    
    // Стили для ссылок
    $css .= "a {\n";
    $css .= "  color: var(--color-link);\n";
    $css .= "  text-decoration: " . ['none', 'underline'][mt_rand(0, 1)] . ";\n";
    $css .= "}\n\n";
    
    $css .= "a:hover {\n";
    $css .= "  color: var(--color-primary);\n";
    $css .= "  text-decoration: underline;\n";
    $css .= "}\n\n";
    
    // Заголовки
    $css .= "h1, h2, h3, h4, h5, h6 {\n";
    $css .= "  color: var(--color-primary);\n";
    $css .= "  font-weight: " . ['bold', 'normal'][mt_rand(0, 1)] . ";\n";
    $css .= "  margin-bottom: " . mt_rand(10, 20) . "px;\n";
    $css .= "}\n\n";
    
    // Упрощенная версия стилей для установщика
    $css .= ".container {\n";
    $css .= "  max-width: 1200px;\n";
    $css .= "  margin: 0 auto;\n";
    $css .= "  padding: 0 15px;\n";
    $css .= "}\n\n";
    
    $css .= ".video-grid {\n";
    $css .= "  display: grid;\n";
    $css .= "  grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));\n";
    $css .= "  gap: 20px;\n";
    $css .= "  margin: 20px 0;\n";
    $css .= "}\n\n";
    
    return $css;
}

/**
 * Генерация favicon
 * @param string $siteName Название сайта
 * @return string Путь к favicon
 */
function generate_favicon($siteName) {
    $faviconDir = 'favicon';
    
    if (!is_dir($faviconDir)) {
        mkdir($faviconDir, 0755, true);
    }
    
    // Получаем первую букву названия сайта
    if (function_exists('mb_substr')) {
        $letter = mb_substr($siteName, 0, 1);
        $letter = mb_strtoupper($letter);
    } else {
        $letter = substr($siteName, 0, 1);
        $letter = strtoupper($letter);
    }
    
    // Создаем изображение
    $size = 32;
    $image = imagecreatetruecolor($size, $size);
    
    // Генерируем цвета
    $bgColor = imagecolorallocate($image, mt_rand(50, 200), mt_rand(50, 200), mt_rand(50, 200));
    $textColor = imagecolorallocate($image, 255, 255, 255);
    
    // Заполняем фон
    imagefilledrectangle($image, 0, 0, $size, $size, $bgColor);
    
    // Добавляем букву
    $fontSize = 5;
    
    // Используем встроенный шрифт
    imagestring($image, $fontSize, 11, 8, $letter, $textColor);
    
    // Сохраняем favicon
    $faviconPath = $faviconDir . '/favicon.ico';
    imagepng($image, $faviconPath);
    imagedestroy($image);
    
    return $faviconPath;
}

/**
 * Генерация логотипа
 * @param string $siteName Название сайта
 * @return string Путь к логотипу
 */
function generate_logo($siteName) {
    $logoDir = 'uploads';
    
    if (!is_dir($logoDir)) {
        mkdir($logoDir, 0755, true);
    }
    
    // Создаем изображение
    $width = 200;
    $height = 50;
    $image = imagecreatetruecolor($width, $height);
    
    // Генерируем цвета
    $bgColor = imagecolorallocate($image, mt_rand(0, 50), mt_rand(0, 50), mt_rand(0, 50));
    $textColor = imagecolorallocate($image, 255, 255, 255);
    
    // Заполняем фон
    imagefilledrectangle($image, 0, 0, $width, $height, $bgColor);
    
    // Добавляем название сайта
    $fontSize = 5;
    
    // Используем встроенный шрифт
    imagestring($image, $fontSize, 10, 15, $siteName, $textColor);
    
    // Сохраняем логотип
    $logoPath = $logoDir . '/logo.png';
    imagepng($image, $logoPath);
    imagedestroy($image);
    
    return $logoPath;
}

/**
 * Проверка системных требований
 * @return array Результаты проверки
 */
function checkRequirements() {
    $requirements = [
        'php_version' => [
            'name' => 'Версия PHP',
            'required' => '7.4.0',
            'current' => PHP_VERSION,
            'status' => version_compare(PHP_VERSION, '7.4.0', '>=')
        ],
        'pdo_mysql' => [
            'name' => 'PDO MySQL',
            'required' => 'Включено',
            'current' => extension_loaded('pdo_mysql') ? 'Включено' : 'Отключено',
            'status' => extension_loaded('pdo_mysql')
        ],
        'gd' => [
            'name' => 'GD',
            'required' => 'Включено',
            'current' => extension_loaded('gd') ? 'Включено' : 'Отключено',
            'status' => extension_loaded('gd')
        ],
        'xml' => [
            'name' => 'XML',
            'required' => 'Включено',
            'current' => extension_loaded('xml') ? 'Включено' : 'Отключено',
            'status' => extension_loaded('xml')
        ],
        'json' => [
            'name' => 'JSON',
            'required' => 'Включено',
            'current' => extension_loaded('json') ? 'Включено' : 'Отключено',
            'status' => extension_loaded('json')
        ],
        'writableDirs' => [
            'name' => 'Права на запись',
            'required' => 'Доступно',
            'current' => is_writable('.') ? 'Доступно' : 'Недоступно',
            'status' => is_writable('.')
        ]
    ];
    
    $allMet = true;
    
    foreach ($requirements as $requirement) {
        if (!$requirement['status']) {
            $allMet = false;
            break;
        }
    }
    
    return [
        'requirements' => $requirements,
        'all_met' => $allMet
    ];
}

// Проверяем требования на первом шаге
if ($currentStep === 1) {
    $requirementsCheck = checkRequirements();
}

?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Установка видеодвижка</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        h1 {
            color: #444;
            border-bottom: 1px solid #ddd;
            padding-bottom: 10px;
        }
        .step {
            display: flex;
            margin-bottom: 20px;
        }
        .step-number {
            width: 30px;
            height: 30px;
            background-color: #f0f0f0;
            color: #666;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 10px;
        }
        .step.active .step-number {
            background-color: #4CAF50;
            color: white;
        }
        .step.completed .step-number {
            background-color: #2196F3;
            color: white;
        }
        .step-title {
            line-height: 30px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        input[type="text"],
        input[type="email"],
        input[type="password"] {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        button {
            background-color: #4CAF50;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 4px;
            cursor: pointer;
        }
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        table th, table td {
            padding: 8px;
            border: 1px solid #ddd;
            text-align: left;
        }
        table th {
            background-color: #f5f5f5;
        }
        .status-ok {
            color: #4CAF50;
        }
        .status-error {
            color: #f44336;
        }
    </style>
</head>
<body>
    <h1>Установка видеодвижка</h1>
    
    <div class="steps">
        <?php foreach ($steps as $stepNum => $stepTitle): ?>
            <div class="step <?php echo $stepNum == $currentStep ? 'active' : ($stepNum < $currentStep ? 'completed' : ''); ?>">
                <div class="step-number"><?php echo $stepNum; ?></div>
                <div class="step-title"><?php echo $stepTitle; ?></div>
            </div>
        <?php endforeach; ?>
    </div>
    
    <?php if (isset($error)): ?>
        <div class="alert alert-error">
            <?php echo $error; ?>
        </div>
    <?php endif; ?>
    
    <?php if (isset($success)): ?>
        <div class="alert alert-success">
            <?php echo $success; ?>
        </div>
    <?php endif; ?>
    
    <?php if ($currentStep === 1): ?>
        <h2>Проверка требований</h2>
        
        <table>
            <thead>
                <tr>
                    <th>Требование</th>
                    <th>Необходимо</th>
                    <th>Текущее значение</th>
                    <th>Статус</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($requirementsCheck['requirements'] as $requirement): ?>
                    <tr>
                        <td><?php echo $requirement['name']; ?></td>
                        <td><?php echo $requirement['required']; ?></td>
                        <td><?php echo $requirement['current']; ?></td>
                        <td class="<?php echo $requirement['status'] ? 'status-ok' : 'status-error'; ?>">
                            <?php echo $requirement['status'] ? 'OK' : 'Ошибка'; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <?php if ($requirementsCheck['all_met']): ?>
            <form method="get" action="installer.php">
                <input type="hidden" name="step" value="2">
                <button type="submit">Продолжить</button>
            </form>
        <?php else: ?>
            <div class="alert alert-error">
                Пожалуйста, исправьте ошибки перед продолжением установки.
            </div>
        <?php endif; ?>
    <?php elseif ($currentStep === 2): ?>
        <h2>Настройка базы данных</h2>
        
        <form method="post" action="installer.php?step=2">
            <div class="form-group">
                <label for="db_host">Хост базы данных</label>
                <input type="text" id="db_host" name="db_host" value="localhost" required>
            </div>
            
            <div class="form-group">
                <label for="db_name">Имя базы данных</label>
                <input type="text" id="db_name" name="db_name" value="<?php echo isset($_SESSION['install_db']) && isset($_SESSION['install_db']['name']) ? $_SESSION['install_db']['name'] : 'videosite'; ?>" required>
            </div>
            
            <div class="form-group">
                <label for="db_user">Пользователь базы данных</label>
                <input type="text" id="db_user" name="db_user" value="<?php echo isset($_SESSION['install_db']) && isset($_SESSION['install_db']['user']) ? $_SESSION['install_db']['user'] : 'root'; ?>" required>
            </div>
            
            <div class="form-group">
                <label for="db_pass">Пароль базы данных</label>
                <input type="password" id="db_pass" name="db_pass" value="<?php echo isset($_SESSION['install_db']) && isset($_SESSION['install_db']['pass']) ? $_SESSION['install_db']['pass'] : ''; ?>">
            </div>
            
            <div class="form-group">
                <label for="db_prefix">Префикс таблиц</label>
                <input type="text" id="db_prefix" name="db_prefix" value="<?php echo isset($_SESSION['install_db']) && isset($_SESSION['install_db']['prefix']) ? $_SESSION['install_db']['prefix'] : 'vs_'; ?>" required>
            </div>
            
            <button type="submit">Продолжить</button>
        </form>
    <?php elseif ($currentStep === 3): ?>
        <h2>Настройка сайта</h2>
        
        <form method="post" action="installer.php?step=3">
            <div class="form-group">
                <label for="site_name">Название сайта</label>
                <input type="text" id="site_name" name="site_name" value="<?php echo isset($_SESSION['install_site']) && isset($_SESSION['install_site']['name']) ? $_SESSION['install_site']['name'] : 'VideoSite'; ?>" required>
            </div>
            
            <div class="form-group">
                <label for="admin_email">Email администратора</label>
                <input type="email" id="admin_email" name="admin_email" value="<?php echo isset($_SESSION['install_site']) && isset($_SESSION['install_site']['admin_email']) ? $_SESSION['install_site']['admin_email'] : ''; ?>" required>
            </div>
            
            <div class="form-group">
                <label for="admin_username">Имя пользователя администратора</label>
                <input type="text" id="admin_username" name="admin_username" value="<?php echo isset($_SESSION['install_site']) && isset($_SESSION['install_site']['admin_username']) ? $_SESSION['install_site']['admin_username'] : 'admin'; ?>" required>
            </div>
            
            <div class="form-group">
                <label for="admin_password">Пароль администратора</label>
                <input type="password" id="admin_password" name="admin_password" required>
            </div>
            
            <div class="form-group">
                <label for="admin_password_confirm">Подтверждение пароля</label>
                <input type="password" id="admin_password_confirm" name="admin_password_confirm" required>
            </div>
            
            <button type="submit">Продолжить</button>
        </form>
    <?php elseif ($currentStep === 4): ?>
        <h2>Настройка API</h2>
        
        <form method="post" action="installer.php?step=4">
            <div class="form-group">
                <label>
                    <input type="checkbox" name="chatgpt_enabled" value="1" <?php echo (isset($_SESSION['install_api']) && isset($_SESSION['install_api']['chatgpt_enabled']) && $_SESSION['install_api']['chatgpt_enabled']) ? 'checked' : ''; ?>>
                    Включить интеграцию с ChatGPT
                </label>
            </div>
            
            <div class="form-group">
                <label for="chatgpt_api_key">API ключ ChatGPT</label>
                <input type="text" id="chatgpt_api_key" name="chatgpt_api_key" value="<?php echo isset($_SESSION['install_api']) && isset($_SESSION['install_api']['chatgpt_api_key']) ? $_SESSION['install_api']['chatgpt_api_key'] : ''; ?>">
                <p><small>Необходим для рерайта заголовков и генерации описаний</small></p>
            </div>
            
            <button type="submit">Продолжить</button>
        </form>
    <?php elseif ($currentStep === 5): ?>
        <h2>Настройка CloudFlare</h2>
        
        <form method="post" action="installer.php?step=5">
            <div class="form-group">
                <label>
                    <input type="checkbox" name="cloudflare_enabled" value="1" <?php echo (isset($_SESSION['install_cloudflare']) && isset($_SESSION['install_cloudflare']['enabled']) && $_SESSION['install_cloudflare']['enabled']) ? 'checked' : ''; ?>>
                    Включить интеграцию с CloudFlare
                </label>
            </div>
            
            <div class="form-group">
                <label for="cloudflare_email">Email CloudFlare</label>
                <input type="email" id="cloudflare_email" name="cloudflare_email" value="<?php echo isset($_SESSION['install_cloudflare']) && isset($_SESSION['install_cloudflare']['email']) ? $_SESSION['install_cloudflare']['email'] : ''; ?>">
            </div>
            
            <div class="form-group">
                <label for="cloudflare_api_key">API ключ CloudFlare</label>
                <input type="text" id="cloudflare_api_key" name="cloudflare_api_key" value="<?php echo isset($_SESSION['install_cloudflare']) && isset($_SESSION['install_cloudflare']['api_key']) ? $_SESSION['install_cloudflare']['api_key'] : ''; ?>">
            </div>
            
            <div class="form-group">
                <label for="cloudflare_zone_id">Zone ID CloudFlare</label>
                <input type="text" id="cloudflare_zone_id" name="cloudflare_zone_id" value="<?php echo isset($_SESSION['install_cloudflare']) && isset($_SESSION['install_cloudflare']['zone_id']) ? $_SESSION['install_cloudflare']['zone_id'] : ''; ?>">
                <p><small>Необязательно, можно добавить позже в админ-панели</small></p>
            </div>
            
            <button type="submit">Продолжить</button>
        </form>
    <?php elseif ($currentStep === 6): ?>
        <h2>Генерация стилей</h2>
        
        <form method="post" action="installer.php?step=6">
            <div class="form-group">
                <label for="template_variant">Вариант шаблона</label>
                <select id="template_variant" name="template_variant">
                    <option value="random" <?php echo (isset($_SESSION['install_styles']) && isset($_SESSION['install_styles']['template_variant']) && $_SESSION['install_styles']['template_variant'] === 'random') ? 'selected' : ''; ?>>Случайный</option>
                    <option value="dark" <?php echo (isset($_SESSION['install_styles']) && isset($_SESSION['install_styles']['template_variant']) && $_SESSION['install_styles']['template_variant'] === 'dark') ? 'selected' : ''; ?>>Темный</option>
                    <option value="light" <?php echo (isset($_SESSION['install_styles']) && isset($_SESSION['install_styles']['template_variant']) && $_SESSION['install_styles']['template_variant'] === 'light') ? 'selected' : ''; ?>>Светлый</option>
                    <option value="colorful" <?php echo (isset($_SESSION['install_styles']) && isset($_SESSION['install_styles']['template_variant']) && $_SESSION['install_styles']['template_variant'] === 'colorful') ? 'selected' : ''; ?>>Яркий</option>
                </select>
                <p><small>Стиль сайта будет сгенерирован автоматически</small></p>
            </div>
            
            <button type="submit">Продолжить</button>
        </form>
    <?php elseif ($currentStep === 7): ?>
        <h2>Завершение установки</h2>
        
        <?php if (isset($success)): ?>
            <div class="alert alert-success">
                <?php echo $success; ?>
            </div>
            
            <p>Установка успешно завершена! Теперь вы можете:</p>
            
            <ul>
                <li><a href="index.php">Перейти на главную страницу сайта</a></li>
                <li><a href="admin.php">Войти в панель администратора</a></li>
                </ul>
            
            <p><strong>Важно:</strong> Для безопасности рекомендуется удалить файл установщика (installer.php) с сервера.</p>
        <?php else: ?>
            <form method="post" action="installer.php?step=7">
                <p>Нажмите кнопку ниже, чтобы завершить установку:</p>
                <button type="submit">Установить</button>
            </form>
        <?php endif; ?>
    <?php endif; ?>
</body>
</html>