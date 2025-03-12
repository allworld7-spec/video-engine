<?php if (!defined('VIDEOSYSTEM')) die('Прямой доступ запрещен!'); ?>

<h1>Тег: <?php echo htmlspecialchars($tag['name']); ?></h1>

<div class="video-grid">
    <?php if (!empty($videos)): ?>
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
                        <span class="video-views"><?php echo $video['popularity']; ?> просмотров</span>
                    </div>
                </div>
            </a>
        </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="no-videos">По данному тегу пока нет видео.</div>
    <?php endif; ?>
</div>

<?php if ($total_pages > 1): ?>
<div class="pagination">
    <?php if ($current_page > 1): ?>
    <a href="<?php echo getConfig('site.url'); ?>/tag/<?php echo $tag['slug']; ?>?page=<?php echo $current_page - 1; ?>" class="page-link">&laquo; Предыдущая</a>
    <?php endif; ?>
    
    <?php
    // Определяем диапазон страниц
    $start = max(1, $current_page - 2);
    $end = min($total_pages, $current_page + 2);
    
    // Показываем первую страницу
    if ($start > 1): ?>
    <a href="<?php echo getConfig('site.url'); ?>/tag/<?php echo $tag['slug']; ?>?page=1" class="page-link">1</a>
    <?php if ($start > 2): ?>
    <span class="page-dots">...</span>
    <?php endif; ?>
    <?php endif; ?>
    
    <!-- Показываем страницы из диапазона -->
    <?php for ($i = $start; $i <= $end; $i++): ?>
    <a href="<?php echo getConfig('site.url'); ?>/tag/<?php echo $tag['slug']; ?>?page=<?php echo $i; ?>" class="page-link <?php echo $i == $current_page ? 'active' : ''; ?>"><?php echo $i; ?></a>
    <?php endfor; ?>
    
    <!-- Показываем последнюю страницу -->
    <?php if ($end < $total_pages): ?>
    <?php if ($end < $total_pages - 1): ?>
    <span class="page-dots">...</span>
    <?php endif; ?>
    <a href="<?php echo getConfig('site.url'); ?>/tag/<?php echo $tag['slug']; ?>?page=<?php echo $total_pages; ?>" class="page-link"><?php echo $total_pages; ?></a>
    <?php endif; ?>
    
    <?php if ($current_page < $total_pages): ?>
    <a href="<?php echo getConfig('site.url'); ?>/tag/<?php echo $tag['slug']; ?>?page=<?php echo $current_page + 1; ?>" class="page-link">Следующая &raquo;</a>
    <?php endif; ?>
</div>
<?php endif; ?>