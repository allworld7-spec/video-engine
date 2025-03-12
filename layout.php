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
    
    /* Modern tube-style design improvements */
    :root {
        --primary-bg-color: #181818;
        --secondary-bg-color: #222;
        --header-bg-color: #222;
        --footer-bg-color: #1a1a1a;
        --text-color: #eee;
        --link-color: #f90;
        --link-hover-color: #ffb84d;
        --button-color: #f90;
        --button-hover-color: #ffb84d;
        --border-color: #333;
        --highlight-color: #f90;
        --duration-bg: rgba(0, 0, 0, 0.7);
    }
    
    body {
        background-color: var(--primary-bg-color);
        color: var(--text-color);
        font-family: 'Arial', sans-serif;
        line-height: 1.6;
        margin: 0;
        padding: 0;
    }
    
    a {
        color: var(--link-color);
        text-decoration: none;
        transition: color 0.2s ease;
    }
    
    a:hover {
        color: var(--link-hover-color);
    }
    
    .container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 0 15px;
    }
    
    header {
        background-color: var(--header-bg-color);
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
        padding: 10px 0;
        position: relative;
        z-index: 100;
    }
    
    .header-content {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
    }
    
    .logo {
        margin-right: 20px;
    }
    
    .logo img {
        max-height: 40px;
    }
    
    .search-form {
        display: flex;
        flex-grow: 1;
        max-width: 500px;
        margin: 10px 0;
    }
    
    .search-input {
        background-color: #333;
        border: 1px solid #444;
        border-radius: 3px 0 0 3px;
        color: #fff;
        flex-grow: 1;
        padding: 8px 12px;
        outline: none;
    }
    
    .search-input:focus {
        border-color: var(--highlight-color);
    }
    
    .search-button {
        background-color: var(--button-color);
        border: none;
        border-radius: 0 3px 3px 0;
        color: #fff;
        cursor: pointer;
        font-weight: bold;
        padding: 8px 15px;
        transition: background-color 0.2s ease;
    }
    
    .search-button:hover {
        background-color: var(--button-hover-color);
    }
    
    nav {
        background-color: var(--secondary-bg-color);
        padding: 10px 0;
        border-top: 1px solid var(--border-color);
        border-bottom: 1px solid var(--border-color);
    }
    
    nav ul {
        display: flex;
        flex-wrap: wrap;
        list-style: none;
        margin: 0;
        padding: 0;
    }
    
    nav li {
        margin: 5px 15px 5px 0;
    }
    
    nav a {
        color: #ddd;
        font-weight: bold;
        padding: 5px 0;
        position: relative;
    }
    
    nav a:hover {
        color: var(--link-hover-color);
    }
    
    nav a:after {
        content: '';
        position: absolute;
        bottom: 0;
        left: 0;
        width: 0;
        height: 2px;
        background-color: var(--highlight-color);
        transition: width 0.3s;
    }
    
    nav a:hover:after {
        width: 100%;
    }
    
    main {
        padding: 20px 0;
    }
    
    footer {
        background-color: var(--footer-bg-color);
        border-top: 1px solid var(--border-color);
        color: #999;
        padding: 30px 0 20px;
        margin-top: 30px;
    }
    
    .footer-links {
        display: flex;
        flex-wrap: wrap;
        margin-bottom: 20px;
    }
    
    .footer-links a {
        color: #aaa;
        margin: 0 15px 10px 0;
        font-size: 13px;
    }
    
    .footer-links a:hover {
        color: var(--link-hover-color);
        text-decoration: underline;
    }
    
    .copyright {
        border-top: 1px solid #333;
        color: #777;
        font-size: 12px;
        padding-top: 20px;
        text-align: center;
    }
    
    /* Video grid styling - improved for tube sites */
    .video-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
        gap: 15px;
        margin-bottom: 30px;
    }
    
    .video-item {
        background-color: var(--secondary-bg-color);
        border-radius: 4px;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
        overflow: hidden;
        position: relative;
        transition: transform 0.2s, box-shadow 0.2s;
        height: 100%;
        display: flex;
        flex-direction: column;
    }
    
    .video-item:hover {
        transform: translateY(-3px);
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
    }
    
    .video-thumbnail {
        position: relative;
        padding-bottom: 56.25%; /* 16:9 aspect ratio */
        height: 0;
        overflow: hidden;
    }
    
    .video-thumbnail img {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform 0.3s ease;
    }
    
    .video-item:hover .video-thumbnail img {
        transform: scale(1.05);
    }
    
    .video-duration {
        background-color: var(--duration-bg);
        border-radius: 3px;
        bottom: 5px;
        color: #fff;
        font-size: 12px;
        font-weight: bold;
        padding: 2px 5px;
        position: absolute;
        right: 5px;
        z-index: 2;
    }
    
    .video-info {
        flex-grow: 1;
        display: flex;
        flex-direction: column;
        padding: 10px;
    }
    
    .video-title {
        color: #fff;
        font-size: 14px;
        font-weight: normal;
        line-height: 1.4;
        margin: 0 0 8px 0;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
        text-overflow: ellipsis;
        height: auto;
        min-height: 40px;
    }
    
    /* Fixed issue: Full video title display without breaking layout */
    .video-item:hover .video-title {
        display: block;
        -webkit-line-clamp: initial;
        height: auto;
        overflow: visible;
        position: absolute;
        top: 100%;
        left: 0;
        width: 100%;
        background-color: rgba(34, 34, 34, 0.95);
        padding: 10px;
        border-radius: 0 0 4px 4px;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
        z-index: 10;
    }
    
    .video-stats {
        color: #aaa;
        font-size: 12px;
        margin-top: auto;
    }
    
    .video-date {
        display: inline-block;
    }
    
    .video-views {
        display: inline-block;
        margin-left: 10px;
    }
    
    /* Pagination styling */
    .pagination {
        display: flex;
        flex-wrap: wrap;
        justify-content: center;
        margin: 30px 0;
    }
    
    .page-link {
        background-color: var(--secondary-bg-color);
        border-radius: 3px;
        color: #ddd;
        display: inline-block;
        margin: 0 5px 5px 0;
        padding: 8px 12px;
        transition: background-color 0.2s;
    }
    
    .page-link:hover {
        background-color: var(--highlight-color);
        color: #fff;
    }
    
    .page-link.active {
        background-color: var(--highlight-color);
        color: #fff;
    }
    
    .page-dots {
        color: #777;
        display: inline-block;
        margin: 0 5px;
        padding: 8px 12px;
    }
    
    /* Responsive design */
    @media (max-width: 768px) {
        .header-content {
            flex-direction: column;
            align-items: stretch;
        }
        
        .logo {
            margin: 0 auto 15px;
            text-align: center;
        }
        
        .search-form {
            max-width: 100%;
            margin-bottom: 15px;
        }
        
        .video-grid {
            grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
        }
        
        nav ul {
            justify-content: center;
        }
    }
    
    /* Fix for site description */
    .site-description {
        background-color: var(--secondary-bg-color);
        border-left: 4px solid var(--highlight-color);
        border-radius: 4px;
        margin-top: 30px;
        padding: 15px 20px;
    }
    
    .site-description h2 {
        color: #fff;
        font-size: 18px;
        margin-top: 0;
    }
    
    .description-content {
        color: #ddd;
        line-height: 1.6;
    }
    
    /* Category description */
    .category-description {
        background-color: rgba(34, 34, 34, 0.5);
        border-radius: 4px;
        margin: 10px 0 20px;
        padding: 15px;
        color: #ddd;
        line-height: 1.6;
    }
    
    /* Video page specific styles */
    .video-page {
        margin-bottom: 30px;
    }
    
    .video-container {
        background-color: #000;
        border-radius: 4px;
        margin-bottom: 20px;
        overflow: hidden;
    }
    
    .video-player-wrapper {
        position: relative;
        padding-bottom: 56.25%; /* 16:9 aspect ratio */
        height: 0;
        overflow: hidden;
    }
    
    .video-player-wrapper iframe,
    .video-player-wrapper video,
    .video-player-wrapper #player {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
    }
    
    .video-page h1 {
        color: #fff;
        font-size: 22px;
        margin: 0 0 15px;
    }
    
    .video-categories {
        margin: 15px 0;
    }
    
    .video-category {
        background-color: var(--highlight-color);
        border-radius: 3px;
        color: #fff;
        display: inline-block;
        font-size: 12px;
        margin: 0 5px 5px 0;
        padding: 3px 8px;
        text-decoration: none;
    }
    
    .video-category:hover {
        background-color: var(--button-hover-color);
    }
    
    .video-description {
        background-color: var(--secondary-bg-color);
        border-radius: 4px;
        color: #ddd;
        line-height: 1.6;
        margin: 15px 0;
        padding: 15px;
    }
    
    .video-tags {
        margin: 15px 0;
    }
    
    .tags-label {
        color: #aaa;
        margin-right: 5px;
    }
    
    .video-tag {
        background-color: #333;
        border-radius: 3px;
        color: #ddd;
        display: inline-block;
        font-size: 12px;
        margin: 0 5px 5px 0;
        padding: 3px 8px;
        text-decoration: none;
    }
    
    .video-tag:hover {
        background-color: var(--button-color);
        color: #fff;
    }
    
    .related-videos h2 {
        color: #fff;
        font-size: 18px;
        margin: 30px 0 15px;
    }
    
    /* Search results */
    .search-results {
        margin-bottom: 30px;
    }
    
    .no-results {
        background-color: var(--secondary-bg-color);
        border-radius: 4px;
        margin: 20px 0;
        padding: 30px 20px;
        text-align: center;
    }
    
    .search-suggestions {
        margin-top: 20px;
        text-align: left;
        max-width: 600px;
        margin-left: auto;
        margin-right: auto;
    }
    
    .search-suggestions h3 {
        color: #ddd;
        font-size: 16px;
        margin-bottom: 10px;
    }
    
    .search-suggestions ul {
        padding-left: 20px;
    }
    
    .search-suggestions li {
        margin-bottom: 8px;
    }
    </style>
<?php if (isset($include_player) && $include_player): ?>
    <script src="<?php echo getConfig('site.url'); ?>/assets/js/playerjs.js"></script>
<?php endif; ?>
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
                try {
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
                } catch (Exception $e) {
                    // Обработка ошибки - пустой массив
                    $popularSearches = [];
                }
                
                if (!empty($popularSearches)) {
                    foreach ($popularSearches as $search): 
                    ?>
                    <a href="<?php echo getConfig('site.url'); ?>/index.php?action=search&q=<?php echo urlencode($search['query']); ?>">
                        <?php echo htmlspecialchars($search['query']); ?>
                    </a>
                    <?php endforeach;
                }
                ?>
                
                <?php 
                // Ссылки на другие сайты сети
                if (getConfig('network.enabled') && getConfig('network.cross_footer_links')): 
                    try {
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
                    } catch (Exception $e) {
                        // Обработка ошибки
                    }
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
