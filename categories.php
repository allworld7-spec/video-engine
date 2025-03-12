<?php if (!defined('VIDEOSYSTEM')) die('Прямой доступ запрещен!'); ?>

<div class="card">
    <div class="card-header">
        Добавить новую категорию
    </div>
    <div class="card-body">
        <form id="add-category-form">
            <div class="row">
                <div class="col-sm-6">
                    <div class="form-group">
                        <label for="name">Название</label>
                        <input type="text" id="name" name="name" required>
                    </div>
                </div>
                <div class="col-sm-6">
                    <div class="form-group">
                        <label for="slug">Слаг (ЧПУ)</label>
                        <input type="text" id="slug" name="slug" required>
                    </div>
                </div>
            </div>
            
            <div class="form-group">
                <label for="description">Описание</label>
                <textarea id="description" name="description"></textarea>
            </div>
            
            <div class="form-group">
                <label for="keywords">Ключевые слова (через запятую)</label>
                <input type="text" id="keywords" name="keywords">
                <small>Используются для автоматической категоризации видео</small>
            </div>
            
            <button type="submit" class="btn btn-success">Добавить категорию</button>
        </form>
        
        <div id="add-category-result" style="margin-top: 15px;"></div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        Список категорий
    </div>
    <div class="card-body">
        <table class="table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Название</th>
                    <th>Слаг</th>
                    <th>Ключевые слова</th>
                    <th>Действия</th>
                </tr>
            </thead>
            <tbody id="categories-list">
                <?php foreach ($categories as $category): ?>
                <tr data-id="<?php echo $category['id']; ?>">
                    <td><?php echo $category['id']; ?></td>
                    <td><?php echo htmlspecialchars($category['name']); ?></td>
                    <td><?php echo htmlspecialchars($category['slug']); ?></td>
                    <td><?php echo htmlspecialchars($category['keywords'] ?? ''); ?></td>
                    <td>
                        <button class="btn btn-primary btn-edit" data-id="<?php echo $category['id']; ?>">Редактировать</button>
                        <button class="btn btn-danger btn-delete" data-id="<?php echo $category['id']; ?>">Удалить</button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Модальное окно редактирования категории -->
<div id="edit-modal" style="display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.4);">
    <div style="background-color: #fefefe; margin: 10% auto; padding: 20px; border: 1px solid #888; width: 80%; max-width: 700px;">
        <span style="color: #aaa; float: right; font-size: 28px; font-weight: bold; cursor: pointer;" id="close-modal">&times;</span>
        <h2>Редактирование категории</h2>
        <form id="edit-category-form">
            <input type="hidden" id="edit-id" name="id">
            <div class="row">
                <div class="col-sm-6">
                    <div class="form-group">
                        <label for="edit-name">Название</label>
                        <input type="text" id="edit-name" name="name" required>
                    </div>
                </div>
                <div class="col-sm-6">
                    <div class="form-group">
                        <label for="edit-slug">Слаг (ЧПУ)</label>
                        <input type="text" id="edit-slug" name="slug" required>
                    </div>
                </div>
            </div>
            
            <div class="form-group">
                <label for="edit-description">Описание</label>
                <textarea id="edit-description" name="description"></textarea>
            </div>
            
            <div class="form-group">
                <label for="edit-keywords">Ключевые слова (через запятую)</label>
                <input type="text" id="edit-keywords" name="keywords">
            </div>
            
            <button type="submit" class="btn btn-success">Сохранить изменения</button>
        </form>
        
        <div id="edit-category-result" style="margin-top: 15px;"></div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Автоматическая генерация слага из названия
    document.getElementById('name').addEventListener('input', function() {
        var name = this.value;
        var slug = name.toLowerCase()
            .replace(/[^\p{L}0-9]+/gu, '-') // Заменяем все не буквы и не цифры на дефис
            .replace(/^-+|-+$/g, '') // Удаляем дефисы в начале и конце
            .replace(/ё/g, 'yo').replace(/й/g, 'i').replace(/ц/g, 'ts').replace(/у/g, 'u').replace(/к/g, 'k')
            .replace(/е/g, 'e').replace(/н/g, 'n').replace(/г/g, 'g').replace(/ш/g, 'sh').replace(/щ/g, 'sch')
            .replace(/з/g, 'z').replace(/х/g, 'h').replace(/ъ/g, '').replace(/ф/g, 'f').replace(/ы/g, 'y')
            .replace(/в/g, 'v').replace(/а/g, 'a').replace(/п/g, 'p').replace(/р/g, 'r').replace(/о/g, 'o')
            .replace(/л/g, 'l').replace(/д/g, 'd').replace(/ж/g, 'zh').replace(/э/g, 'e').replace(/я/g, 'ya')
            .replace(/ч/g, 'ch').replace(/с/g, 's').replace(/м/g, 'm').replace(/и/g, 'i').replace(/т/g, 't')
            .replace(/б/g, 'b').replace(/ю/g, 'yu').replace(/ь/g, '');
        document.getElementById('slug').value = slug;
    });
    
    // Добавление новой категории
    document.getElementById('add-category-form').addEventListener('submit', function(e) {
        e.preventDefault();
        
        var formData = new FormData(this);
        var data = {};
        
        formData.forEach(function(value, key) {
            data[key] = value;
        });
        
        fetch('admin.php?action=api&method=add_category', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams(data)
        })
        .then(response => response.json())
        .then(result => {
            var resultElement = document.getElementById('add-category-result');
            
            if (result.success) {
                resultElement.className = 'alert alert-success';
                resultElement.textContent = result.message;
                
                // Очищаем форму
                document.getElementById('add-category-form').reset();
                
                // Добавляем новую категорию в таблицу
                var tbody = document.getElementById('categories-list');
                var row = document.createElement('tr');
                row.setAttribute('data-id', result.data.id);
                
                row.innerHTML = `
                    <td>${result.data.id}</td>
                    <td>${data.name}</td>
                    <td>${data.slug}</td>
                    <td>${data.keywords || ''}</td>
                    <td>
                        <button class="btn btn-primary btn-edit" data-id="${result.data.id}">Редактировать</button>
                        <button class="btn btn-danger btn-delete" data-id="${result.data.id}">Удалить</button>
                    </td>
                `;
                
                tbody.appendChild(row);
                
                // Добавляем обработчики событий для новых кнопок
                addEditHandler(row.querySelector('.btn-edit'));
                addDeleteHandler(row.querySelector('.btn-delete'));
            } else {
                resultElement.className = 'alert alert-danger';
                resultElement.textContent = result.error;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            var resultElement = document.getElementById('add-category-result');
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
            document.getElementById('edit-slug').value = row.cells[2].textContent;
            document.getElementById('edit-keywords').value = row.cells[3].textContent;
            
            // Получаем описание категории
            fetch(`admin.php?action=api&method=get_category&id=${id}`)
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
            if (confirm('Вы уверены, что хотите удалить эту категорию?')) {
                var id = this.getAttribute('data-id');
                
                fetch('admin.php?action=api&method=delete_category', {
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
    
    // Добавляем обработчики для существующих кнопок
    document.querySelectorAll('.btn-edit').forEach(addEditHandler);
    document.querySelectorAll('.btn-delete').forEach(addDeleteHandler);
    
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
    document.getElementById('edit-category-form').addEventListener('submit', function(e) {
        e.preventDefault();
        
        var formData = new FormData(this);
        var data = {};
        
        formData.forEach(function(value, key) {
            data[key] = value;
        });
        
        fetch('admin.php?action=api&method=edit_category', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams(data)
        })
        .then(response => response.json())
        .then(result => {
            var resultElement = document.getElementById('edit-category-result');
            
            if (result.success) {
                resultElement.className = 'alert alert-success';
                resultElement.textContent = result.message;
                
                // Обновляем данные в таблице
                var row = document.querySelector(`tr[data-id="${data.id}"]`);
                row.cells[1].textContent = data.name;
                row.cells[2].textContent = data.slug;
                row.cells[3].textContent = data.keywords || '';
                
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
            var resultElement = document.getElementById('edit-category-result');
            resultElement.className = 'alert alert-danger';
            resultElement.textContent = 'Произошла ошибка при отправке запроса';
        });
    });
    
    // Автоматическая генерация слага в форме редактирования
    document.getElementById('edit-name').addEventListener('input', function() {
        var name = this.value;
        var slug = name.toLowerCase()
            .replace(/[^\p{L}0-9]+/gu, '-') // Заменяем все не буквы и не цифры на дефис
            .replace(/^-+|-+$/g, '') // Удаляем дефисы в начале и конце
            .replace(/ё/g, 'yo').replace(/й/g, 'i').replace(/ц/g, 'ts').replace(/у/g, 'u').replace(/к/g, 'k')
            .replace(/е/g, 'e').replace(/н/g, 'n').replace(/г/g, 'g').replace(/ш/g, 'sh').replace(/щ/g, 'sch')
            .replace(/з/g, 'z').replace(/х/g, 'h').replace(/ъ/g, '').replace(/ф/g, 'f').replace(/ы/g, 'y')
            .replace(/в/g, 'v').replace(/а/g, 'a').replace(/п/g, 'p').replace(/р/g, 'r').replace(/о/g, 'o')
            .replace(/л/g, 'l').replace(/д/g, 'd').replace(/ж/g, 'zh').replace(/э/g, 'e').replace(/я/g, 'ya')
            .replace(/ч/g, 'ch').replace(/с/g, 's').replace(/м/g, 'm').replace(/и/g, 'i').replace(/т/g, 't')
            .replace(/б/g, 'b').replace(/ю/g, 'yu').replace(/ь/g, '');
        document.getElementById('edit-slug').value = slug;
    });
});
</script>