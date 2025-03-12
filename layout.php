<?php if (!defined('VIDEOSYSTEM')) die('Прямой доступ запрещен!'); ?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($title); ?> - Админ-панель</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f4f6f9;
            color: #333;
        }
        .wrapper {
            display: flex;
            min-height: 100vh;
        }
        .sidebar {
            width: 250px;
            background-color: #343a40;
            color: #fff;
            position: fixed;
            height: 100%;
            overflow-y: auto;
        }
        .sidebar-header {
            padding: 15px;
            text-align: center;
            border-bottom: 1px solid #4f5962;
        }
        .sidebar-header h3 {
            margin: 0;
            font-size: 20px;
        }
        .sidebar-nav {
            list-style-type: none;
            padding: 0;
            margin: 0;
        }
        .sidebar-nav li {
            margin: 0;
            padding: 0;
        }
        .sidebar-nav a {
            display: block;
            padding: 15px;
            color: #c2c7d0;
            text-decoration: none;
            border-left: 3px solid transparent;
        }
        .sidebar-nav a:hover, .sidebar-nav a.active {
            background-color: #2c3136;
            color: #fff;
            border-left-color: #3c8dbc;
        }
        .content-wrapper {
            flex: 1;
            margin-left: 250px;
            padding: 20px;
        }
        .content-header {
            padding: 15px 0;
            border-bottom: 1px solid #ddd;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .content-header h1 {
            margin: 0;
            font-size: 24px;
        }
        .btn {
            display: inline-block;
            padding: 8px 16px;
            margin-bottom: 0;
            font-size: 14px;
            font-weight: 400;
            line-height: 1.42857143;
            text-align: center;
            white-space: nowrap;
            vertical-align: middle;
            cursor: pointer;
            border: 1px solid transparent;
            border-radius: 4px;
            text-decoration: none;
        }
        .btn-primary {
            color: #fff;
            background-color: #3c8dbc;
            border-color: #367fa9;
        }
        .btn-primary:hover {
            background-color: #367fa9;
        }
        .btn-danger {
            color: #fff;
            background-color: #dd4b39;
            border-color: #d73925;
        }
        .btn-danger:hover {
            background-color: #d73925;
        }
        .btn-success {
            color: #fff;
            background-color: #00a65a;
            border-color: #008d4c;
        }
        .btn-success:hover {
            background-color: #008d4c;
        }
        .card {
            background-color: #fff;
            border-radius: 3px;
            box-shadow: 0 1px 1px rgba(0,0,0,.1);
            margin-bottom: 20px;
        }
        .card-header {
            padding: 15px;
            border-bottom: 1px solid #f4f4f4;
            background-color: #f8f9fa;
            font-size: 18px;
            font-weight: bold;
        }
        .card-body {
            padding: 15px;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
        }
        .table th, .table td {
            padding: 10px;
            border-bottom: 1px solid #ddd;
            text-align: left;
        }
        .table th {
            background-color: #f8f9fa;
        }
        .table tr:hover {
            background-color: #f5f5f5;
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        input[type="text"], input[type="password"], input[type="email"], input[type="number"], textarea, select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 3px;
            box-sizing: border-box;
        }
        textarea {
            min-height: 100px;
        }
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border: 1px solid transparent;
            border-radius: 4px;
        }
        .alert-success {
            color: #3c763d;
            background-color: #dff0d8;
            border-color: #d6e9c6;
        }
        .alert-danger {
            color: #a94442;
            background-color: #f2dede;
            border-color: #ebccd1;
        }
        .alert-info {
            color: #31708f;
            background-color: #d9edf7;
            border-color: #bce8f1;
        }
        .pagination {
            display: flex;
            list-style-type: none;
            padding: 0;
            margin: 20px 0;
        }
        .pagination li {
            margin: 0 5px;
        }
        .pagination a {
            display: block;
            padding: 8px 12px;
            text-decoration: none;
            background-color: #fff;
            border: 1px solid #ddd;
            color: #337ab7;
            border-radius: 3px;
        }
        .pagination a:hover {
            background-color: #eee;
        }
        .pagination .active a {
            background-color: #337ab7;
            color: #fff;
            border-color: #337ab7;
        }
        .small-box {
            border-radius: 3px;
            position: relative;
            display: block;
            margin-bottom: 20px;
            box-shadow: 0 1px 1px rgba(0,0,0,.1);
            color: #fff;
        }
        .small-box-content {
            padding: 15px;
        }
        .small-box h3 {
            font-size: 38px;
            margin: 0 0 10px 0;
        }
        .small-box p {
            font-size: 15px;
            margin: 0;
        }
        .small-box-footer {
            display: block;
            text-align: center;
            padding: 3px 0;
            color: #fff;
            background-color: rgba(0,0,0,.1);
            text-decoration: none;
        }
        .small-box-blue {
            background-color: #3c8dbc;
        }
        .small-box-green {
            background-color: #00a65a;
        }
        .small-box-yellow {
            background-color: #f39c12;
        }
        .small-box-red {
            background-color: #dd4b39;
        }
        .row {
            display: flex;
            flex-wrap: wrap;
            margin-right: -15px;
            margin-left: -15px;
        }
        .col-sm-3, .col-sm-6, .col-sm-4, .col-sm-8, .col-sm-12 {
            position: relative;
            width: 100%;
            min-height: 1px;
            padding-right: 15px;
            padding-left: 15px;
            box-sizing: border-box;
        }
        .col-sm-3 {
            flex: 0 0 25%;
            max-width: 25%;
        }
        .col-sm-4 {
            flex: 0 0 33.333333%;
            max-width: 33.333333%;
        }
        .col-sm-6 {
            flex: 0 0 50%;
            max-width: 50%;
        }
        .col-sm-8 {
            flex: 0 0 66.666667%;
            max-width: 66.666667%;
        }
        .col-sm-12 {
            flex: 0 0 100%;
            max-width: 100%;
        }
        .user-menu {
            text-align: right;
        }
    </style>
</head>

<body>
    <div class="wrapper">
        <div class="sidebar">
            <div class="sidebar-header">
                <h3>Админ-панель</h3>
                <p><?php echo htmlspecialchars(getConfig('site.name')); ?></p>
            </div>
            <ul class="sidebar-nav">
                <li><a href="admin.php?action=dashboard" <?php echo $action === 'dashboard' ? 'class="active"' : ''; ?>>Панель управления</a></li>
                <li><a href="admin.php?action=videos" <?php echo $action === 'videos' ? 'class="active"' : ''; ?>>Видео</a></li>
                <li><a href="admin.php?action=categories" <?php echo $action === 'categories' ? 'class="active"' : ''; ?>>Категории</a></li>
                <li><a href="admin.php?action=import" <?php echo $action === 'import' ? 'class="active"' : ''; ?>>Импорт</a></li>
                <li><a href="admin.php?action=tags" <?php echo $action === 'tags' ? 'class="active"' : ''; ?>>Теги</a></li>
                <li><a href="admin.php?action=auto_tags" <?php echo $action === 'auto_tags' ? 'class="active"' : ''; ?>>Авто-теги</a></li>
                <li><a href="admin.php?action=settings" <?php echo $action === 'settings' ? 'class="active"' : ''; ?>>Настройки</a></li>
                <li><a href="admin.php?action=network" <?php echo $action === 'network' ? 'class="active"' : ''; ?>>Сеть сайтов</a></li>
                <li><a href="admin.php?logout=1">Выход</a></li>
            </ul>
        </div>
        
        <div class="content-wrapper">
            <div class="content-header">
                <h1><?php echo htmlspecialchars($title); ?></h1>
                <div class="user-menu">
                    <a href="<?php echo getConfig('site.url'); ?>" target="_blank" class="btn btn-primary">Просмотр сайта</a>
                </div>
            </div>
            
            <div class="content">
                <?php echo $content; ?>
            </div>
        </div>
    </div>
    
    <script>
        // Базовый JavaScript для админ-панели
        document.addEventListener('DOMContentLoaded', function() {
            // Добавление подтверждения на кнопки удаления
            var deleteButtons = document.querySelectorAll('.btn-delete');
            deleteButtons.forEach(function(button) {
                button.addEventListener('click', function(e) {
                    if (!confirm('Вы уверены, что хотите удалить этот элемент?')) {
                        e.preventDefault();
                    }
                });
            });
        });
    </script>
</body>
</html>