<?php if (!defined('VIDEOSYSTEM')) die('Прямой доступ запрещен!'); ?>
<!DOCTYPE html>
<html lang="ru" itemscope itemtype="http://schema.org/WebPage">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($title); ?></title>
    
    <?php 
    // Генерируем мета-теги для SEO
    $metaDescription = isset($meta_description) ? $meta_description : (getConfig('site.description') ?? 'Смотрите лучшие видео на нашем сайте');
    $metaKeywords = isset($meta_keywords) ? $meta_keywords : '';
    $canonicalUrl = isset($canonical_url) ? $canonical_url : (getConfig('site.url') . $_SERVER['REQUEST_URI']);
    $pageImage = isset($page_image) ? $page_image : '';
    
    // Очищаем канонический URL от параметров пагинации
    $canonicalUrl = preg_replace('/([?&])page=\d+(&|$)/', '$1', $canonicalUrl);
    $canonicalUrl = rtrim($canonicalUrl, '?&');
    
    // Проверяем, является ли страница частью пагинации
    $isPagination = isset($_GET['page']) && intval($_GET['page']) > 1;
    
    // Генерируем мета-теги
    echo '<meta name="description" content="' . htmlspecialchars($metaDescription) . '">' . PHP_EOL;
    
    if (!empty($metaKeywords)) {
        echo '<meta name="keywords" content="' . htmlspecialchars($metaKeywords) . '">' . PHP_EOL;
    }
    
    // Генерируем тег canonical
    echo generate_canonical_tag($canonicalUrl);
    
    // Генерируем noindex для страниц пагинации
    echo generate_noindex_tag($isPagination);
    
    // Генерируем Open Graph и Twitter Card теги
    echo generate_og_tags($title, $metaDescription, $canonicalUrl, $pageImage);
    
    // Генерируем мета-теги с атрибутами itemprop для микроданных Schema.org
    echo generate_meta_itemprop($title, $metaDescription);
    
    // Добавляем Schema.org разметку для сайта
    $siteSchema = [
        '@context' => 'https://schema.org',
        '@type' => 'WebSite',
        'name' => getConfig('site.name'),
        'url' => getConfig('site.url'),
        'description' => $metaDescription,
        'potentialAction' => [
            '@type' => 'SearchAction',
            'target' => getConfig('site.url') . '/index.php?action=search&q={search_term_string}',
            'query-input' => 'required name=search_term_string'
        ]
    ];
    echo '<script type="application/ld+json">' . json_encode($siteSchema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>' . PHP_EOL;
    
    // Добавляем Schema.org разметку для страницы видео, если это страница видео
    if (isset($video) && !empty($video)) {
        echo generate_video_schema($video, getConfig('site.url'));
    }
    
    // Добавляем дополнительную Schema.org разметку (если есть)
    if (isset($additional_schema) && !empty($additional_schema)) {
        echo $additional_schema;
    }
    ?>
    
    <link rel="icon" href="<?php echo getConfig('site.url'); ?>/favicon/favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="<?php echo getConfig('site.url'); ?>/templates/<?php echo getConfig('site.template'); ?>/style.css">
    <style>
    <?php include 'templates/' . getConfig('site.template') . '/style.css'; ?>
    
    /* Переопределение стилей для улучшенной версии */
    .video-title {
        margin: 0 0 8px 0;
        font-size: 14px;
        font-weight: normal;
        color: #fff;
        overflow: visible; /* Изменено с overflow: hidden для полного вывода названия */
        white-space: normal;
        line-height: 1.4;
        min-height: 2.8em; /* Минимальная высота для сохранения выравнивания плитки */
    }
    
    .video-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
        align-items: start; /* Выравнивание плиток по верхнему краю */
    }
    
    .video-item {
        display: flex;
        flex-direction: column;
        height: 100%; /* Полная высота для выравнивания плиток */
    }
    
    .video-info {
        flex-grow: 1; /* Растягивание блока для равной высоты */
        display: flex;
        flex-direction: column;
    }
    
    .video-stats {
        margin-top: auto; /* Прижимаем статистику к низу */
    }
    
    /* Улучшенные стили для форм */
    input:focus, textarea:focus, select:focus {
        outline: none;
        border-color: var(--color-primary);
        box-shadow: 0 0 5px rgba(var(--color-primary-rgb), 0.5);
    }
    
    /* Разные эффекты при наведении */
    .video-item:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 15px rgba(0, 0, 0, 0.3);
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }
    
    .video-thumbnail img {
        transition: transform 0.5s ease;
    }
    
    .video-item:hover .video-thumbnail img {
        transform: scale(1.05);
    }
    
    /* Отзывчивая навигация */
    @media (max-width: 768px) {
        nav ul {
            flex-wrap: wrap;
            justify-content: center;
        }
        
        nav li {
            margin: 5px;
        }
    }
    </style>
</head>
<body>
    <?php
    // Проверяем, включен ли сайт
    $siteEnabled = is_site_enabled();
    $isAdmin = is_admin();
    
    // Если сайт выключен и текущий пользователь не администратор, показываем заглушку
    if (!$siteEnabled && !$isAdmin):
    ?>
    <div class="maintenance-mode">
        <div class="container">
            <h1>Сайт временно недоступен</h1>
            <p>Идут технические работы. Пожалуйста, зайдите позже.</p>
        </div>
    </div>
    <?php
    // Устанавливаем HTTP-статус 503 Service Unavailable
    http_response_code(503);
    exit;
    endif;
    ?>
    
    <header>
        <div class="container">
            <div class="header-content">
                <div class="logo">
                    <a href="<?php echo getConfig('site.url'); ?>">
                        <img src="<?php echo getConfig('site.url'); ?>/uploads/logo.png" alt="<?php echo htmlspecialchars(getConfig('site.name')); ?>">
                    </a>
                </div>
                <div class="search-form">
                    <form action="<?php echo getConfig('site.url'); ?>/index.php" method="get">
                        <input type="hidden" name="action" value="search">
                        <input type="text" name="q" placeholder="Поиск видео..." class="search-input">
                        <button type="submit" class="search-button">Поиск</button>
                    </form>
                </div>
            </div>
        </div>
    </header>
    
    <nav>
        <div class="container">
            <ul>
                <li><a href="<?php echo getConfig('site.url'); ?>/">Главная</a></li>
                <li><a href="<?php echo get_popular_videos_url(); ?>">Популярные</a></li>
                <li><a href="<?php echo get_tags_page_url(); ?>">Теги</a></li>
                <li><a href="<?php echo get_random_video_url(); ?>">Случайное видео</a></li>
                <?php 
                // Выводим только первые 8 категорий для экономии места
                $displayCategories = array_slice($categories, 0, 8);
                foreach ($displayCategories as $category): 
                ?>
                    <li>
                        <a href="<?php echo getConfig('site.url'); ?>/category/<?php echo $category['slug']; ?>">
                            <?php echo htmlspecialchars($category['name']); ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </nav>
    
    <main>
        <div class="container">
            <?php echo $content; ?>
        </div>
    </main>
    
    <footer>
        <div class="container">
            <div class="footer-links">
                <?php 
                // Популярные поисковые запросы с результатами
                $popularSearches = db_get_rows(
                    "SELECT * FROM " . getConfig('db.prefix') . "search_queries 
                    WHERE has_results = 1 AND result_count >= 5
                    ORDER BY count DESC LIMIT 20"
                );
                
                if (empty($popularSearches)) {
                    // Если нет помеченных запросов, берем все с сортировкой по популярности
                    $popularSearches = db_get_rows(
                        "SELECT * FROM " . getConfig('db.prefix') . "search_queries 
                        ORDER BY count DESC LIMIT 20"
                    );
                }
                
                foreach ($popularSearches as $search): 
                ?>
                <a href="<?php echo getConfig('site.url'); ?>/index.php?action=search&q=<?php echo urlencode($search['query']); ?>">
                    <?php echo htmlspecialchars($search['query']); ?>
                </a>
                <?php endforeach; ?>
                
                <?php 
                // Ссылки на другие сайты сети
                if (getConfig('network.enabled') && getConfig('network.cross_footer_links')): 
                    $sites = getConfig('network.sites');
                    $maxLinks = getConfig('network.max_footer_links');
                    $count = 0;
                    
                    if (!empty($sites)):
                        foreach ($sites as $site):
                            if ($count >= $maxLinks) break;
                            $count++;
                ?>
                <a href="<?php echo $site['url']; ?>" target="_blank">
                    <?php echo htmlspecialchars($site['name']); ?>
                </a>
                <?php
                        endforeach;
                    endif;
                endif; 
                ?>
            </div>
            
            <div class="copyright">
                &copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars(getConfig('site.name')); ?>. Все права защищены.
            </div>
        </div>
    </footer>
    
    <!-- Скрипт плеера (если нужен) -->
    <?php if (isset($include_player) && $include_player): ?>
    <script src="https://cdn.playerjs.io/player.js"></script>
    <?php endif; ?>
    
       <a href="https://www.liveinternet.ru/click" target="_blank">
        <img id="licntA7F4" width="1" height="1" style="border:0" 
            title="LiveInternet: показано число посетителей за сегодня"
            src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAEALAAAAAABAAEAAAIBTAA7"
            alt=""/>
    </a>
    <script>(function(d,s){d.getElementById("licntA7F4").src=
    "https://counter.yadro.ru/hit?t26.6;r"+escape(d.referrer)+
    ((typeof(s)=="undefined")?"":";s"+s.width+"*"+s.height+"*"+
    (s.colorDepth?s.colorDepth:s.pixelDepth))+";u"+escape(d.URL)+
    ";h"+escape(d.title.substring(0,150))+";"+Math.random()})
    (document,screen)</script>
</body>
</html>