<?php if (!defined('VIDEOSYSTEM')) die('Прямой доступ запрещен!'); ?>

<div class="card">
    <div class="card-header">
        Редактирование видео #<?php echo $video['id']; ?>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-sm-4">
                <div class="video-preview" style="margin-bottom: 20px;">
                    <img src="<?php echo $video['thumb_processed']; ?>" alt="<?php echo htmlspecialchars($video['title']); ?>" style="max-width: 100%; margin-bottom: 10px;">
                    <div class="video-info">
                        <p><strong>ID:</strong> <?php echo $video['id']; ?></p>
                        <p><strong>Длительность:</strong> <?php echo format_duration($video['duration']); ?></p>
                        <p><strong>Размер файла:</strong> <?php echo format_filesize($video['filesize']); ?></p>
                        <p><strong>Разрешение:</strong> <?php echo $video['width']; ?> x <?php echo $video['height']; ?></p>
                        <p><strong>Просмотры:</strong> <?php echo $video['popularity']; ?></p>
                        <p><strong>Рейтинг:</strong> <?php echo number_format($video['rating'], 1); ?> (<?php echo $video['votes']; ?> голосов)</p>
                    </div>
                </div>
                
                <div class="original-link">
                    <?php if (!empty($video['original_link'])): ?>
                        <p><a href="<?php echo $video['original_link']; ?>" target="_blank" class="btn btn-info btn-sm">Перейти к оригиналу</a></p>
                    <?php endif; ?>
                    <p><a href="<?php echo getConfig('site.url'); ?>/video/<?php echo $video['slug']; ?>" target="_blank" class="btn btn-success btn-sm">Просмотр на сайте</a></p>
                </div>
            </div>
            
            <div class="col-sm-8">
                <form id="edit-video-form">
                    <input type="hidden" name="id" value="<?php echo $video['id']; ?>">
                    
                    <div class="form-group">
                        <label for="title">Название видео</label>
                        <input type="text" id="title" name="title" value="<?php echo htmlspecialchars($video['title']); ?>" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="slug">Слаг (ЧПУ)</label>
                        <input type="text" id="slug" name="slug" value="<?php echo htmlspecialchars($video['slug']); ?>" class="form-control" readonly>
                        <small>Слаг используется в URL видео и не может быть изменен.</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="description">Описание</label>
                        <textarea id="description" name="description" class="form-control" rows="8"><?php echo htmlspecialchars($video['description']); ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>Категории</label>
                        <div class="categories-checkboxes" style="max-height: 200px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; border-radius: 4px;">
                            <?php 
                            // Получаем ID категорий видео
                            $videoCategoryIds = array_column($video['categories'], 'id');
                            
                            foreach ($categories as $category): 
                            ?>
                                <div class="checkbox">
                                    <label>
                                        <input type="checkbox" name="categories[]" value="<?php echo $category['id']; ?>" 
                                            <?php echo in_array($category['id'], $videoCategoryIds) ? 'checked' : ''; ?>>
                                        <?php echo htmlspecialchars($category['name']); ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="tags">Теги</label>
                        <input type="text" id="tags" name="tags" value="<?php echo htmlspecialchars(implode(', ', array_column($video['tags'], 'name'))); ?>" class="form-control">
                        <small>Разделяйте теги запятыми</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="embed_code">Код для встраивания</label>
                        <textarea id="embed_code" name="embed_code" class="form-control" rows="4"><?php echo htmlspecialchars($video['embed_code']); ?></textarea>
                    </div>
                    
                    <div class="form-buttons">
                        <button type="submit" class="btn btn-primary">Сохранить изменения</button>
                        <a href="admin.php?action=videos" class="btn btn-secondary">Вернуться к списку</a>
                        <button type="button" id="btn-delete" data-id="<?php echo $video['id']; ?>" class="btn btn-danger">Удалить видео</button>
                    </div>
                </form>
                
                <div id="edit-result" style="margin-top: 15px;"></div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Обработка формы редактирования
    document.getElementById('edit-video-form').addEventListener('submit', function(e) {
        e.preventDefault();
        
        var formData = new FormData(this);
        var data = {};
        
        for (var [key, value] of formData.entries()) {
            // Особая обработка для чекбоксов категорий
            if (key === 'categories[]') {
                if (!data.categories) {
                    data.categories = [];
                }
                data.categories.push(value);
            } else {
                data[key] = value;
            }
        }
        
        // Преобразуем в формат x-www-form-urlencoded
        var formBody = [];
        for (var property in data) {
            var encodedKey = encodeURIComponent(property);
            var encodedValue = encodeURIComponent(data[property]);
            formBody.push(encodedKey + "=" + encodedValue);
        }
        formBody = formBody.join("&");
        
        fetch('admin.php?action=api&method=edit_video', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: formBody
        })
        .then(response => response.json())
        .then(result => {
            var resultElement = document.getElementById('edit-result');
            
            if (result.success) {
                resultElement.className = 'alert alert-success';
                resultElement.textContent = result.message;
            } else {
                resultElement.className = 'alert alert-danger';
                resultElement.textContent = result.error;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            var resultElement = document.getElementById('edit-result');
            resultElement.className = 'alert alert-danger';
            resultElement.textContent = 'Произошла ошибка при отправке запроса';
        });
    });
    
    // Обработка кнопки удаления
    document.getElementById('btn-delete').addEventListener('click', function() {
        var videoId = this.getAttribute('data-id');
        
        if (confirm('Вы уверены, что хотите удалить это видео? Это действие невозможно отменить.')) {
            fetch('admin.php?action=api&method=delete_video', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: 'id=' + videoId
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    alert(result.message);
                    window.location.href = 'admin.php?action=videos';
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
</script>