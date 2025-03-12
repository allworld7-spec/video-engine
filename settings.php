<?php if (!defined('VIDEOSYSTEM')) die('Прямой доступ запрещен!'); ?>

<div class="card">
    <div class="card-header">
        Настройки сайта
    </div>
    <div class="card-body">
        <form id="settings-form">
            <div class="tab-container">
                <div class="tab-buttons">
                    <button type="button" onclick="openTab(event, 'general')" class="btn btn-primary tab-button active">Общие</button>
                    <button type="button" onclick="openTab(event, 'videos')" class="btn btn-primary tab-button">Видео</button>
                    <button type="button" onclick="openTab(event, 'api')" class="btn btn-primary tab-button">API</button>
                    <button type="button" onclick="openTab(event, 'network')" class="btn btn-primary tab-button">Сеть</button>
                    <button type="button" onclick="openTab(event, 'advanced')" class="btn btn-primary tab-button">Дополнительно</button>
                    <button type="button" onclick="openTab(event, 'chatgpt')" class="btn btn-primary tab-button">ChatGPT</button>
                </div>
            </div>
            
            <div id="general" class="tab-content" style="display: block;">
                <h3>Общие настройки</h3>
                
                <div class="form-group">
                    <label for="site_enabled">
                        <input type="checkbox" id="site_enabled" name="settings[site_enabled]" value="1" <?php echo (isset($settings['site_enabled']) && $settings['site_enabled'] == '1') || !isset($settings['site_enabled']) ? 'checked' : ''; ?>>
                        Сайт включен
                    </label>
                    <small>Если сайт выключен, посетители увидят страницу с сообщением о техническом обслуживании</small>
                </div>
                
                <div class="form-group">
                    <label for="site_name">Название сайта</label>
                    <input type="text" id="site_name" name="settings[site_name]" value="<?php echo htmlspecialchars($settings['site_name'] ?? getConfig('site.name')); ?>" class="form-control">
                </div>
                
                <div class="form-group">
                    <label for="site_description">Описание сайта</label>
                    <textarea id="site_description" name="settings[site_description]" class="form-control"><?php echo htmlspecialchars($settings['site_description'] ?? ''); ?></textarea>
                </div>
                
                <div class="form-group">
                    <label for="admin_email">Email администратора</label>
                    <input type="email" id="admin_email" name="settings[admin_email]" value="<?php echo htmlspecialchars($settings['admin_email'] ?? getConfig('site.admin_email')); ?>" class="form-control">
                </div>
                
                <div class="form-group">
                    <label for="timezone">Часовой пояс</label>
                    <select id="timezone" name="settings[timezone]" class="form-control">
                        <?php
                        $timezones = timezone_identifiers_list();
                        $currentTimezone = $settings['timezone'] ?? getConfig('site.timezone');
                        
                        foreach ($timezones as $timezone) {
                            $selected = ($timezone === $currentTimezone) ? 'selected' : '';
                            echo "<option value=\"{$timezone}\" {$selected}>{$timezone}</option>";
                        }
                        ?>
                    </select>
                </div>
            </div>
<div id="videos" class="tab-content" style="display: none;">
                <h3>Настройки видео</h3>
                
                <div class="form-group">
                    <label for="videos_per_page">Видео на странице</label>
                    <input type="number" id="videos_per_page" name="settings[videos_per_page]" min="1" max="100" value="<?php echo intval($settings['videos_per_page'] ?? getConfig('videos.per_page')); ?>" class="form-control">
                </div>
                
                <div class="form-group">
                    <label for="related_count">Количество похожих видео</label>
                    <input type="number" id="related_count" name="settings[related_count]" min="0" max="50" value="<?php echo intval($settings['related_count'] ?? getConfig('videos.related_count')); ?>" class="form-control">
                </div>
                
                <div class="form-group">
                    <label for="default_sorting">Сортировка по умолчанию</label>
                    <select id="default_sorting" name="settings[default_sorting]" class="form-control">
                        <?php
                        $sortOptions = [
                            'date' => 'По дате',
                            'popularity' => 'По популярности',
                            'rating' => 'По рейтингу'
                        ];
                        
                        $currentSorting = $settings['default_sorting'] ?? getConfig('videos.default_sorting');
                        
                        foreach ($sortOptions as $value => $label) {
                            $selected = ($value === $currentSorting) ? 'selected' : '';
                            echo "<option value=\"{$value}\" {$selected}>{$label}</option>";
                        }
                        ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="use_hotlink">
                        <input type="checkbox" id="use_hotlink" name="settings[use_hotlink]" value="1" <?php echo (isset($settings['use_hotlink']) && $settings['use_hotlink'] == '1') || getConfig('videos.use_hotlink') ? 'checked' : ''; ?>>
                        Использовать защиту от хотлинка
                    </label>
                </div>
                
                <div class="form-group">
                    <label for="hotlink_expire">Время жизни ссылки (в секундах)</label>
                    <input type="number" id="hotlink_expire" name="settings[hotlink_expire]" min="60" max="86400" value="<?php echo intval($settings['hotlink_expire'] ?? getConfig('videos.hotlink_expire')); ?>" class="form-control">
                </div>
            </div>
            
            <div id="api" class="tab-content" style="display: none;">
                <h3>Настройки API</h3>
                
                <div class="form-group">
                    <label for="chatgpt_enabled">
                        <input type="checkbox" id="chatgpt_enabled" name="settings[chatgpt_enabled]" value="1" <?php echo (isset($settings['chatgpt_enabled']) && $settings['chatgpt_enabled'] == '1') || getConfig('chatgpt.enabled') ? 'checked' : ''; ?>>
                        Включить интеграцию с ChatGPT
                    </label>
                </div>
                
                <div class="form-group">
                    <label for="chatgpt_api_key">API ключ ChatGPT</label>
                    <input type="text" id="chatgpt_api_key" name="settings[chatgpt_api_key]" value="<?php echo htmlspecialchars($settings['chatgpt_api_key'] ?? getConfig('chatgpt.api_key')); ?>" class="form-control">
                    <small>Необходим для рерайта заголовков и генерации описаний</small>
                </div>
                
                <div class="form-group">
                    <label for="chatgpt_model">Модель ChatGPT</label>
                    <select id="chatgpt_model" name="settings[chatgpt_model]" class="form-control">
                        <?php
                        $models = [
                            'gpt-3.5-turbo' => 'GPT-3.5 Turbo',
                            'gpt-3.5-turbo-16k' => 'GPT-3.5 Turbo 16K',
                            'gpt-4' => 'GPT-4',
                            'gpt-4-turbo' => 'GPT-4 Turbo',
                            'gpt-4-32k' => 'GPT-4 32K'
                        ];
                        
                        $currentModel = $settings['chatgpt_model'] ?? getConfig('chatgpt.model');
                        
                        foreach ($models as $value => $label) {
                            $selected = ($value === $currentModel) ? 'selected' : '';
                            echo "<option value=\"{$value}\" {$selected}>{$label}</option>";
                        }
                        ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="cloudflare_enabled">
                        <input type="checkbox" id="cloudflare_enabled" name="settings[cloudflare_enabled]" value="1" <?php echo (isset($settings['cloudflare_enabled']) && $settings['cloudflare_enabled'] == '1') || getConfig('cloudflare.enabled') ? 'checked' : ''; ?>>
                        Включить интеграцию с CloudFlare
                    </label>
                </div>
                
                <div class="form-group">
                    <label for="cloudflare_email">Email CloudFlare</label>
                    <input type="email" id="cloudflare_email" name="settings[cloudflare_email]" value="<?php echo htmlspecialchars($settings['cloudflare_email'] ?? getConfig('cloudflare.email')); ?>" class="form-control">
                </div>
                
                <div class="form-group">
                    <label for="cloudflare_api_key">API ключ CloudFlare</label>
                    <input type="text" id="cloudflare_api_key" name="settings[cloudflare_api_key]" value="<?php echo htmlspecialchars($settings['cloudflare_api_key'] ?? getConfig('cloudflare.api_key')); ?>" class="form-control">
                </div>
                
                <div class="form-group">
                    <label for="cloudflare_zone_id">Zone ID CloudFlare</label>
                    <input type="text" id="cloudflare_zone_id" name="settings[cloudflare_zone_id]" value="<?php echo htmlspecialchars($settings['cloudflare_zone_id'] ?? getConfig('cloudflare.zone_id')); ?>" class="form-control">
                </div>
            </div>
<div id="network" class="tab-content" style="display: none;">
                <h3>Настройки сети</h3>
                
                <div class="form-group">
                    <label for="network_enabled">
                        <input type="checkbox" id="network_enabled" name="settings[network_enabled]" value="1" <?php echo (isset($settings['network_enabled']) && $settings['network_enabled'] == '1') || getConfig('network.enabled') ? 'checked' : ''; ?>>
                        Включить сетевую интеграцию
                    </label>
                </div>
                
                <div class="form-group">
                    <label for="cross_related">
                        <input type="checkbox" id="cross_related" name="settings[cross_related]" value="1" <?php echo (isset($settings['cross_related']) && $settings['cross_related'] == '1') || getConfig('network.cross_related') ? 'checked' : ''; ?>>
                        Показывать видео с других сайтов сети в блоке похожих
                    </label>
                </div>
                
                <div class="form-group">
                    <label for="cross_related_ratio">Процент видео с других сайтов</label>
                    <input type="number" id="cross_related_ratio" name="settings[cross_related_ratio]" min="0" max="100" value="<?php echo intval($settings['cross_related_ratio'] ?? getConfig('network.cross_related_ratio')); ?>" class="form-control">
                    <small>Процент видео с других сайтов в блоке похожих</small>
                </div>
                
                <div class="form-group">
                    <label for="cross_footer_links">
                        <input type="checkbox" id="cross_footer_links" name="settings[cross_footer_links]" value="1" <?php echo (isset($settings['cross_footer_links']) && $settings['cross_footer_links'] == '1') || getConfig('network.cross_footer_links') ? 'checked' : ''; ?>>
                        Показывать ссылки на другие сайты в футере
                    </label>
                </div>
                
                <div class="form-group">
                    <label for="max_footer_links">Максимальное количество ссылок в футере</label>
                    <input type="number" id="max_footer_links" name="settings[max_footer_links]" min="0" max="50" value="<?php echo intval($settings['max_footer_links'] ?? getConfig('network.max_footer_links')); ?>" class="form-control">
                </div>
            </div>
            
            <div id="advanced" class="tab-content" style="display: none;">
                <h3>Дополнительные настройки</h3>
                
                <div class="form-group">
                    <label for="cache_enabled">
                        <input type="checkbox" id="cache_enabled" name="settings[cache_enabled]" value="1" <?php echo (isset($settings['cache_enabled']) && $settings['cache_enabled'] == '1') || getConfig('cache.enabled') ? 'checked' : ''; ?>>
                        Включить кэширование
                    </label>
                </div>
                
                <div class="form-group">
                    <label for="cache_method">Метод кэширования</label>
                    <select id="cache_method" name="settings[cache_method]" class="form-control">
                        <?php
                        $methods = [
                            'file' => 'Файловое кэширование',
                            'redis' => 'Redis',
                            'memcached' => 'Memcached'
                        ];
                        
                        $currentMethod = $settings['cache_method'] ?? getConfig('cache.method');
                        
                        foreach ($methods as $value => $label) {
                            $selected = ($value === $currentMethod) ? 'selected' : '';
                            echo "<option value=\"{$value}\" {$selected}>{$label}</option>";
                        }
                        ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="cache_expire">Время жизни кэша (в секундах)</label>
                    <input type="number" id="cache_expire" name="settings[cache_expire]" min="60" max="86400" value="<?php echo intval($settings['cache_expire'] ?? getConfig('cache.expire')); ?>" class="form-control">
                </div>
                
                <div class="form-group">
                    <label for="redis_enabled">
                        <input type="checkbox" id="redis_enabled" name="settings[redis_enabled]" value="1" <?php echo (isset($settings['redis_enabled']) && $settings['redis_enabled'] == '1') || getConfig('redis.enabled') ? 'checked' : ''; ?>>
                        Включить Redis
                    </label>
                </div>
                
                <div class="form-group">
                    <label for="redis_host">Redis хост</label>
                    <input type="text" id="redis_host" name="settings[redis_host]" value="<?php echo htmlspecialchars($settings['redis_host'] ?? getConfig('redis.host')); ?>" class="form-control">
                </div>
                
                <div class="form-group">
                    <label for="redis_port">Redis порт</label>
                    <input type="number" id="redis_port" name="settings[redis_port]" min="1" max="65535" value="<?php echo intval($settings['redis_port'] ?? getConfig('redis.port')); ?>" class="form-control">
                </div>
                
                <div class="form-group">
                    <label for="elasticsearch_enabled">
                        <input type="checkbox" id="elasticsearch_enabled" name="settings[elasticsearch_enabled]" value="1" <?php echo (isset($settings['elasticsearch_enabled']) && $settings['elasticsearch_enabled'] == '1') || getConfig('elasticsearch.enabled') ? 'checked' : ''; ?>>
                        Включить Elasticsearch
                    </label>
                </div>
                
                <div class="form-group">
                    <label for="elasticsearch_host">Elasticsearch хост</label>
                    <input type="text" id="elasticsearch_host" name="settings[elasticsearch_host]" value="<?php echo htmlspecialchars($settings['elasticsearch_host'] ?? (getConfig('elasticsearch.hosts')[0] ?? 'localhost:9200')); ?>" class="form-control">
                </div>
            </div>
<div id="chatgpt" class="tab-content" style="display: none;">
                <h3>Настройки ChatGPT</h3>
                
                <div class="card">
                    <div class="card-header">
                        Настройки генерации заголовков видео
                    </div>
                    <div class="card-body">
                        <div class="form-group">
                            <label for="chatgpt_title_prompt">Промпт для генерации заголовков</label>
                            <textarea id="chatgpt_title_prompt" name="settings[chatgpt_title_prompt]" rows="4" class="form-control"><?php echo htmlspecialchars($settings['chatgpt_title_prompt'] ?? getConfig('chatgpt.prompts.video_title_rewrite')); ?></textarea>
                            <small>Используйте {{video_title}} для указания места, где будет подставлен оригинальный заголовок</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="chatgpt_title_temperature">Температура</label>
                            <input type="number" id="chatgpt_title_temperature" name="settings[chatgpt_title_temperature]" min="0" max="1" step="0.1" value="<?php echo floatval($settings['chatgpt_title_temperature'] ?? 0.6); ?>" class="form-control">
                            <small>Чем выше значение, тем более креативным будет результат (от 0 до 1)</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="chatgpt_title_tokens">Максимальное количество токенов</label>
                            <input type="number" id="chatgpt_title_tokens" name="settings[chatgpt_title_tokens]" min="10" max="500" value="<?php echo intval($settings['chatgpt_title_tokens'] ?? 100); ?>" class="form-control">
                            <small>Рекомендуемое значение: 100</small>
                        </div>
                        
                        <div class="form-group">
                            <button type="button" id="btn-process-titles" class="btn btn-primary">Сгенерировать заголовки</button>
                            <div id="process-titles-status"></div>
                        </div>
                    </div>
                </div>
                
                <div class="card" style="margin-top: 20px;">
                    <div class="card-header">
                        Настройки генерации описаний видео
                    </div>
                    <div class="card-body">
                        <div class="form-group">
                            <label for="chatgpt_description_prompt">Промпт для генерации описаний</label>
                            <textarea id="chatgpt_description_prompt" name="settings[chatgpt_description_prompt]" rows="4" class="form-control"><?php echo htmlspecialchars($settings['chatgpt_description_prompt'] ?? getConfig('chatgpt.prompts.video_description')); ?></textarea>
                            <small>Используйте {{video_title}} для указания места, где будет подставлен заголовок видео</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="chatgpt_description_temperature">Температура</label>
                            <input type="number" id="chatgpt_description_temperature" name="settings[chatgpt_description_temperature]" min="0" max="1" step="0.1" value="<?php echo floatval($settings['chatgpt_description_temperature'] ?? 0.5); ?>" class="form-control">
                            <small>Чем выше значение, тем более креативным будет результат (от 0 до 1)</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="chatgpt_description_tokens">Максимальное количество токенов</label>
                            <input type="number" id="chatgpt_description_tokens" name="settings[chatgpt_description_tokens]" min="50" max="1000" value="<?php echo intval($settings['chatgpt_description_tokens'] ?? 250); ?>" class="form-control">
                            <small>Рекомендуемое значение: 250</small>
                        </div>
                        
                        <div class="form-group">
                            <button type="button" id="btn-process-descriptions" class="btn btn-primary">Сгенерировать описания</button>
                            <div id="process-descriptions-status"></div>
                        </div>
                    </div>
                </div>
<div class="card" style="margin-top: 20px;">
                    <div class="card-header">
                        Настройки генерации описания сайта
                    </div>
                    <div class="card-body">
                        <div class="form-group">
                            <label for="chatgpt_site_prompt">Промпт для генерации описания сайта</label>
                            <textarea id="chatgpt_site_prompt" name="settings[chatgpt_site_prompt]" rows="4" class="form-control"><?php echo htmlspecialchars($settings['chatgpt_site_prompt'] ?? getConfig('chatgpt.prompts.site_description')); ?></textarea>
                            <small>Используйте {{site_name}} для указания места, где будет подставлено название сайта</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="chatgpt_site_temperature">Температура</label>
                            <input type="number" id="chatgpt_site_temperature" name="settings[chatgpt_site_temperature]" min="0" max="1" step="0.1" value="<?php echo floatval($settings['chatgpt_site_temperature'] ?? 0.7); ?>" class="form-control">
                            <small>Чем выше значение, тем более креативным будет результат (от 0 до 1)</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="chatgpt_site_tokens">Максимальное количество токенов</label>
                            <input type="number" id="chatgpt_site_tokens" name="settings[chatgpt_site_tokens]" min="100" max="1000" value="<?php echo intval($settings['chatgpt_site_tokens'] ?? 350); ?>" class="form-control">
                            <small>Рекомендуемое значение: 350</small>
                        </div>
                        
                        <div class="form-group">
                            <button type="button" id="btn-generate-site-description" class="btn btn-primary">Сгенерировать описание сайта</button>
                            <div id="site-description-result"></div>
                        </div>
                    </div>
                </div>
                
                <div class="card" style="margin-top: 20px;">
                    <div class="card-header">
                        Настройки генерации описаний категорий
                    </div>
                    <div class="card-body">
                        <div class="form-group">
                            <label for="chatgpt_category_prompt">Промпт для генерации описаний категорий</label>
                            <textarea id="chatgpt_category_prompt" name="settings[chatgpt_category_prompt]" rows="4" class="form-control"><?php echo htmlspecialchars($settings['chatgpt_category_prompt'] ?? getConfig('chatgpt.prompts.category_description')); ?></textarea>
                            <small>Используйте {{category_name}} для указания места, где будет подставлено название категории</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="chatgpt_category_temperature">Температура</label>
                            <input type="number" id="chatgpt_category_temperature" name="settings[chatgpt_category_temperature]" min="0" max="1" step="0.1" value="<?php echo floatval($settings['chatgpt_category_temperature'] ?? 0.6); ?>" class="form-control">
                            <small>Чем выше значение, тем более креативным будет результат (от 0 до 1)</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="chatgpt_category_tokens">Максимальное количество токенов</label>
                            <input type="number" id="chatgpt_category_tokens" name="settings[chatgpt_category_tokens]" min="50" max="500" value="<?php echo intval($settings['chatgpt_category_tokens'] ?? 200); ?>" class="form-control">
                            <small>Рекомендуемое значение: 200</small>
                        </div>
                        
                        <div class="form-group">
                            <button type="button" id="btn-generate-category-descriptions" class="btn btn-primary">Сгенерировать описания категорий</button>
                            <div id="category-descriptions-result"></div>
                        </div>
                    </div>
                </div>
<div class="card" style="margin-top: 20px;">
                    <div class="card-header">
                        Настройки генерации описаний тегов
                    </div>
                    <div class="card-body">
                        <div class="form-group">
                            <label for="chatgpt_tag_prompt">Промпт для генерации описаний тегов</label>
                            <textarea id="chatgpt_tag_prompt" name="settings[chatgpt_tag_prompt]" rows="4" class="form-control"><?php echo htmlspecialchars($settings['chatgpt_tag_prompt'] ?? getConfig('chatgpt.prompts.tag_description')); ?></textarea>
                            <small>Используйте {{tag_name}} для указания места, где будет подставлено название тега</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="chatgpt_tag_temperature">Температура</label>
                            <input type="number" id="chatgpt_tag_temperature" name="settings[chatgpt_tag_temperature]" min="0" max="1" step="0.1" value="<?php echo floatval($settings['chatgpt_tag_temperature'] ?? 0.6); ?>" class="form-control">
                            <small>Чем выше значение, тем более креативным будет результат (от 0 до 1)</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="chatgpt_tag_tokens">Максимальное количество токенов</label>
                            <input type="number" id="chatgpt_tag_tokens" name="settings[chatgpt_tag_tokens]" min="50" max="500" value="<?php echo intval($settings['chatgpt_tag_tokens'] ?? 150); ?>" class="form-control">
                            <small>Рекомендуемое значение: 150</small>
                        </div>
                        
                        <div class="form-group">
                            <button type="button" id="btn-generate-tag-descriptions" class="btn btn-primary">Сгенерировать описания тегов</button>
                            <div id="tag-descriptions-result"></div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div style="margin-top: 20px;">
                <button type="button" id="save-settings" class="btn btn-success">Сохранить настройки</button>
                <div id="save-result" style="margin-top: 15px;"></div>
            </div>
        </form>
    </div>
</div>

<script>
// Функция для переключения вкладок
function openTab(evt, tabName) {
    // Скрываем все вкладки
    var tabContents = document.getElementsByClassName("tab-content");
    for (var i = 0; i < tabContents.length; i++) {
        tabContents[i].style.display = "none";
    }
    
    // Удаляем активный класс со всех кнопок
    var tabButtons = document.getElementsByClassName("tab-button");
    for (var i = 0; i < tabButtons.length; i++) {
        tabButtons[i].className = tabButtons[i].className.replace(" active", "");
    }
    
    // Показываем выбранную вкладку и делаем кнопку активной
    document.getElementById(tabName).style.display = "block";
    evt.currentTarget.className += " active";
}

document.addEventListener('DOMContentLoaded', function() {
    // Сохранение настроек
    document.getElementById('save-settings').addEventListener('click', function() {
        var form = document.getElementById('settings-form');
        var formData = new FormData(form);
        var data = {};
        
        // Преобразуем данные формы в объект
        for (var [key, value] of formData.entries()) {
            data[key] = value;
        }
        
        // Отправляем запрос
        fetch('admin.php?action=api&method=save_settings', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams(data)
        })
        .then(response => response.json())
        .then(result => {
            var resultElement = document.getElementById('save-result');
            
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
            var resultElement = document.getElementById('save-result');
            resultElement.className = 'alert alert-danger';
            resultElement.textContent = 'Произошла ошибка при отправке запроса';
        });
    });
    
    // Обработка кнопки генерации заголовков
    document.getElementById('btn-process-titles').addEventListener('click', function() {
        var statusElement = document.getElementById('process-titles-status');
        
        statusElement.className = 'alert alert-info';
        statusElement.textContent = 'Выполняется генерация заголовков...';
        
        // Отображаем прогресс-бар
        var progressHtml = `
            <div style="margin-top: 10px;">
                <div style="width: 100%; background-color: #e0e0e0; border-radius: 5px; overflow: hidden;">
                    <div id="titles-progress-bar" style="height: 20px; background-color: #4CAF50; width: 0%;"></div>
                </div>
                <p id="titles-progress-text">Обработано: 0/0</p>
            </div>
        `;
        statusElement.innerHTML += progressHtml;
        
        // Запускаем процесс
        processVideoTitles();
    });
    
    // Обработка кнопки генерации описаний
    document.getElementById('btn-process-descriptions').addEventListener('click', function() {
        var statusElement = document.getElementById('process-descriptions-status');
        
        statusElement.className = 'alert alert-info';
        statusElement.textContent = 'Выполняется генерация описаний...';
        
        // Отображаем прогресс-бар
        var progressHtml = `
            <div style="margin-top: 10px;">
                <div style="width: 100%; background-color: #e0e0e0; border-radius: 5px; overflow: hidden;">
                    <div id="descriptions-progress-bar" style="height: 20px; background-color: #4CAF50; width: 0%;"></div>
                </div>
                <p id="descriptions-progress-text">Обработано: 0/0</p>
            </div>
        `;
        statusElement.innerHTML += progressHtml;
        
        // Запускаем процесс
        processVideoDescriptions();
    });
    
    // Обработка кнопки генерации описания сайта
    document.getElementById('btn-generate-site-description').addEventListener('click', function() {
        var resultElement = document.getElementById('site-description-result');
        
        resultElement.className = 'alert alert-info';
        resultElement.textContent = 'Выполняется генерация описания сайта...';
        
        // Отправляем запрос
        fetch('admin.php?action=api&method=generate_site_description', {
            method: 'POST'
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                resultElement.className = 'alert alert-success';
                resultElement.innerHTML = result.message + '<br><br><strong>Описание:</strong><br>' + 
                                         result.data.description;
                
                // Обновляем текстовое поле с описанием сайта
                document.getElementById('site_description').value = result.data.description;
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
    
    // Обработка кнопки генерации описаний категорий
    document.getElementById('btn-generate-category-descriptions').addEventListener('click', function() {
        var resultElement = document.getElementById('category-descriptions-result');
        
        resultElement.className = 'alert alert-info';
        resultElement.textContent = 'Выполняется генерация описаний категорий...';
        
        // Отправляем запрос
        fetch('admin.php?action=api&method=generate_all_category_descriptions', {
            method: 'POST'
        })
        .then(response => response.json())
        .then(result => {
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
            resultElement.className = 'alert alert-danger';
            resultElement.textContent = 'Произошла ошибка при отправке запроса';
        });
    });
    
    // Обработка кнопки генерации описаний тегов
    document.getElementById('btn-generate-tag-descriptions').addEventListener('click', function() {
        var resultElement = document.getElementById('tag-descriptions-result');
        
        resultElement.className = 'alert alert-info';
        resultElement.textContent = 'Выполняется генерация описаний тегов...';
        
        // Отправляем запрос
        fetch('admin.php?action=api&method=generate_all_tag_descriptions', {
            method: 'POST'
        })
        .then(response => response.json())
        .then(result => {
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
            resultElement.className = 'alert alert-danger';
            resultElement.textContent = 'Произошла ошибка при отправке запроса';
        });
    });
    
    // Функция для обработки заголовков видео
    function processVideoTitles(batchSize = 10) {
        var progressBar = document.getElementById('titles-progress-bar');
        var progressText = document.getElementById('titles-progress-text');
        
        // Отправляем запрос на обработку
        fetch('admin.php?action=api&method=process_video_titles', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({limit: batchSize})
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                // Обновляем прогресс
                var processed = result.total - result.remaining;
                var percent = (processed / result.total) * 100;
                
                progressBar.style.width = percent + '%';
                progressText.textContent = 'Обработано: ' + processed + '/' + result.total;
                
                // Если есть еще видео для обработки, продолжаем
                if (result.remaining > 0 && result.processed > 0) {
                    // Продолжаем обработку через небольшую задержку
                    setTimeout(function() {
                        processVideoTitles(batchSize);
                    }, 1000);
                } else {
                    // Обработка завершена или больше нет видео для обработки
                    var statusElement = document.getElementById('process-titles-status');
                    statusElement.className = 'alert alert-success';
                    statusElement.innerHTML = 'Генерация заголовков завершена. ' + processed + ' из ' + result.total + ' видео обработано.';
                }
            } else {
                // В случае ошибки
                var statusElement = document.getElementById('process-titles-status');
                statusElement.className = 'alert alert-danger';
                statusElement.textContent = result.message || 'Произошла ошибка при обработке видео';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            
            // В случае ошибки сети повторяем попытку через некоторое время
            setTimeout(function() {
                processVideoTitles(batchSize);
            }, 5000);
        });
    }
    
    // Функция для обработки описаний видео
    function processVideoDescriptions(batchSize = 10) {
        var progressBar = document.getElementById('descriptions-progress-bar');
        var progressText = document.getElementById('descriptions-progress-text');
        
        // Отправляем запрос на обработку
        fetch('admin.php?action=api&method=process_video_descriptions', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({limit: batchSize})
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                // Обновляем прогресс
                var processed = result.total - result.remaining;
                var percent = (processed / result.total) * 100;
                
                progressBar.style.width = percent + '%';
                progressText.textContent = 'Обработано: ' + processed + '/' + result.total;
                
                // Если есть еще видео для обработки, продолжаем
                if (result.remaining > 0 && result.processed > 0) {
                    // Продолжаем обработку через небольшую задержку
                    setTimeout(function() {
                        processVideoDescriptions(batchSize);
                    }, 1000);
                } else {
                    // Обработка завершена или больше нет видео для обработки
                    var statusElement = document.getElementById('process-descriptions-status');
                    statusElement.className = 'alert alert-success';
                    statusElement.innerHTML = 'Генерация описаний завершена. ' + processed + ' из ' + result.total + ' видео обработано.';
                }
            } else {
                // В случае ошибки
                var statusElement = document.getElementById('process-descriptions-status');
                statusElement.className = 'alert alert-danger';
                statusElement.textContent = result.message || 'Произошла ошибка при обработке видео';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            
            // В случае ошибки сети повторяем попытку через некоторое время
            setTimeout(function() {
                processVideoDescriptions(batchSize);
            }, 5000);
        });
    }
});
</script>