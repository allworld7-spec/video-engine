<?php if (!defined('VIDEOSYSTEM')) die('Прямой доступ запрещен!'); ?>

<div class="card">
    <div class="card-header">
        Управление видео
    </div>
    <div class="card-body">
        <!-- Фильтры видео -->
        <div class="filters-container" style="margin-bottom: 20px;">
            <form method="get" action="admin.php">
                <input type="hidden" name="action" value="videos">
                <div class="row">
                    <div class="col-sm-5">
                        <div class="form-group">
                            <label for="q">Поиск по названию или описанию</label>
                            <input type="text" id="q" name="q" value="<?php echo htmlspecialchars($filters['query'] ?? ''); ?>" class="form-control">
                        </div>
                    </div>
                    <div class="col-sm-5">
                        <div class="form-group">
                            <label for="category">Категория</label>
                            <select id="category" name="category" class="form-control">
                                <option value="">Все категории</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo $category['id']; ?>" <?php echo (isset($filters['category']) && $filters['category'] == $category['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($category['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-sm-2" style="display: flex; align-items: flex-end;">
                        <button type="submit" class="btn btn-primary" style="margin-bottom: 1rem;">Применить фильтры</button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Кнопки для массовых действий -->
        <div class="bulk-actions" style="margin-bottom: 20px;">
            <button id="select-all" class="btn btn-info">Выбрать все</button>
            <button id="deselect-all" class="btn btn-default">Отменить выбор</button>
            <button id="delete-selected" class="btn btn-danger" disabled>Удалить выбранные</button>
        </div>

        <!-- Список видео -->
        <form id="videos-form">
            <table class="table">
                <thead>
                    <tr>
                        <th width="30px"><input type="checkbox" id="select-all-checkbox"></th>
                        <th>ID</th>
                        <th>Превью</th>
                        <th>Название</th>
                        <th>Категории</th>
                        <th>Длительность</th>
                        <th>Действия</th>
                    </tr>
                </thead>
                <tbody id="videos-list">
                    <?php if (!empty($videos)): ?>
                        <?php foreach ($videos as $video): ?>
                            <tr>
                                <td>
                                    <input type="checkbox" class="video-select" name="selected_videos[]" value="<?php echo $video['id']; ?>">
                                </td>
                                <td><?php echo $video['id']; ?></td>
                                <td>
                                    <img src="<?php echo $video['thumb_processed']; ?>" alt="<?php echo htmlspecialchars($video['title']); ?>" style="max-width: 120px; max-height: 70px;">
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($video['title']); ?>
                                    <div style="font-size: 12px; color: #777;">
                                        <span>Просмотры: <?php echo $video['popularity']; ?></span> | 
                                        <span>Рейтинг: <?php echo number_format($video['rating'], 1); ?></span>
                                    </div>
                                </td>
                                <td>
                                    <?php if (!empty($video['categories'])): ?>
                                        <?php foreach ($video['categories'] as $category): ?>
                                            <span class="badge" style="background-color: #337ab7; color: white; margin-right: 5px;"><?php echo htmlspecialchars($category['name']); ?></span>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <span class="badge" style="background-color: #999; color: white;">Без категории</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo format_duration($video['duration']); ?></td>
                                <td>
                                    <a href="admin.php?action=video_edit&id=<?php echo $video['id']; ?>" class="btn btn-primary btn-sm">Редактировать</a>
                                    <button type="button" class="btn btn-danger btn-sm btn-delete-video" data-id="<?php echo $video['id']; ?>">Удалить</button>
                                    <a href="<?php echo getConfig('site.url'); ?>/video/<?php echo $video['slug']; ?>" target="_blank" class="btn btn-success btn-sm">Просмотр</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center">Видео не найдены</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </form>

        <!-- Пагинация -->
        <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($current_page > 1): ?>
                    <a href="admin.php?action=videos&page=<?php echo $current_page - 1; ?><?php echo isset($filters['query']) ? '&q=' . urlencode($filters['query']) : ''; ?><?php echo isset($filters['category']) ? '&category=' . $filters['category'] : ''; ?>" class="page-link">&laquo; Предыдущая</a>
                <?php endif; ?>
                
                <?php
                // Определяем диапазон страниц
                $start = max(1, $current_page - 2);
                $end = min($total_pages, $current_page + 2);
                
                // Показываем первую страницу
                if ($start > 1): ?>
                    <a href="admin.php?action=videos&page=1<?php echo isset($filters['query']) ? '&q=' . urlencode($filters['query']) : ''; ?><?php echo isset($filters['category']) ? '&category=' . $filters['category'] : ''; ?>" class="page-link">1</a>
                    <?php if ($start > 2): ?>
                        <span class="page-dots">...</span>
                    <?php endif; ?>
                <?php endif; ?>
                
                <!-- Показываем страницы из диапазона -->
                <?php for ($i = $start; $i <= $end; $i++): ?>
                    <a href="admin.php?action=videos&page=<?php echo $i; ?><?php echo isset($filters['query']) ? '&q=' . urlencode($filters['query']) : ''; ?><?php echo isset($filters['category']) ? '&category=' . $filters['category'] : ''; ?>" class="page-link <?php echo $i == $current_page ? 'active' : ''; ?>"><?php echo $i; ?></a>
                <?php endfor; ?>
                
                <!-- Показываем последнюю страницу -->
                <?php if ($end < $total_pages): ?>
                    <?php if ($end < $total_pages - 1): ?>
                        <span class="page-dots">...</span>
                    <?php endif; ?>
                    <a href="admin.php?action=videos&page=<?php echo $total_pages; ?><?php echo isset($filters['query']) ? '&q=' . urlencode($filters['query']) : ''; ?><?php echo isset($filters['category']) ? '&category=' . $filters['category'] : ''; ?>" class="page-link"><?php echo $total_pages; ?></a>
                <?php endif; ?>
                
                <?php if ($current_page < $total_pages): ?>
                    <a href="admin.php?action=videos&page=<?php echo $current_page + 1; ?><?php echo isset($filters['query']) ? '&q=' . urlencode($filters['query']) : ''; ?><?php echo isset($filters['category']) ? '&category=' . $filters['category'] : ''; ?>" class="page-link">Следующая &raquo;</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Модальное окно для подтверждения массового удаления -->
<div id="confirm-delete-modal" style="display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.4);">
    <div style="background-color: #fefefe; margin: 15% auto; padding: 20px; border: 1px solid #888; width: 50%;">
        <h3>Подтверждение удаления</h3>
        <p>Вы действительно хотите удалить выбранные видео? Это действие нельзя отменить.</p>
        <p id="selected-count"></p>
        <div style="text-align: right; margin-top: 20px;">
            <button id="cancel-delete" class="btn btn-secondary">Отмена</button>
            <button id="confirm-delete" class="btn btn-danger">Удалить</button>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Выбор/снятие выбора для всех видео
    document.getElementById('select-all-checkbox').addEventListener('change', function() {
        var checkboxes = document.querySelectorAll('.video-select');
        checkboxes.forEach(function(checkbox) {
            checkbox.checked = this.checked;
        }, this);
        updateDeleteButton();
    });
    
    // Обработка кнопки "Выбрать все"
    document.getElementById('select-all').addEventListener('click', function() {
        var checkboxes = document.querySelectorAll('.video-select');
        checkboxes.forEach(function(checkbox) {
            checkbox.checked = true;
        });
        document.getElementById('select-all-checkbox').checked = true;
        updateDeleteButton();
    });
    
    // Обработка кнопки "Отменить выбор"
    document.getElementById('deselect-all').addEventListener('click', function() {
        var checkboxes = document.querySelectorAll('.video-select');
        checkboxes.forEach(function(checkbox) {
            checkbox.checked = false;
        });
        document.getElementById('select-all-checkbox').checked = false;
        updateDeleteButton();
    });
    
    // Обновление состояния кнопки удаления
    function updateDeleteButton() {
        var selectedCount = document.querySelectorAll('.video-select:checked').length;
        var deleteButton = document.getElementById('delete-selected');
        
        if (selectedCount > 0) {
            deleteButton.disabled = false;
        } else {
            deleteButton.disabled = true;
        }
    }
    
    // Обработка изменения чекбоксов
    var videoCheckboxes = document.querySelectorAll('.video-select');
    videoCheckboxes.forEach(function(checkbox) {
        checkbox.addEventListener('change', updateDeleteButton);
    });
    
    // Обработка кнопки "Удалить выбранные"
    document.getElementById('delete-selected').addEventListener('click', function() {
        var selectedVideos = document.querySelectorAll('.video-select:checked');
        if (selectedVideos.length === 0) {
            return;
        }
        
        // Показываем модальное окно подтверждения
        document.getElementById('confirm-delete-modal').style.display = 'block';
        document.getElementById('selected-count').textContent = 'Выбрано видео: ' + selectedVideos.length;
    });
    
    // Отмена удаления
    document.getElementById('cancel-delete').addEventListener('click', function() {
        document.getElementById('confirm-delete-modal').style.display = 'none';
    });
    
    // Подтверждение удаления
    document.getElementById('confirm-delete').addEventListener('click', function() {
        var selectedVideos = Array.from(document.querySelectorAll('.video-select:checked')).map(function(checkbox) {
            return checkbox.value;
        });
        
        // Отправляем запрос на удаление
        fetch('admin.php?action=api&method=delete_videos', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'video_ids=' + JSON.stringify(selectedVideos)
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                alert('Успешно удалено: ' + result.data.success + ' видео. Ошибок: ' + result.data.errors);
                
                // Перезагружаем страницу
                window.location.reload();
            } else {
                alert(result.error);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Произошла ошибка при отправке запроса');
        });
        
        // Скрываем модальное окно
        document.getElementById('confirm-delete-modal').style.display = 'none';
    });
    
    // Обработка кнопок удаления отдельных видео
    document.querySelectorAll('.btn-delete-video').forEach(function(button) {
        button.addEventListener('click', function() {
            var videoId = this.getAttribute('data-id');
            
            if (confirm('Вы уверены, что хотите удалить это видео? Это действие нельзя отменить.')) {
                fetch('admin.php?action=api&method=delete_video', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'id=' + videoId
                })
                .then(response => response.json())
                .then(result => {
                    if (result.success) {
                        // Удаляем строку из таблицы
                        var row = document.querySelector('.video-select[value="' + videoId + '"]').closest('tr');
                        row.parentNode.removeChild(row);
                    } else {
                        alert(result.error);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Произошла ошибка при отправке запроса');
                });
            }
        });
    });
});
</script>