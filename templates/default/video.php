<?php
if (!defined('VIDEOSYSTEM')) {
    die('Прямой доступ запрещен!');
}

$include_player = true; // Включаем скрипт плеера 

// Создаем канонический URL для видео
$canonical_url = getConfig('site.url') . '/video/' . $video['slug'];

// Создаем мета-описание для страницы
$meta_description = !empty($video['description']) 
    ? truncate_text($video['description'], 160) 
    : "Смотрите видео " . $video['title'] . " на сайте " . getConfig('site.name');

// Определяем ключевые слова для страницы
$keywords = [];
if (!empty($video['tags'])) {
    foreach ($video['tags'] as $tag) {
        $keywords[] = $tag['name'];
    }
}
$meta_keywords = implode(', ', $keywords);

// Устанавливаем изображение для страницы
$page_image = $video['thumb_processed'];
?>
<div class="video-page" itemscope itemtype="http://schema.org/VideoObject">
    <div class="video-container">
        <div class="video-player-wrapper">
            <?php if (getConfig('player.type') === 'playerjs'): ?>
               <?php if (getConfig('player.type') === 'playerjs'): ?>
    <div id="player" style="width:100%; height:auto;"></div>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Проверяем, загружен ли Playerjs
            if (typeof Playerjs === 'undefined') {
                // Если Playerjs не загружен, динамически загружаем его
                var script = document.createElement('script');
                script.type = 'text/javascript';
                script.src = '//cdn.playerjs.io/player.js';
                script.onload = function() {
                    // После загрузки инициализируем плеер
                    var player = new Playerjs({
                        id: "player",
                        file: "<?php echo $video['secure_url']; ?>",
                        poster: "<?php echo $video['thumb_processed']; ?>",
                        autoplay: <?php echo getConfig('player.autoplay') ? 'true' : 'false'; ?>,
                        preload: "<?php echo getConfig('player.preload'); ?>"
                    });
                };
                document.head.appendChild(script);
            } else {
                // Если Playerjs уже загружен, инициализируем плеер напрямую
                var player = new Playerjs({
                    id: "player",
                    file: "<?php echo $video['secure_url']; ?>",
                    poster: "<?php echo $video['thumb_processed']; ?>",
                    autoplay: <?php echo getConfig('player.autoplay') ? 'true' : 'false'; ?>,
                    preload: "<?php echo getConfig('player.preload'); ?>"
                });
            }
        });
    </script>
<?php elseif (!empty($video['embed_code'])): ?>
    <div class="embed-container">
        <?php echo $video['embed_code']; ?>
    </div>
<?php else: ?>
    <video controls 
           preload="<?php echo getConfig('player.preload'); ?>" 
           poster="<?php echo $video['thumb_processed']; ?>" 
           width="100%">
        <source src="<?php echo $video['secure_url']; ?>" type="video/mp4">
        Ваш браузер не поддерживает HTML5 видео.
    </video>
<?php endif; ?>
            <?php endif; ?>
        </div>
        <div class="video-info">
        <h1 class="video-title" itemprop="name">
            <?php echo htmlspecialchars($video['title']); ?>
        </h1>
        
        <div class="video-stats">
            <span class="video-views" 
                  itemprop="interactionCount" 
                  content="<?php echo $video['popularity']; ?> UserPlays">
                <i class="icon-eye"></i> <?php echo $video['popularity']; ?> просмотров
            </span>
            
            <span class="video-rating">
                <i class="icon-thumbs-up"></i> 
                <span itemprop="aggregateRating" 
                      itemscope 
                      itemtype="http://schema.org/AggregateRating">
                    <meta itemprop="ratingValue" 
                          content="<?php echo number_format($video['rating'] / 10, 1); ?>">
                    <meta itemprop="bestRating" content="10">
                    <meta itemprop="worstRating" content="1">
                    <meta itemprop="ratingCount" content="<?php echo $video['votes']; ?>">
                    <?php echo number_format($video['rating'], 1); ?>%
                </span>
            </span>
            
            <?php if (!empty($video['duration'])): ?>
                <span class="video-duration">
                    <meta itemprop="duration" 
                          content="PT<?php echo floor($video['duration'] / 60); ?>M<?php echo $video['duration'] % 60; ?>S">
                    <?php echo format_duration($video['duration']); ?>
                </span>
            <?php endif; ?>
            
            <meta itemprop="uploadDate" content="<?php echo date('c', strtotime($video['post_date'])); ?>">
            <link itemprop="thumbnailUrl" href="<?php echo $video['thumb_processed']; ?>">
            <link itemprop="contentUrl" href="<?php echo $video['secure_url']; ?>">
            <meta itemprop="width" content="<?php echo $video['width']; ?>">
            <meta itemprop="height" content="<?php echo $video['height']; ?>">
        </div>
        
        <?php if (!empty($video['categories'])): ?>
            <div class="video-categories">
                <?php foreach ($video['categories'] as $category): ?>
                    <a href="<?php echo getConfig('site.url'); ?>/category/<?php echo $category['slug']; ?>" 
                       class="video-category">
                        <?php echo htmlspecialchars($category['name']); ?>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($video['description'])): ?>
            <div class="video-description" itemprop="description">
                <?php echo nl2br(htmlspecialchars($video['description'])); ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($video['tags'])): ?>
            <div class="video-tags">
                <span class="tags-label">Теги:</span>
                <?php foreach ($video['tags'] as $tag): ?>
                    <a href="<?php echo getConfig('site.url'); ?>/tag/<?php echo $tag['slug']; ?>" 
                       class="video-tag">
                        <?php echo htmlspecialchars($tag['name']); ?>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
</div>
<?php if (!empty($related_videos)): ?>
<div class="related-videos">
    <h2>Похожие видео</h2>
    
    <div class="video-grid">
        <?php foreach ($related_videos as $related): ?>
            <div class="video-item">
                <a href="<?php
                    if (isset($related['external']) && $related['external']) {
                        echo $related['site_url'] . '/video/' . $related['slug'];
                    } else {
                        echo getConfig('site.url') . '/video/' . $related['slug'];
                    }
                ?>">
                    <div class="video-thumbnail">
                        <img src="<?php echo $related['thumb_processed']; ?>" 
                             alt="<?php echo htmlspecialchars($related['title']); ?>">
                        <div class="video-duration">
                            <?php echo format_duration($related['duration']); ?>
                        </div>
                    </div>
                    <div class="video-info">
                        <h3 class="video-title">
                            <?php echo htmlspecialchars($related['title']); ?>
                        </h3>
                        <div class="video-stats">
                            <span class="video-date">
                                <?php echo format_date($related['post_date'], 'd.m.Y'); ?>
                            </span>
                        </div>
                    </div>
                </a>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>