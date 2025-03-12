<?php
/**
 * Главный файл сайта
 * Обрабатывает запросы и отображает страницы
 */

// Определяем константу для доступа к файлам
define('VIDEOSYSTEM', true);

// Проверяем установку
if (!file_exists('installed.lock')) {
    // Перенаправляем на установщик
    header('Location: installer.php');
    exit;
}

// Подключаем конфигурационный файл
require_once 'config.php';

// Проверяем, включен ли сайт
$siteEnabled = is_site_enabled();
$isAdmin = is_admin();

// Если сайт выключен и текущий пользователь не администратор, показываем заглушку
if (!$siteEnabled && !$isAdmin) {
    // Устанавливаем HTTP-статус 503 Service Unavailable
    http_response_code(503);
    ?>
    <!DOCTYPE html>
    <html lang="ru">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Сайт временно недоступен</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                background-color: #181818;
                color: #fff;
                text-align: center;
                padding: 50px 20px;
            }
            .container {
                max-width: 600px;
                margin: 0 auto;
                padding: 30px;
                background-color: #222;
                border-radius: 5px;
                box-shadow: 0 0 10px rgba(0,0,0,0.5);
            }
            h1 {
                font-size: 24px;
                margin-bottom: 20px;
            }
            p {
                font-size: 16px;
                margin-bottom: 0;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>Сайт временно недоступен</h1>
            <p>Идут технические работы. Пожалуйста, зайдите позже.</p>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Определяем действие
$action = isset($_GET['action']) ? $_GET['action'] : 'home';

// Инициализируем видеодвижок
require_once 'engine.php';
$videoEngine = new VideoEngine();

// Глобальная переменная для доступа к движку из функций
$GLOBALS['videoEngine'] = $videoEngine;

// Функция для отображения шаблона
function renderTemplate($template, $data = []) {
    $templateFile = getConfig('paths.templates') . '/' . getConfig('site.template') . '/' . $template . '.php';
    
    if (!file_exists($templateFile)) {
        die('Шаблон не найден: ' . $template);
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

// Функция для отображения страницы с макетом
function renderPage($template, $data = []) {
    // Добавляем общие данные
    $data['site_name'] = getConfig('site.name');
    $data['site_url'] = getConfig('site.url');
    
    // Получаем содержимое страницы
    $content = renderTemplate($template, $data);
    
    // Отображаем макет с содержимым
    echo renderTemplate('layout', array_merge($data, ['content' => $content]));
    exit;
}

// Функция для отображения ошибки 404
function show404() {
    header('HTTP/1.0 404 Not Found');
    renderPage('404', ['title' => 'Страница не найдена']);
}

// Получаем категории для меню
$allCategories = get_categories();

// Обрабатываем запрос
switch ($action) {
    case 'home':
        // Главная страница
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $perPage = getConfig('videos.per_page');
        $offset = ($page - 1) * $perPage;
        
        // Получаем последние видео
        $latestVideos = $videoEngine->getLatestVideos($perPage, $offset);
        
        // Получаем общее количество видео
        $totalVideos = db_get_var("SELECT COUNT(*) FROM " . getConfig('db.prefix') . "videos");
        
        // Рассчитываем пагинацию
        $totalPages = ceil($totalVideos / $perPage);
        
        // Отображаем шаблон
        renderPage('home', [
            'title' => getConfig('site.name') . ' - Лучшие видео',
            'videos' => $latestVideos,
            'total_videos' => $totalVideos,
            'current_page' => $page,
            'total_pages' => $totalPages,
            'categories' => $allCategories
        ]);
        break;
        
    case 'popular':
        // Страница популярных видео
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $perPage = getConfig('videos.per_page');
        $offset = ($page - 1) * $perPage;
        
        // Получаем популярные видео
        $popularVideos = $videoEngine->getPopularVideos($perPage, $offset);
        
        // Получаем общее количество видео
        $totalVideos = db_get_var("SELECT COUNT(*) FROM " . getConfig('db.prefix') . "videos");
        
        // Рассчитываем пагинацию
        $totalPages = ceil($totalVideos / $perPage);
        
        // Отображаем шаблон
        renderPage('popular', [
            'title' => 'Популярные видео - ' . getConfig('site.name'),
            'videos' => $popularVideos,
            'total_videos' => $totalVideos,
            'current_page' => $page,
            'total_pages' => $totalPages,
            'categories' => $allCategories
        ]);
        break;
        
    case 'tags':
        // Страница со всеми тегами
        // Получаем теги, которые имеют видео
        $tags = $videoEngine->getTagsWithVideos();
        
        // Отображаем шаблон
        renderPage('tags', [
            'title' => 'Все теги - ' . getConfig('site.name'),
            'tags' => $tags,
            'categories' => $allCategories
        ]);
        break;
        
    case 'video':
        // Страница видео
        $slug = isset($_GET['slug']) ? $_GET['slug'] : '';
        
        if (empty($slug)) {
            show404();
        }
        
        // Получаем видео по слагу
        $video = $videoEngine->getVideoBySlug($slug);
        
        if (!$video) {
            show404();
        }
        
        // Увеличиваем счетчик просмотров
        db_update('videos', [
            'popularity' => $video['popularity'] + 1
        ], ['id' => $video['id']]);
        
        // Получаем похожие видео
        $relatedVideos = get_related_videos($video['id'], $video['title'], getConfig('videos.related_count'));
        
        // Отображаем шаблон
        renderPage('video', [
            'title' => $video['title'] . ' - ' . getConfig('site.name'),
            'video' => $video,
            'related_videos' => $relatedVideos,
            'categories' => $allCategories
        ]);
        break;
        
    case 'category':
        // Страница категории
        $slug = isset($_GET['slug']) ? $_GET['slug'] : '';
        
        if (empty($slug)) {
            show404();
        }
        
        // Получаем категорию по слагу
        $category = db_get_row(
            "SELECT * FROM " . getConfig('db.prefix') . "categories WHERE slug = ?",
            [$slug]
        );
        
        if (!$category) {
            show404();
        }
        
        // Получаем видео по категории
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $perPage = getConfig('videos.per_page');
        
        $result = $videoEngine->getVideosByCategory($category['id'], $page, $perPage);
        
        // Отображаем шаблон
        renderPage('category', [
            'title' => $category['name'] . ' - ' . getConfig('site.name'),
            'category' => $category,
            'videos' => $result['videos'],
            'total_videos' => $result['total'],
            'current_page' => $page,
            'total_pages' => ceil($result['total'] / $perPage),
            'categories' => $allCategories
        ]);
        break;
        
    case 'search':
        // Страница поиска
        $query = isset($_GET['q']) ? trim($_GET['q']) : '';
        
        if (empty($query)) {
            // Перенаправляем на главную страницу
            header('Location: index.php');
            exit;
        }
        
        // Фильтруем поисковый запрос для безопасности
        $query = sanitize_search_query($query);
        
        // Сохраняем поисковый запрос в базу
        $existingQuery = db_get_row(
            "SELECT * FROM " . getConfig('db.prefix') . "search_queries WHERE query = ?",
            [$query]
        );
        
        if ($existingQuery) {
            // Обновляем существующий запрос
            db_update('search_queries', [
                'count' => $existingQuery['count'] + 1,
                'last_search' => date('Y-m-d H:i:s')
            ], ['id' => $existingQuery['id']]);
        } else {
            // Добавляем новый запрос
            db_insert('search_queries', [
                'query' => $query,
                'count' => 1,
                'last_search' => date('Y-m-d H:i:s')
            ]);
        }
        
        // Выполняем поиск
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $perPage = getConfig('videos.per_page');
        
        $result = $videoEngine->searchVideos($query, $page, $perPage);
        
        // Отображаем шаблон
        renderPage('search', [
            'title' => 'Поиск: ' . $query . ' - ' . getConfig('site.name'),
            'query' => $query,
            'videos' => $result['videos'],
            'total_videos' => $result['total'],
            'current_page' => $page,
            'total_pages' => ceil($result['total'] / $perPage),
            'categories' => $allCategories
        ]);
        break;
        
    case 'tag':
        // Страница тега
        $slug = isset($_GET['slug']) ? $_GET['slug'] : '';
        
        if (empty($slug)) {
            show404();
        }
        
        // Получаем тег по слагу
        $tag = db_get_row(
            "SELECT * FROM " . getConfig('db.prefix') . "tags WHERE slug = ?",
            [$slug]
        );
        
        if (!$tag) {
            show404();
        }
        
        // Получаем видео по тегу
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $perPage = getConfig('videos.per_page');
        
        $result = $videoEngine->getVideosByTag($tag['id'], $page, $perPage);
        
        // Отображаем шаблон
        renderPage('tag', [
            'title' => 'Тег: ' . $tag['name'] . ' - ' . getConfig('site.name'),
            'tag' => $tag,
            'videos' => $result['videos'],
            'total_videos' => $result['total'],
            'current_page' => $page,
            'total_pages' => ceil($result['total'] / $perPage),
            'categories' => $allCategories
        ]);
        break;
        
    case 'get_video':
        // Обработка защищенной ссылки на видео
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        $token = isset($_GET['token']) ? $_GET['token'] : '';
        $expires = isset($_GET['expires']) ? (int)$_GET['expires'] : 0;
        
        if (!$id || !$token || !$expires) {
            header('HTTP/1.0 403 Forbidden');
            exit('Доступ запрещен');
        }
        
        // Проверяем ссылку
        if (!validate_video_url($id, $token, $expires)) {
            header('HTTP/1.0 403 Forbidden');
            exit('Доступ запрещен или ссылка устарела');
        }
        
        // Получаем видео
        $video = $videoEngine->getVideoById($id);
        
        if (!$video) {
            header('HTTP/1.0 404 Not Found');
            exit('Видео не найдено');
        }
        
        // Перенаправляем на оригинальную ссылку
        header('Location: ' . $video['file_url']);
        exit;
        break;
        
    case 'feed':
        // RSS-фид
        header('Content-Type: application/xml; charset=utf-8');
        
        // Получаем последние видео
        $videos = $videoEngine->getLatestVideos(50);
        
        // Отображаем шаблон без макета
        echo renderTemplate('feed', [
            'site_name' => getConfig('site.name'),
            'site_url' => getConfig('site.url'),
            'videos' => $videos
        ]);
        exit;
        break;
        
    case 'sitemap':
        // Карта сайта
        header('Content-Type: application/xml; charset=utf-8');
        
        // Получаем URL для карты сайта
        $urls = [];
        
        // Добавляем главную страницу
        $urls[] = [
            'loc' => getConfig('site.url'),
            'changefreq' => 'daily',
            'priority' => '1.0'
        ];
        
        // Добавляем URL популярных видео
        $urls[] = [
            'loc' => get_popular_videos_url(),
            'changefreq' => 'daily',
            'priority' => '0.9'
        ];
        
        // Добавляем URL страницы тегов
        $urls[] = [
            'loc' => get_tags_page_url(),
            'changefreq' => 'weekly',
            'priority' => '0.8'
        ];
        
        // Добавляем URL категорий
        foreach ($allCategories as $category) {
            $urls[] = [
                'loc' => getConfig('site.url') . '/category/' . $category['slug'],
                'changefreq' => 'daily',
                'priority' => '0.8'
            ];
        }
        
        // Добавляем URL видео
        $allVideos = db_get_rows(
            "SELECT id, slug, added_date FROM " . getConfig('db.prefix') . "videos ORDER BY post_date DESC LIMIT 1000"
        );
        
        foreach ($allVideos as $video) {
            $urls[] = [
                'loc' => getConfig('site.url') . '/video/' . $video['slug'],
                'lastmod' => date('Y-m-d', strtotime($video['added_date'])),
                'changefreq' => 'monthly',
                'priority' => '0.6'
            ];
        }
        
        // Отображаем шаблон без макета
        echo renderTemplate('sitemap', ['urls' => $urls]);
        exit;
        break;
        
    case 'random':
        // Случайное видео
        $video = db_get_row(
            "SELECT slug FROM " . getConfig('db.prefix') . "videos ORDER BY RAND() LIMIT 1"
        );
        
        if ($video) {
            header('Location: ' . getConfig('site.url') . '/video/' . $video['slug']);
            exit;
        } else {
            // Если нет видео, перенаправляем на главную
            header('Location: ' . getConfig('site.url'));
            exit;
        }
        break;
        
    case 'api':
        // API для внешних запросов
        header('Content-Type: application/json; charset=utf-8');
        
        $apiAction = isset($_GET['method']) ? $_GET['method'] : '';
        $response = ['success' => false, 'error' => 'Unknown method'];
        
        switch ($apiAction) {
            case 'random_videos':
                $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
                $videos = $videoEngine->getRandomVideos($limit);
                
                // Упрощаем данные для API
                $simpleVideos = [];
                foreach ($videos as $video) {
                    $simpleVideos[] = [
                        'id' => $video['id'],
                        'title' => $video['title'],
                        'slug' => $video['slug'],
                        'thumb' => $video['thumb_processed'],
                        'duration' => $video['duration'],
                        'post_date' => $video['post_date']
                    ];
                }
                
                $response = [
                    'success' => true,
                    'count' => count($simpleVideos),
                    'videos' => $simpleVideos
                ];
                break;
                
            case 'get_categories':
                $response = [
                    'success' => true,
                    'count' => count($allCategories),
                    'categories' => $allCategories
                ];
                break;
                
            case 'search':
                $query = isset($_GET['q']) ? trim($_GET['q']) : '';
                $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
                
                if (empty($query)) {
                    $response = ['success' => false, 'error' => 'Query is required'];
                    break;
                }
                
                $result = $videoEngine->searchVideos($query, 1, $limit);
                
                // Упрощаем данные для API
                $simpleVideos = [];
                foreach ($result['videos'] as $video) {
                    $simpleVideos[] = [
                        'id' => $video['id'],
                        'title' => $video['title'],
                        'slug' => $video['slug'],
                        'thumb' => $video['thumb_processed'],
                        'duration' => $video['duration'],
                        'post_date' => $video['post_date']
                    ];
                }
                
                $response = [
                    'success' => true,
                    'count' => count($simpleVideos),
                    'total' => $result['total'],
                    'videos' => $simpleVideos
                ];
                break;
        }
        
        echo json_encode($response);
        exit;
        break;
        
    default:
        // Обработка ЧПУ
        if (preg_match('/^video\/([a-z0-9-]+)$/', $action, $matches)) {
            // Страница видео
            $_GET['slug'] = $matches[1];
            $_GET['action'] = 'video';
            
            // Повторно вызываем обработчик
            return include __FILE__;
        } elseif (preg_match('/^category\/([a-z0-9-]+)$/', $action, $matches)) {
            // Страница категории
            $_GET['slug'] = $matches[1];
            $_GET['action'] = 'category';
            
            // Повторно вызываем обработчик
            return include __FILE__;
        } elseif (preg_match('/^tag\/([a-z0-9-]+)$/', $action, $matches)) {
            // Страница тега
            $_GET['slug'] = $matches[1];
            $_GET['action'] = 'tag';
            
            // Повторно вызываем обработчик
            return include __FILE__;
        } elseif ($action === 'popular') {
            // Страница популярных видео
            $_GET['action'] = 'popular';
            
            // Повторно вызываем обработчик
            return include __FILE__;
        } elseif ($action === 'tags') {
            // Страница тегов
            $_GET['action'] = 'tags';
            
            // Повторно вызываем обработчик
            return include __FILE__;
        } elseif ($action === 'random') {
            // Случайное видео
            $_GET['action'] = 'random';
            
            // Повторно вызываем обработчик
            return include __FILE__;
        } else {
            // Страница не найдена
            show404();
        }
}