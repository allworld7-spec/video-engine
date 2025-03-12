<?php if (!defined('VIDEOSYSTEM')) die('Прямой доступ запрещен!'); ?>

<div class="row">
    <div class="col-sm-3">
        <div class="small-box small-box-blue">
            <div class="small-box-content">
                <h3><?php echo $total_videos; ?></h3>
                <p>Видео</p>
            </div>
            <a href="admin.php?action=videos" class="small-box-footer">
                Подробнее <i class="fa fa-arrow-circle-right"></i>
            </a>
        </div>
    </div>
    
    <div class="col-sm-3">
        <div class="small-box small-box-green">
            <div class="small-box-content">
                <h3><?php echo $total_categories; ?></h3>
                <p>Категории</p>
            </div>
            <a href="admin.php?action=categories" class="small-box-footer">
                Подробнее <i class="fa fa-arrow-circle-right"></i>
            </a>
        </div>
    </div>
    
    <div class="col-sm-3">
        <div class="small-box small-box-yellow">
            <div class="small-box-content">
                <h3><?php echo $total_tags; ?></h3>
                <p>Теги</p>
            </div>
            <a href="#" class="small-box-footer">
                Подробнее <i class="fa fa-arrow-circle-right"></i>
            </a>
        </div>
    </div>
    
    <div class="col-sm-3">
        <div class="small-box small-box-red">
            <div class="small-box-content">
                <h3><?php echo $total_searches; ?></h3>
                <p>Поисковые запросы</p>
            </div>
            <a href="#" class="small-box-footer">
                Подробнее <i class="fa fa-arrow-circle-right"></i>
            </a>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-sm-6">
        <div class="card">
            <div class="card-header">
                Последние добавленные видео
            </div>
            <div class="card-body">
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Название</th>
                            <th>Дата</th>
                            <th>Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($latest_videos as $video): ?>
                        <tr>
                            <td><?php echo $video['id']; ?></td>
                            <td><?php echo htmlspecialchars(truncate_text($video['title'], 50)); ?></td>
                            <td><?php echo format_date($video['post_date'], 'd.m.Y H:i'); ?></td>
                            <td>
                                <a href="admin.php?action=video_edit&id=<?php echo $video['id']; ?>" class="btn btn-primary">Редактировать</a>
                                <a href="<?php echo getConfig('site.url'); ?>/video/<?php echo $video['slug']; ?>" target="_blank" class="btn btn-success">Просмотр</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <a href="admin.php?action=videos" class="btn btn-primary">Все видео</a>
            </div>
        </div>
    </div>
    
    <div class="col-sm-6">
        <div class="card">
            <div class="card-header">
                Популярные поисковые запросы
            </div>
            <div class="card-body">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Запрос</th>
                            <th>Количество</th>
                            <th>Последний поиск</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($popular_searches as $search): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($search['query']); ?></td>
                            <td><?php echo $search['count']; ?></td>
                            <td><?php echo format_date($search['last_search'], 'd.m.Y H:i'); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-sm-12">
        <div class="card">
            <div class="card-header">
                Быстрые действия
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-sm-4">
                        <a href="admin.php?action=import" class="btn btn-primary">Импортировать видео</a>
                    </div>
                    <div class="col-sm-4">
                        <a href="admin.php?action=settings" class="btn btn-primary">Настройки сайта</a>
                    </div>
                    <div class="col-sm-4">
                        <a href="admin.php?action=categories" class="btn btn-primary">Управление категориями</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>