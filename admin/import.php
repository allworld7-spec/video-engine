<?php if (!defined('VIDEOSYSTEM')) die('Прямой доступ запрещен!'); ?>

<div class="row">
    <div class="col-sm-6">
        <div class="card">
            <div class="card-header">
                Импорт видео из XML
            </div>
            <div class="card-body">
                <form id="import-xml-form">
                    <div class="form-group">
                        <label for="xml-url">URL XML-фида</label>
                        <input type="url" id="xml-url" name="url" required placeholder="https://example.com/feed.xml" class="form-control">
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Импортировать</button>
                </form>
                
                <div id="import-result" style="margin-top: 15px;"></div>
                
                <div id="import-progress" style="display: none; margin-top: 15px;">
                    <div style="width: 100%; background-color: #e0e0e0; border-radius: 5px; overflow: hidden;">
                        <div id="progress-bar" style="height: 20px; background-color: #4CAF50; width: 0%;"></div>
                    </div>
                    <p id="progress-text">Загрузка: 0%</p>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-sm-6">
        <div class="card">
            <div class="card-header">
                Обработка видео с помощью ChatGPT
            </div>
            <div class="card-body">
                <form id="process-form">
                    <div class="form-group">
                        <label for="process-limit">Количество видео для обработки</label>
                        <input type="number" id="process-limit" name="limit" min="1" max="50" value="10" class="form-control">
                        <small>Рекомендуется обрабатывать не более 10-20 видео за раз для экономии API запросов</small>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Обработать</button>
                </form>
                
                <div id="process-result" style="margin-top: 15px;"></div>
            </div>
        </div>
    </div>
</div>

<div class="row" style="margin-top: 20px;">
    <div class="col-sm-6">
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
    </div>
    
    <div class="col-sm-6">
        <div class="card">
            <div class="card-header">
                Импорт поисковых запросов
            </div>
            <div class="card-body">
                <form id="import-search-form" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="search-file">Файл с поисковыми запросами (по одному запросу на строку)</label>
                        <input type="file" id="search-file" name="search_file" required class="form-control">
                        <small>Поддерживаемые форматы: .txt</small>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Импортировать запросы</button>
                </form>
                
                <div id="search-import-result" style="margin-top: 15px;"></div>
            </div>
        </div>
    </div>
</div>

<div class="row" style="margin-top: 20px;">
    <div class="col-sm-12">
        <div class="card">
            <div class="card-header">
                Генерация описаний с помощью ChatGPT
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-sm-6">
                        <div class="card">
                            <div class="card-header">
                                Описание сайта
                            </div>
                            <div class="card-body">
                                <button id="generate-site-description" class="btn btn-primary">Сгенерировать описание сайта</button>
                                <div id="site-description-result" style="margin-top: 15px;"></div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-sm-6">
                        <div class="card">
                            <div class="card-header">
                                Описания категорий
                            </div>
                            <div class="card-body">
                                <div class="form-group">
                                    <label for="category-id">Выберите категорию</label>
                                    <select id="category-id" name="category_id" class="form-control">
                                        <option value="">-- Выберите категорию --</option>
                                        <?php 
                                        $categories = get_categories();
                                        foreach ($categories as $category): 
                                        ?>
                                            <option value="<?php echo $category['id']; ?>"><?php echo htmlspecialchars($category['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <button id="generate-category-description" class="btn btn-primary">Сгенерировать описание категории</button>
                                <div id="category-description-result" style="margin-top: 15px;"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Импорт видео из XML
    document.getElementById('import-xml-form').addEventListener('submit', function(e) {
        e.preventDefault();
        
        var url = document.getElementById('xml-url').value;
        var resultElement = document.getElementById('import-result');
        var progressElement = document.getElementById('import-progress');
        var progressBar = document.getElementById('progress-bar');
        var progressText = document.getElementById('progress-text');
        
        if (!url) {
            resultElement.className = 'alert alert-danger';
            resultElement.textContent = 'Пожалуйста, укажите URL XML-фида';
            return;
        }
        
        // Показываем прогресс
        resultElement.textContent = '';
        progressElement.style.display = 'block';
        progressBar.style.width = '0%';
        progressText.textContent = 'Загрузка: 0%';
        
        // Имитация прогресса
        var progress = 0;
        var progressInterval = setInterval(function() {
            progress += 5;
            if (progress > 90) {
                clearInterval(progressInterval);
            }
            progressBar.style.width = progress + '%';
            progressText.textContent = 'Загрузка: ' + progress + '%';
        }, 500);
        
        // Отправляем запрос
        fetch('admin.php?action=api&method=import_xml', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({url: url})
        })
        .then(response => response.json())
        .then(result => {
            // Останавливаем анимацию прогресса
            clearInterval(progressInterval);
            progressBar.style.width = '100%';
            progressText.textContent = 'Загрузка: 100%';
            
            // Показываем результат
            if (result.success) {
                resultElement.className = 'alert alert-success';
                resultElement.textContent = result.message;
            } else {
                resultElement.className = 'alert alert-danger';
                resultElement.textContent = result.error;
            }
            
            // Скрываем прогресс через 2 секунды
            setTimeout(function() {
                progressElement.style.display = 'none';
            }, 2000);
        })
        .catch(error => {
            console.error('Error:', error);
            
            // Останавливаем анимацию прогресса
            clearInterval(progressInterval);
            progressElement.style.display = 'none';
            
            // Показываем ошибку
            resultElement.className = 'alert alert-danger';
            resultElement.textContent = 'Произошла ошибка при отправке запроса';
        });
    });
    
    // Обработка видео с помощью ChatGPT
    document.getElementById('process-form').addEventListener('submit', function(e) {
        e.preventDefault();
        
        var limit = document.getElementById('process-limit').value;
        var resultElement = document.getElementById('process-result');
        
        if (!limit || limit < 1) {
            resultElement.className = 'alert alert-danger';
            resultElement.textContent = 'Пожалуйста, укажите корректное количество видео';
            return;
        }
        
        // Показываем сообщение о загрузке
        resultElement.className = 'alert alert-info';
        resultElement.textContent = 'Выполняется обработка видео...';
        
        // Отправляем запрос
        fetch('admin.php?action=api&method=process_videos', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({limit: limit})
        })
        .then(response => response.json())
        .then(result => {
            // Показываем результат
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
            
            // Показываем ошибку
            resultElement.className = 'alert alert-danger';
            resultElement.textContent = 'Произошла ошибка при отправке запроса';
        });
    });
    
    // Импорт тегов из файла
    document.getElementById('import-tags-form').addEventListener('submit', function(e) {
        e.preventDefault();
        
        var resultElement = document.getElementById('tags-import-result');
        var formData = new FormData(this);
        
        // Проверяем, что файл выбран
        if (!document.getElementById('tag-file').files.length) {
            resultElement.className = 'alert alert-danger';
            resultElement.textContent = 'Пожалуйста, выберите файл';
            return;
        }
        
        // Показываем сообщение о загрузке
        resultElement.className = 'alert alert-info';
        resultElement.textContent = 'Выполняется импорт тегов...';
        
        // Отправляем запрос
        fetch('admin.php?action=api&method=import_tags', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(result => {
            // Показываем результат
            if (result.success) {
                resultElement.className = 'alert alert-success';
                resultElement.textContent = result.message;
                // Очищаем поле выбора файла
                document.getElementById('tag-file').value = '';
            } else {
                resultElement.className = 'alert alert-danger';
                resultElement.textContent = result.error;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            
            // Показываем ошибку
            resultElement.className = 'alert alert-danger';
            resultElement.textContent = 'Произошла ошибка при отправке запроса';
        });
    });
    
    // Импорт поисковых запросов из файла
    document.getElementById('import-search-form').addEventListener('submit', function(e) {
        e.preventDefault();
        
        var resultElement = document.getElementById('search-import-result');
        var formData = new FormData(this);
        
        // Проверяем, что файл выбран
        if (!document.getElementById('search-file').files.length) {
            resultElement.className = 'alert alert-danger';
            resultElement.textContent = 'Пожалуйста, выберите файл';
            return;
        }
        
        // Показываем сообщение о загрузке
        resultElement.className = 'alert alert-info';
        resultElement.textContent = 'Выполняется импорт поисковых запросов...';
        
        // Отправляем запрос
        fetch('admin.php?action=api&method=import_search_queries', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(result => {
            // Показываем результат
            if (result.success) {
                resultElement.className = 'alert alert-success';
                resultElement.textContent = result.message;
                // Очищаем поле выбора файла
                document.getElementById('search-file').value = '';
            } else {
                resultElement.className = 'alert alert-danger';
                resultElement.textContent = result.error;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            
            // Показываем ошибку
            resultElement.className = 'alert alert-danger';
            resultElement.textContent = 'Произошла ошибка при отправке запроса';
        });
    });
    
    // Генерация описания сайта
    document.getElementById('generate-site-description').addEventListener('click', function() {
        var resultElement = document.getElementById('site-description-result');
        
        // Показываем сообщение о загрузке
        resultElement.className = 'alert alert-info';
        resultElement.textContent = 'Генерация описания сайта...';
        
        // Отправляем запрос
        fetch('admin.php?action=api&method=generate_site_description', {
            method: 'POST'
        })
        .then(response => response.json())
        .then(result => {
            // Показываем результат
            if (result.success) {
                resultElement.className = 'alert alert-success';
                resultElement.innerHTML = result.message + '<br><br><strong>Описание:</strong><br>' + 
                                         result.data.description;
            } else {
                resultElement.className = 'alert alert-danger';
                resultElement.textContent = result.error;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            
            // Показываем ошибку
            resultElement.className = 'alert alert-danger';
            resultElement.textContent = 'Произошла ошибка при отправке запроса';
        });
    });
    
    // Генерация описания категории
    document.getElementById('generate-category-description').addEventListener('click', function() {
        var categoryId = document.getElementById('category-id').value;
        var resultElement = document.getElementById('category-description-result');
        
        if (!categoryId) {
            resultElement.className = 'alert alert-danger';
            resultElement.textContent = 'Пожалуйста, выберите категорию';
            return;
        }
        
        // Показываем сообщение о загрузке
        resultElement.className = 'alert alert-info';
        resultElement.textContent = 'Генерация описания категории...';
        
        // Отправляем запрос
        fetch('admin.php?action=api&method=generate_category_description', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({category_id: categoryId})
        })
        .then(response => response.json())
        .then(result => {
            // Показываем результат
            if (result.success) {
                resultElement.className = 'alert alert-success';
                resultElement.innerHTML = result.message + '<br><br><strong>Описание:</strong><br>' + 
                                         result.data.description;
            } else {
                resultElement.className = 'alert alert-danger';
                resultElement.textContent = result.error;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            
            // Показываем ошибку
            resultElement.className = 'alert alert-danger';
            resultElement.textContent = 'Произошла ошибка при отправке запроса';
        });
    });
});
</script>