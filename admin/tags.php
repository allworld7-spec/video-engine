<?php if (!defined('VIDEOSYSTEM')) die('Прямой доступ запрещен!'); ?>

<div class="card">
    <div class="card-header">
        Управление тегами
    </div>
    <div class="card-body">
        <div class="tag-stats">
            <p><strong>Всего тегов:</strong> <?php echo count($tags); ?></p>
        </div>
        
        <div class="tag-cloud" style="margin-bottom: 20px;">
            <?php foreach ($tags as $tag): ?>
                <span class="badge" style="background-color: #337ab7; color: white; margin: 5px; padding: 5px 10px; display: inline-block; font-size: 14px;">
                    <?php echo htmlspecialchars($tag['name']); ?>
                    <span class="badge-count" style="margin-left: 5px; background-color: #fff; color: #337ab7; border-radius: 10px; padding: 0 5px;">
                        <?php 
                            // Получаем количество видео с этим тегом
                            $videoCount = db_get_var(
                                "SELECT COUNT(*) FROM " . getConfig('db.prefix') . "video_tags WHERE tag_id = ?",
                                [$tag['id']]
                            );
                            echo $videoCount; 
                        ?>
                    </span>
                    <a href="#" class="tag-delete" data-id="<?php echo $tag['id']; ?>" style="margin-left: 5px; color: #fff; text-decoration: none;">×</a>
                </span>
            <?php endforeach; ?>
        </div>
        
        <div class="card">
            <div class="card-header">
                Добавить новый тег
            </div>
            <div class="card-body">
                <form id="add-tag-form">
                    <div class="form-group">
                        <label for="tag-name">Название тега</label>
                        <input type="text" id="tag-name" name="name" required class="form-control">
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Добавить тег</button>
                </form>
                
                <div id="add-tag-result" style="margin-top: 15px;"></div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Добавление нового тега
    document.getElementById('add-tag-form').addEventListener('submit', function(e) {
        e.preventDefault();
        
        var tagName = document.getElementById('tag-name').value;
        var resultElement = document.getElementById('add-tag-result');
        
        if (!tagName) {
            resultElement.className = 'alert alert-danger';
            resultElement.textContent = 'Пожалуйста, укажите название тега';
            return;
        }
        
        // Отправляем запрос
        fetch('admin.php?action=api&method=add_tag', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({name: tagName})
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                resultElement.className = 'alert alert-success';
                resultElement.textContent = result.message;
                
                // Очищаем поле ввода
                document.getElementById('tag-name').value = '';
                
                // Обновляем страницу для отображения нового тега
                setTimeout(function() {
                    location.reload();
                }, 1000);
            } else {
                resultElement.className = 'alert alert-danger';
                resultElement.textContent = result.error;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            resultElement.className = 'alert alert-danger';
            resultElement.textContent = 'Произошла ошибка при отправке запроса';
        });
    });
    
    // Удаление тега
    document.querySelectorAll('.tag-delete').forEach(function(button) {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            
            var tagId = this.getAttribute('data-id');
            
            if (confirm('Вы уверены, что хотите удалить этот тег?')) {
                // Отправляем запрос на удаление
                fetch('admin.php?action=api&method=delete_tag', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({id: tagId})
                })
                .then(response => response.json())
                .then(result => {
                    if (result.success) {
                        // Удаляем тег из DOM
                        this.closest('.badge').remove();
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