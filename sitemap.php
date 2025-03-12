<?php if (!defined('VIDEOSYSTEM')) die('Прямой доступ запрещен!'); 
header('Content-Type: application/xml; charset=utf-8');
echo '<?xml version="1.0" encoding="UTF-8"?>';
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    <?php foreach ($urls as $url): ?>
    <url>
        <loc><?php echo htmlspecialchars($url['loc']); ?></loc>
        <?php if (isset($url['lastmod'])): ?>
        <lastmod><?php echo htmlspecialchars($url['lastmod']); ?></lastmod>
        <?php endif; ?>
        <?php if (isset($url['changefreq'])): ?>
        <changefreq><?php echo htmlspecialchars($url['changefreq']); ?></changefreq>
        <?php endif; ?>
        <?php if (isset($url['priority'])): ?>
        <priority><?php echo htmlspecialchars($url['priority']); ?></priority>
        <?php endif; ?>
    </url>
    <?php endforeach; ?>
</urlset>