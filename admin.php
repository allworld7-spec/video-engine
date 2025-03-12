<?php
/**
 * Панель администратора
 * Управление сайтом, видео, категориями и настройками
 */

// Проверяем установку
if (!file_exists('installed.lock')) {
    // Перенаправляем на установщик
    header('Location: installer.php');
    exit;
}

// Определяем константу для доступа к файлам
define('VIDEOSYSTEM', true);

// Подключаем конфигурационный файл
require_once 'config.php';

// Запускаем сессию
session_start();

// Инициализируем видеодвижок
require_once 'engine.php';
$videoEngine = new VideoEngine();

// Проверяем авторизацию
$isLoggedIn = false;

if (isset($_SESSION[getConfig('security.admin_session_name')])) {
    $userId = $_SESSION[getConfig('security.admin_session_name')];
    
    // Проверяем пользователя
    $user = db_get_row(
        "SELECT * FROM " . getConfig('db.prefix') . "users WHERE id = ? AND role = 'admin'",
        [$userId]
    );
    
    if ($user) {
        $isLoggedIn = true;
    } else {
        // Сессия недействительна, удаляем ее
        unset($_SESSION[getConfig('security.admin_session_name')]);
    }
} elseif (isset($_COOKIE[getConfig('security.admin_cookie_name')])) {
    // Проверяем cookie
    list($userId, $token) = explode(':', $_COOKIE[getConfig('security.admin_cookie_name')]);
    
    $user = db_get_row(
        "SELECT * FROM " . getConfig('db.prefix') . "users WHERE id = ? AND role = 'admin'",
        [$userId]
    );
    
    if ($user) {
        $validToken = md5($user['id'] . $user['password'] . getConfig('security.salt'));
        
        if ($token === $validToken) {
            $isLoggedIn = true;
            $_SESSION[getConfig('security.admin_session_name')] = $user['id'];
        } else {
            // Cookie недействителен, удаляем его
            setcookie(getConfig('security.admin_cookie_name'), '', time() - 3600, '/');
        }
    } else {
        // Cookie недействителен, удаляем его
        setcookie(getConfig('security.admin_cookie_name'), '', time() - 3600, '/');
    }
}

// Обработка входа
if (!$isLoggedIn && isset($_POST['login'])) {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $rememberMe = isset($_POST['remember_me']);
    
    if (!empty($username) && !empty($password)) {
        $user = db_get_row(
            "SELECT * FROM " . getConfig('db.prefix') . "users WHERE username = ? AND role = 'admin'",
            [$username]
        );
        
        if ($user && password_verify($password, $user['password'])) {
            // Успешный вход
            $_SESSION[getConfig('security.admin_session_name')] = $user['id'];
            
            // Обновляем дату последнего входа
            db_update('users', ['last_login' => date('Y-m-d H:i:s')], ['id' => $user['id']]);
            
            // Если выбрано "запомнить меня", устанавливаем cookie
            if ($rememberMe) {
                $token = md5($user['id'] . $user['password'] . getConfig('security.salt'));
                setcookie(
                    getConfig('security.admin_cookie_name'),
                    $user['id'] . ':' . $token,
                    time() + getConfig('security.admin_cookie_expire'),
                    '/'
                );
            }
            
            $isLoggedIn = true;
            
            // Перенаправляем на страницу администратора
            header('Location: admin.php');
            exit;
        } else {
            $error = 'Неверное имя пользователя или пароль';
        }
    } else {
        $error = 'Пожалуйста, заполните все поля';
    }
}

// Обработка выхода
if ($isLoggedIn && isset($_GET['logout'])) {
    // Удаляем сессию
    unset($_SESSION[getConfig('security.admin_session_name')]);
    
    // Удаляем cookie
    setcookie(getConfig('security.admin_cookie_name'), '', time() - 3600, '/');
    
    // Перенаправляем на страницу входа
    header('Location: admin.php');
    exit;
}

// Если не авторизованы, показываем форму входа
if (!$isLoggedIn) {
    ?>
    <!DOCTYPE html>
    <html lang="ru">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Вход в панель администратора</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                background-color: #f5f5f5;
                margin: 0;
                padding: 0;
                display: flex;
                justify-content: center;
                align-items: center;
                height: 100vh;
            }
            .login-container {
                background-color: #fff;
                padding: 30px;
                border-radius: 5px;
                box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
                width: 350px;
            }
            h1 {
                margin-top: 0;
                color: #333;
                text-align: center;
            }
            .form-group {
                margin-bottom: 15px;
            }
            label {
                display: block;
                margin-bottom: 5px;
                color: #666;
                font-weight: bold;
            }
            input[type="text"],
            input[type="password"] {
                width: 100%;
                padding: 10px;
                border: 1px solid #ddd;
                border-radius: 3px;
                font-size: 14px;
                box-sizing: border-box;
            }
            .remember-me {
                display: flex;
                align-items: center;
                margin-bottom: 15px;
            }
            .remember-me input {
                margin-right: 10px;
            }
            .btn {
                background-color: #4CAF50;
                color: white;
                border: none;
                padding: 10px 15px;
                border-radius: 3px;
                cursor: pointer;
                font-size: 16px;
                width: 100%;
            }
            .btn:hover {
                background-color: #45a049;
            }
            .error {
                color: #f44336;
                margin-bottom: 15px;
                text-align: center;
            }
            .back-link {
                text-align: center;
                margin-top: 15px;
            }
            .back-link a {
                color: #666;
                text-decoration: none;
            }
            .back-link a:hover {
                text-decoration: underline;
            }
        </style>
    </head>
    
    <body>
        <div class="login-container">
            <h1>Вход в админ-панель</h1>
            
            <?php if (isset($error)): ?>
                <div class="error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <form method="post" action="admin.php">
                <div class="form-group">
                    <label for="username">Имя пользователя</label>
                    <input type="text" id="username" name="username" required>
                </div>
                
                <div class="form-group">
                    <label for="password">Пароль</label>
                    <input type="password" id="password" name="password" required>
                </div>
                
                <div class="remember-me">
                    <input type="checkbox" id="remember_me" name="remember_me">
                    <label for="remember_me">Запомнить меня</label>
                </div>
                
                <button type="submit" name="login" class="btn">Войти</button>
            </form>
            
            <div class="back-link">
                <a href="index.php">Вернуться на сайт</a>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Определяем действие
$action = isset($_GET['action']) ? $_GET['action'] : 'dashboard';

// Функция для отображения шаблона
function renderAdminTemplate($template, $data = []) {
    $templateFile = 'admin/' . $template . '.php';
    
    if (!file_exists($templateFile)) {
        // Создаем директорию для шаблонов админки, если её нет
        if (!is_dir('admin')) {
            mkdir('admin', 0755);
        }
        
        // Если шаблон не найден, создаем базовый
        $defaultContent = "<?php if (!defined('VIDEOSYSTEM')) die('Прямой доступ запрещен!'); ?>\n";
        $defaultContent .= "<h2>{$template}</h2>\n";
        $defaultContent .= "<p>Шаблон в разработке.</p>";
        
        file_put_contents($templateFile, $defaultContent);
    }
    
    // Извлекаем переменные из массива
    extract($data);
    
    // Начинаем буферизацию вывода
    ob_start();
    
    // Подключаем шаблон
    include $templateFile;
    
    // Получаем содержимое буфера и очищаем его
    $content = ob_get_clean();
    
    return $content;
}

// Функция для отображения страницы администратора
function renderAdminPage($template, $data = []) {
    // Добавляем общие данные
    $data['site_name'] = getConfig('site.name');
    $data['site_url'] = getConfig('site.url');
    
    // Получаем содержимое страницы
    $content = renderAdminTemplate($template, $data);
    
    // Отображаем макет с содержимым
    echo renderAdminTemplate('layout', array_merge($data, ['content' => $content]));
    exit;
}

// Обработка запросов API
if ($action === 'api') {
    header('Content-Type: application/json; charset=utf-8');
    
    $apiAction = isset($_GET['method']) ? $_GET['method'] : '';
    $response = ['success' => false, 'error' => 'Unknown method'];
    
    switch ($apiAction) {
        case 'import_xml':
            $url = $_POST['url'] ?? '';
            
            if (empty($url)) {
                $response = ['success' => false, 'error' => 'URL is required'];
                break;
            }
            
            $result = $videoEngine->importFromXml($url);
            
            $response = [
                'success' => true,
                'message' => "Импортировано {$result['imported']} из {$result['total']} видео. Пропущено: {$result['skipped']}. Ошибок: {$result['errors']}.",
                'data' => $result
            ];
            break;
            
        
            
        case 'process_videos':
            $limit = $_POST['limit'] ?? 10;
            $result = $videoEngine->processVideosWithChatGPT($limit);
            
            $response = [
                'success' => $result['success'],
                'message' => $result['message'],
                'data' => $result
            ];
            break;

        case 'delete_videos':
    $videoIds = json_decode($_POST['video_ids'] ?? '[]', true);
    
    if (empty($videoIds)) {
        $response = ['success' => false, 'error' => 'Не выбраны видео для удаления'];
        break;
    }
    
    // Удаляем видео
    $result = $videoEngine->deleteVideos($videoIds);
    
    $response = [
        'success' => true,
        'message' => "Удалено {$result['success']} видео. Ошибок: {$result['errors']}",
        'data' => $result
    ];
    break;

        case 'delete_videos':
    $videoIds = json_decode($_POST['video_ids'] ?? '[]', true);
    
    if (empty($videoIds)) {
        $response = ['success' => false, 'error' => 'Не выбраны видео для удаления'];
        break;
    }
    
    // Удаляем видео
    $result = $videoEngine->deleteVideos($videoIds);
    
    $response = [
        'success' => true,
        'message' => "Удалено {$result['success']} видео. Ошибок: {$result['errors']}",
        'data' => $result
    ];
    break;

// Добавляем метод для удаления тегов в файл admin.php
// В секцию case 'api': добавляем новый case для обработки удаления тегов:

case 'delete_tags':
    $tagIds = json_decode($_POST['tag_ids'] ?? '[]', true);
    
    if (empty($tagIds)) {
        $response = ['success' => false, 'error' => 'Не выбраны теги для удаления'];
        break;
    }
    
    $success = 0;
    $errors = 0;
    
    foreach ($tagIds as $tagId) {
        // Удаляем связи тега с видео
        $deleteLinks = db_query(
            "DELETE FROM " . getConfig('db.prefix') . "video_tags WHERE tag_id = ?",
            [$tagId]
        );
        
        // Удаляем сам тег
        $deleteTag = db_delete('tags', ['id' => $tagId]);
        
        if ($deleteTag) {
            $success++;
        } else {
            $errors++;
        }
    }
    
    $response = [
        'success' => true,
        'message' => "Удалено {$success} тегов. Ошибок: {$errors}",
        'data' => [
            'success' => $success,
            'errors' => $errors
        ]
    ];
    break;

// Добавляем метод для получения списка тегов
// В секцию case 'api': добавляем новый case для получения списка тегов:

case 'get_tags':
    $sortBy = $_GET['sort'] ?? 'name';
    
    if ($sortBy === 'count') {
        $tags = db_get_rows(
            "SELECT t.*, COUNT(vt.video_id) as video_count 
            FROM " . getConfig('db.prefix') . "tags t
            LEFT JOIN " . getConfig('db.prefix') . "video_tags vt ON t.id = vt.tag_id
            GROUP BY t.id
            ORDER BY video_count DESC"
        );
    } else {
        $tags = db_get_rows(
            "SELECT t.*, COUNT(vt.video_id) as video_count 
            FROM " . getConfig('db.prefix') . "tags t
            LEFT JOIN " . getConfig('db.prefix') . "video_tags vt ON t.id = vt.tag_id
            GROUP BY t.id
            ORDER BY t.name ASC"
        );
    }
    
    $response = [
        'success' => true,
        'tags' => $tags
    ];
    break;

// Добавляем метод для удаления тегов в файл admin.php
// В секцию case 'api': добавляем новый case для обработки удаления тегов:

case 'delete_tags':
    $tagIds = json_decode($_POST['tag_ids'] ?? '[]', true);
    
    if (empty($tagIds)) {
        $response = ['success' => false, 'error' => 'Не выбраны теги для удаления'];
        break;
    }
    
    $success = 0;
    $errors = 0;
    
    foreach ($tagIds as $tagId) {
        // Удаляем связи тега с видео
        $deleteLinks = db_query(
            "DELETE FROM " . getConfig('db.prefix') . "video_tags WHERE tag_id = ?",
            [$tagId]
        );
        
        // Удаляем сам тег
        $deleteTag = db_delete('tags', ['id' => $tagId]);
        
        if ($deleteTag) {
            $success++;
        } else {
            $errors++;
        }
    }
    
    $response = [
        'success' => true,
        'message' => "Удалено {$success} тегов. Ошибок: {$errors}",
        'data' => [
            'success' => $success,
            'errors' => $errors
        ]
    ];
    break;

// Добавляем метод для получения списка тегов
// В секцию case 'api': добавляем новый case для получения списка тегов:

case 'get_tags':
    $sortBy = $_GET['sort'] ?? 'name';
    
    if ($sortBy === 'count') {
        $tags = db_get_rows(
            "SELECT t.*, COUNT(vt.video_id) as video_count 
            FROM " . getConfig('db.prefix') . "tags t
            LEFT JOIN " . getConfig('db.prefix') . "video_tags vt ON t.id = vt.tag_id
            GROUP BY t.id
            ORDER BY video_count DESC"
        );
    } else {
        $tags = db_get_rows(
            "SELECT t.*, COUNT(vt.video_id) as video_count 
            FROM " . getConfig('db.prefix') . "tags t
            LEFT JOIN " . getConfig('db.prefix') . "video_tags vt ON t.id = vt.tag_id
            GROUP BY t.id
            ORDER BY t.name ASC"
        );
    }
    
    $response = [
        'success' => true,
        'tags' => $tags
    ];
    break;
        
        case 'import_tags':
            // Проверяем, что файл был загружен
            if (!isset($_FILES['tag_file']) || $_FILES['tag_file']['error'] !== UPLOAD_ERR_OK) {
                $response = [
                    'success' => false,
                    'error' => 'Файл не был загружен или возникла ошибка при загрузке'
                ];
                break;
            }
            
            $filePath = $_FILES['tag_file']['tmp_name'];
            $result = $videoEngine->importTagsFromFile($filePath);
            
            $response = [
                'success' => true,
                'message' => "Импортировано {$result['imported']} из {$result['total']} тегов. Пропущено: {$result['skipped']}. Ошибок: {$result['errors']}.",
                'data' => $result
            ];
            break;
            
        case 'import_search_queries':
            // Проверяем, что файл был загружен
            if (!isset($_FILES['search_file']) || $_FILES['search_file']['error'] !== UPLOAD_ERR_OK) {
                $response = [
                    'success' => false,
                    'error' => 'Файл не был загружен или возникла ошибка при загрузке'
                ];
                break;
            }
            
            $filePath = $_FILES['search_file']['tmp_name'];
            $result = $videoEngine->importSearchQueriesFromFile($filePath);
            
            $response = [
                'success' => true,
                'message' => "Импортировано {$result['imported']} из {$result['total']} поисковых запросов. Пропущено: {$result['skipped']}. Ошибок: {$result['errors']}.",
                'data' => $result
            ];
            break;
            
        case 'generate_site_description':
            $description = $videoEngine->generateSiteDescription();
            
            if (!empty($description)) {
                $response = [
                    'success' => true,
                    'message' => 'Описание сайта успешно сгенерировано',
                    'data' => ['description' => $description]
                ];
            } else {
                $response = [
                    'success' => false,
                    'error' => 'Не удалось сгенерировать описание сайта'
                ];
            }
            break;
            
        case 'generate_category_description':
            $categoryId = $_POST['category_id'] ?? 0;
            
            if (empty($categoryId)) {
                $response = [
                    'success' => false,
                    'error' => 'ID категории не указан'
                ];
                break;
            }
            
            $description = $videoEngine->generateCategoryDescription($categoryId);
            
            if (!empty($description)) {
                $response = [
                    'success' => true,
                    'message' => 'Описание категории успешно сгенерировано',
                    'data' => ['description' => $description]
                ];
            } else {
                $response = [
                    'success' => false,
                    'error' => 'Не удалось сгенерировать описание категории'
                ];
            }
            break;
            
            case 'save_settings':
            $settings = $_POST['settings'] ?? [];
            
            if (empty($settings)) {
                $response = ['success' => false, 'error' => 'No settings provided'];
                break;
            }
            
            // Сохраняем настройки в базу данных
            foreach ($settings as $name => $value) {
                db_query(
                    "INSERT INTO " . getConfig('db.prefix') . "settings (name, value) 
                    VALUES (?, ?) ON DUPLICATE KEY UPDATE value = ?",
                    [$name, $value, $value]
                );
            }
            
            // Очищаем кэш
            cache_clear();
            
            $response = [
                'success' => true,
                'message' => 'Настройки успешно сохранены'
            ];
            break;
            
        case 'add_category':
            $name = $_POST['name'] ?? '';
            $slug = $_POST['slug'] ?? '';
            $description = $_POST['description'] ?? '';
            $keywords = $_POST['keywords'] ?? '';
            
            if (empty($name) || empty($slug)) {
                $response = ['success' => false, 'error' => 'Name and slug are required'];
                break;
            }
            
            // Проверяем уникальность слага
            $existingCategory = db_get_row(
                "SELECT * FROM " . getConfig('db.prefix') . "categories WHERE slug = ?",
                [$slug]
            );
            
            if ($existingCategory) {
                $response = ['success' => false, 'error' => 'Slug already exists'];
                break;
            }
            
            // Добавляем категорию
            $categoryId = db_insert('categories', [
                'name' => $name,
                'slug' => $slug,
                'description' => $description,
                'keywords' => $keywords
            ]);
            
            if ($categoryId) {
                $response = [
                    'success' => true,
                    'message' => 'Категория успешно добавлена',
                    'data' => ['id' => $categoryId]
                ];
            } else {
                $response = ['success' => false, 'error' => 'Failed to add category'];
            }
            break;
            
        case 'edit_category':
            $id = $_POST['id'] ?? 0;
            $name = $_POST['name'] ?? '';
            $slug = $_POST['slug'] ?? '';
            $description = $_POST['description'] ?? '';
            $keywords = $_POST['keywords'] ?? '';
            
            if (empty($id) || empty($name) || empty($slug)) {
                $response = ['success' => false, 'error' => 'ID, name and slug are required'];
                break;
            }
            
            // Проверяем существование категории
            $existingCategory = db_get_row(
                "SELECT * FROM " . getConfig('db.prefix') . "categories WHERE id = ?",
                [$id]
            );
            
            if (!$existingCategory) {
                $response = ['success' => false, 'error' => 'Category not found'];
                break;
            }
            
            // Проверяем уникальность слага
            $slugCheck = db_get_row(
                "SELECT * FROM " . getConfig('db.prefix') . "categories WHERE slug = ? AND id != ?",
                [$slug, $id]
            );
            
            if ($slugCheck) {
                $response = ['success' => false, 'error' => 'Slug already exists'];
                break;
            }
            
            // Обновляем категорию
            $result = db_update('categories', [
                'name' => $name,
                'slug' => $slug,
                'description' => $description,
                'keywords' => $keywords
            ], ['id' => $id]);
            
            if ($result) {
                $response = [
                    'success' => true,
                    'message' => 'Категория успешно обновлена'
                ];
            } else {
                $response = ['success' => false, 'error' => 'Failed to update category'];
            }
            break;
            
        case 'delete_category':
            $id = $_POST['id'] ?? 0;
            
            if (empty($id)) {
                $response = ['success' => false, 'error' => 'ID is required'];
                break;
            }
            
            // Проверяем существование категории
            $existingCategory = db_get_row(
                "SELECT * FROM " . getConfig('db.prefix') . "categories WHERE id = ?",
                [$id]
            );
            
            if (!$existingCategory) {
                $response = ['success' => false, 'error' => 'Category not found'];
                break;
            }
            
            // Удаляем категорию
            $result = db_delete('categories', ['id' => $id]);
            
            if ($result) {
                // Удаляем связи с видео
                db_query(
                    "DELETE FROM " . getConfig('db.prefix') . "video_categories WHERE category_id = ?",
                    [$id]
                );
                
                $response = [
                    'success' => true,
                    'message' => 'Категория успешно удалена'
                ];
            } else {
                $response = ['success' => false, 'error' => 'Failed to delete category'];
            }
            break;
            
            case 'add_site':
            $name = $_POST['name'] ?? '';
            $url = $_POST['url'] ?? '';
            $description = $_POST['description'] ?? '';
            
            if (empty($name) || empty($url)) {
                $response = ['success' => false, 'error' => 'Name and URL are required'];
                break;
            }
            
            // Проверяем уникальность URL
            $existingSite = db_get_row(
                "SELECT * FROM " . getConfig('db.prefix') . "network_sites WHERE url = ?",
                [$url]
            );
            
            if ($existingSite) {
                $response = ['success' => false, 'error' => 'URL already exists'];
                break;
            }
            
            // Генерируем API ключ
            $apiKey = bin2hex(random_bytes(16));
            
            // Добавляем сайт
            $siteId = db_insert('network_sites', [
                'name' => $name,
                'url' => $url,
                'api_key' => $apiKey,
                'description' => $description,
                'active' => 1
            ]);
            
            if ($siteId) {
                // Обновляем настройки сети
                $sites = getConfig('network.sites') ?: [];
                $sites[$siteId] = [
                    'name' => $name,
                    'url' => $url,
                    'api_key' => $apiKey
                ];
                
                // Сохраняем в настройки
                db_query(
                    "INSERT INTO " . getConfig('db.prefix') . "settings (name, value) 
                    VALUES ('network_sites', ?) ON DUPLICATE KEY UPDATE value = ?",
                    [json_encode($sites), json_encode($sites)]
                );
                
                // Включаем сетевую интеграцию, если это первый сайт
                if (count($sites) === 1) {
                    db_query(
                        "INSERT INTO " . getConfig('db.prefix') . "settings (name, value) 
                        VALUES ('network_enabled', '1') ON DUPLICATE KEY UPDATE value = '1'"
                    );
                    
                    db_query(
                        "INSERT INTO " . getConfig('db.prefix') . "settings (name, value) 
                        VALUES ('cross_related', '1') ON DUPLICATE KEY UPDATE value = '1'"
                    );
                }
                
                $response = [
                    'success' => true,
                    'message' => 'Сайт успешно добавлен в сеть',
                    'data' => ['id' => $siteId, 'api_key' => $apiKey]
                ];
            } else {
                $response = ['success' => false, 'error' => 'Failed to add site'];
            }
            break;
            
        case 'edit_site':
            $id = $_POST['id'] ?? 0;
            $name = $_POST['name'] ?? '';
            $url = $_POST['url'] ?? '';
            $description = $_POST['description'] ?? '';
            $active = isset($_POST['active']) ? (int)$_POST['active'] : 1;
            
            if (empty($id) || empty($name) || empty($url)) {
                $response = ['success' => false, 'error' => 'ID, name and URL are required'];
                break;
            }
            
            // Проверяем существование сайта
            $existingSite = db_get_row(
                "SELECT * FROM " . getConfig('db.prefix') . "network_sites WHERE id = ?",
                [$id]
            );
            
            if (!$existingSite) {
                $response = ['success' => false, 'error' => 'Site not found'];
                break;
            }
            
            // Проверяем уникальность URL
            $urlCheck = db_get_row(
                "SELECT * FROM " . getConfig('db.prefix') . "network_sites WHERE url = ? AND id != ?",
                [$url, $id]
            );
            
            if ($urlCheck) {
                $response = ['success' => false, 'error' => 'URL already exists'];
                break;
            }
            
            // Обновляем сайт
            $result = db_update('network_sites', [
                'name' => $name,
                'url' => $url,
                'description' => $description,
                'active' => $active
            ], ['id' => $id]);
            
            if ($result) {
                // Обновляем настройки сети
                $sites = getConfig('network.sites') ?: [];
                
                if (isset($sites[$id])) {
                    $sites[$id]['name'] = $name;
                    $sites[$id]['url'] = $url;
                    
                    // Сохраняем в настройки
                    db_query(
                        "INSERT INTO " . getConfig('db.prefix') . "settings (name, value) 
                        VALUES ('network_sites', ?) ON DUPLICATE KEY UPDATE value = ?",
                        [json_encode($sites), json_encode($sites)]
                    );
                }
                
                $response = [
                    'success' => true,
                    'message' => 'Сайт успешно обновлен'
                ];
            } else {
                $response = ['success' => false, 'error' => 'Failed to update site'];
            }
            break;
            
            case 'delete_site':
            $id = $_POST['id'] ?? 0;
            
            if (empty($id)) {
                $response = ['success' => false, 'error' => 'ID is required'];
                break;
            }
            
            // Проверяем существование сайта
            $existingSite = db_get_row(
                "SELECT * FROM " . getConfig('db.prefix') . "network_sites WHERE id = ?",
                [$id]
            );
            
            if (!$existingSite) {
                $response = ['success' => false, 'error' => 'Site not found'];
                break;
            }
            
            // Удаляем сайт
            $result = db_delete('network_sites', ['id' => $id]);
            
            if ($result) {
                // Обновляем настройки сети
                $sites = getConfig('network.sites') ?: [];
                
                if (isset($sites[$id])) {
                    unset($sites[$id]);
                    
                    // Сохраняем в настройки
                    db_query(
                        "INSERT INTO " . getConfig('db.prefix') . "settings (name, value) 
                        VALUES ('network_sites', ?) ON DUPLICATE KEY UPDATE value = ?",
                        [json_encode($sites), json_encode($sites)]
                    );
                }
                
                $response = [
                    'success' => true,
                    'message' => 'Сайт успешно удален из сети'
                ];
            } else {
                $response = ['success' => false, 'error' => 'Failed to delete site'];
            }
            break;
            
        case 'regenerate_api_key':
            $id = $_POST['id'] ?? 0;
            
            if (empty($id)) {
                $response = ['success' => false, 'error' => 'ID is required'];
                break;
            }
            
            // Проверяем существование сайта
            $existingSite = db_get_row(
                "SELECT * FROM " . getConfig('db.prefix') . "network_sites WHERE id = ?",
                [$id]
            );
            
            if (!$existingSite) {
                $response = ['success' => false, 'error' => 'Site not found'];
                break;
            }
            
            // Генерируем новый API ключ
            $apiKey = bin2hex(random_bytes(16));
            
            // Обновляем сайт
            $result = db_update('network_sites', [
                'api_key' => $apiKey
            ], ['id' => $id]);
            
            if ($result) {
                // Обновляем настройки сети
                $sites = getConfig('network.sites') ?: [];
                
                if (isset($sites[$id])) {
                    $sites[$id]['api_key'] = $apiKey;
                    
                    // Сохраняем в настройки
                    db_query(
                        "INSERT INTO " . getConfig('db.prefix') . "settings (name, value) 
                        VALUES ('network_sites', ?) ON DUPLICATE KEY UPDATE value = ?",
                        [json_encode($sites), json_encode($sites)]
                    );
                }
                
                $response = [
                    'success' => true,
                    'message' => 'API ключ успешно обновлен',
                    'data' => ['api_key' => $apiKey]
                ];
            } else {
                $response = ['success' => false, 'error' => 'Failed to update API key'];
            }
            break;
            
            case 'edit_video':
            $id = $_POST['id'] ?? 0;
            $title = $_POST['title'] ?? '';
            $description = $_POST['description'] ?? '';
            $categories = isset($_POST['categories']) ? $_POST['categories'] : [];
            $tags = isset($_POST['tags']) ? explode(',', $_POST['tags']) : [];
            
            if (empty($id) || empty($title)) {
                $response = ['success' => false, 'error' => 'ID and title are required'];
                break;
            }
            
            // Проверяем существование видео
            $video = $videoEngine->getVideoById($id);
            
            if (!$video) {
                $response = ['success' => false, 'error' => 'Video not found'];
                break;
            }
            
            // Обновляем видео
            $updateData = [
                'title' => $title,
                'description' => $description
            ];
            
            // Если есть категории и теги, добавляем их в данные
            if (!empty($categories)) {
                $updateData['categories'] = $categories;
            }
            
            if (!empty($tags)) {
                $updateData['tags'] = $tags;
            }
            
            $result = $videoEngine->updateVideo($id, $updateData);
            
            if ($result) {
                $response = [
                    'success' => true,
                    'message' => 'Видео успешно обновлено'
                ];
            } else {
                $response = ['success' => false, 'error' => 'Failed to update video'];
            }
            break;
            
case 'auto_tag_videos':
    $limit = $_POST['limit'] ?? 50;
    
    // Запускаем процесс авто-теггинга
    $result = $videoEngine->autoTagAllVideos($limit);
    
    $response = [
        'success' => true,
        'message' => "Добавлены теги для {$result['processed']} видео",
        'processed' => $result['processed'],
        'total' => $result['total'],
        'remaining' => $result['total'] - $result['processed']
    ];
    
    echo json_encode($response);
    exit;

case 'reset_tag_process':
    // Сбрасываем последний обработанный ID
    db_query(
        "INSERT INTO " . getConfig('db.prefix') . "settings (name, value) 
        VALUES ('auto_tag_last_id', '0') ON DUPLICATE KEY UPDATE value = '0'"
    );
    
    $response = [
        'success' => true,
        'message' => 'Процесс подбора тегов сброшен'
    ];
    
    echo json_encode($response);
    exit;
            
        case 'delete_video':
            $id = $_POST['id'] ?? 0;
            
            if (empty($id)) {
                $response = ['success' => false, 'error' => 'ID is required'];
                break;
            }
            
            // Проверяем существование видео
            $video = $videoEngine->getVideoById($id);
            
            if (!$video) {
                $response = ['success' => false, 'error' => 'Video not found'];
                break;
            }
            
            // Удаляем видео
            $result = $videoEngine->deleteVideo($id);
            
            if ($result) {
                $response = [
                    'success' => true,
                    'message' => 'Видео успешно удалено'
                ];
            } else {
                $response = ['success' => false, 'error' => 'Failed to delete video'];
            }
            break;
    }
    
    echo json_encode($response);
    exit;
}

// Обработка основных действий
switch ($action) {
    case 'dashboard':
        // Статистика сайта
        $totalVideos = db_get_var("SELECT COUNT(*) FROM " . getConfig('db.prefix') . "videos");
        $totalCategories = db_get_var("SELECT COUNT(*) FROM " . getConfig('db.prefix') . "categories");
        $totalTags = db_get_var("SELECT COUNT(*) FROM " . getConfig('db.prefix') . "tags");
        $totalSearches = db_get_var("SELECT COUNT(*) FROM " . getConfig('db.prefix') . "search_queries");
        
        // Последние добавленные видео
        $latestVideos = $videoEngine->getLatestVideos(10);
        
        // Популярные поисковые запросы
        $popularSearches = db_get_rows(
            "SELECT * FROM " . getConfig('db.prefix') . "search_queries 
            ORDER BY count DESC LIMIT 10"
        );
        
        renderAdminPage('dashboard', [
            'title' => 'Панель управления',
            'total_videos' => $totalVideos,
            'total_categories' => $totalCategories,
            'total_tags' => $totalTags,
            'total_searches' => $totalSearches,
            'latest_videos' => $latestVideos,
            'popular_searches' => $popularSearches
        ]);
        break;
        
        case 'tags':
        // Управление тегами
        $tags = db_get_rows("SELECT * FROM " . getConfig('db.prefix') . "tags ORDER BY name ASC");
        
        renderAdminPage('tags', [
            'title' => 'Управление тегами',
            'tags' => $tags
        ]);
        break;
        
    case 'auto_tags':
        // Автоматический подбор тегов
        renderAdminPage('auto_tags', [
            'title' => 'Автоматический подбор тегов'
        ]);
        break;
        
    case 'videos':
        // Управление видео
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $perPage = 20;
        $offset = ($page - 1) * $perPage;
        
        // Фильтры
        $filters = [];
        
        if (isset($_GET['q']) && !empty($_GET['q'])) {
            $filters['query'] = $_GET['q'];
        }
        
        if (isset($_GET['category']) && !empty($_GET['category'])) {
            $filters['category'] = $_GET['category'];
        }
        
        // Получаем видео
        $videos = [];
        $totalVideos = 0;
        
        if (!empty($filters)) {
            // Поиск видео
            $searchResult = $videoEngine->searchVideos(
                $filters['query'] ?? '',
                $page,
                $perPage,
                ['category' => $filters['category'] ?? null]
            );
            
            $videos = $searchResult['videos'];
            $totalVideos = $searchResult['total'];
        } else {
            // Все видео с пагинацией
            $totalVideos = db_get_var("SELECT COUNT(*) FROM " . getConfig('db.prefix') . "videos");
            $videos = db_get_rows(
                "SELECT * FROM " . getConfig('db.prefix') . "videos ORDER BY post_date DESC LIMIT {$offset}, {$perPage}"
            );
            
            // Получаем дополнительные данные для каждого видео
            foreach ($videos as &$video) {
                $video['categories'] = $videoEngine->getVideoCategories($video['id']);
                $video['tags'] = $videoEngine->getVideoTags($video['id']);
            }
        }
        
        // Получаем категории для фильтра
        $categories = get_categories();
        
        renderAdminPage('videos', [
            'title' => 'Управление видео',
            'videos' => $videos,
            'total_videos' => $totalVideos,
            'current_page' => $page,
            'total_pages' => ceil($totalVideos / $perPage),
            'categories' => $categories,
            'filters' => $filters
        ]);
        break;
        
        case 'video_edit':
        // Редактирование видео
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        
        if ($id) {
            // Получаем видео
            $video = $videoEngine->getVideoById($id);
            
            if (!$video) {
                // Видео не найдено
                header('Location: admin.php?action=videos');
                exit;
            }
            
            // Получаем категории
            $categories = get_categories();
            
            renderAdminPage('video_edit', [
                'title' => 'Редактирование видео: ' . $video['title'],
                'video' => $video,
                'categories' => $categories
            ]);
        } else {
            // Перенаправляем на список видео
            header('Location: admin.php?action=videos');
            exit;
        }
        break;
        
    case 'categories':
        // Управление категориями
        $categories = get_categories();
        
        renderAdminPage('categories', [
            'title' => 'Управление категориями',
            'categories' => $categories
        ]);
        break;
        
    case 'tags':
        // Управление тегами
        $tags = db_get_rows("SELECT * FROM " . getConfig('db.prefix') . "tags ORDER BY name ASC");
        
        renderAdminPage('tags', [
            'title' => 'Управление тегами',
            'tags' => $tags
        ]);
        break;
        
    case 'import':
        // Импорт видео
        renderAdminPage('import', [
            'title' => 'Импорт контента'
        ]);
        break;
        
    case 'settings':
        // Настройки сайта
        $allSettings = db_get_rows("SELECT * FROM " . getConfig('db.prefix') . "settings");
        
        // Преобразуем в ассоциативный массив
        $settings = [];
        foreach ($allSettings as $setting) {
            $settings[$setting['name']] = $setting['value'];
        }
        
        renderAdminPage('settings', [
            'title' => 'Настройки сайта',
            'settings' => $settings
        ]);
        break;
        
    case 'network':
        // Управление сетью сайтов
        $sites = db_get_rows("SELECT * FROM " . getConfig('db.prefix') . "network_sites ORDER BY name ASC");
        
        renderAdminPage('network', [
            'title' => 'Управление сетью сайтов',
            'sites' => $sites
        ]);
        break;
        
    default:
        // Перенаправляем на дашборд
        header('Location: admin.php?action=dashboard');
        exit;
}