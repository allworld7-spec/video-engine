<?php if (!defined('VIDEOSYSTEM')) die('Прямой доступ запрещен!'); ?>

<div class="card">
    <div class="card-header">
        Добавить новый сайт в сеть
    </div>
    <div class="card-body">
        <form id="add-site-form">
            <div class="row">
                <div class="col-sm-6">
                    <div class="form-group">
                        <label for="name">Название сайта</label>
                        <input type="text" id="name" name="name" required>
                    </div>
                </div>
                <div class="col-sm-6">
                    <div class="form-group">
                        <label for="url">URL сайта</label>
                        <input type="url" id="url" name="url" required placeholder="https://example.com">
                    </div>
                </div>
            </div>
            
            <div class="form-group">
                <label for="description">Описание</label>
                <textarea id="description" name="description"></textarea>
            </div>
            
            <button type="submit" class="btn btn-success">Добавить сайт</button>
        </form>
        
        <div id="add-site-result" style="margin-top: 15px;"></div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        Список сайтов сети
    </div>
    <div class="card-body">
        <table class="table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Название</th>
                    <th>URL</th>
                    <th>API ключ</th>
                    <th>Статус</th>
                    <th>Действия</th>
                </tr>
            </thead>
            <tbody id="sites-list">
                <?php foreach ($sites as $site): ?>
                <tr data-id="<?php echo $site['id']; ?>">
                    <td><?php echo $site['id']; ?></td>
                    <td><?php echo htmlspecialchars($site['name']); ?></td>
                    <td><a href="<?php echo htmlspecialchars($site['url']); ?>" target="_blank"><?php echo htmlspecialchars($site['url']); ?></a></td>
                    <td>
                        <div style="display: flex; align-items: center;">
                            <input type="text" value="<?php echo htmlspecialchars($site['api_key']); ?>" readonly style="width: 200px; margin-right: 10px;">
                            <button class="btn btn-primary btn-regenerate-api" data-id="<?php echo $site['id']; ?>" title="Сгенерировать новый API ключ"><i>🔄</i></button>
                        </div>
                    </td>
                    <td><?php echo $site['active'] ? '<span style="color: green;">Активен</span>' : '<span style="color: red;">Отключен</span>'; ?></td>
                    <td>
                        <button class="btn btn-primary btn-edit" data-id="<?php echo $site['id']; ?>">Редактировать</button>
                        <button class="btn btn-danger btn-delete" data-id="<?php echo $site['id']; ?>">Удалить</button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card">
    <div class="card-header">
        Перелинковка и обмен трафиком
    </div>
    <div class="card-body">
        <div class="form-group">
            <label>
                <input type="checkbox" id="network_enabled" <?php echo getConfig('network.enabled') ? 'checked' : ''; ?>>
                Включить обмен трафиком между сайтами
            </label>
            <small style="display: block; margin-top: 5px;">Эта опция влияет на все сайты сети. Включите её, чтобы сайты обменивались трафиком.</small>
        </div>
        
        <div class="form-group">
            <label>
                <input type="checkbox" id="cross_related" <?php echo getConfig('network.cross_related') ? 'checked' : ''; ?>>
                Показывать видео с других сайтов в блоке похожих
            </label>
        </div>
        
        <div class="form-group">
            <label for="cross_related_ratio">Процент видео с других сайтов</label>
            <input type="number" id="cross_related_ratio" min="0" max="100" value="<?php echo getConfig('network.cross_related_ratio'); ?>">
            <small style="display: block;">Процент видео с других сайтов в блоке похожих</small>
        </div>
        
        <div class="form-group">
            <label>
                <input type="checkbox" id="cross_footer_links" <?php echo getConfig('network.cross_footer_links') ? 'checked' : ''; ?>>
                Показывать ссылки на другие сайты в футере
            </label>
        </div>
        
        <div class="form-group">
            <label for="max_footer_links">Максимальное количество ссылок в футере</label>
            <input type="number" id="max_footer_links" min="0" max="50" value="<?php echo getConfig('network.max_footer_links'); ?>">
        </div>
        
        <button id="save-network-settings" class="btn btn-success">Сохранить настройки</button>
        
        <div id="network-settings-result" style="margin-top: 15px;"></div>
    </div>
</div>

<!-- Модальное окно редактирования сайта -->
<div id="edit-modal" style="display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.4);">
    <div style="background-color: #fefefe; margin: 10% auto; padding: 20px; border: 1px solid #888; width: 80%; max-width: 700px;">
        <span style="color: #aaa; float: right; font-size: 28px; font-weight: bold; cursor: pointer;" id="close-modal">&times;</span>
        <h2>Редактирование сайта</h2>
        <form id="edit-site-form">
            <input type="hidden" id="edit-id" name="id">
            <div class="row">
                <div class="col-sm-6">
                    <div class="form-group">
                        <label for="edit-name">Название сайта</label>
                        <input type="text" id="edit-name" name="name" required>
                    </div>
                </div>
                <div class="col-sm-6">
                    <div class="form-group">
                        <label for="edit-url">URL сайта</label>
                        <input type="url" id="edit-url" name="url" required>
                    </div>
                </div>
            </div>
            
            <div class="form-group">
                <label for="edit-description">Описание</label>
                <textarea id="edit-description" name="description"></textarea>
            </div>
            
            <div class="form-group">
                <label for="edit-active">
                    <input type="checkbox" id="edit-active" name="active" value="1">
                    Активен
                </label>
            </div>
            
            <button type="submit" class="btn btn-success">Сохранить изменения</button>
        </form>
        
        <div id="edit-site-result" style="margin-top: 15px;"></div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Добавление нового сайта
    document.getElementById('add-site-form').addEventListener('submit', function(e) {
        e.preventDefault();
        
        var formData = new FormData(this);
        var data = {};
        
        formData.forEach(function(value, key) {
            data[key] = value;
        });
        
        fetch('admin.php?action=api&method=add_site', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams(data)
        })
        .then(response => response.json())
        .then(result => {
            var resultElement = document.getElementById('add-site-result');
            
            if (result.success) {
                resultElement.className = 'alert alert-success';
                resultElement.textContent = result.message;
                
                // Очищаем форму
                document.getElementById('add-site-form').reset();
                
                // Добавляем новый сайт в таблицу
                var tbody = document.getElementById('sites-list');
                var row = document.createElement('tr');
                row.setAttribute('data-id', result.data.id);
                
                row.innerHTML = `
                    <td>${result.data.id}</td>
                    <td>${data.name}</td>
                    <td><a href="${data.url}" target="_blank">${data.url}</a></td>
                    <td>
                        <div style="display: flex; align-items: center;">
                            <input type="text" value="${result.data.api_key}" readonly style="width: 200px; margin-right: 10px;">
                            <button class="btn btn-primary btn-regenerate-api" data-id="${result.data.id}" title="Сгенерировать новый API ключ"><i>🔄</i></button>
                        </div>
                    </td>
                    <td><span style="color: green;">Активен</span></td>
                    <td>
                        <button class="btn btn-primary btn-edit" data-id="${result.data.id}">Редактировать</button>
                        <button class="btn btn-danger btn-delete" data-id="${result.data.id}">Удалить</button>
                    </td>
                `;
                
                tbody.appendChild(row);
                
                // Добавляем обработчики событий для новых кнопок
                addEditHandler(row.querySelector('.btn-edit'));
                addDeleteHandler(row.querySelector('.btn-delete'));
                addRegenerateHandler(row.querySelector('.btn-regenerate-api'));
            } else {
                resultElement.className = 'alert alert-danger';
                resultElement.textContent = result.error;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            var resultElement = document.getElementById('add-site-result');
            resultElement.className = 'alert alert-danger';
            resultElement.textContent = 'Произошла ошибка при отправке запроса';
        });
    });
    
    // Функция для добавления обработчика редактирования
    function addEditHandler(button) {
        button.addEventListener('click', function() {
            var id = this.getAttribute('data-id');
            var row = document.querySelector(`tr[data-id="${id}"]`);
            
            // Заполняем форму редактирования
            document.getElementById('edit-id').value = id;
            document.getElementById('edit-name').value = row.cells[1].textContent;
            document.getElementById('edit-url').value = row.cells[2].querySelector('a').textContent;
            document.getElementById('edit-active').checked = row.cells[4].querySelector('span').style.color === 'green';
            
            // Получаем описание сайта
            fetch(`admin.php?action=api&method=get_site&id=${id}`)
                .then(response => response.json())
                .then(result => {
                    if (result.success) {
                        document.getElementById('edit-description').value = result.data.description || '';
                    }
                });
            
            // Показываем модальное окно
            document.getElementById('edit-modal').style.display = 'block';
        });
    }
    
    // Функция для добавления обработчика удаления
    function addDeleteHandler(button) {
        button.addEventListener('click', function() {
            if (confirm('Вы уверены, что хотите удалить этот сайт?')) {
                var id = this.getAttribute('data-id');
                
                fetch('admin.php?action=api&method=delete_site', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({id: id})
                })
                .then(response => response.json())
                .then(result => {
                    if (result.success) {
                        // Удаляем строку из таблицы
                        document.querySelector(`tr[data-id="${id}"]`).remove();
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
    }
    
    // Функция для добавления обработчика регенерации API ключа
    function addRegenerateHandler(button) {
        button.addEventListener('click', function() {
            if (confirm('Вы уверены, что хотите сгенерировать новый API ключ? Все текущие интеграции с этим ключом перестанут работать.')) {
                var id = this.getAttribute('data-id');
                var row = document.querySelector(`tr[data-id="${id}"]`);
                var input = row.cells[3].querySelector('input');
                
                fetch('admin.php?action=api&method=regenerate_api_key', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({id: id})
                })
                .then(response => response.json())
                .then(result => {
                    if (result.success) {
                        // Обновляем API ключ
                        input.value = result.data.api_key;
                        alert('API ключ успешно обновлен!');
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
    }
    
    // Добавляем обработчики для существующих кнопок
    document.querySelectorAll('.btn-edit').forEach(addEditHandler);
    document.querySelectorAll('.btn-delete').forEach(addDeleteHandler);
    document.querySelectorAll('.btn-regenerate-api').forEach(addRegenerateHandler);
    
    // Закрытие модального окна
    document.getElementById('close-modal').addEventListener('click', function() {
        document.getElementById('edit-modal').style.display = 'none';
    });
    
    // Обработка клика вне модального окна
    window.addEventListener('click', function(event) {
        var modal = document.getElementById('edit-modal');
        if (event.target == modal) {
            modal.style.display = 'none';
        }
    });
    
    // Обработка формы редактирования
    document.getElementById('edit-site-form').addEventListener('submit', function(e) {
        e.preventDefault();
        
        var formData = new FormData(this);
        var data = {};
        
        formData.forEach(function(value, key) {
            data[key] = value;
        });
        
        // Обработка checkbox
        data.active = document.getElementById('edit-active').checked ? 1 : 0;
        
        fetch('admin.php?action=api&method=edit_site', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams(data)
        })
        .then(response => response.json())
        .then(result => {
            var resultElement = document.getElementById('edit-site-result');
            
            if (result.success) {
                resultElement.className = 'alert alert-success';
                resultElement.textContent = result.message;
                
                // Обновляем данные в таблице
                var row = document.querySelector(`tr[data-id="${data.id}"]`);
                row.cells[1].textContent = data.name;
                row.cells[2].innerHTML = `<a href="${data.url}" target="_blank">${data.url}</a>`;
                row.cells[4].innerHTML = data.active ? '<span style="color: green;">Активен</span>' : '<span style="color: red;">Отключен</span>';
                
                // Закрываем модальное окно через 1 секунду
                setTimeout(function() {
                    document.getElementById('edit-modal').style.display = 'none';
                    resultElement.textContent = '';
                }, 1000);
            } else {
                resultElement.className = 'alert alert-danger';
                resultElement.textContent = result.error;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            var resultElement = document.getElementById('edit-site-result');
            resultElement.className = 'alert alert-danger';
            resultElement.textContent = 'Произошла ошибка при отправке запроса';
        });
    });
    
    // Сохранение настроек сети
    document.getElementById('save-network-settings').addEventListener('click', function() {
        var data = {
            'settings[network_enabled]': document.getElementById('network_enabled').checked ? 1 : 0,
            'settings[cross_related]': document.getElementById('cross_related').checked ? 1 : 0,
            'settings[cross_related_ratio]': document.getElementById('cross_related_ratio').value,
            'settings[cross_footer_links]': document.getElementById('cross_footer_links').checked ? 1 : 0,
            'settings[max_footer_links]': document.getElementById('max_footer_links').value
        };
        
        fetch('admin.php?action=api&method=save_settings', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams(data)
        })
        .then(response => response.json())
        .then(result => {
            var resultElement = document.getElementById('network-settings-result');
            
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
            var resultElement = document.getElementById('network-settings-result');
            resultElement.className = 'alert alert-danger';
            resultElement.textContent = 'Произошла ошибка при отправке запроса';
        });
    });
});
</script>