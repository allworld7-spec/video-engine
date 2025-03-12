<?php if (!defined('VIDEOSYSTEM')) die('Прямой доступ запрещен!'); ?>

<?php 
// Создаем канонический URL для главной страницы
$canonical_url = getConfig('site.url') . '/';

// Проверяем, является ли страница частью пагинации
$isPagination = isset($_GET['page']) && intval($_GET['page']) > 1;

// Если это страница пагинации, генерируем правильный canionical
if ($isPagination) {
    $canonical_url = getConfig('site.url') . '/';
}
?>

<div class="main-content">
    <h1 class="main-title">Последние видео</h1>

    <div class="video-grid">
        <?php foreach ($videos as $video): ?>
        <div class="video-item">
            <a href="<?php echo getConfig('site.url'); ?>/video/<?php echo $video['slug']; ?>">
                <div class="video-thumbnail">
                    <img src="<?php echo $video['thumb_processed']; ?>" alt="<?php echo htmlspecialchars($video['title']); ?>">
                    <div class="video-duration"><?php echo format_duration($video['duration']); ?></div>
                </div>
                <div class="video-info">
                    <h3 class="video-title"><?php echo htmlspecialchars($video['title']); ?></h3>
                    <div class="video-stats">
                        <span class="video-date"><?php echo format_date($video['post_date'], 'd.m.Y'); ?></span>
                    </div>
                </div>
            </a>
        </div>
        <?php endforeach; ?>
    </div>

    <?php if ($total_pages > 1): ?>
    <div class="pagination">
        <?php if ($current_page > 1): ?>
        <a href="<?php echo getConfig('site.url'); ?>/?page=<?php echo $current_page - 1; ?>" class="page-link">&laquo; Предыдущая</a>
        <?php endif; ?>
        
        <?php
        // Определяем диапазон страниц
        $start = max(1, $current_page - 2);
        $end = min($total_pages, $current_page + 2);
        
        // Показываем первую страницу
        if ($start > 1): ?>
        <a href="<?php echo getConfig('site.url'); ?>/?page=1" class="page-link">1</a>
        <?php if ($start > 2): ?>
        <span class="page-dots">...</span>
        <?php endif; ?>
        <?php endif; ?>
        
        <!-- Показываем страницы из диапазона -->
        <?php for ($i = $start; $i <= $end; $i++): ?>
        <a href="<?php echo getConfig('site.url'); ?>/?page=<?php echo $i; ?>" class="page-link <?php echo $i == $current_page ? 'active' : ''; ?>"><?php echo $i; ?></a>
        <?php endfor; ?>
        
        <!-- Показываем последнюю страницу -->
        <?php if ($end < $total_pages): ?>
        <?php if ($end < $total_pages - 1): ?>
        <span class="page-dots">...</span>
        <?php endif; ?>
        <a href="<?php echo getConfig('site.url'); ?>/?page=<?php echo $total_pages; ?>" class="page-link"><?php echo $total_pages; ?></a>
        <?php endif; ?>
        
        <?php if ($current_page < $total_pages): ?>
        <a href="<?php echo getConfig('site.url'); ?>/?page=<?php echo $current_page + 1; ?>" class="page-link">Следующая &raquo;</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    
    <!-- Описание сайта -->
    <?php 
    // Получаем описание сайта из настроек
    $siteDescription = db_get_var("SELECT value FROM " . getConfig('db.prefix') . "settings WHERE name = 'site_description'");
    if (!empty($siteDescription)): 
    ?>
    <div class="site-description">
        <h2>О сайте <?php echo htmlspecialchars(getConfig('site.name')); ?></h2>
        <div class="description-content">
            <?php echo nl2br(htmlspecialchars($siteDescription)); ?>
        </div>
    </div>
    <?php endif; ?>
</div>