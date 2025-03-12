<?php if (!defined('VIDEOSYSTEM')) die('Прямой доступ запрещен!'); ?>

<?php 
// Создаем канонический URL для страницы тегов
$canonical_url = getConfig('site.url') . '/tags';

// Создаем мета-описание для страницы
$meta_description = "Все теги на сайте " . getConfig('site.name');

// Создаем дополнительную schema.org разметку для страницы тегов
$schema = [
    '@context' => 'https://schema.org',
    '@type' => 'CollectionPage',
    'name' => 'Все теги',
    'description' => $meta_description,
    'url' => $canonical_url
];
$additional_schema = '<script type="application/ld+json">' . json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>';
?>

<h1>Все теги</h1>

<div class="tags-page">
    <div class="tags-cloud">
        <?php if (!empty($tags)): ?>
            <?php foreach ($tags as $tag): ?>
                <a href="<?php echo getConfig('site.url'); ?>/tag/<?php echo $tag['slug']; ?>" class="tag-item" style="font-size: <?php echo min(24, max(14, 14 + $tag['video_count'] / 5)); ?>px;">
                    <?php echo htmlspecialchars($tag['name']); ?>
                    <span class="tag-count">(<?php echo $tag['video_count']; ?>)</span>
                </a>
            <?php endforeach; ?>
        <?php else: ?>
            <p>На сайте пока нет тегов.</p>
        <?php endif; ?>
    </div>
    
    <style>
        .tags-cloud {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin: 20px 0;
        }
        
        .tag-item {
            display: inline-block;
            background-color: #333;
            color: #fff;
            padding: 8px 15px;
            border-radius: 5px;
            transition: background-color 0.3s, transform 0.3s;
            text-decoration: none;
        }
        
        .tag-item:hover {
            background-color: #ff9000;
            transform: scale(1.05);
        }
        
        .tag-count {
            font-size: 0.8em;
            opacity: 0.8;
            margin-left: 3px;
        }
    </style>
</div>