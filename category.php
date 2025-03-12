<?php if (!defined('VIDEOSYSTEM')) die('Прямой доступ запрещен!'); ?>

<?php 
// Создаем канонический URL для категории
$canonical_url = getConfig('site.url') . '/category/' . $category['slug'];

// Создаем мета-описание для страницы
$meta_description = !empty($category['description']) ? $category['description'] : "Видео в категории " . $category['name'] . " на сайте " . getConfig('site.name');

// Создаем дополнительную schema.org разметку для категории
$schema = [
    '@context' => 'https://schema.org',
    '@type' => 'CollectionPage',
    'name' => $category['name'],
    'description' => $meta_description,
    'url' => $canonical_url
];
$additional_schema = '<script type="application/ld+json">' . json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>';

// Проверяем, является ли страница частью пагинации
$isPagination = isset($_GET['page']) && intval($_GET['page']) > 1;
?>

<h1 itemprop="name"><?php echo htmlspecialchars($category['name']); ?></h1>

<?php if (!empty($category['description'])): ?>
<div class="category-description" itemprop="description">
    <?php echo htmlspecialchars($category['description']); ?>
</div>
<?php endif; ?>

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
                    </div>
                </div>
            </a>
        </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="no-videos">В данной категории пока нет видео.</div>
    <?php endif; ?>
</div>

<?php if ($total_pages > 1): ?>
<div class="pagination">
    <?php if ($current_page > 1): ?>
    <a href="<?php echo getConfig('site.url'); ?>/category/<?php echo $category['slug']; ?>?page=<?php echo $current_page - 1; ?>" class="page-link">&laquo; Предыдущая</a>
    <?php endif; ?>
    
    <?php
    // Определяем диапазон страниц
    $start = max(1, $current_page - 2);
    $end = min($total_pages, $current_page + 2);
    
    // Показываем первую страницу
    if ($start > 1): ?>
    <a href="<?php echo getConfig('site.url'); ?>/category/<?php echo $category['slug']; ?>?page=1" class="page-link">1</a>
    <?php if ($start > 2): ?>
    <span class="page-dots">...</span>
    <?php endif; ?>
    <?php endif; ?>
    
    <!-- Показываем страницы из диапазона -->
    <?php for ($i = $start; $i <= $end; $i++): ?>
    <a href="<?php echo getConfig('site.url'); ?>/category/<?php echo $category['slug']; ?>?page=<?php echo $i; ?>" class="page-link <?php echo $i == $current_page ? 'active' : ''; ?>"><?php echo $i; ?></a>
    <?php endfor; ?>
    
    <!-- Показываем последнюю страницу -->
    <?php if ($end < $total_pages): ?>
    <?php if ($end < $total_pages - 1): ?>
    <span class="page-dots">...</span>
    <?php endif; ?>
    <a href="<?php echo getConfig('site.url'); ?>/category/<?php echo $category['slug']; ?>?page=<?php echo $total_pages; ?>" class="page-link"><?php echo $total_pages; ?></a>
    <?php endif; ?>
    
    <?php if ($current_page < $total_pages): ?>
    <a href="<?php echo getConfig('site.url'); ?>/category/<?php echo $category['slug']; ?>?page=<?php echo $current_page + 1; ?>" class="page-link">Следующая &raquo;</a>
    <?php endif; ?>
</div>
<?php endif; ?>