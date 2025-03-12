<?php if (!defined('VIDEOSYSTEM')) die('Прямой доступ запрещен!'); 
header('Content-Type: application/xml; charset=utf-8');
echo '<?xml version="1.0" encoding="UTF-8"?>';
?>
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
    <channel>
        <title><?php echo htmlspecialchars($site_name); ?></title>
        <link><?php echo htmlspecialchars($site_url); ?></link>
        <description><?php echo htmlspecialchars($settings['site_description'] ?? "Лучшие видео на {$site_name}"); ?></description>
        <language>ru</language>
        <pubDate><?php echo date('r'); ?></pubDate>
        <lastBuildDate><?php echo date('r'); ?></lastBuildDate>
        <atom:link href="<?php echo htmlspecialchars($site_url); ?>/feed.xml" rel="self" type="application/rss+xml" />
        
        <?php foreach ($videos as $video): ?>
        <item>
            <title><?php echo htmlspecialchars($video['title']); ?></title>
            <link><?php echo htmlspecialchars($site_url . '/video/' . $video['slug']); ?></link>
            <guid isPermaLink="false"><?php echo htmlspecialchars($site_url . '/video/' . $video['id']); ?></guid>
            <pubDate><?php echo date('r', strtotime($video['post_date'])); ?></pubDate>
            <description><![CDATA[
                <p><?php echo htmlspecialchars($video['description'] ?? ''); ?></p>
                <p><img src="<?php echo htmlspecialchars($video['thumb_processed']); ?>" alt="<?php echo htmlspecialchars($video['title']); ?>" /></p>
            ]]></description>
            <?php if (!empty($video['categories'])): ?>
                <?php foreach ($video['categories'] as $category): ?>
                <category><?php echo htmlspecialchars($category['name']); ?></category>
                <?php endforeach; ?>
            <?php endif; ?>
        </item>
        <?php endforeach; ?>
    </channel>
</rss>