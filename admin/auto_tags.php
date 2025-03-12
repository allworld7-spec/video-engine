<?php if (!defined('VIDEOSYSTEM')) die('Прямой доступ запрещен!'); ?>

<div class="card">
    <div class="card-header">
        Автоматический подбор тегов
    </div>
    <div class="card-body">
        <p>На этой странице вы можете импортировать список тегов из файла и автоматически применить их к видео, основываясь на названиях и описаниях.</p>
        
        <div class="card">
            <div class="card-header">
                Импорт тегов из файла
            </div>
            <div class="card-body">
                <form id="import-tags-form" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="tag-file">Файл с тегами (по одному тегу на строку)</label>
                        <input type="file" id="tag-file" name="tag_file" required class="form-control">
                        <small>Поддерживаемые форматы: .txt</small>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Импортировать теги</button>
                </form>
                
                <div id="tags-import-result" style="margin-top: 15px;"></div>
            </div>
        </div>
        
        <div class="card" style="margin-top: 20px;">
            <div class="card-header">
                Автоматическое применение тегов к видео
            </div>
            <div class="card-body">
                <p>Запустите процесс автоматического назначения тегов для видео на основе их названий и описаний.</p>
                
                <div class="form-group">
                    <label for="auto-tag-limit">Количество видео для обработки за раз</label>
                    <input type="number" id="auto-tag-limit" min="10" max="100" value="50" class="form-control">
                    <small>Рекомендуемое значение: 50 видео</small>
                </div>
                
                <button id="start-auto-tag" class="btn btn-success">Запустить автоподбор тегов</button>
                <button id="reset-auto-tag" class="btn btn-warning">Сбросить процесс</button>
                
                <div id="auto-tag-progress" style="display: none; margin-top: 15px;">
                    <div class="progress">
                        <div id="progress-bar" class="progress-bar" role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                    <p id="progress-text">Обработано: 0 из 0 видео</p>
                </div>
                
                <div id="auto-tag-result" style="margin-top: 15px;"></div>
            </div>
        </div>
        
        <div class="card" style="margin-top: 20px;">
            <div class="card-header">
                Управление тегами
            </div>
            <div class="card-body">
                <div id="tags-list-container">
                    <div class="form-group">
                        <label>Сортировка:</label>
                        <select id="tags-sort" class="form-control">
                            <option value="name">По названию</option>
                            <option value="count" selected>По количеству видео</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <input type="text" id="tag-search" placeholder="Поиск тегов..." class="form-control">
                    </div>
                    
                    <div class="tag-list">
                        <div id="tags-list" style="margin-top: 15px;">
                            <p>Загрузка списка тегов...</p>
                        </div>
                    </div>
                    
                    <div class="bulk-actions" style="margin-top: 15px;">
                        <button id="select-all-tags" class="btn btn-info">Выбрать все</button>
                        <button id="deselect-all-tags" class="btn btn-secondary">Отменить выбор</button>
                        <button id="delete-selected-tags" class="btn btn-danger" disabled>Удалить выбранные теги</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Загрузка списка тегов при загрузке страницы
    loadTags();
    
    // Импорт тегов из файла
    document.getElementById('import-tags-form').addEventListener('submit', function(e) {
        e.preventDefault();
        
        var formData = new FormData(this);
        var resultElement = document.getElementById('tags-import-result');
        
        // Показываем сообщение о загрузке
        resultElement.className = 'alert alert-info';
        resultElement.textContent = 'Импорт тегов...';
        
        fetch('admin.php?action=api&method=import_tags', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                resultElement.className = 'alert alert-success';
                resultElement.textContent = result.message;
                
                // Перезагружаем список тегов
                loadTags();
                
                // Очищаем поле выбора файла
                document.getElementById('tag-file').value = '';
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
    
    // Запуск автоподбора тегов
    document.getElementById('start-auto-tag').addEventListener('click', function() {
        var limit = document.getElementById('auto-tag-limit').value;
        var resultElement = document.getElementById('auto-tag-result');
        var progressElement = document.getElementById('auto-tag-progress');
        var progressBar = document.getElementById('progress-bar');
        var progressText = document.getElementById('progress-text');
        
        // Показываем прогресс-бар
        progressElement.style.display = 'block';
        progressBar.style.width = '0%';
        progressBar.setAttribute('aria-valuenow', 0);
        
        // Запускаем процесс
        runAutoTagging(limit);
        
        function runAutoTagging(limit) {
            fetch('admin.php?action=api&method=auto_tag_videos', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'limit=' + limit
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    // Обновляем прогресс
                    var progress = (result.processed / result.total) * 100;
                    progressBar.style.width = progress + '%';
                    progressBar.setAttribute('aria-valuenow', progress);
                    progressText.textContent = 'Обработано: ' + result.processed + ' из ' + result.total + ' видео';
                    
                    resultElement.className = 'alert alert-info';
                    resultElement.textContent = result.message;
                    
                    // Если есть еще видео для обработки, продолжаем
                    if (result.remaining > 0) {
                        // Продолжаем обработку через небольшую задержку
                        setTimeout(function() {
                            runAutoTagging(limit);
                        }, 1000);
                    } else {
                        // Обработка завершена
                        resultElement.className = 'alert alert-success';
                        resultElement.textContent = 'Автоподбор тегов завершен. Обработано ' + result.processed + ' видео.';
                        
                        // Перезагружаем список тегов
                        loadTags();
                    }
                } else {
                    resultElement.className = 'alert alert-danger';
                    resultElement.textContent = 'Ошибка: ' + (result.message || 'Неизвестная ошибка');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                resultElement.className = 'alert alert-danger';
                resultElement.textContent = 'Произошла ошибка при отправке запроса';
            });
        }
    });
    
    // Сброс процесса автоподбора тегов
    document.getElementById('reset-auto-tag').addEventListener('click', function() {
        if (confirm('Вы уверены, что хотите сбросить процесс автоподбора тегов? Прогресс будет утерян.')) {
            var resultElement = document.getElementById('auto-tag-result');
            
            fetch('admin.php?action=api&method=reset_tag_process', {
                method: 'POST'
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    resultElement.className = 'alert alert-success';
                    resultElement.textContent = result.message;
                } else {
                    resultElement.className = 'alert alert-danger';
                    resultElement.textContent = 'Ошибка: ' + (result.message || 'Неизвестная ошибка');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                resultElement.className = 'alert alert-danger';
                resultElement.textContent = 'Произошла ошибка при отправке запроса';
            });
        }
    });
    
    // Функция загрузки списка тегов
    function loadTags() {
        var tagsListElement = document.getElementById('tags-list');
        var sortBy = document.getElementById('tags-sort').value;
        var searchQuery = document.getElementById('tag-search').value.toLowerCase();
        
        // Показываем сообщение о загрузке
        tagsListElement.innerHTML = '<p>Загрузка списка тегов...</p>';
        
        fetch('admin.php?action=api&method=get_tags&sort=' + sortBy)
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                if (result.tags.length === 0) {
                    tagsListElement.innerHTML = '<p>Теги не найдены.</p>';
                    return;
                }
                
                // Фильтруем теги по поисковому запросу
                var filteredTags = result.tags;
                if (searchQuery) {
                    filteredTags = result.tags.filter(tag => 
                        tag.name.toLowerCase().includes(searchQuery)
                    );
                }
                
                if (filteredTags.length === 0) {
                    tagsListElement.innerHTML = '<p>По вашему запросу ничего не найдено.</p>';
                    return;
                }
                
                // Создаем HTML для списка тегов
                var html = '<div class="tag-cloud" style="margin-bottom: 20px;">';
                
                filteredTags.forEach(tag => {
                    html += `
                        <div class="tag-item" style="display: inline-block; margin: 5px; padding: 5px 10px; background-color: #f8f9fa; border-radius: 4px; border: 1px solid #dee2e6;">
                            <label>
                                <input type="checkbox" class="tag-checkbox" value="${tag.id}" style="margin-right: 5px;">
                                ${tag.name}
                            </label>
                            <span class="badge badge-primary" style="margin-left: 5px; background-color: #007bff; color: white;">${tag.video_count}</span>
                        </div>
                    `;
                });
                
                html += '</div>';
                
                tagsListElement.innerHTML = html;
                
                // Добавляем обработчики для чекбоксов
                var checkboxes = document.querySelectorAll('.tag-checkbox');
                checkboxes.forEach(function(checkbox) {
                    checkbox.addEventListener('change', updateDeleteButton);
                });
            } else {
                tagsListElement.innerHTML = '<p>Ошибка загрузки тегов: ' + (result.message || 'Неизвестная ошибка') + '</p>';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            tagsListElement.innerHTML = '<p>Произошла ошибка при загрузке тегов</p>';
        });
    }
    
    // Обработка сортировки тегов
    document.getElementById('tags-sort').addEventListener('change', function() {
        loadTags();
    });
    
    // Обработка поиска тегов
    document.getElementById('tag-search').addEventListener('input', function() {
        loadTags();
    });
    
    // Обновление кнопки удаления тегов
    function updateDeleteButton() {
        var selectedCount = document.querySelectorAll('.tag-checkbox:checked').length;
        var deleteButton = document.getElementById('delete-selected-tags');
        
        deleteButton.disabled = selectedCount === 0;
    }
    
    // Выбор всех тегов
    document.getElementById('select-all-tags').addEventListener('click', function() {
        var checkboxes = document.querySelectorAll('.tag-checkbox');
        checkboxes.forEach(function(checkbox) {
            checkbox.checked = true;
        });
        updateDeleteButton();
    });
    
    // Отмена выбора всех тегов
    document.getElementById('deselect-all-tags').addEventListener('click', function() {
        var checkboxes = document.querySelectorAll('.tag-checkbox');
        checkboxes.forEach(function(checkbox) {
            checkbox.checked = false;
        });
        updateDeleteButton();
    });
    
    // Удаление выбранных тегов
    document.getElementById('delete-selected-tags').addEventListener('click', function() {
        var selectedTags = Array.from(document.querySelectorAll('.tag-checkbox:checked')).map(function(checkbox) {
            return checkbox.value;
        });
        
        if (selectedTags.length === 0) {
            return;
        }
        
        if (confirm('Вы уверены, что хотите удалить выбранные теги? Они будут удалены из всех видео.')) {
            var resultElement = document.getElementById('auto-tag-result');
            
            fetch('admin.php?action=api&method=delete_tags', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'tag_ids=' + JSON.stringify(selectedTags)
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    resultElement.className = 'alert alert-success';
                    resultElement.textContent = result.message;
                    
                    // Перезагружаем список тегов
                    loadTags();
                } else {
                    resultElement.className = 'alert alert-danger';
                    resultElement.textContent = 'Ошибка: ' + (result.message || 'Неизвестная ошибка');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                resultElement.className = 'alert alert-danger';
                resultElement.textContent = 'Произошла ошибка при отправке запроса';
            });
        }
    });
});
</script>