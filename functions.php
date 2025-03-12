<?php
/**
 * Основные функции движка видеосайтов
 * Содержит утилиты для работы с БД, кэшированием, API и обработкой данных
 */

// Предотвращение прямого доступа к файлу
if (!defined('VIDEOSYSTEM')) {
    die('Прямой доступ к этому файлу запрещен!');
}

// ========== РАБОТА С БАЗОЙ ДАННЫХ ==========

/**
 * Подключение к базе данных MySQL
 * @return PDO Объект подключения к БД
 */
function db_connect() {
    static $db = null;
    
    if ($db === null) {
        try {
            $dsn = 'mysql:host=' . getConfig('db.host') . ';dbname=' . getConfig('db.name') . ';charset=' . getConfig('db.charset') . ';port=' . getConfig('db.port');
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . getConfig('db.charset') . " COLLATE " . getConfig('db.collation')
            ];
            
            $db = new PDO($dsn, getConfig('db.user'), getConfig('db.pass'), $options);
        } catch (PDOException $e) {
            log_error('Database connection error: ' . $e->getMessage());
            die('Ошибка подключения к базе данных.');
        }
    }
    
    return $db;
}

/**
 * Выполнение SQL запроса
 * @param string $sql SQL запрос
 * @param array $params Параметры запроса
 * @return PDOStatement
 */
function db_query($sql, $params = []) {
    try {
        $db = db_connect();
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    } catch (PDOException $e) {
        log_error('Database query error: ' . $e->getMessage() . ' | SQL: ' . $sql);
        die('Ошибка выполнения запроса к базе данных.');
    }
}

/**
 * Получение одной записи из базы данных
 * @param string $sql SQL запрос
 * @param array $params Параметры запроса
 * @return array|null Запись или null, если запись не найдена
 */
function db_get_row($sql, $params = []) {
    $stmt = db_query($sql, $params);
    return $stmt->fetch();
}

/**
 * Получение нескольких записей из базы данных
 * @param string $sql SQL запрос
 * @param array $params Параметры запроса
 * @return array Массив записей
 */
function db_get_rows($sql, $params = []) {
    $stmt = db_query($sql, $params);
    return $stmt->fetchAll();
}

/**
 * Получение значения из базы данных
 * @param string $sql SQL запрос
 * @param array $params Параметры запроса
 * @return mixed Значение или null
 */
function db_get_var($sql, $params = []) {
    $stmt = db_query($sql, $params);
    return $stmt->fetchColumn();
}

/**
 * Вставка записи в таблицу
 * @param string $table Имя таблицы
 * @param array $data Данные для вставки
 * @return int ID вставленной записи
 */
function db_insert($table, $data) {
    $db = db_connect();
    $prefix = getConfig('db.prefix');
    
    $columns = implode(', ', array_keys($data));
    $placeholders = implode(', ', array_fill(0, count($data), '?'));
    
    $sql = "INSERT INTO {$prefix}{$table} ({$columns}) VALUES ({$placeholders})";
    
    try {
        $stmt = $db->prepare($sql);
        $stmt->execute(array_values($data));
        return $db->lastInsertId();
    } catch (PDOException $e) {
        log_error('Database insert error: ' . $e->getMessage() . ' | SQL: ' . $sql);
        return false;
    }
}

/**
 * Обновление записи в таблице
 * @param string $table Имя таблицы
 * @param array $data Данные для обновления
 * @param array $where Условия WHERE
 * @return int Количество обновленных записей
 */
function db_update($table, $data, $where) {
    $db = db_connect();
    $prefix = getConfig('db.prefix');
    
    $sets = [];
    foreach (array_keys($data) as $column) {
        $sets[] = "{$column} = ?";
    }
    
    $whereClause = [];
    foreach (array_keys($where) as $column) {
        $whereClause[] = "{$column} = ?";
    }
    
    $sql = "UPDATE {$prefix}{$table} SET " . implode(', ', $sets) . " WHERE " . implode(' AND ', $whereClause);
    
    try {
        $stmt = $db->prepare($sql);
        $stmt->execute(array_merge(array_values($data), array_values($where)));
        return $stmt->rowCount();
    } catch (PDOException $e) {
        log_error('Database update error: ' . $e->getMessage() . ' | SQL: ' . $sql);
        return false;
    }
}

/**
 * Удаление записи из таблицы
 * @param string $table Имя таблицы
 * @param array $where Условия WHERE
 * @return int Количество удаленных записей
 */
function db_delete($table, $where) {
    $db = db_connect();
    $prefix = getConfig('db.prefix');
    
    $whereClause = [];
    foreach (array_keys($where) as $column) {
        $whereClause[] = "{$column} = ?";
    }
    
    $sql = "DELETE FROM {$prefix}{$table} WHERE " . implode(' AND ', $whereClause);
    
    try {
        $stmt = $db->prepare($sql);
        $stmt->execute(array_values($where));
        return $stmt->rowCount();
    } catch (PDOException $e) {
        log_error('Database delete error: ' . $e->getMessage() . ' | SQL: ' . $sql);
        return false;
    }
}

// ========== РАБОТА С КЭШЕМ ==========

/**
 * Получение данных из кэша
 * @param string $key Ключ кэша
 * @return mixed Данные из кэша или null, если кэш не найден
 */
function cache_get($key) {
    if (!getConfig('cache.enabled')) {
        return null;
    }
    
    $method = getConfig('cache.method');
    $cacheKey = md5($key);
    
    if ($method === 'file') {
        $cacheFile = getConfig('paths.cache') . '/' . $cacheKey . '.cache';
        
        if (file_exists($cacheFile)) {
            $data = file_get_contents($cacheFile);
            $cache = unserialize($data);
            
            if ($cache['expire'] > time()) {
                return $cache['data'];
            } else {
                @unlink($cacheFile);
            }
        }
    } elseif ($method === 'redis' && getConfig('redis.enabled')) {
        try {
            $redis = redis_connect();
            $data = $redis->get(getConfig('redis.prefix') . $cacheKey);
            
            if ($data !== false) {
                return unserialize($data);
            }
        } catch (Exception $e) {
            log_error('Redis cache error: ' . $e->getMessage());
        }
    }
    
    return null;
}

/**
 * Сохранение данных в кэш
 * @param string $key Ключ кэша
 * @param mixed $data Данные для сохранения
 * @param int $expire Время жизни кэша в секундах
 * @return bool
 */
function cache_set($key, $data, $expire = null) {
    if (!getConfig('cache.enabled')) {
        return false;
    }
    
    if ($expire === null) {
        $expire = getConfig('cache.expire');
    }
    
    $method = getConfig('cache.method');
    $cacheKey = md5($key);
    
    if ($method === 'file') {
        $cacheDir = getConfig('paths.cache');
        
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }
        
        $cacheFile = $cacheDir . '/' . $cacheKey . '.cache';
        $cache = [
            'expire' => time() + $expire,
            'data' => $data
        ];
        
        return file_put_contents($cacheFile, serialize($cache)) !== false;
    } elseif ($method === 'redis' && getConfig('redis.enabled')) {
        try {
            $redis = redis_connect();
            return $redis->setex(
                getConfig('redis.prefix') . $cacheKey,
                $expire,
                serialize($data)
            );
        } catch (Exception $e) {
            log_error('Redis cache error: ' . $e->getMessage());
        }
    }
    
    return false;
}

/**
 * Очистка кэша
 * @param string $key Ключ кэша (пустая строка - очистка всего кэша)
 * @return bool
 */
function cache_clear($key = '') {
    if (!getConfig('cache.enabled')) {
        return false;
    }
    
    $method = getConfig('cache.method');
    
    if ($method === 'file') {
        $cacheDir = getConfig('paths.cache');
        
        if (!is_dir($cacheDir)) {
            return true;
        }
        
        if (empty($key)) {
            $files = glob($cacheDir . '/*.cache');
            foreach ($files as $file) {
                @unlink($file);
            }
            return true;
        } else {
            $cacheKey = md5($key);
            $cacheFile = $cacheDir . '/' . $cacheKey . '.cache';
            return @unlink($cacheFile);
        }
    } elseif ($method === 'redis' && getConfig('redis.enabled')) {
        try {
            $redis = redis_connect();
            
            if (empty($key)) {
                return $redis->flushDB();
            } else {
                $cacheKey = getConfig('redis.prefix') . md5($key);
                return $redis->del($cacheKey) > 0;
            }
        } catch (Exception $e) {
            log_error('Redis cache error: ' . $e->getMessage());
        }
    }
    
    return false;
}

/**
 * Подключение к Redis
 * @return Redis
 */
function redis_connect() {
    static $redis = null;
    
    if ($redis === null && getConfig('redis.enabled')) {
        try {
            $redis = new Redis();
            $redis->connect(
                getConfig('redis.host'),
                getConfig('redis.port')
            );
            
            if (getConfig('redis.password')) {
                $redis->auth(getConfig('redis.password'));
            }
            
            if (getConfig('redis.database') !== 0) {
                $redis->select(getConfig('redis.database'));
            }
        } catch (Exception $e) {
            log_error('Redis connection error: ' . $e->getMessage());
        }
    }
    
    return $redis;
}

// ========== РАБОТА С ELASTICSEARCH ==========

/**
 * Подключение к Elasticsearch
 * @return \Elasticsearch\Client|null
 */
function es_connect() {
    static $client = null;
    
    if ($client === null && getConfig('elasticsearch.enabled')) {
        try {
            require_once 'vendor/autoload.php';
            
            $hosts = getConfig('elasticsearch.hosts');
            $client = \Elasticsearch\ClientBuilder::create()
                ->setHosts($hosts)
                ->build();
        } catch (Exception $e) {
            log_error('Elasticsearch connection error: ' . $e->getMessage());
        }
    }
    
    return $client;
}

/**
 * Индексация видео в Elasticsearch
 * @param array $video Данные видео
 * @return bool
 */
function es_index_video($video) {
    if (!getConfig('elasticsearch.enabled')) {
        return false;
    }
    
    try {
        $client = es_connect();
        
        if (!$client) {
            return false;
        }
        
        $index = getConfig('elasticsearch.index_prefix') . 'videos';
        
        // Проверяем существование индекса
        if (!$client->indices()->exists(['index' => $index])) {
            // Создаем индекс с настройками
            $client->indices()->create([
                'index' => $index,
                'body' => [
                    'settings' => getConfig('elasticsearch.settings'),
                    'mappings' => [
                        'properties' => [
                            'id' => ['type' => 'integer'],
                            'title' => [
                                'type' => 'text',
                                'analyzer' => 'standard'
                            ],
                            'description' => [
                                'type' => 'text',
                                'analyzer' => 'standard'
                            ],
                            'tags' => [
                                'type' => 'text',
                                'analyzer' => 'standard'
                            ],
                            'categories' => [
                                'type' => 'keyword'
                            ],
                            'post_date' => [
                                'type' => 'date',
                                'format' => 'yyyy-MM-dd HH:mm:ss||yyyy-MM-dd'
                            ],
                            'popularity' => ['type' => 'integer'],
                            'rating' => ['type' => 'float']
                        ]
                    ]
                ]
            ]);
        }
        
        // Индексируем документ
        $params = [
            'index' => $index,
            'id' => $video['id'],
            'body' => [
                'id' => $video['id'],
                'title' => $video['title'],
                'description' => $video['description'] ?? '',
                'tags' => implode(' ', $video['tags'] ?? []),
                'categories' => $video['categories'] ?? [],
                'post_date' => $video['post_date'],
                'popularity' => (int)$video['popularity'],
                'rating' => (float)$video['rating']
            ]
        ];
        
        $response = $client->index($params);
        return isset($response['result']) && ($response['result'] === 'created' || $response['result'] === 'updated');
    } catch (Exception $e) {
        log_error('Elasticsearch index error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Поиск видео в Elasticsearch
 * @param string $query Поисковый запрос
 * @param int $from Смещение выборки
 * @param int $size Размер выборки
 * @param array $filters Дополнительные фильтры
 * @return array Результаты поиска
 */
function es_search_videos($query, $from = 0, $size = 24, $filters = []) {
    if (!getConfig('elasticsearch.enabled')) {
        return ['total' => 0, 'videos' => []];
    }
    
    try {
        $client = es_connect();
        
        if (!$client) {
            return ['total' => 0, 'videos' => []];
        }
        
        $index = getConfig('elasticsearch.index_prefix') . 'videos';
        
        // Проверяем существование индекса
        if (!$client->indices()->exists(['index' => $index])) {
            return ['total' => 0, 'videos' => []];
        }
        
        // Формируем запрос
        $params = [
            'index' => $index,
            'from' => $from,
            'size' => $size,
            'body' => [
                'query' => [
                    'bool' => [
                        'must' => [
                            'multi_match' => [
                                'query' => $query,
                                'fields' => ['title^3', 'description', 'tags^2']
                            ]
                        ]
                    ]
                ],
                'sort' => []
            ]
        ];
        
        // Добавляем фильтры
        if (!empty($filters)) {
            foreach ($filters as $field => $value) {
                if ($field === 'categories' && is_array($value)) {
                    $params['body']['query']['bool']['filter'][] = [
                        'terms' => ['categories' => $value]
                    ];
                } elseif ($field === 'date_from' && !empty($value)) {
                    $params['body']['query']['bool']['filter'][] = [
                        'range' => [
                            'post_date' => ['gte' => $value]
                        ]
                    ];
                } elseif ($field === 'date_to' && !empty($value)) {
                    $params['body']['query']['bool']['filter'][] = [
                        'range' => [
                            'post_date' => ['lte' => $value]
                        ]
                    ];
                }
            }
        }
        
        // Добавляем сортировку
        if (!empty($filters['sort'])) {
            if ($filters['sort'] === 'date') {
                $params['body']['sort'][] = ['post_date' => ['order' => 'desc']];
            } elseif ($filters['sort'] === 'popularity') {
                $params['body']['sort'][] = ['popularity' => ['order' => 'desc']];
            } elseif ($filters['sort'] === 'rating') {
                $params['body']['sort'][] = ['rating' => ['order' => 'desc']];
            }
        } else {
            // По умолчанию сортировка по релевантности
            $params['body']['sort'][] = ['_score' => ['order' => 'desc']];
        }
        
        $response = $client->search($params);
        
        $results = [
            'total' => $response['hits']['total']['value'],
            'videos' => []
        ];
        
        foreach ($response['hits']['hits'] as $hit) {
            $results['videos'][] = [
                'id' => $hit['_source']['id'],
                'title' => $hit['_source']['title'],
                'description' => $hit['_source']['description'],
                'post_date' => $hit['_source']['post_date'],
                'popularity' => $hit['_source']['popularity'],
                'rating' => $hit['_source']['rating'],
                'categories' => $hit['_source']['categories'],
                'score' => $hit['_score']
            ];
        }
        
        return $results;
    } catch (Exception $e) {
        log_error('Elasticsearch search error: ' . $e->getMessage());
        return ['total' => 0, 'videos' => []];
    }
}

// ========== РАБОТА С API ==========

/**
 * Отправка HTTP запроса
 * @param string $url URL
 * @param string $method HTTP метод (GET, POST, PUT, DELETE)
 * @param array $data Данные запроса
 * @param array $headers Заголовки запроса
 * @return array Ответ [body, status_code, headers]
 */
function http_request($url, $method = 'GET', $data = [], $headers = []) {
    $options = [
        'http' => [
            'method' => $method,
            'header' => [],
            'ignore_errors' => true
        ]
    ];
    
    // Добавляем заголовки
    foreach ($headers as $name => $value) {
        $options['http']['header'][] = "{$name}: {$value}";
    }
    
    // Добавляем данные
    if (!empty($data)) {
        if ($method === 'GET') {
            $url .= (strpos($url, '?') === false ? '?' : '&') . http_build_query($data);
        } else {
            $options['http']['header'][] = 'Content-Type: application/json';
            $options['http']['content'] = json_encode($data);
        }
    }
    
    $options['http']['header'] = implode("\r\n", $options['http']['header']);
    
    $context = stream_context_create($options);
    $response = file_get_contents($url, false, $context);
    
    return [
        'body' => $response,
        'status_code' => $http_response_header[0],
        'headers' => $http_response_header
    ];
}

/**
 * Отправка запроса к API CloudFlare
 * @param string $endpoint Конечная точка API
 * @param string $method HTTP метод
 * @param array $data Данные запроса
 * @return array Ответ API
 */
function cloudflare_api($endpoint, $method = 'GET', $data = []) {
    if (!getConfig('cloudflare.enabled')) {
        return ['success' => false, 'errors' => [['message' => 'CloudFlare API не настроен']]];
    }
    
    $apiKey = getConfig('cloudflare.api_key');
    $email = getConfig('cloudflare.email');
    
    $url = 'https://api.cloudflare.com/client/v4/' . ltrim($endpoint, '/');
    
    $headers = [
        'X-Auth-Email' => $email,
        'X-Auth-Key' => $apiKey,
        'Content-Type' => 'application/json'
    ];
    
    $response = http_request($url, $method, $data, $headers);
    
    if ($response['body'] === false) {
        return ['success' => false, 'errors' => [['message' => 'Ошибка соединения с CloudFlare API']]];
    }
    
    return json_decode($response['body'], true);
}

/**
 * Отправка запроса к API ChatGPT
 * @param string $prompt Текст запроса
 * @param array $options Дополнительные параметры
 * @return string Ответ ChatGPT
 */
function chatgpt_api($prompt, $options = []) {
    if (!getConfig('chatgpt.enabled')) {
        return '';
    }
    
    $apiKey = getConfig('chatgpt.api_key');
    $model = getConfig('chatgpt.model');
    
    $url = 'https://api.openai.com/v1/chat/completions';
    
    $headers = [
        'Authorization' => 'Bearer ' . $apiKey,
        'Content-Type' => 'application/json'
    ];
    
    $data = [
        'model' => $model,
        'messages' => [
            ['role' => 'user', 'content' => $prompt]
        ],
        'max_tokens' => $options['max_tokens'] ?? 500,
        'temperature' => floatval($options['temperature'] ?? 0.7)
    ];
    
    // Выполняем повторные попытки при ошибках соединения
    $maxRetries = 3;
    $retryDelay = 2; // начальная задержка в секундах
    
    for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
        try {
            $response = http_request($url, 'POST', $data, $headers);
            
            if ($response['body'] === false) {
                throw new Exception('Connection failed');
            }
            
            $result = json_decode($response['body'], true);
            
            if (isset($result['choices'][0]['message']['content'])) {
                return trim($result['choices'][0]['message']['content']);
            }
            
            if (isset($result['error'])) {
                throw new Exception($result['error']['message']);
            } else {
                throw new Exception('Unknown error format: ' . json_encode($result));
            }
        } catch (Exception $e) {
            $errorMessage = $e->getMessage();
            
            // Регистрируем ошибку
            log_error('ChatGPT API error (attempt ' . $attempt . '/' . $maxRetries . '): ' . $errorMessage);
            
            // Проверяем, нужно ли повторить попытку
            $shouldRetry = $attempt < $maxRetries && (
                strpos($errorMessage, 'rate limit') !== false ||
                strpos($errorMessage, 'timeout') !== false ||
                strpos($errorMessage, 'Connection failed') !== false
            );
            
            if (!$shouldRetry) {
                // Если не нужно повторять или это была последняя попытка
                break;
            }
            
            // Экспоненциальная задержка перед повторной попыткой
            $sleepTime = $retryDelay * pow(2, $attempt - 1);
            sleep($sleepTime);
        }
    }
    
    return '';
}

/**
 * Получение статуса обработки видео через ChatGPT
 * @return array Информация о процессе
 */
function getChatGPTProcessingStatus() {
    $status = [
        'total_videos' => db_get_var("SELECT COUNT(*) FROM " . getConfig('db.prefix') . "videos"),
        'processed_titles' => db_get_var("SELECT COUNT(*) FROM " . getConfig('db.prefix') . "videos WHERE processed_title = 1"),
        'processed_descriptions' => db_get_var("SELECT COUNT(*) FROM " . getConfig('db.prefix') . "videos WHERE processed_description = 1"),
        'last_processed_title_id' => db_get_var("SELECT value FROM " . getConfig('db.prefix') . "settings WHERE name = 'chatgpt_last_title_id'") ?? 0,
        'last_processed_description_id' => db_get_var("SELECT value FROM " . getConfig('db.prefix') . "settings WHERE name = 'chatgpt_last_description_id'") ?? 0
    ];
    
    // Добавляем процент выполнения
    if ($status['total_videos'] > 0) {
        $status['title_progress_percent'] = round(($status['processed_titles'] / $status['total_videos']) * 100, 2);
        $status['description_progress_percent'] = round(($status['processed_descriptions'] / $status['total_videos']) * 100, 2);
    } else {
        $status['title_progress_percent'] = 0;
        $status['description_progress_percent'] = 0;
    }
    
    return $status;
}

/**
 * Рерайт заголовков видео через ChatGPT
 * @param int $limit Количество видео для обработки
 * @return array Результаты обработки
 */
function processVideoTitlesWithChatGPT($limit = 10) {
    if (!getConfig('chatgpt.enabled')) {
        return [
            'success' => false,
            'message' => 'ChatGPT API не настроен',
            'processed' => 0,
            'total' => 0,
            'remaining' => 0
        ];
    }
    
    // Получаем последний обработанный ID
    $lastId = db_get_var("SELECT value FROM " . getConfig('db.prefix') . "settings WHERE name = 'chatgpt_last_title_id'") ?? 0;
    
    // Получаем видео без обработанных заголовков
    $sql = "SELECT * FROM " . getConfig('db.prefix') . "videos 
            WHERE processed_title = 0 AND id > ? 
            ORDER BY id ASC LIMIT {$limit}";
    
    $videos = db_get_rows($sql, [$lastId]);
    
    if (empty($videos)) {
        // Если не осталось необработанных видео после lastId, начинаем сначала
        $sql = "SELECT * FROM " . getConfig('db.prefix') . "videos 
                WHERE processed_title = 0
                ORDER BY id ASC LIMIT {$limit}";
        
        $videos = db_get_rows($sql);
        
        if (empty($videos)) {
            // Все видео обработаны
            return [
                'success' => true,
                'message' => 'Все заголовки видео уже обработаны',
                'processed' => 0,
                'total' => db_get_var("SELECT COUNT(*) FROM " . getConfig('db.prefix') . "videos"),
                'remaining' => 0
            ];
        }
    }
    
    $processed = 0;
    $lastProcessedId = $lastId;
    
    foreach ($videos as $video) {
        // Запрос к ChatGPT для рерайта заголовка
        $prompt = db_get_var("SELECT value FROM " . getConfig('db.prefix') . "settings WHERE name = 'chatgpt_title_prompt'") ?? 
                 getConfig('chatgpt.prompts.video_title_rewrite');
        
        $temperature = floatval(db_get_var("SELECT value FROM " . getConfig('db.prefix') . "settings WHERE name = 'chatgpt_title_temperature'") ?? 0.6);
        
        $maxTokens = intval(db_get_var("SELECT value FROM " . getConfig('db.prefix') . "settings WHERE name = 'chatgpt_title_tokens'") ?? 100);
        
        $prompt = str_replace('{{video_title}}', $video['title'], $prompt);
        
        $options = [
            'temperature' => $temperature,
            'max_tokens' => $maxTokens
        ];
        
        $newTitle = chatgpt_api($prompt, $options);
        
        if (!empty($newTitle)) {
            // Обновляем заголовок видео и устанавливаем флаг обработки
            db_update('videos', [
                'title' => $newTitle,
                'processed_title' => 1
            ], ['id' => $video['id']]);
            
            $processed++;
        }
        
        // Обновляем последний обработанный ID независимо от результата (чтобы не зацикливаться)
        $lastProcessedId = $video['id'];
        
        // Сохраняем последний обработанный ID после каждой обработки
        db_query(
            "INSERT INTO " . getConfig('db.prefix') . "settings (name, value) 
            VALUES ('chatgpt_last_title_id', ?) ON DUPLICATE KEY UPDATE value = ?",
            [$lastProcessedId, $lastProcessedId]
        );
    }
    
    // Получаем общее количество видео и оставшихся для обработки
    $total = db_get_var("SELECT COUNT(*) FROM " . getConfig('db.prefix') . "videos");
    $remaining = db_get_var("SELECT COUNT(*) FROM " . getConfig('db.prefix') . "videos WHERE processed_title = 0");
    
    return [
        'success' => true,
        'message' => "Обработано {$processed} заголовков видео",
        'processed' => $processed,
        'total' => $total,
        'remaining' => $remaining
    ];
}

/**
 * Генерация описаний видео через ChatGPT
 * @param int $limit Количество видео для обработки
 * @return array Результаты обработки
 */
function processVideoDescriptionsWithChatGPT($limit = 10) {
    if (!getConfig('chatgpt.enabled')) {
        return [
            'success' => false,
            'message' => 'ChatGPT API не настроен',
            'processed' => 0,
            'total' => 0,
            'remaining' => 0
        ];
    }
    
    // Получаем последний обработанный ID для описаний
    $lastId = db_get_var("SELECT value FROM " . getConfig('db.prefix') . "settings WHERE name = 'chatgpt_last_description_id'") ?? 0;
    
    // Получаем видео с обработанными заголовками, но без обработанных описаний
    $sql = "SELECT * FROM " . getConfig('db.prefix') . "videos 
            WHERE processed_title = 1 AND processed_description = 0 AND id > ? 
            ORDER BY id ASC LIMIT {$limit}";
    
    $videos = db_get_rows($sql, [$lastId]);
    
    if (empty($videos)) {
        // Если не осталось необработанных видео после lastId, начинаем сначала
        $sql = "SELECT * FROM " . getConfig('db.prefix') . "videos 
                WHERE processed_title = 1 AND processed_description = 0
                ORDER BY id ASC LIMIT {$limit}";
        
        $videos = db_get_rows($sql);
        
        if (empty($videos)) {
            // Все видео с обработанными заголовками уже имеют описания
            return [
                'success' => true,
                'message' => 'Все описания видео уже обработаны',
                'processed' => 0,
                'total' => db_get_var("SELECT COUNT(*) FROM " . getConfig('db.prefix') . "videos"),
                'remaining' => 0
            ];
        }
    }
    
    $processed = 0;
    $lastProcessedId = $lastId;
    
    foreach ($videos as $video) {
        // Запрос к ChatGPT для генерации описания
        $prompt = db_get_var("SELECT value FROM " . getConfig('db.prefix') . "settings WHERE name = 'chatgpt_description_prompt'") ?? 
                 getConfig('chatgpt.prompts.video_description');
        
        $temperature = floatval(db_get_var("SELECT value FROM " . getConfig('db.prefix') . "settings WHERE name = 'chatgpt_description_temperature'") ?? 0.5);
        
        $maxTokens = intval(db_get_var("SELECT value FROM " . getConfig('db.prefix') . "settings WHERE name = 'chatgpt_description_tokens'") ?? 250);
        
        $prompt = str_replace('{{video_title}}', $video['title'], $prompt);
        
        $options = [
            'temperature' => $temperature,
            'max_tokens' => $maxTokens
        ];
        
        $newDescription = chatgpt_api($prompt, $options);
        if (!empty($newDescription)) {
            // Обновляем описание видео и устанавливаем флаг обработки
            db_update('videos', [
                'description' => $newDescription,
                'processed_description' => 1
            ], ['id' => $video['id']]);
            
            $processed++;
        }
        
        // Обновляем последний обработанный ID независимо от результата
        $lastProcessedId = $video['id'];
        
        // Сохраняем последний обработанный ID после каждой обработки
        db_query(
            "INSERT INTO " . getConfig('db.prefix') . "settings (name, value) 
            VALUES ('chatgpt_last_description_id', ?) ON DUPLICATE KEY UPDATE value = ?",
            [$lastProcessedId, $lastProcessedId]
        );
    }
    
    // Получаем общее количество видео и оставшихся для обработки
    $total = db_get_var("SELECT COUNT(*) FROM " . getConfig('db.prefix') . "videos");
    $remaining = db_get_var("SELECT COUNT(*) FROM " . getConfig('db.prefix') . "videos WHERE processed_title = 1 AND processed_description = 0");
    
    return [
        'success' => true,
        'message' => "Обработано {$processed} описаний видео",
        'processed' => $processed,
        'total' => $total,
        'remaining' => $remaining
    ];
}

/**
 * Генерация описаний для всех категорий через ChatGPT
 * @return array Результаты обработки
 */
function generateAllCategoryDescriptions() {
    if (!getConfig('chatgpt.enabled')) {
        return [
            'success' => false,
            'message' => 'ChatGPT API не настроен',
            'processed' => 0,
            'total' => 0
        ];
    }
    
    // Получаем категории, которые имеют видео
    $categories = db_get_rows(
        "SELECT c.* FROM " . getConfig('db.prefix') . "categories c
        WHERE EXISTS (
            SELECT 1 FROM " . getConfig('db.prefix') . "video_categories vc 
            WHERE vc.category_id = c.id
        )"
    );
    
    if (empty($categories)) {
        return [
            'success' => true,
            'message' => 'Нет категорий с видео для обработки',
            'processed' => 0,
            'total' => 0
        ];
    }
    
    $processed = 0;
    
    foreach ($categories as $category) {
        $prompt = db_get_var("SELECT value FROM " . getConfig('db.prefix') . "settings WHERE name = 'chatgpt_category_prompt'") ?? 
                 getConfig('chatgpt.prompts.category_description');
        
        $temperature = floatval(db_get_var("SELECT value FROM " . getConfig('db.prefix') . "settings WHERE name = 'chatgpt_category_temperature'") ?? 0.6);
        
        $maxTokens = intval(db_get_var("SELECT value FROM " . getConfig('db.prefix') . "settings WHERE name = 'chatgpt_category_tokens'") ?? 200);
        
        $prompt = str_replace('{{category_name}}', $category['name'], $prompt);
        
        $options = [
            'temperature' => $temperature,
            'max_tokens' => $maxTokens
        ];
        
        $description = chatgpt_api($prompt, $options);
        
        if (!empty($description)) {
            // Обновляем описание категории
            db_update('categories', ['description' => $description], ['id' => $category['id']]);
            $processed++;
        }
    }
    
    return [
        'success' => true,
        'message' => "Обработано {$processed} из " . count($categories) . " категорий",
        'processed' => $processed,
        'total' => count($categories)
    ];
}

/**
 * Генерация описаний для всех тегов через ChatGPT
 * @return array Результаты обработки
 */
function generateAllTagDescriptions() {
    if (!getConfig('chatgpt.enabled')) {
        return [
            'success' => false,
            'message' => 'ChatGPT API не настроен',
            'processed' => 0,
            'total' => 0
        ];
    }
    
    // Получаем теги, которые имеют видео
    $tags = db_get_rows(
        "SELECT t.* FROM " . getConfig('db.prefix') . "tags t
        WHERE EXISTS (
            SELECT 1 FROM " . getConfig('db.prefix') . "video_tags vt 
            WHERE vt.tag_id = t.id
        )"
    );
    
    if (empty($tags)) {
        return [
            'success' => true,
            'message' => 'Нет тегов с видео для обработки',
            'processed' => 0,
            'total' => 0
        ];
    }
    
    $processed = 0;
    
    foreach ($tags as $tag) {
        $prompt = db_get_var("SELECT value FROM " . getConfig('db.prefix') . "settings WHERE name = 'chatgpt_tag_prompt'") ?? 
                 getConfig('chatgpt.prompts.tag_description');
        
        $temperature = floatval(db_get_var("SELECT value FROM " . getConfig('db.prefix') . "settings WHERE name = 'chatgpt_tag_temperature'") ?? 0.6);
        
        $maxTokens = intval(db_get_var("SELECT value FROM " . getConfig('db.prefix') . "settings WHERE name = 'chatgpt_tag_tokens'") ?? 150);
        
        $prompt = str_replace('{{tag_name}}', $tag['name'], $prompt);
        
        $options = [
            'temperature' => $temperature,
            'max_tokens' => $maxTokens
        ];
        
        $description = chatgpt_api($prompt, $options);
        
        if (!empty($description)) {
            // Обновляем описание тега
            db_update('tags', ['description' => $description], ['id' => $tag['id']]);
            $processed++;
        }
    }
    
    return [
        'success' => true,
        'message' => "Обработано {$processed} из " . count($tags) . " тегов",
        'processed' => $processed,
        'total' => count($tags)
    ];
}

/**
 * Генерация описания для сайта через ChatGPT
 * @return string Сгенерированное описание или пустая строка в случае ошибки
 */
function generateSiteDescription() {
    if (!getConfig('chatgpt.enabled')) {
        return '';
    }
    
    $prompt = db_get_var("SELECT value FROM " . getConfig('db.prefix') . "settings WHERE name = 'chatgpt_site_prompt'") ?? 
             getConfig('chatgpt.prompts.site_description');
    
    $temperature = floatval(db_get_var("SELECT value FROM " . getConfig('db.prefix') . "settings WHERE name = 'chatgpt_site_temperature'") ?? 0.7);
    
    $maxTokens = intval(db_get_var("SELECT value FROM " . getConfig('db.prefix') . "settings WHERE name = 'chatgpt_site_tokens'") ?? 350);
    
    $prompt = str_replace('{{site_name}}', getConfig('site.name'), $prompt);
    
    $options = [
        'temperature' => $temperature,
        'max_tokens' => $maxTokens
    ];
    
    $description = chatgpt_api($prompt, $options);
    
    if (!empty($description)) {
        // Сохраняем описание в настройки
        db_query(
            "INSERT INTO " . getConfig('db.prefix') . "settings (name, value) 
            VALUES ('site_description', ?) ON DUPLICATE KEY UPDATE value = ?",
            [$description, $description]
        );
        
        // Очищаем кэш
        cache_clear();
    }
    
    return $description;
}

/**
 * Автоматический подбор тегов для всех видео
 * @param int $limit Количество видео для обработки
 * @return array Результаты обработки
 */
function autoTagAllVideos($limit = 50) {
    // Получаем видео без тегов
    $lastId = db_get_var("SELECT value FROM " . getConfig('db.prefix') . "settings WHERE name = 'auto_tag_last_id'") ?? 0;
    
    $sql = "SELECT v.id, v.title, v.description FROM " . getConfig('db.prefix') . "videos v
            LEFT JOIN " . getConfig('db.prefix') . "video_tags vt ON v.id = vt.video_id
            WHERE v.id > ? AND vt.tag_id IS NULL
            GROUP BY v.id
            ORDER BY v.id ASC
            LIMIT {$limit}";
    
    $videos = db_get_rows($sql, [$lastId]);
    
    if (empty($videos)) {
        // Если не осталось необработанных видео после lastId, начинаем сначала
        $sql = "SELECT v.id, v.title, v.description FROM " . getConfig('db.prefix') . "videos v
                LEFT JOIN " . getConfig('db.prefix') . "video_tags vt ON v.id = vt.video_id
                WHERE vt.tag_id IS NULL
                GROUP BY v.id
                ORDER BY v.id ASC
                LIMIT {$limit}";
        
        $videos = db_get_rows($sql);
        
        if (empty($videos)) {
            return [
                'success' => true,
                'message' => 'Все видео уже имеют теги',
                'processed' => 0,
                'total' => 0
            ];
        }
    }
    
    $processed = 0;
    $lastProcessedId = $lastId;
    
    foreach ($videos as $video) {
        $matchedTags = advanced_auto_generate_tags([
            'title' => $video['title'],
            'description' => $video['description']
        ]);
        
        if (!empty($matchedTags)) {
            foreach ($matchedTags as $tagId) {
                db_insert('video_tags', [
                    'video_id' => $video['id'],
                    'tag_id' => $tagId
                ]);
            }
            
            $processed++;
        }
        
        $lastProcessedId = $video['id'];
    }
    
    // Сохраняем последний обработанный ID
    db_query(
        "INSERT INTO " . getConfig('db.prefix') . "settings (name, value) 
        VALUES ('auto_tag_last_id', ?) ON DUPLICATE KEY UPDATE value = ?",
        [$lastProcessedId, $lastProcessedId]
    );
    
    return [
        'success' => true,
        'message' => "Добавлены теги для {$processed} видео",
        'processed' => $processed,
        'total' => count($videos)
    ];
}

/**
 * Улучшенный автоматический подбор тегов для видео
 * @param array $video Данные видео (включая title и description)
 * @return array Массив с ID подходящих тегов
 */
function advanced_auto_generate_tags($video) {
    $title = mb_strtolower($video['title']);
    $description = mb_strtolower($video['description'] ?? '');
    $content = $title . ' ' . $description;
    
    // Исключаемые слова
    $stopWords = ['и', 'в', 'на', 'с', 'по', 'за', 'к', 'от', 'из', 'а', 'о', 'у', 'е', 'что', 'как', 'так', 'где', 
                 'когда', 'или', 'то', 'но', 'не', 'да', 'всё', 'все', 'a', 'the', 'and', 'or', 'for', 'with', 'in', 
                 'on', 'at', 'to', 'of', 'by', 'as', 'is', 'are', 'was', 'were', 'be', 'this', 'that', 'these', 'those'];
    
    // Русские синонимы и формы слов
    $synonyms = [
        'смешн' => ['смешная', 'смешной', 'смешное', 'смешные', 'смешно', 'прикольн', 'забавн'],
        'кошк' => ['кошка', 'кошки', 'кошке', 'кошку', 'кошкой', 'кошечк', 'кот', 'котик', 'котенок'],
        'собак' => ['собака', 'собаки', 'собаке', 'собаку', 'собакой', 'пес', 'пса', 'псу', 'псом', 'щенок'],
        'молод' => ['молодая', 'молодой', 'молодое', 'молодые', 'юная', 'юный', 'юное', 'юные', 'молодежь'],
        'зрел' => ['зрелая', 'зрелый', 'зрелое', 'зрелые', 'взрослая', 'взрослый', 'взрослое', 'взрослые'],
        'красив' => ['красивая', 'красивый', 'красивое', 'красивые', 'прекрасн', 'привлекательн'],
        'секс' => ['секс', 'сексуальн', 'эротичн', 'интим'],
        'блондинк' => ['блондинка', 'блондинки', 'блондинку', 'блондинкой', 'блондин', 'светловолос'],
        'брюнетк' => ['брюнетка', 'брюнетки', 'брюнетку', 'брюнеткой', 'брюнет', 'темноволос'],
        'рыжая' => ['рыжая', 'рыжий', 'рыжее', 'рыжие', 'рыжеволос'],
        'анал' => ['анал', 'анальн', 'задниц', 'попа', 'попу', 'попой', 'попке', 'зад', 'жоп'],
        'груд' => ['груд', 'грудью', 'сиски', 'сиськи', 'сиси', 'тить', 'биг-титс'],
        'минет' => ['минет', 'отсос', 'сосет', 'сосут', 'оральн', 'орал', 'глоток'],
        'лесби' => ['лесби', 'лесбиянк', 'лесбийск', 'лезби', 'девушки'],
        'больш' => ['больш', 'огромн', 'гигант']
    ];
    
    // Получаем все теги из базы для сравнения
    $allTags = db_get_rows("SELECT * FROM " . getConfig('db.prefix') . "tags");
    $tagMatches = [];
    
    // Сопоставляем каждый тег с контентом
    foreach ($allTags as $tag) {
        $tagName = mb_strtolower($tag['name']);
        
        // Если тег содержит несколько слов, разбиваем его
        $tagWords = explode(' ', $tagName);
        
        // Проверка совпадения для многословных тегов
        if (count($tagWords) > 1) {
            $allWordsFound = true;
            
            foreach ($tagWords as $word) {
                if (strlen($word) <= 3 || in_array($word, $stopWords)) {
                    continue; // Пропускаем короткие слова и стоп-слова
                }
                
                $wordFound = false;
                
                // Прямое совпадение
                if (mb_strpos($content, $word) !== false) {
                    $wordFound = true;
                } else {
                    // Проверка синонимов
                    foreach ($synonyms as $base => $forms) {
                        if (mb_strpos($word, $base) === 0) { // Слово начинается с базовой формы
                            foreach ($forms as $form) {
                                if (mb_strpos($content, $form) !== false) {
                                    $wordFound = true;
                                    break 2;
                                }
                            }
                        }
                    }
                }
                
                if (!$wordFound) {
                    $allWordsFound = false;
                    break;
                }
            }
            
            if ($allWordsFound) {
                $tagMatches[] = $tag['id'];
            }
        } else {
            // Для однословных тегов
            if (mb_strpos($content, $tagName) !== false) {
                $tagMatches[] = $tag['id'];
            } else {
                // Проверка синонимов для однословных тегов
                foreach ($synonyms as $base => $forms) {
                    if (mb_strpos($tagName, $base) === 0) { // Тег начинается с базовой формы
                        foreach ($forms as $form) {
                            if (mb_strpos($content, $form) !== false) {
                                $tagMatches[] = $tag['id'];
                                break 2;
                            }
                        }
                    }
                }
            }
        }
    }
    
    return array_unique($tagMatches);
}

/**
 * Запуск фонового поиска видео по поисковым запросам
 * @param int $minVideos Минимальное количество видео для поискового запроса
 * @return array Результаты обработки
 */
function runBackgroundSearch($minVideos = 5) {
    // Получаем все поисковые запросы
    $queries = db_get_rows(
        "SELECT * FROM " . getConfig('db.prefix') . "search_queries 
        ORDER BY count DESC"
    );
    
    $processed = 0;
    $validQueries = 0;
    
    foreach ($queries as $query) {
        // Получаем результаты поиска для запроса
        $videoIds = search_videos_for_query($query['query'], false);
        
        if (count($videoIds) >= $minVideos) {
            // Помечаем запрос как имеющий достаточно результатов
            db_update('search_queries', [
                'has_results' => 1,
                'result_count' => count($videoIds)
            ], ['id' => $query['id']]);
            
            $validQueries++;
        } else {
            // Помечаем запрос как не имеющий достаточно результатов
            db_update('search_queries', [
                'has_results' => 0,
                'result_count' => count($videoIds)
            ], ['id' => $query['id']]);
        }
        
        $processed++;
    }
    
    return [
        'success' => true,
        'message' => "Обработано {$processed} поисковых запросов, из них {$validQueries} имеют достаточно результатов",
        'processed' => $processed,
        'valid_queries' => $validQueries
    ];
}

/**
 * Генерация названия видео с помощью ChatGPT
 * @param string $title Исходное название
 * @return string Новое название
 */
function generate_video_title($title) {
    $prompt = db_get_var("SELECT value FROM " . getConfig('db.prefix') . "settings WHERE name = 'chatgpt_title_prompt'");
    $temperature = floatval(db_get_var("SELECT value FROM " . getConfig('db.prefix') . "settings WHERE name = 'chatgpt_title_temperature'") ?? 0.6);
    $max_tokens = intval(db_get_var("SELECT value FROM " . getConfig('db.prefix') . "settings WHERE name = 'chatgpt_title_max_tokens'") ?? 100);
    
    if (empty($prompt)) {
        $prompt = 'Перепиши название видео для взрослых, сохранив ключевые слова, но сделав его более уникальным: {{video_title}}';
    }
    
    $prompt = str_replace('{{video_title}}', $title, $prompt);
    
    $options = [
        'temperature' => $temperature,
        'max_tokens' => $max_tokens
    ];
    
    return chatgpt_api($prompt, $options);
}

/**
 * Генерация описания видео с помощью ChatGPT
 * @param string $title Название видео
 * @return string Описание
 */
function generate_video_description($title) {
    $prompt = db_get_var("SELECT value FROM " . getConfig('db.prefix') . "settings WHERE name = 'chatgpt_description_prompt'");
    $temperature = floatval(db_get_var("SELECT value FROM " . getConfig('db.prefix') . "settings WHERE name = 'chatgpt_description_temperature'") ?? 0.5);
    $max_tokens = intval(db_get_var("SELECT value FROM " . getConfig('db.prefix') . "settings WHERE name = 'chatgpt_description_max_tokens'") ?? 250);
    
    if (empty($prompt)) {
        $prompt = 'Создай подробное описание для видео для взрослых на основе названия. Добавь больше деталей и ключевых слов. Название: {{video_title}}';
    }
    
    $prompt = str_replace('{{video_title}}', $title, $prompt);
    
    $options = [
        'temperature' => $temperature,
        'max_tokens' => $max_tokens
    ];
    
    return chatgpt_api($prompt, $options);
}

/**
 * Генерация описания сайта с помощью ChatGPT
 * @param string $siteName Название сайта
 * @return string Описание
 */
function generate_site_description($siteName) {
    $prompt = db_get_var("SELECT value FROM " . getConfig('db.prefix') . "settings WHERE name = 'chatgpt_site_description_prompt'");
    $temperature = floatval(db_get_var("SELECT value FROM " . getConfig('db.prefix') . "settings WHERE name = 'chatgpt_site_description_temperature'") ?? 0.7);
    $max_tokens = intval(db_get_var("SELECT value FROM " . getConfig('db.prefix') . "settings WHERE name = 'chatgpt_site_description_max_tokens'") ?? 350);
    
    if (empty($prompt)) {
        $prompt = 'Напиши уникальное SEO-оптимизированное описание для сайта с видео контентом для взрослых. Два абзаца, не более 300 символов. Название сайта: {{site_name}}';
    }
    
    $prompt = str_replace('{{site_name}}', $siteName, $prompt);
    
    $options = [
        'temperature' => $temperature,
        'max_tokens' => $max_tokens
    ];
    
    return chatgpt_api($prompt, $options);
}

/**
 * Генерация описания категории с помощью ChatGPT
 * @param string $categoryName Название категории
 * @return string Описание
 */
function generate_category_description($categoryName) {
    $prompt = db_get_var("SELECT value FROM " . getConfig('db.prefix') . "settings WHERE name = 'chatgpt_category_description_prompt'");
    $temperature = floatval(db_get_var("SELECT value FROM " . getConfig('db.prefix') . "settings WHERE name = 'chatgpt_category_description_temperature'") ?? 0.6);
    $max_tokens = intval(db_get_var("SELECT value FROM " . getConfig('db.prefix') . "settings WHERE name = 'chatgpt_category_description_max_tokens'") ?? 200);
    
    if (empty($prompt)) {
        $prompt = 'Напиши уникальное SEO-оптимизированное описание категории видео для взрослых: {{category_name}}. Один абзац, не более 150 символов.';
    }
    
    $prompt = str_replace('{{category_name}}', $categoryName, $prompt);
    
    $options = [
        'temperature' => $temperature,
        'max_tokens' => $max_tokens
    ];
    
    return chatgpt_api($prompt, $options);
}

/**
 * Генерация описания тега с помощью ChatGPT
 * @param string $tagName Название тега
 * @return string Описание
 */
function generate_tag_description($tagName) {
    $prompt = db_get_var("SELECT value FROM " . getConfig('db.prefix') . "settings WHERE name = 'chatgpt_tag_description_prompt'");
    $temperature = floatval(db_get_var("SELECT value FROM " . getConfig('db.prefix') . "settings WHERE name = 'chatgpt_tag_description_temperature'") ?? 0.6);
    $max_tokens = intval(db_get_var("SELECT value FROM " . getConfig('db.prefix') . "settings WHERE name = 'chatgpt_tag_description_max_tokens'") ?? 150);
    
    if (empty($prompt)) {
        $prompt = 'Напиши краткое SEO-оптимизированное описание для тега видео для взрослых: {{tag_name}}. Одно предложение, не более 100 символов.';
    }
    
    $prompt = str_replace('{{tag_name}}', $tagName, $prompt);
    
    $options = [
        'temperature' => $temperature,
        'max_tokens' => $max_tokens
    ];
    
    return chatgpt_api($prompt, $options);
}

// ========== РАБОТА С ВИДЕО ==========

/**
 * Парсинг XML фида с видео
 * @param string $url URL XML фида
 * @return array Массив с видео
 */
function parse_video_xml($url) {
    try {
        // Используем контекст с отключенной проверкой SSL сертификата
        $arrContextOptions = [
            "ssl" => [
                "verify_peer" => false,
                "verify_peer_name" => false,
            ],
        ];
        
        // Сначала пробуем загрузить содержимое URL с отключенной проверкой SSL
        $xml_content = file_get_contents($url, false, stream_context_create($arrContextOptions));
        
        if ($xml_content === false) {
            log_error('Failed to get content from URL: ' . $url);
            return [];
        }
        
        // Затем загружаем XML из полученного содержимого
        $xml = simplexml_load_string($xml_content);
        
        if ($xml === false) {
            log_error('Failed to parse XML: ' . $url);
            return [];
        }
        
        $videos = [];
        
        foreach ($xml->video as $item) {
            $video = [];
            
            // Извлекаем основные данные
            $video['id'] = (string)$item->id;
            $video['title'] = (string)$item->title;
            $video['dir'] = (string)$item->dir;
            $video['description'] = (string)$item->description;
            $video['rating'] = (float)$item->rating;
            $video['votes'] = (int)$item->votes;
            $video['popularity'] = (int)$item->popularity;
            $video['post_date'] = (string)$item->post_date;
            $video['user'] = (string)$item->user;
            $video['link'] = (string)$item->link;
            $video['embed'] = (string)$item->embed;
            
            // Получаем данные о файле
            if (isset($item->files->file)) {
                $video['duration'] = (int)$item->files->file->duration;
                $video['width'] = (int)$item->files->file->width;
                $video['height'] = (int)$item->files->file->height;
                $video['filesize'] = (int)$item->files->file->filesize;
                $video['file_url'] = (string)$item->files->file->url;
            }
            
            // Получаем скриншоты
            $video['screens'] = [];
            $video['main_screen'] = isset($item->screens['main']) ? (int)$item->screens['main'] : 1;
            
            if (isset($item->screens->screen)) {
                $i = 1;
                foreach ($item->screens->screen as $screen) {
                    $video['screens'][$i] = (string)$screen;
                    $i++;
                }
            }
            
            $videos[] = $video;
        }
        
        return $videos;
    } catch (Exception $e) {
        log_error('XML parsing error: ' . $e->getMessage());
        return [];
    }
}

/**
 * Улучшенная обработка файла с превью
 * @param string $url URL исходного изображения
 * @param int $videoId ID видео
 * @return string URL обработанного изображения
 */
function process_thumbnail($url, $videoId) {
    $cacheKey = 'thumb_' . md5($url);
    $cached = cache_get($cacheKey);
    
    if ($cached !== null) {
        return $cached;
    }
    
    try {
        // Создаем директорию для обработанных скриншотов
        $thumbsDir = getConfig('paths.thumbs');
        
        if (!is_dir($thumbsDir)) {
            mkdir($thumbsDir, 0755, true);
        }
        
        // Получаем расширение файла
        $pathInfo = pathinfo($url);
        $extension = isset($pathInfo['extension']) ? strtolower($pathInfo['extension']) : 'jpg';
        
        // Формируем имя файла
        $filename = $videoId . '_' . md5($url) . '.' . $extension;
        $thumbPath = $thumbsDir . '/' . $filename;
        
        // Проверяем существование файла
        if (file_exists($thumbPath)) {
            $thumbUrl = getConfig('site.url') . '/thumbs/' . $filename;
            cache_set($cacheKey, $thumbUrl, 86400); // кэшируем на сутки
            return $thumbUrl;
        }
        
        // Настройка контекста для игнорирования проверки SSL
        $arrContextOptions = [
            "ssl" => [
                "verify_peer" => false,
                "verify_peer_name" => false,
            ],
        ];
        
        // Загружаем изображение с отключенной проверкой SSL
        $imageData = file_get_contents($url, false, stream_context_create($arrContextOptions));
        
        if ($imageData === false) {
            log_error('Failed to download thumbnail: ' . $url);
            return $url;
        }
        
        // Создаем изображение из данных
        $image = imagecreatefromstring($imageData);
        
        if ($image === false) {
            log_error('Failed to create image from data: ' . $url);
            return $url;
        }
        
        // Получаем размеры изображения
        $width = imagesx($image);
        $height = imagesy($image);
        
        // Апскейл изображения с сохранением качества, если оно маленькое
        if ($width < 640 || $height < 360) {
            $newWidth = max($width, 640);
            $newHeight = max($height, 360);
            
            // Сохраняем пропорции
            if ($width / $height > $newWidth / $newHeight) {
                $newHeight = round($height * $newWidth / $width);
            } else {
                $newWidth = round($width * $newHeight / $height);
            }
            
            // Создаем новое изображение с увеличенными размерами
            $newImage = imagecreatetruecolor($newWidth, $newHeight);
            
            // Включаем альфа-канал для прозрачности
            imagealphablending($newImage, false);
            imagesavealpha($newImage, true);
            
            // Включаем более качественный алгоритм интерполяции
            imagesetinterpolation($newImage, IMG_BICUBIC);
            
            // Используем более качественный алгоритм ресайза
            imagecopyresampled($newImage, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
            
            // Освобождаем память от исходного изображения
            imagedestroy($image);
            
            // Обновляем ссылку на изображение и его размеры
            $image = $newImage;
            $width = $newWidth;
            $height = $newHeight;
        }
        
        // Добавляем водяной знак с URL сайта
        $siteUrl = getConfig('site.url');
        // Удаляем http(s):// из URL для водяного знака
        $siteUrl = preg_replace('#^https?://#', '', $siteUrl);
        
        // Выбираем случайное расположение для водяного знака (один из четырех углов)
        $position = mt_rand(1, 4);
        
        // Настройки размера водяного знака
        $fontSize = max($width * 0.020, 10); // Размер шрифта - 2% от ширины, но не меньше 10px
        $padding = max($width * 0.015, 5);   // Отступ от края - 1.5% от ширины, но не меньше 5px
        
        // Создаем полупрозрачный фон для водяного знака
        $watermarkBgColor = imagecolorallocatealpha($image, 0, 0, 0, 75); // 75 = ~70% непрозрачности
        
        // Находим подходящий TTF шрифт
        $fontFile = __DIR__ . '/assets/fonts/arial.ttf';
        
        // Если указанный шрифт не найден, ищем системные шрифты
        if (!file_exists($fontFile)) {
            // Проверяем стандартные пути к шрифтам
            $fontPaths = [
                '/usr/share/fonts/truetype/ttf-dejavu/DejaVuSans.ttf', // Linux
                '/usr/share/fonts/truetype/ubuntu/Ubuntu-R.ttf',       // Ubuntu
                '/Library/Fonts/Arial.ttf',                           // Mac
                'C:\\Windows\\Fonts\\arial.ttf',                      // Windows
                'C:\\Windows\\Fonts\\verdana.ttf'                     // Windows alternative
            ];
            
            foreach ($fontPaths as $path) {
                if (file_exists($path)) {
                    $fontFile = $path;
                    break;
                }
            }
        }
        
        // Цвет текста водяного знака
        $textColor = imagecolorallocate($image, 255, 255, 255);
        
        // Если нашли TrueType шрифт, используем его
        if (file_exists($fontFile) && function_exists('imagettftext')) {
            // Определяем размеры текста
            $bbox = imagettfbbox($fontSize, 0, $fontFile, $siteUrl);
            $textWidth = $bbox[2] - $bbox[0];
            $textHeight = $bbox[1] - $bbox[7];
            
            // Добавляем полупрозрачный фон для водяного знака
            switch ($position) {
                case 1: // Верхний левый угол
                    imagefilledrectangle(
                        $image,
                        $padding,
                        $padding,
                        $padding + $textWidth + 10,
                        $padding + $textHeight + 10,
                        $watermarkBgColor
                    );
                    imagettftext(
                        $image,
                        $fontSize,
                        0,
                        $padding + 5,
                        $padding + $textHeight + 5,
                        $textColor,
                        $fontFile,
                        $siteUrl
                    );
                    break;
                case 2: // Верхний правый угол
                    imagefilledrectangle(
                        $image,
                        $width - $padding - $textWidth - 10,
                        $padding,
                        $width - $padding,
                        $padding + $textHeight + 10,
                        $watermarkBgColor
                    );
                    imagettftext(
                        $image,
                        $fontSize,
                        0,
                        $width - $padding - $textWidth - 5,
                        $padding + $textHeight + 5,
                        $textColor,
                        $fontFile,
                        $siteUrl
                    );
                    break;
                case 3: // Нижний правый угол
                    imagefilledrectangle(
                        $image,
                        $width - $padding - $textWidth - 10,
                        $height - $padding - $textHeight - 10,
                        $width - $padding,
                        $height - $padding,
                        $watermarkBgColor
                    );
                    imagettftext(
                        $image,
                        $fontSize,
                        0,
                        $width - $padding - $textWidth - 5,
                        $height - $padding - 5,
                        $textColor,
                        $fontFile,
                        $siteUrl
                    );
                    break;
                case 4: // Нижний левый угол
                    imagefilledrectangle(
                        $image,
                        $padding,
                        $height - $padding - $textHeight - 10,
                        $padding + $textWidth + 10,
                        $height - $padding,
                        $watermarkBgColor
                    );
                    imagettftext(
                        $image,
                        $fontSize,
                        0,
                        $padding + 5,
                        $height - $padding - 5,
                        $textColor,
                        $fontFile,
                        $siteUrl
                    );
                    break;
            }
        } else {
            // Если не нашли TrueType шрифт, используем встроенный
            $fontSize = 3; // Размер шрифта для imagestring (1-5)
            $textWidth = strlen($siteUrl) * imagefontwidth($fontSize);
            $textHeight = imagefontheight($fontSize);
            
            // Добавляем полупрозрачный фон для водяного знака
            switch ($position) {
                case 1: // Верхний левый угол
                    imagefilledrectangle(
                        $image,
                        $padding,
                        $padding,
                        $padding + $textWidth + 10,
                        $padding + $textHeight + 10,
                        $watermarkBgColor
                    );
                    imagestring(
                        $image,
                        $fontSize,
                        $padding + 5,
                        $padding + 5,
                        $siteUrl,
                        $textColor
                    );
                    break;
                case 2: // Верхний правый угол
                    imagefilledrectangle(
                        $image,
                        $width - $padding - $textWidth - 10,
                        $padding,
                        $width - $padding,
                        $padding + $textHeight + 10,
                        $watermarkBgColor
                    );
                    imagestring(
                        $image,
                        $fontSize,
                        $width - $padding - $textWidth - 5,
                        $padding + 5,
                        $siteUrl,
                        $textColor
                    );
                    break;
                case 3: // Нижний правый угол
                    imagefilledrectangle(
                        $image,
                        $width - $padding - $textWidth - 10,
                        $height - $padding - $textHeight - 10,
                        $width - $padding,
                        $height - $padding,
                        $watermarkBgColor
                    );
                    imagestring(
                        $image,
                        $fontSize,
                        $width - $padding - $textWidth - 5,
                        $height - $padding - $textHeight - 5,
                        $siteUrl,
                        $textColor
                    );
                    break;
                case 4: // Нижний левый угол
                    imagefilledrectangle(
                        $image,
                        $padding,
                        $height - $padding - $textHeight - 10,
                        $padding + $textWidth + 10,
                        $height - $padding,
                        $watermarkBgColor
                    );
                    imagestring(
                        $image,
                        $fontSize,
                        $padding + 5,
                        $height - $padding - $textHeight - 5,
                        $siteUrl,
                        $textColor
                    );
                    break;
            }
        }
        
        // Добавляем EXIF-данные
        $exifData = [
            'Title' => getConfig('site.name'),
            'Author' => $siteUrl,
            'Copyright' => '© ' . date('Y') . ' ' . $siteUrl,
            'Description' => 'Изображение с сайта ' . $siteUrl
        ];
        
        // Используем временный файл, так как PHP не имеет прямого API для добавления EXIF-данных
        $tempFile = tempnam(sys_get_temp_dir(), 'thumb');
        
        // Сохраняем изображение с качеством 95%
        if ($extension === 'jpg' || $extension === 'jpeg') {
            imagejpeg($image, $tempFile, 95); // Высокое качество 
        } elseif ($extension === 'png') {
            // PNG не поддерживает EXIF, но сохраняем с максимальным качеством
            imagepng($image, $tempFile, 1); // Максимальное качество (1 = наименьшая компрессия)
        } elseif ($extension === 'gif') {
            imagegif($image, $tempFile);
        }
        
        // Добавляем EXIF-данные, если это JPEG и есть соответствующее расширение
        if (($extension === 'jpg' || $extension === 'jpeg') && function_exists('exif_read_data') && function_exists('exec')) {
            // Пытаемся использовать exiftool, если доступен
            $title = escapeshellarg($exifData['Title']);
            $author = escapeshellarg($exifData['Author']);
            $copyright = escapeshellarg($exifData['Copyright']);
            $description = escapeshellarg($exifData['Description']);
            
            // Пробуем добавить EXIF-данные с помощью exiftool, если он установлен
            @exec("exiftool -Title={$title} -Author={$author} -Copyright={$copyright} -Description={$description} -overwrite_original {$tempFile} 2>&1", $output, $returnVar);
            
            // Если exiftool не установлен, можно использовать другие методы или просто скопировать файл без EXIF
            if ($returnVar !== 0) {
                // Записываем в лог и продолжаем без EXIF данных
                log_error('EXIF data could not be added: exiftool not available');
            }
        }
        
        // Копируем временный файл в финальное место назначения
        copy($tempFile, $thumbPath);
        unlink($tempFile);
        
        // Освобождаем память
        imagedestroy($image);
        
        // Возвращаем URL обработанного скриншота
        $thumbUrl = getConfig('site.url') . '/thumbs/' . $filename;
        cache_set($cacheKey, $thumbUrl, 86400); // кэшируем на сутки
        
        return $thumbUrl;
    } catch (Exception $e) {
        log_error('Thumbnail processing error: ' . $e->getMessage());
        return $url;
    }
}

/**
 * Генерация защищенной ссылки на видео (hotlink protection)
 * @param string $url Исходная ссылка на видео
 * @param int $videoId ID видео
 * @param int $expire Время жизни ссылки в секундах
 * @return string Защищенная ссылка
 */
function generate_video_url($url, $videoId, $expire = null) {
    if (!getConfig('videos.use_hotlink')) {
        return $url;
    }
    
    if ($expire === null) {
        $expire = getConfig('videos.hotlink_expire');
    }
    
    $salt = getConfig('videos.hotlink_token_salt');
    $expires = time() + $expire;
    
    // Создаем токен
    $token = md5($videoId . $expires . $salt);
    
    // Формируем защищенную ссылку
    $secureUrl = getConfig('site.url') . '/video.php?id=' . $videoId . '&token=' . $token . '&expires=' . $expires;
    
    return $secureUrl;
}

/**
 * Проверка защищенной ссылки на видео
 * @param int $videoId ID видео
 * @param string $token Токен
 * @param int $expires Время истечения
 * @return bool Валидность ссылки
 */
function validate_video_url($videoId, $token, $expires) {
    if (!getConfig('videos.use_hotlink') || empty($token) || empty($expires)) {
        return false;
    }
    
    // Проверяем срок действия
    if (time() > $expires) {
        return false;
    }
    
    $salt = getConfig('videos.hotlink_token_salt');
    $validToken = md5($videoId . $expires . $salt);
    
    // Проверяем токен
    return $token === $validToken;
}

/**
 * Автоматический подбор категорий для видео
 * @param string $title Название видео
 * @param string $description Описание видео
 * @return array Подобранные категории
 */
function auto_categorize_video($title, $description) {
    $title = mb_strtolower($title);
    $description = mb_strtolower($description);
    $content = $title . ' ' . $description;
    
    // Получаем список категорий
    $categories = get_categories();
    $matches = [];
    
    foreach ($categories as $category) {
        $categoryName = mb_strtolower($category['name']);
        $keywords = explode(',', $category['keywords']);
        
        foreach ($keywords as $keyword) {
            $keyword = trim(mb_strtolower($keyword));
            
            if (!empty($keyword) && mb_strpos($content, $keyword) !== false) {
                $matches[] = $category['id'];
                break;
            }
        }
    }
    
    return array_unique($matches);
}

/**
 * Автоматический подбор тегов для видео
 * @param string $title Название видео
 * @param string $description Описание видео
 * @return array Подобранные теги
 */
function auto_generate_tags($title, $description) {
    $title = mb_strtolower($title);
    $description = mb_strtolower($description);
    $content = $title . ' ' . $description;
    
    // Исключаемые слова
    $stopWords = ['и', 'в', 'на', 'с', 'по', 'за', 'к', 'от', 'из', 'a', 'the', 'and', 'or', 'for', 'with', 'без', 'под', 'над'];
    
    // Получаем слова из контента
    $words = preg_split('/\s+/', $content);
    $words = array_filter($words, function($word) use ($stopWords) {
        return strlen($word) > 3 && !in_array($word, $stopWords);
    });
    
    // Подсчитываем частоту слов
    $wordCount = array_count_values($words);
    arsort($wordCount);
    
    // Выбираем топ-10 самых часто встречающихся слов
    $tags = array_slice(array_keys($wordCount), 0, 10);
    
    return $tags;
}

/**
 * Получение списка категорий
 * @return array Список категорий
 */
function get_categories() {
    $cacheKey = 'categories_list';
    $cached = cache_get($cacheKey);
    
    if ($cached !== null) {
        return $cached;
    }
    
    $categories = db_get_rows("SELECT * FROM " . getConfig('db.prefix') . "categories ORDER BY name ASC");
    
    cache_set($cacheKey, $categories, 3600);
    
    return $categories;
}

// ========== УТИЛИТЫ ==========

/**
 * Запись в лог
 * @param string $message Сообщение
 * @param string $level Уровень лога (debug, info, warning, error)
 * @return bool
 */
function log_message($message, $level = 'info') {
    if (!getConfig('logging.enabled') || $level === 'debug' && getConfig('logging.level') !== 'debug') {
        return false;
    }
    
    $levelMap = [
        'debug' => 0,
        'info' => 1,
        'warning' => 2,
        'error' => 3
    ];
    
    $configLevel = getConfig('logging.level');
    
    if ($levelMap[$level] < $levelMap[$configLevel]) {
        return false;
    }
    
    $logsDir = getConfig('paths.logs');
    
    if (!is_dir($logsDir)) {
        mkdir($logsDir, 0755, true);
    }
    
    $date = date('Y-m-d');
    $time = date('H:i:s');
    $logFile = $logsDir . '/' . (getConfig('logging.rotate') ? "{$date}_" : '') . 'site.log';
    
    $entry = "[{$date} {$time}] [{$level}] {$message}" . PHP_EOL;
    
    return file_put_contents($logFile, $entry, FILE_APPEND) !== false;
}

/**
 * Запись ошибки в лог
 * @param string $message Сообщение об ошибке
 * @return bool
 */
function log_error($message) {
    return log_message($message, 'error');
}

/**
 * Генерация случайной строки
 * @param int $length Длина строки
 * @return string Случайная строка
 */
function generate_random_string($length = 32) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $result = '';
    
    for ($i = 0; $i < $length; $i++) {
        $result .= $characters[rand(0, strlen($characters) - 1)];
    }
    
    return $result;
}

/**
 * Форматирование времени
 * @param int $seconds Количество секунд
 * @return string Отформатированное время
 */
function format_duration($seconds) {
    $hours = floor($seconds / 3600);
    $minutes = floor(($seconds % 3600) / 60);
    $seconds = $seconds % 60;
    
    return ($hours > 0 ? $hours . ':' : '') . 
           sprintf('%02d:%02d', $minutes, $seconds);
}

/**
 * Форматирование размера файла
 * @param int $bytes Размер в байтах
 * @return string Отформатированный размер
 */
function format_filesize($bytes) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = 0;
    
    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }
    
    return round($bytes, 2) . ' ' . $units[$i];
}

/**
 * Транслитерация строки
 * @param string $str Исходная строка
 * @return string Транслитерированная строка
 */
function translit($str) {
    $rus = ['а', 'б', 'в', 'г', 'д', 'е', 'ё', 'ж', 'з', 'и', 'й', 'к', 'л', 'м', 'н', 'о', 'п', 'р', 'с', 'т', 'у', 'ф', 'х', 'ц', 'ч', 'ш', 'щ', 'ъ', 'ы', 'ь', 'э', 'ю', 'я', ' '];
    $lat = ['a', 'b', 'v', 'g', 'd', 'e', 'e', 'zh', 'z', 'i', 'y', 'k', 'l', 'm', 'n', 'o', 'p', 'r', 's', 't', 'u', 'f', 'h', 'ts', 'ch', 'sh', 'sch', '', 'y', '', 'e', 'yu', 'ya', '-'];
    
    $str = mb_strtolower($str);
    $str = str_replace($rus, $lat, $str);
    
    // Удаляем все символы, кроме латинских букв, цифр и дефиса
    $str = preg_replace('/[^a-z0-9\-]/', '', $str);
    
    // Удаляем повторяющиеся дефисы
    $str = preg_replace('/-+/', '-', $str);
    
    // Удаляем дефисы в начале и конце строки
    return trim($str, '-');
}

/**
 * Генерация мета-тегов
 * @param string $title Заголовок
 * @param string $description Описание
 * @param array $keywords Ключевые слова
 * @param string $url URL страницы для canonical
 * @param string $image URL изображения для og:image
 * @return string HTML код мета-тегов
 */
function generate_meta_tags($title, $description = '', $keywords = [], $url = '', $image = '') {
    $html = '<title>' . htmlspecialchars($title) . '</title>' . PHP_EOL;
    
    if (!empty($description)) {
        $html .= '<meta name="description" content="' . htmlspecialchars($description) . '">' . PHP_EOL;
    }
    
    if (!empty($keywords)) {
        $html .= '<meta name="keywords" content="' . htmlspecialchars(implode(', ', $keywords)) . '">' . PHP_EOL;
    }
    
    // Добавляем canonical
    if (!empty($url)) {
        $html .= '<link rel="canonical" href="' . htmlspecialchars($url) . '">' . PHP_EOL;
    }
    
    // Open Graph теги
    $html .= '<meta property="og:title" content="' . htmlspecialchars($title) . '">' . PHP_EOL;
    
    if (!empty($description)) {
        $html .= '<meta property="og:description" content="' . htmlspecialchars($description) . '">' . PHP_EOL;
    }
    
    if (!empty($url)) {
        $html .= '<meta property="og:url" content="' . htmlspecialchars($url) . '">' . PHP_EOL;
    }
    
    $html .= '<meta property="og:type" content="website">' . PHP_EOL;
    $html .= '<meta property="og:site_name" content="' . htmlspecialchars(getConfig('site.name')) . '">' . PHP_EOL;
    
    if (!empty($image)) {
        $html .= '<meta property="og:image" content="' . htmlspecialchars($image) . '">' . PHP_EOL;
    }
    
    // Twitter Card теги
    $html .= '<meta name="twitter:card" content="summary_large_image">' . PHP_EOL;
    $html .= '<meta name="twitter:title" content="' . htmlspecialchars($title) . '">' . PHP_EOL;
    
    if (!empty($description)) {
        $html .= '<meta name="twitter:description" content="' . htmlspecialchars($description) . '">' . PHP_EOL;
    }
    
    if (!empty($image)) {
        $html .= '<meta name="twitter:image" content="' . htmlspecialchars($image) . '">' . PHP_EOL;
    }
    
    return $html;
}

/**
 * Генерация Schema.org разметки
 * @param array $data Данные для разметки
 * @return string HTML код разметки
 */
function generate_schema_markup($data) {
    $schema = [];
    
    switch ($data['type']) {
        case 'video':
            $schema = [
                '@context' => 'https://schema.org',
                '@type' => 'VideoObject',
                'name' => $data['title'],
                'description' => $data['description'] ?? '',
                'thumbnailUrl' => $data['thumbnail'] ?? '',
                'uploadDate' => $data['upload_date'] ?? date('c'),
                'duration' => $data['duration'] ?? 'PT0M0S',
                'contentUrl' => $data['content_url'] ?? '',
                'embedUrl' => $data['embed_url'] ?? '',
                'interactionStatistic' => [
                    '@type' => 'InteractionCounter',
                    'interactionType' => 'https://schema.org/WatchAction',
                    'userInteractionCount' => $data['views'] ?? 0
                ]
            ];
            break;
            
        case 'website':
            $schema = [
                '@context' => 'https://schema.org',
                '@type' => 'WebSite',
                'name' => $data['name'],
                'url' => $data['url'],
                'description' => $data['description'] ?? '',
                'potentialAction' => [
                    '@type' => 'SearchAction',
                    'target' => $data['search_url'] . '{search_term_string}',
                    'query-input' => 'required name=search_term_string'
                ]
            ];
            break;
            
        case 'breadcrumb':
            $schema = [
                '@context' => 'https://schema.org',
                '@type' => 'BreadcrumbList',
                'itemListElement' => []
            ];
            
            foreach ($data['items'] as $i => $item) {
                $schema['itemListElement'][] = [
                    '@type' => 'ListItem',
                    'position' => $i + 1,
                    'name' => $item['name'],
                    'item' => $item['url']
                ];
            }
            break;
    }
    
    return '<script type="application/ld+json">' . json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . '</script>';
}

/**
 * Генерация уникальной цветовой схемы для сайта
 * @return array Массив с цветами
 */
function generate_color_scheme() {
    // Генерируем основной цвет (HSL)
    $hue = mt_rand(0, 360);
    $saturation = mt_rand(30, 70);
    $lightness = mt_rand(30, 60);
    
    // Создаем дополнительные цвета
    $colors = [
        'primary' => "hsl({$hue}, {$saturation}%, {$lightness}%)",
        'secondary' => "hsl(" . ($hue + 30) % 360 . ", {$saturation}%, " . ($lightness - 10) . "%)",
        'accent' => "hsl(" . ($hue + 180) % 360 . ", {$saturation}%, {$lightness}%)",
        'background' => "hsl({$hue}, " . ($saturation / 4) . "%, " . ($lightness + 30) . "%)",
        'text' => "hsl({$hue}, 10%, " . ($lightness - 30) . "%)",
        'link' => "hsl(" . ($hue + 200) % 360 . ", {$saturation}%, {$lightness}%)"
    ];
    
    return $colors;
}

/**
 * Генерация уникального CSS
 * @param array $colors Цветовая схема
 * @return string CSS код
 */
function generate_custom_css($colors) {
    // Выбор случайного варианта шаблона
    $templateVariant = mt_rand(1, 3);
    
    $css = "/* Автоматически сгенерированный CSS для шаблона вариант {$templateVariant} */\n\n";
    
    // Общие стили
    $css .= ":root {\n";
    foreach ($colors as $name => $color) {
        $css .= "  --color-{$name}: {$color};\n";
    }
    $css .= "}\n\n";
    
    // Выбираем один из нескольких шрифтов
    $primaryFonts = [
        "'Roboto', Arial, sans-serif",
        "'Open Sans', Arial, sans-serif",
        "'Montserrat', Arial, sans-serif",
        "'Lato', Arial, sans-serif",
        "'PT Sans', Arial, sans-serif",
        "'Source Sans Pro', Arial, sans-serif"
    ];
    
    $headerFonts = [
        "'Roboto Condensed', Arial, sans-serif",
        "'Oswald', Arial, sans-serif",
        "'Playfair Display', serif",
        "'Montserrat', Arial, sans-serif",
        "'Raleway', Arial, sans-serif"
    ];
    
    $primaryFont = $primaryFonts[mt_rand(0, count($primaryFonts) - 1)];
    $headerFont = $headerFonts[mt_rand(0, count($headerFonts) - 1)];
    
    $css .= "@import url('https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;700&family=Open+Sans:wght@300;400;700&family=Montserrat:wght@300;400;700&family=Lato:wght@300;400;700&family=PT+Sans:wght@400;700&family=Source+Sans+Pro:wght@300;400;700&family=Roboto+Condensed:wght@300;400;700&family=Oswald:wght@300;400;700&family=Playfair+Display:wght@400;700&family=Raleway:wght@300;400;700&display=swap');\n\n";
    
    $css .= "body {\n";
    $css .= "  background-color: var(--color-background);\n";
    $css .= "  color: var(--color-text);\n";
    $css .= "  font-family: {$primaryFont};\n";
    $css .= "  line-height: " . (mt_rand(140, 170) / 100) . ";\n";
    $css .= "  margin: 0;\n";
    $css .= "  padding: 0;\n";
    $css .= "}\n\n";
    
    // Стили для ссылок
    $css .= "a {\n";
    $css .= "  color: var(--color-link);\n";
    $css .= "  text-decoration: " . ['none', 'underline'][mt_rand(0, 1)] . ";\n";
    $css .= "  transition: color 0.3s ease, text-decoration 0.2s ease;\n";
    $css .= "}\n\n";
    
    // Разные стили подсветки ссылок при наведении
    $hoverStyleVariant = mt_rand(1, 4);
    
    switch ($hoverStyleVariant) {
        case 1: // Простое изменение цвета
            $css .= "a:hover {\n";
            $css .= "  color: var(--color-primary);\n";
            $css .= "  text-decoration: underline;\n";
            $css .= "}\n\n";
            break;
            
        case 2: // Подчеркивание с анимацией
            $css .= "a {\n";
            $css .= "  position: relative;\n";
            $css .= "}\n\n";
            
            $css .= "a:after {\n";
            $css .= "  content: '';\n";
            $css .= "  position: absolute;\n";
            $css .= "  width: 0;\n";
            $css .= "  height: 2px;\n";
            $css .= "  display: block;\n";
            $css .= "  margin-top: 2px;\n";
            $css .= "  right: 0;\n";
            $css .= "  background: var(--color-primary);\n";
            $css .= "  transition: width 0.3s ease;\n";
            $css .= "}\n\n";
            
            $css .= "a:hover:after {\n";
            $css .= "  width: 100%;\n";
            $css .= "  left: 0;\n";
            $css .= "  background: var(--color-primary);\n";
            $css .= "}\n\n";
            
            $css .= "a:hover {\n";
            $css .= "  color: var(--color-primary);\n";
            $css .= "  text-decoration: none;\n";
            $css .= "}\n\n";
            break;
            
        case 3: // Эффект смены фона
            $css .= "a {\n";
            $css .= "  padding: 0 3px;\n";
            $css .= "  border-radius: 3px;\n";
            $css .= "  transition: background 0.3s ease, color 0.3s ease;\n";
            $css .= "}\n\n";
            
            $css .= "a:hover {\n";
            $css .= "  background-color: var(--color-primary);\n";
            $css .= "  color: white;\n";
            $css .= "  text-decoration: none;\n";
            $css .= "}\n\n";
            break;
            
        case 4: // Трансформация при наведении
            $css .= "a {\n";
            $css .= "  display: inline-block;\n";
            $css .= "  transition: transform 0.2s ease, color 0.3s ease;\n";
            $css .= "}\n\n";
            
            $css .= "a:hover {\n";
            $css .= "  color: var(--color-primary);\n";
            $css .= "  transform: translateY(-2px);\n";
            $css .= "  text-decoration: underline;\n";
            $css .= "}\n\n";
            break;
    }
    
    // Заголовки
    $headingStyles = [
        'normal', 
        'bold', 
        'uppercase', 
        'capitalize'
    ];
    $headingStyle = $headingStyles[mt_rand(0, count($headingStyles) - 1)];
    
    $css .= "h1, h2, h3, h4, h5, h6 {\n";
    $css .= "  color: var(--color-primary);\n";
    $css .= "  font-family: {$headerFont};\n";
    $css .= "  font-weight: " . ['bold', 'normal'][mt_rand(0, 1)] . ";\n";
    
    if ($headingStyle === 'uppercase') {
        $css .= "  text-transform: uppercase;\n";
    } elseif ($headingStyle === 'capitalize') {
        $css .= "  text-transform: capitalize;\n";
    }
    
    $css .= "  margin-bottom: " . mt_rand(10, 20) . "px;\n";
    
    // Добавляем различные подчеркивания или эффекты для заголовков
    $headingEffectVariant = mt_rand(1, 4);
    if ($headingEffectVariant === 1) {
        $css .= "  border-bottom: 2px solid var(--color-accent);\n";
        $css .= "  padding-bottom: 5px;\n";
    } elseif ($headingEffectVariant === 2) {
        $css .= "  text-shadow: 1px 1px 2px rgba(0,0,0,0.1);\n";
    } elseif ($headingEffectVariant === 3) {
        $css .= "  position: relative;\n";
    }
    
    $css .= "}\n\n";
    
    if ($headingEffectVariant === 3) {
        $css .= "h1:after, h2:after, h3:after, h4:after, h5:after, h6:after {\n";
        $css .= "  content: '';\n";
        $css .= "  display: block;\n";
        $css .= "  width: 50px;\n";
        $css .= "  height: 3px;\n";
        $css .= "  background-color: var(--color-accent);\n";
        $css .= "  margin-top: 5px;\n";
        $css .= "}\n\n";
    }
    
    // Специальные стили для каждого уровня заголовка
    $css .= "h1 {\n";
    $css .= "  font-size: " . mt_rand(24, 32) . "px;\n";
    $css .= "  letter-spacing: " . (mt_rand(-100, 100) / 100) . "px;\n";
    $css .= "}\n\n";
    
    $css .= "h2 {\n";
    $css .= "  font-size: " . mt_rand(20, 28) . "px;\n";
    $css .= "  letter-spacing: " . (mt_rand(-100, 100) / 100) . "px;\n";
    $css .= "}\n\n";
    
    $css .= "h3 {\n";
    $css .= "  font-size: " . mt_rand(18, 24) . "px;\n";
    $css .= "}\n\n";
    
    // Стили для кнопок
    $buttonRadius = mt_rand(3, 8);
    $buttonPaddingV = mt_rand(8, 12);
    $buttonPaddingH = mt_rand(15, 25);
    
    $css .= ".button, button, input[type='submit'] {\n";
    $css .= "  background-color: var(--color-primary);\n";
    $css .= "  border: none;\n";
    $css .= "  border-radius: {$buttonRadius}px;\n";
    $css .= "  color: white;\n";
    $css .= "  cursor: pointer;\n";
    $css .= "  padding: {$buttonPaddingV}px {$buttonPaddingH}px;\n";
    $css .= "  transition: background-color 0.3s, transform 0.2s, box-shadow 0.3s;\n";
    $css .= "  display: inline-block;\n";
    $css .= "  text-align: center;\n";
    $css .= "  font-weight: bold;\n";
    $css .= "  font-size: 14px;\n";
    $css .= "  text-decoration: none;\n";
    $css .= "}\n\n";
    
    $css .= ".button:hover, button:hover, input[type='submit']:hover {\n";
    $css .= "  background-color: var(--color-secondary);\n";
    
    // Добавляем эффект при наведении случайным образом
    $hoverEffect = mt_rand(1, 3);
    if ($hoverEffect === 1) {
        $css .= "  transform: translateY(-2px);\n";
    } elseif ($hoverEffect === 2) {
        $css .= "  box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);\n";
    } elseif ($hoverEffect === 3) {
        $css .= "  filter: brightness(110%);\n";
    }
    
    $css .= "}\n\n";
    
    $css .= ".button:active, button:active, input[type='submit']:active {\n";
    $css .= "  transform: translateY(1px);\n";
    $css .= "}\n\n";
    
    // Стили для контейнера
    $containerWidth = mt_rand(1100, 1300);
    $containerPadding = mt_rand(10, 20);
    
    $css .= ".container {\n";
    $css .= "  max-width: {$containerWidth}px;\n";
    $css .= "  margin: 0 auto;\n";
    $css .= "  padding: 0 {$containerPadding}px;\n";
    $css .= "  width: 100%;\n";
    $css .= "  box-sizing: border-box;\n";
    $css .= "}\n\n";
    
    // Создаем разные варианты шаблонов
    switch ($templateVariant) {
        case 1: // Вариант 1: Стандартный макет с верхним меню
            $headerPadding = mt_rand(15, 25);
            
            $css .= "header {\n";
            $css .= "  background-color: var(--color-" . ['primary', 'secondary'][mt_rand(0, 1)] . ");\n";
            $css .= "  color: white;\n";
            $css .= "  padding: {$headerPadding}px 0;\n";
            $css .= "}\n\n";
            
            $css .= ".header-content {\n";
            $css .= "  display: flex;\n";
            $css .= "  justify-content: space-between;\n";
            $css .= "  align-items: center;\n";
            $css .= "}\n\n";
            
            $css .= ".logo {\n";
            $css .= "  margin-right: 20px;\n";
            $css .= "}\n\n";
            
            $css .= ".logo img {\n";
            $css .= "  max-height: 40px;\n";
            $css .= "}\n\n";
            
            $css .= ".search-form {\n";
            $css .= "  flex-grow: 1;\n";
            $css .= "  max-width: 500px;\n";
            $css .= "  display: flex;\n";
            $css .= "}\n\n";
            
            $css .= ".search-input {\n";
            $css .= "  flex-grow: 1;\n";
            $css .= "  padding: 10px;\n";
            $css .= "  border: none;\n";
            $css .= "  border-radius: {$buttonRadius}px 0 0 {$buttonRadius}px;\n";
            $css .= "}\n\n";
            
            $css .= ".search-button {\n";
            $css .= "  border-radius: 0 {$buttonRadius}px {$buttonRadius}px 0;\n";
            $css .= "  padding: 10px 20px;\n";
            $css .= "}\n\n";
            
            $navPadding = mt_rand(8, 15);
            
            $css .= "nav {\n";
            $css .= "  background-color: var(--color-secondary);\n";
            $css .= "  padding: {$navPadding}px 0;\n";
            $css .= "}\n\n";
            
            $css .= "nav ul {\n";
            $css .= "  display: flex;\n";
            $css .= "  flex-wrap: wrap;\n";
            $css .= "  list-style: none;\n";
            $css .= "  padding: 0;\n";
            $css .= "  margin: 0;\n";
            $css .= "}\n\n";
            
            $navItemMargin = mt_rand(10, 20);
            
            $css .= "nav li {\n";
            $css .= "  margin-right: {$navItemMargin}px;\n";
            $css .= "  margin-bottom: 5px;\n";
            $css .= "}\n\n";
            
            $css .= "nav a {\n";
            $css .= "  color: white;\n";
            $css .= "  text-decoration: none;\n";
            
            // Случайно добавляем разные стили для пунктов меню
            $navItemStyle = mt_rand(1, 3);
            if ($navItemStyle === 1) {
                $css .= "  font-weight: bold;\n";
            } elseif ($navItemStyle === 2) {
                $css .= "  text-transform: uppercase;\n";
                $css .= "  font-size: 12px;\n";
                $css .= "  letter-spacing: 1px;\n";
            } elseif ($navItemStyle === 3) {
                $css .= "  padding: 5px 10px;\n";
                $css .= "  border-radius: 3px;\n";
                $css .= "  transition: background-color 0.3s;\n";
            }
            
            $css .= "}\n\n";
            
            if ($navItemStyle === 3) {
                $css .= "nav a:hover {\n";
                $css .= "  background-color: rgba(255, 255, 255, 0.1);\n";
                $css .= "  color: white;\n";
                $css .= "}\n\n";
            } else {
                $css .= "nav a:hover {\n";
                $css .= "  color: rgba(255, 255, 255, 0.8);\n";
                $css .= "}\n\n";
            }
            
            $contentPadding = mt_rand(20, 40);
            
            $css .= ".main-content {\n";
            $css .= "  padding: {$contentPadding}px 0;\n";
            $css .= "}\n\n";
            
            $footerPadding = mt_rand(25, 40);
            $footerMargin = mt_rand(30, 50);
            
            $css .= "footer {\n";
            $css .= "  background-color: var(--color-" . ['primary', 'secondary', 'text'][mt_rand(0, 2)] . ");\n";
            $css .= "  color: white;\n";
            $css .= "  padding: {$footerPadding}px 0;\n";
            $css .= "  margin-top: {$footerMargin}px;\n";
            $css .= "}\n\n";
            
            break;
            
        case 2:
            // Вариант 2: Макет с боковым меню
            $css .= "body {\n";
            $css .= "  display: flex;\n";
            $css .= "  flex-direction: column;\n";
            $css .= "  min-height: 100vh;\n";
            $css .= "}\n\n";
            
            $headerPadding = mt_rand(15, 25);
            
            $css .= "header {\n";
            $css .= "  background-color: var(--color-primary);\n";
            $css .= "  color: white;\n";
            $css .= "  padding: {$headerPadding}px 0;\n";
            $css .= "}\n\n";
            
            $css .= ".header-content {\n";
            $css .= "  display: flex;\n";
            $css .= "  justify-content: space-between;\n";
            $css .= "  align-items: center;\n";
            $css .= "}\n\n";
            
            $css .= ".main-wrapper {\n";
            $css .= "  display: flex;\n";
            $css .= "  flex: 1;\n";
            $css .= "}\n\n";
            
            $sidebarWidth = mt_rand(220, 280);
            $sidebarPadding = mt_rand(15, 25);
            
            $css .= ".sidebar {\n";
            $css .= "  width: {$sidebarWidth}px;\n";
            $css .= "  background-color: var(--color-background);\n";
            $css .= "  padding: {$sidebarPadding}px;\n";
            $css .= "  box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);\n";
            $css .= "}\n\n";
            
            $css .= "nav ul {\n";
            $css .= "  list-style: none;\n";
            $css .= "  padding: 0;\n";
            $css .= "  margin: 0;\n";
            $css .= "}\n\n";
            
            $navItemMargin = mt_rand(8, 15);
            
            $css .= "nav li {\n";
            $css .= "  margin-bottom: {$navItemMargin}px;\n";
            $css .= "}\n\n";
            
            $navPadding = mt_rand(8, 12);
            $navRadius = mt_rand(3, 6);
            
            $css .= "nav a {\n";
            $css .= "  display: block;\n";
            $css .= "  padding: {$navPadding}px;\n";
            $css .= "  color: var(--color-text);\n";
            $css .= "  text-decoration: none;\n";
            $css .= "  border-radius: {$navRadius}px;\n";
            $css .= "  transition: background-color 0.3s, color 0.3s;\n";
            $css .= "}\n\n";
            
            $css .= "nav a:hover {\n";
            $css .= "  background-color: var(--color-primary);\n";
            $css .= "  color: white;\n";
            $css .= "}\n\n";
            
            $contentPadding = mt_rand(20, 30);
            
            $css .= ".main-content {\n";
            $css .= "  flex: 1;\n";
            $css .= "  padding: {$contentPadding}px;\n";
            $css .= "}\n\n";
            
            $footerPadding = mt_rand(20, 30);
            
            $css .= "footer {\n";
            $css .= "  background-color: var(--color-primary);\n";
            $css .= "  color: white;\n";
            $css .= "  padding: {$footerPadding}px 0;\n";
            $css .= "}\n\n";
            
            break;
            
        case 3:
            // Вариант 3: Современный макет с фиксированным верхним меню
            $headerHeight = mt_rand(50, 70);
            
            $css .= "body {\n";
            $css .= "  padding-top: {$headerHeight}px; /* Для фиксированного хедера */\n";
            $css .= "}\n\n";
            
            $css .= "header {\n";
            $css .= "  background-color: var(--color-primary);\n";
            $css .= "  color: white;\n";
            $css .= "  position: fixed;\n";
            $css .= "  top: 0;\n";
            $css .= "  left: 0;\n";
            $css .= "  right: 0;\n";
            $css .= "  z-index: 1000;\n";
            $css .= "  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);\n";
            $css .= "}\n\n";
            
            $css .= ".header-content {\n";
            $css .= "  display: flex;\n";
            $css .= "  justify-content: space-between;\n";
            $css .= "  align-items: center;\n";
            $css .= "  height: {$headerHeight}px;\n";
            $css .= "}\n\n";
            
            $css .= ".logo {\n";
            $css .= "  display: flex;\n";
            $css .= "  align-items: center;\n";
            $css .= "}\n\n";
            
            $css .= ".logo img {\n";
            $css .= "  max-height: " . ($headerHeight - 20) . "px;\n";
            $css .= "}\n\n";
            
            $css .= "nav {\n";
            $css .= "  display: flex;\n";
            $css .= "  align-items: center;\n";
            $css .= "}\n\n";
            
            $css .= "nav ul {\n";
            $css .= "  display: flex;\n";
            $css .= "  list-style: none;\n";
            $css .= "  padding: 0;\n";
            $css .= "  margin: 0;\n";
            $css .= "}\n\n";
            
            $navItemMargin = mt_rand(15, 25);
            
            $css .= "nav li {\n";
            $css .= "  margin-left: {$navItemMargin}px;\n";
            $css .= "}\n\n";
            
            $css .= "nav a {\n";
            $css .= "  color: white;\n";
            $css .= "  text-decoration: none;\n";
            $css .= "  font-weight: bold;\n";
            $css .= "  text-transform: uppercase;\n";
            $css .= "  font-size: 14px;\n";
            $css .= "  letter-spacing: 1px;\n";
            $css .= "  position: relative;\n";
            $css .= "  padding: 5px 0;\n";
            $css .= "}\n\n";
            
            // Добавляем эффектную анимацию для пунктов меню
            $css .= "nav a:after {\n";
            $css .= "  content: '';\n";
            $css .= "  position: absolute;\n";
            $css .= "  bottom: 0;\n";
            $css .= "  left: 0;\n";
            $css .= "  width: 0;\n";
            $css .= "  height: 2px;\n";
            $css .= "  background-color: var(--color-accent);\n";
            $css .= "  transition: width 0.3s;\n";
            $css .= "}\n\n";
            
            $css .= "nav a:hover:after {\n";
            $css .= "  width: 100%;\n";
            $css .= "}\n\n";
            
            $css .= "nav a:hover {\n";
            $css .= "  color: var(--color-accent);\n";
            $css .= "}\n\n";
            
            $css .= ".search-form {\n";
            $css .= "  display: flex;\n";
            $css .= "  margin-left: 20px;\n";
            $css .= "}\n\n";
            
            $contentPadding = mt_rand(30, 50);
            
            $css .= ".main-content {\n";
            $css .= "  padding: {$contentPadding}px 0;\n";
            $css .= "}\n\n";
            
            $footerPadding = mt_rand(40, 60);
            $footerMargin = mt_rand(40, 60);
            
            $css .= "footer {\n";
            $css .= "  background-color: var(--color-secondary);\n";
            $css .= "  color: white;\n";
            $css .= "  padding: {$footerPadding}px 0 20px;\n";
            $css .= "  margin-top: {$footerMargin}px;\n";
            $css .= "}\n\n";
            
            break;
    }
    
    // Стили для видеогалереи
    $gridGap = mt_rand(15, 25);
    
    $css .= ".video-grid {\n";
    $css .= "  display: grid;\n";
    $css .= "  grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));\n";
    $css .= "  gap: {$gridGap}px;\n";
    $css .= "  margin: 20px 0;\n";
    $css .= "}\n\n";
    
    $itemRadius = mt_rand(3, 8);
    $shadowSize = mt_rand(5, 15);
    
    $css .= ".video-item {\n";
    $css .= "  border-radius: {$itemRadius}px;\n";
    $css .= "  overflow: hidden;\n";
    $css .= "  box-shadow: 0 " . mt_rand(2, 5) . "px {$shadowSize}px rgba(0, 0, 0, 0.1);\n";
    $css .= "  transition: transform 0.3s, box-shadow 0.3s;\n";
    $css .= "  background-color: #222;\n";
    $css .= "  height: 100%;\n"; // Фиксированная высота для выравнивания
    $css .= "  display: flex;\n";
    $css .= "  flex-direction: column;\n";
    $css .= "}\n\n";
    
    $hoverTransform = mt_rand(3, 5); // Уменьшено для более тонкого эффекта
    $hoverShadow = mt_rand(15, 25);
    
    $css .= ".video-item:hover {\transform: translateY(-{$hoverTransform}px);
  box-shadow: 0 " . mt_rand(8, 15) . "px {$hoverShadow}px rgba(0, 0, 0, 0.15);
}

.video-thumbnail {
  position: relative;
  padding-bottom: 56.25%; /* 16:9 соотношение */
  background-color: #000;
  overflow: hidden;
}

.video-thumbnail img {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  object-fit: cover;
  transition: transform 0.3s;
}

/* Добавляем эффект при наведении на превью */
.video-item:hover .video-thumbnail img {
  transform: scale(1.05);
}

.video-duration {
  position: absolute;
  bottom: " . mt_rand(5, 10) . "px;
  right: " . mt_rand(5, 10) . "px;
  background-color: rgba(0, 0, 0, 0.7);
  color: white;
  padding: 3px 6px;
  border-radius: 3px;
  font-size: 12px;
  font-weight: bold;
  z-index: 2;
}

.video-info {
  padding: 10px;
  flex-grow: 1;
  display: flex;
  flex-direction: column;
}

.video-title {
  font-size: 14px;
  margin: 0 0 8px 0;
  color: #fff;
  overflow: visible; /* Изменено для показа полного названия */
  white-space: normal;
  line-height: 1.4;
  flex-grow: 1;
  word-wrap: break-word;
}

.video-stats {
  font-size: 12px;
  color: #aaa;
  margin-top: auto; /* Прижать к низу */
}

.video-date {
  display: inline-block;
}

/* Стили для страницы видео */
.video-page {
  margin-bottom: 40px;
}

.video-container {
  margin-bottom: 20px;
  background-color: #111;
  border-radius: {$itemRadius}px;
  overflow: hidden;
}

.video-player-wrapper {
  position: relative;
  padding-bottom: 56.25%; /* 16:9 соотношение */
  height: 0;
  overflow: hidden;
}

.video-player-wrapper iframe,
.video-player-wrapper video,
.video-player-wrapper #player {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
}

.video-page h1 {
  font-size: " . mt_rand(20, 24) . "px;
  margin: 15px 0;
  color: var(--color-primary);
}

.video-meta {
  background-color: #222;
  padding: 15px;
  border-radius: {$itemRadius}px;
  margin-bottom: 20px;
}

.video-categories {
  margin: 10px 0;
}

.video-category {
  display: inline-block;
  margin-right: 8px;
  margin-bottom: 8px;
  padding: 5px 8px;
  background-color: var(--color-primary);
  color: #fff;
  border-radius: 3px;
  font-size: 12px;
  text-decoration: none;
}

.video-category:hover {
  background-color: var(--color-secondary);
  color: #fff;
  text-decoration: none;
}

.video-description {
  line-height: 1.6;
  margin: 15px 0;
  color: #ddd;
}

.video-tags {
  margin: 15px 0;
}

.tags-label {
  color: #aaa;
  margin-right: 5px;
}

.video-tag {
  display: inline-block;
  margin-right: 5px;
  margin-bottom: 5px;
  padding: 3px 8px;
  background-color: #333;
  color: #ddd;
  border-radius: 3px;
  font-size: 12px;
  text-decoration: none;
}

.video-tag:hover {
  background-color: var(--color-accent);
  color: #fff;
  text-decoration: none;
}

.related-videos h2 {
  margin: 30px 0 15px;
  color: var(--color-primary);
}

/* Пагинация */
.pagination {
  display: flex;
  flex-wrap: wrap;
  justify-content: center;
  margin: 30px 0;
}

.page-link {
  display: inline-block;
  padding: 8px 12px;
  margin: 0 3px 6px;
  border-radius: 3px;
  background-color: #333;
  color: #fff;
  text-decoration: none;
  transition: background-color 0.3s;
}

.page-link:hover {
  background-color: var(--color-primary);
  color: #fff;
  text-decoration: none;
}

.page-link.active {
  background-color: var(--color-primary);
  color: #fff;
}

.page-dots {
  display: inline-block;
  padding: 8px 12px;
  margin: 0 3px;
  color: #aaa;
}

/* Футер */
.footer-links {
  display: flex;
  flex-wrap: wrap;
  margin-bottom: 20px;
}

.footer-links a {
  color: rgba(255, 255, 255, 0.8);
  margin-right: 15px;
  margin-bottom: 10px;
  font-size: 13px;
  text-decoration: none;
}

.footer-links a:hover {
  color: #fff;
  text-decoration: underline;
}

.copyright {
  color: rgba(255, 255, 255, 0.6);
  font-size: 12px;
  text-align: center;
  padding-top: 20px;
  border-top: 1px solid rgba(255, 255, 255, 0.1);
}

/* Страница тэгов */
.tag-cloud {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  margin: 20px 0;
}

.tag-item {
  padding: 8px 12px;
  background-color: var(--color-secondary);
  color: #fff;
  border-radius: 3px;
  font-size: 14px;
  text-decoration: none;
  transition: transform 0.2s, background-color 0.3s;
}

.tag-item:hover {
  background-color: var(--color-primary);
  transform: translateY(-2px);
}

.tag-item-count {
  background-color: rgba(255, 255, 255, 0.2);
  border-radius: 10px;
  padding: 0 6px;
  margin-left: 6px;
  font-size: 12px;
}

/* Адаптивность для мобильных устройств */
@media (max-width: 768px) {
  .video-grid {
    grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
    gap: 15px;
  }
  
  .video-title {
    font-size: 13px;
  }
  
  header .container {
    flex-direction: column;
  }
  
  .search-form {
    margin-top: 15px;
    max-width: 100%;
  }
  
  nav ul {
    justify-content: center;
  }
  
  nav li {
    margin: 5px;
  }
  
  .footer-links a {
    margin-right: 10px;
    margin-bottom: 8px;
  }
}

/* Описание сайта на главной странице */
.site-description {
  background-color: rgba(0, 0, 0, 0.1);
  padding: 20px;
  margin-top: 40px;
  border-radius: {$itemRadius}px;
  border-left: 4px solid var(--color-primary);
}

.site-description h2 {
  margin-top: 0;
  color: var(--color-primary);
}

.description-content {
  line-height: 1.6;
}

/* Категория - описание под h1 */
.category-description {
  margin-bottom: 30px;
  line-height: 1.6;
  color: #ddd;
}

/* Стили для режима обслуживания */
.maintenance-mode {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background-color: rgba(0, 0, 0, 0.9);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 9999;
}

.maintenance-mode h1 {
  color: #fff;
  font-size: 24px;
  margin-bottom: 10px;
}

.maintenance-mode p {
  color: #ddd;
  font-size: 16px;
}

/* Улучшения для красивых форм */
input:focus, textarea:focus, select:focus {
  outline: none;
  border-color: var(--color-primary);
  box-shadow: 0 0 5px rgba(0, 0, 0, 0.2);
}

.no-videos, .no-results {
  padding: 40px 20px;
  text-align: center;
  background-color: rgba(0, 0, 0, 0.05);
  border-radius: {$itemRadius}px;
  margin: 20px 0;
}

.no-videos p, .no-results p {
  font-size: 16px;
  margin-bottom: 20px;
}

.search-suggestions {
  margin-top: 30px;
  text-align: left;
  max-width: 600px;
  margin-left: auto;
  margin-right: auto;
}

.search-suggestions h3 {
  font-size: 18px;
  margin-bottom: 15px;
}

.search-suggestions ul {
  padding-left: 20px;
}

.search-suggestions li {
  margin-bottom: 8px;
}

/* Анимация для улучшения UX */
@keyframes fadeIn {
  from { opacity: 0; }
  to { opacity: 1; }
}

.video-item, .pagination, .site-description {
  animation: fadeIn 0.5s ease-in-out;
}

/* Улучшения для ссылок в описаниях */
.video-description a {
  color: var(--color-accent);
  text-decoration: underline;
}

.video-description a:hover {
  color: var(--color-primary);
}

return $css;
}


/**
 * Генерация логотипа с поддержкой кириллицы
 * @param string $siteName Название сайта
 * @return string Путь к логотипу
 */
function generate_logo($siteName) {
    $logoDir = getConfig('paths.root') . '/uploads';
    
    if (!is_dir($logoDir)) {
        mkdir($logoDir, 0755, true);
    }
    
    // Создаем изображение с прозрачным фоном
    $width = 200;
    $height = 50;
    $image = imagecreatetruecolor($width, $height);
    
    // Делаем фон прозрачным
    imagealphablending($image, false);
    imagesavealpha($image, true);
    $transparent = imagecolorallocatealpha($image, 0, 0, 0, 127);
    imagefilledrectangle($image, 0, 0, $width, $height, $transparent);
    
    // Включаем alpha blending для текста
    imagealphablending($image, true);
    
    // Генерируем цвета для логотипа
    $textColor = imagecolorallocate(
        $image, 
        mt_rand(200, 255), 
        mt_rand(200, 255), 
        mt_rand(200, 255)
    );
    
    $shadowColor = imagecolorallocatealpha(
        $image, 
        mt_rand(0, 50), 
        mt_rand(0, 50), 
        mt_rand(0, 50),
        70 // Полупрозрачный
    );
    
    // Находим подходящий шрифт, поддерживающий кириллицу
    $fontFile = null;
    $fontPaths = [
        // Общие пути к шрифтам с поддержкой кириллицы
        __DIR__ . '/assets/fonts/DejaVuSans.ttf',
        __DIR__ . '/assets/fonts/arial.ttf',
        __DIR__ . '/assets/fonts/opensans.ttf',
        __DIR__ . '/assets/fonts/roboto.ttf',
        // Linux пути
        '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
        '/usr/share/fonts/truetype/freefont/FreeSans.ttf',
        '/usr/share/fonts/truetype/ubuntu/Ubuntu-R.ttf',
        // Windows пути
        'C:\\Windows\\Fonts\\arial.ttf',
        'C:\\Windows\\Fonts\\calibri.ttf',
        'C:\\Windows\\Fonts\\verdana.ttf',
        // MacOS пути
        '/Library/Fonts/Arial.ttf',
        '/Library/Fonts/Georgia.ttf'
    ];
    
    foreach ($fontPaths as $path) {
        if (file_exists($path)) {
            $fontFile = $path;
            break;
        }
    }
    
    // Если нужно, можно добавить здесь встроенный шрифт на случай, если ни один из путей не найден
    
    if ($fontFile && function_exists('imagettftext')) {
        // Рассчитываем размер шрифта в зависимости от длины названия сайта
        $fontSize = min(24, max(14, 30 - mb_strlen($siteName)));
        
        // Определяем размеры текста
        $bbox = imagettfbbox($fontSize, 0, $fontFile, $siteName);
        $textWidth = $bbox[2] - $bbox[0];
        $textHeight = $bbox[1] - $bbox[7];
        
        // Центрируем текст
        $x = ($width - $textWidth) / 2;
        $y = ($height + $textHeight) / 2;
        
        // Добавляем тень для текста (смещение на 1 пиксель)
        imagettftext($image, $fontSize, 0, $x + 1, $y + 1, $shadowColor, $fontFile, $siteName);
        
        // Выводим текст
        imagettftext($image, $fontSize, 0, $x, $y, $textColor, $fontFile, $siteName);
        
        // Добавляем декоративные элементы
        $decorType = mt_rand(1, 3);
        $decorColor = imagecolorallocate(
            $image, 
            mt_rand(100, 255), 
            mt_rand(100, 255), 
            mt_rand(100, 255)
        );
        
        switch ($decorType) {
            case 1: // Горизонтальные линии до и после текста
                $lineY = $height / 2;
                $lineWidth = ($width - $textWidth) / 2 - 10;
                
                // Линия слева от текста
                imagesetthickness($image, 2);
                imageline($image, 5, $lineY, 5 + $lineWidth, $lineY, $decorColor);
                
                // Линия справа от текста
                imageline($image, $width - 5 - $lineWidth, $lineY, $width - 5, $lineY, $decorColor);
                break;
                
            case 2: // Простая рамка вокруг логотипа
                $border = 3;
                imagesetthickness($image, 1);
                imagerectangle($image, $border, $border, $width - $border, $height - $border, $decorColor);
                break;
                
            case 3: // Точки по углам
                $dotSize = 3;
                $margin = 5;
                
                // Верхний левый угол
                imagefilledellipse($image, $margin, $margin, $dotSize, $dotSize, $decorColor);
                // Верхний правый угол
                imagefilledellipse($image, $width - $margin, $margin, $dotSize, $dotSize, $decorColor);
                // Нижний левый угол
                imagefilledellipse($image, $margin, $height - $margin, $dotSize, $dotSize, $decorColor);
                // Нижний правый угол
                imagefilledellipse($image, $width - $margin, $height - $margin, $dotSize, $dotSize, $decorColor);
                break;
        }
    } else {
        // Если не удалось найти подходящий шрифт для кириллицы, 
        // лучше сгенерировать простой текстовый логотип без кириллицы, чем неправильно отображать
        
        // Рисуем простой прямоугольник с контрастным цветом
        $rectangleColor = imagecolorallocate($image, mt_rand(0, 100), mt_rand(50, 150), mt_rand(100, 255));
        imagefilledrectangle($image, 0, 0, $width, $height, $rectangleColor);
        
        // Добавляем первую букву (на латинице) или логотип
        $letter = mb_substr($siteName, 0, 1);
        $letter = preg_match('/[a-zA-Z0-9]/u', $letter) ? $letter : 'GL'; // GL = GlakTube
        
        // Выводим текст (только латиница)
        $fontSize = 20;
        $textWidth = strlen($letter) * 20;
        $x = ($width - $textWidth) / 2 + 10;
        $y = ($height / 2) + 5;
        
        imagestring($image, 5, $x, $y, $letter, $textColor);
    }
    
    // Сохраняем логотип в формате PNG с прозрачностью
    $logoPath = $logoDir . '/logo.png';
    imagepng($image, $logoPath);
    
    // Освобождаем память
    imagedestroy($image);
    
    return $logoPath;
}

/**
 * Генерация фавиконки в виде осмысленной картинки
 * @param string $siteName Название сайта
 * @return string Путь к фавиконке
 */
function generate_favicon($siteName) {
    $faviconDir = getConfig('paths.root') . '/favicon';
    
    if (!is_dir($faviconDir)) {
        mkdir($faviconDir, 0755, true);
    }
    
    // Создаем изображение
    $size = 32;
    $image = imagecreatetruecolor($size, $size);
    
    // Делаем фон прозрачным
    imagealphablending($image, false);
    imagesavealpha($image, true);
    $transparent = imagecolorallocatealpha($image, 0, 0, 0, 127);
    imagefilledrectangle($image, 0, 0, $size, $size, $transparent);
    
    // Включаем альфа-канал для рисования
    imagealphablending($image, true);
    
    // Выбираем тип пиксельной иконки (1-сердечко, 2-попа, 3-звезда)
    $iconType = mt_rand(1, 3);
    
    // Генерируем цвета для значка
    $mainColor = imagecolorallocate($image, mt_rand(100, 255), mt_rand(50, 150), mt_rand(50, 200));
    $secondaryColor = imagecolorallocate($image, mt_rand(50, 200), mt_rand(100, 255), mt_rand(50, 150));
    $outlineColor = imagecolorallocate($image, 30, 30, 30);
    
    // Создаем пиксельную картинку в зависимости от выбранного типа
    switch ($iconType) {
        case 1: // Сердечко
            // Фон
            imagefilledrectangle($image, 0, 0, $size-1, $size-1, $secondaryColor);
            
            // Создаем форму сердца
            $heart = [
                [0, 0, 0, 0, 0, 0, 0, 0],
                [0, 1, 1, 0, 0, 1, 1, 0],
                [1, 2, 2, 1, 1, 2, 2, 1],
                [1, 2, 2, 2, 2, 2, 2, 1],
                [1, 2, 2, 2, 2, 2, 2, 1],
                [0, 1, 2, 2, 2, 2, 1, 0],
                [0, 0, 1, 2, 2, 1, 0, 0],
                [0, 0, 0, 1, 1, 0, 0, 0]
            ];
            break;
            
        case 2: // Попа/ножки
            // Фон
            imagefilledrectangle($image, 0, 0, $size-1, $size-1, $secondaryColor);
            
            // Создаем форму
            $heart = [
                [0, 0, 0, 0, 0, 0, 0, 0],
                [0, 0, 1, 1, 1, 1, 0, 0],
                [0, 1, 2, 2, 2, 2, 1, 0],
                [0, 1, 2, 1, 1, 2, 1, 0],
                [0, 1, 2, 2, 2, 2, 1, 0],
                [0, 1, 2, 2, 2, 2, 1, 0],
                [0, 0, 1, 1, 1, 1, 0, 0],
                [0, 0, 0, 0, 0, 0, 0, 0]
            ];
            break;
            
        case 3: // Звезда
            // Фон
            imagefilledrectangle($image, 0, 0, $size-1, $size-1, $secondaryColor);
            
            // Создаем форму звезды
            $heart = [
                [0, 0, 0, 1, 1, 0, 0, 0],
                [0, 0, 1, 2, 2, 1, 0, 0],
                [0, 1, 2, 2, 2, 2, 1, 0],
                [1, 2, 2, 2, 2, 2, 2, 1],
                [1, 2, 2, 2, 2, 2, 2, 1],
                [0, 1, 2, 2, 2, 2, 1, 0],
                [0, 0, 1, 1, 1, 1, 0, 0],
                [0, 0, 0, 1, 1, 0, 0, 0]
            ];
            break;
    }
    
    // Рисуем пиксельную картинку
    $pixelSize = 4; // Размер пикселя
    for ($y = 0; $y < 8; $y++) {
        for ($x = 0; $x < 8; $x++) {
            if ($heart[$y][$x] == 1) {
                imagefilledrectangle(
                    $image,
                    $x * $pixelSize, $y * $pixelSize,
                    ($x + 1) * $pixelSize - 1, ($y + 1) * $pixelSize - 1,
                    $outlineColor
                );
            } elseif ($heart[$y][$x] == 2) {
                imagefilledrectangle(
                    $image,
                    $x * $pixelSize, $y * $pixelSize,
                    ($x + 1) * $pixelSize - 1, ($y + 1) * $pixelSize - 1,
                    $mainColor
                );
            }
        }
    }
    
    // Сохраняем фавиконку в формате PNG
    $faviconPath = $faviconDir . '/favicon.png';
    imagepng($image, $faviconPath);
    
    // Также сохраняем в формате ICO для совместимости
    $icoPath = $faviconDir . '/favicon.ico';
    imagepng($image, $icoPath);
    
    // Освобождаем память
    imagedestroy($image);
    
    return $faviconPath;
}

/**
 * Форматирование даты
 * @param string $date Дата в формате MySQL
 * @param string $format Формат вывода
 * @return string Отформатированная дата
 */
function format_date($date, $format = 'd.m.Y') {
    if (empty($date)) {
        return '';
    }
    
    $timestamp = strtotime($date);
    return date($format, $timestamp);
}

/**
 * Обрезка текста до указанной длины
 * @param string $text Исходный текст
 * @param int $length Максимальная длина
 * @param string $suffix Окончание обрезанного текста
 * @return string Обрезанный текст
 */
function truncate_text($text, $length = 100, $suffix = '...') {
    if (mb_strlen($text) <= $length) {
        return $text;
    }
    
    return mb_substr($text, 0, $length) . $suffix;
}

/**
 * Фильтрация введенного текста для предотвращения XSS и SQL-инъекций
 * @param string $text Текст для фильтрации
 * @return string Отфильтрованный текст
 */
function sanitize_text($text) {
    $text = strip_tags($text);
    $text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    return $text;
}

/**
 * Фильтрация поискового запроса
 * @param string $query Поисковый запрос
 * @return string Отфильтрованный запрос
 */
function sanitize_search_query($query) {
    // Базовая фильтрация
    $query = sanitize_text($query);
    
    // Удаляем множественные пробелы
    $query = preg_replace('/\s+/', ' ', $query);
    
    // Ограничиваем длину запроса
    $query = mb_substr(trim($query), 0, 100);
    
    return $query;
}

/**
 * Улучшенная генерация Schema.org разметки для видео
 * @param array $video Данные видео
 * @param string $siteUrl URL сайта
 * @return string HTML-код разметки
 */
function generate_video_schema($video, $siteUrl) {
    $schema = [
        '@context' => 'https://schema.org',
        '@type' => 'VideoObject',
        'name' => $video['title'],
        'description' => isset($video['description']) && !empty($video['description']) ? $video['description'] : $video['title'],
        'thumbnailUrl' => $video['thumb_processed'],
        'uploadDate' => date('c', strtotime($video['post_date'])),
        'duration' => 'PT' . floor($video['duration'] / 60) . 'M' . ($video['duration'] % 60) . 'S',
        'contentUrl' => $video['secure_url'] ?? $video['file_url'],
        'embedUrl' => $siteUrl . '/video/' . $video['slug'],
        'interactionStatistic' => [
            '@type' => 'InteractionCounter',
            'interactionType' => 'https://schema.org/WatchAction',
            'userInteractionCount' => $video['popularity']
        ],
        'thumbnailUrl' => $video['thumb_processed']
    ];
    
    // Добавляем теги как ключевые слова, если они есть
    if (!empty($video['tags'])) {
        $keywords = [];
        foreach ($video['tags'] as $tag) {
            $keywords[] = $tag['name'];
        }
        $schema['keywords'] = implode(', ', $keywords);
    }
    
    return '<script type="application/ld+json">' . json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . '</script>';
}

/**
 * Генерация OpenGraph тегов для страницы
 * @param string $title Заголовок страницы
 * @param string $description Описание страницы
 * @param string $url URL страницы
 * @param string $image URL изображения
 * @param string $type Тип страницы (website, article, video)
 * @return string HTML-код тегов
 */
function generate_og_tags($title, $description, $url, $image = '', $type = 'website') {
    $html = '<meta property="og:title" content="' . htmlspecialchars($title) . '">' . PHP_EOL;
    $html .= '<meta property="og:description" content="' . htmlspecialchars($description) . '">' . PHP_EOL;
    $html .= '<meta property="og:url" content="' . htmlspecialchars($url) . '">' . PHP_EOL;
    $html .= '<meta property="og:type" content="' . htmlspecialchars($type) . '">' . PHP_EOL;
    $html .= '<meta property="og:site_name" content="' . htmlspecialchars(getConfig('site.name')) . '">' . PHP_EOL;
    
    if (!empty($image)) {
        $html .= '<meta property="og:image" content="' . htmlspecialchars($image) . '">' . PHP_EOL;
        $html .= '<meta property="og:image:width" content="1200">' . PHP_EOL;
        $html .= '<meta property="og:image:height" content="630">' . PHP_EOL;
    }
    
    return $html;
}

/**
 * Генерация мета-тегов с атрибутами itemprop для микроданных Schema.org
 * @param string $title Заголовок страницы
 * @param string $description Описание страницы
 * @return string HTML-код тегов
 */
function generate_meta_itemprop($title, $description) {
    $html = '<meta itemprop="name" content="' . htmlspecialchars($title) . '">' . PHP_EOL;
    $html .= '<meta itemprop="description" content="' . htmlspecialchars($description) . '">' . PHP_EOL;
    
    return $html;
}

/**
 * Генерация тега canonical
 * @param string $url URL страницы
 * @return string HTML-код тега
 */
function generate_canonical_tag($url) {
    return '<link rel="canonical" href="' . htmlspecialchars($url) . '">' . PHP_EOL;
}

/**
 * Генерация тега noindex для страниц пагинации
 * @param bool $isPagination Является ли страница частью пагинации
 * @return string HTML-код тега
 */
function generate_noindex_tag($isPagination) {
    if ($isPagination) {
        return '<meta name="robots" content="noindex, follow">' . PHP_EOL;
    }
    return '';
}



/**
 * Проверка, включен ли сайт
 * @return bool Статус сайта
 */
function is_site_enabled() {
    $enabled = db_get_var(
        "SELECT value FROM " . getConfig('db.prefix') . "settings WHERE name = 'site_enabled'"
    );
    
    return $enabled !== '0';
}

/**
 * Проверка, является ли текущий пользователь администратором
 * @return bool
 */
function is_admin() {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    
    return isset($_SESSION[getConfig('security.admin_session_name')]);
}

/**
 * Улучшенный алгоритм подбора похожих видео с использованием полнотекстового поиска
 * @param int $videoId ID текущего видео
 * @param string $title Название видео
 * @param int $limit Количество похожих видео
 * @param bool $crossSite Использовать видео с других сайтов сети
 * @return array Список похожих видео
 */
function get_related_videos($videoId, $title, $limit = 12, $crossSite = null) {
    if ($crossSite === null) {
        $crossSite = getConfig('network.cross_related');
    }
    
    $cacheKey = 'related_' . $videoId . '_' . $limit . '_' . ($crossSite ? '1' : '0');
    $cached = cache_get($cacheKey);
    
    if ($cached !== null) {
        return $cached;
    }
    
    // Получаем ключевые слова из названия
    $keywords = explode(' ', $title);
    $keywords = array_filter($keywords, function($word) {
        return mb_strlen($word) > 3;
    });
    
    $videos = [];
    
    if (!empty($keywords)) {
        // Формируем условие для полнотекстового поиска в натуральном режиме
        $prefix = getConfig('db.prefix');
        
        // Проверяем, существует ли полнотекстовый индекс на поле title
        $hasFulltextIndex = db_get_var(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
            WHERE table_schema = DATABASE() 
            AND table_name = '{$prefix}videos' 
            AND index_name = 'title' 
            AND index_type = 'FULLTEXT'"
        ) > 0;
        
        if ($hasFulltextIndex) {
            // Используем полнотекстовый поиск в натуральном режиме только по названиям
            $searchQuery = implode(' ', $keywords);
            
            $sql = "SELECT v.*, 
                   MATCH(v.title) AGAINST (? IN NATURAL LANGUAGE MODE) AS relevance
                   FROM {$prefix}videos v
                   WHERE v.id != ? AND MATCH(v.title) AGAINST (? IN NATURAL LANGUAGE MODE)
                   ORDER BY RAND() * relevance DESC
                   LIMIT ?";
            
            $videos = db_get_rows($sql, [$searchQuery, $videoId, $searchQuery, $limit]);
        } else {
            // Если полнотекстовый индекс не существует, используем LIKE для названий
            $conditions = [];
            $params = [];
            
            foreach ($keywords as $keyword) {
                $conditions[] = "title LIKE ?";
                $params[] = '%' . $keyword . '%';
            }
            
            $whereClause = implode(' OR ', $conditions);
            $sql = "SELECT * FROM {$prefix}videos 
                    WHERE id != ? AND ({$whereClause}) 
                    ORDER BY RAND() LIMIT ?";
            
            array_unshift($params, $videoId);
            $params[] = $limit;
            
            $videos = db_get_rows($sql, $params);
        }
    }
    
    // Если недостаточно похожих видео или они отсутствуют
    if (count($videos) < $limit) {
        $remaining = $limit - count($videos);
        $existingIds = array_column($videos, 'id');
        $existingIds[] = $videoId;
        
        $placeholders = implode(',', array_fill(0, count($existingIds), '?'));
        $sql = "SELECT * FROM " . getConfig('db.prefix') . "videos 
                WHERE id NOT IN ({$placeholders}) 
                ORDER BY RAND() LIMIT ?";
        
        $params = $existingIds;
        $params[] = $remaining;
        
        $additionalVideos = db_get_rows($sql, $params);
        $videos = array_merge($videos, $additionalVideos);
    }
    
    // Если включен обмен с другими сайтами сети
    if ($crossSite && getConfig('network.enabled') && !empty(getConfig('network.sites'))) {
        $ratio = getConfig('network.cross_related_ratio') / 100;
        $crossCount = (int)($limit * $ratio);
        
        if ($crossCount > 0) {
            // Заменяем часть видео на видео с других сайтов
            $sites = getConfig('network.sites');
            
            if (!empty($sites)) {
                // Получаем случайные сайты
                $siteIds = array_keys($sites);
                shuffle($siteIds);
                $randomSites = array_slice($siteIds, 0, min(count($sites), 3));
                
                $crossVideos = [];
                
                foreach ($randomSites as $siteId) {
                    $site = $sites[$siteId];
                    
                    // Получаем случайные видео с другого сайта
                    $apiUrl = $site['url'] . '/api.php?action=random_videos&limit=' . ceil($crossCount / count($randomSites));
                    
                    try {
                        $response = http_request($apiUrl);
                        $result = json_decode($response['body'], true);
                        
                        if (isset($result['videos']) && !empty($result['videos'])) {
                            foreach ($result['videos'] as $video) {
                                $video['external'] = true;
                                $video['site_url'] = $site['url'];
                                $crossVideos[] = $video;
                            }
                        }
                    } catch (Exception $e) {
                        log_error('Cross-site video fetch error: ' . $e->getMessage());
                    }
                }
                
                // Перемешиваем все видео
                shuffle($videos);
                
                // Заменяем часть собственных видео на внешние
                if (!empty($crossVideos)) {
                    array_splice($videos, 0, min(count($crossVideos), $crossCount), $crossVideos);
                    $videos = array_slice($videos, 0, $limit);
                }
            }
        }
    }
    
    // Получаем дополнительные данные для каждого видео (категории и теги)
    $videoEngine = isset($GLOBALS['videoEngine']) ? $GLOBALS['videoEngine'] : null;
    if ($videoEngine) {
        foreach ($videos as &$video) {
            if (!isset($video['external']) || !$video['external']) {
                $video['categories'] = $videoEngine->getVideoCategories($video['id']);
                $video['tags'] = $videoEngine->getVideoTags($video['id']);
            }
        }
    }
    
    // Кэшируем результат
    cache_set($cacheKey, $videos, 1800); // 30 минут
    
    return $videos;
}

/**
 * Проверяет, существуют ли видео с указанным тегом
 * @param int $tagId ID тега
 * @return bool Есть ли видео с тегом
 */
function tag_has_videos($tagId) {
    $count = db_get_var(
        "SELECT COUNT(*) FROM " . getConfig('db.prefix') . "video_tags WHERE tag_id = ?",
        [$tagId]
    );
    
    return $count > 0;
}

/**
 * Проверяет, существуют ли видео в указанной категории
 * @param int $categoryId ID категории
 * @return bool Есть ли видео в категории
 */
function category_has_videos($categoryId) {
    $count = db_get_var(
        "SELECT COUNT(*) FROM " . getConfig('db.prefix') . "video_categories WHERE category_id = ?",
        [$categoryId]
    );
    
    return $count > 0;
}

/**
 * Поиск видео по поисковому запросу
 * @param string $query Поисковый запрос
 * @param bool $autoSave Сохранять запрос в базе
 * @return array Массив с ID найденных видео
 */
function search_videos_for_query($query, $autoSave = true) {
    // Фильтруем запрос
    $query = sanitize_search_query($query);
    
    if (empty($query)) {
        return [];
    }
    
    // Сохраняем запрос в базу, если нужно
    if ($autoSave) {
        $existingQuery = db_get_row(
            "SELECT * FROM " . getConfig('db.prefix') . "search_queries WHERE query = ?",
            [$query]
        );
        
        if ($existingQuery) {
            // Обновляем существующий запрос
            db_update('search_queries', [
                'count' => $existingQuery['count'] + 1,
                'last_search' => date('Y-m-d H:i:s')
            ], ['id' => $existingQuery['id']]);
        } else {
            // Добавляем новый запрос
            db_insert('search_queries', [
                'query' => $query,
                'count' => 1,
                'last_search' => date('Y-m-d H:i:s')
            ]);
        }
    }
    
    // Проверяем существование полнотекстового индекса на title
    $prefix = getConfig('db.prefix');
    $hasFulltextIndex = db_get_var(
        "SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
        WHERE table_schema = DATABASE() 
        AND table_name = '{$prefix}videos' 
        AND index_name = 'title' 
        AND index_type = 'FULLTEXT'"
    ) > 0;
    
    // Используем полнотекстовый поиск или LIKE в зависимости от наличия индекса
    if ($hasFulltextIndex) {
        $sql = "SELECT id FROM {$prefix}videos 
               WHERE MATCH(title) AGAINST (? IN NATURAL LANGUAGE MODE)
               ORDER BY MATCH(title) AGAINST (? IN NATURAL LANGUAGE MODE) DESC";
        
        $videos = db_get_rows($sql, [$query, $query]);
    } else {
        // Формируем условия для LIKE поиска
        $keywords = explode(' ', $query);
        $conditions = [];
        $params = [];
        
        foreach ($keywords as $keyword) {
            if (strlen($keyword) > 2) {
                $conditions[] = "title LIKE ?";
                $params[] = '%' . $keyword . '%';
            }
        }
        
        if (empty($conditions)) {
            return [];
        }
        
        $whereClause = implode(' OR ', $conditions);
        $sql = "SELECT id FROM {$prefix}videos WHERE {$whereClause} ORDER BY post_date DESC";
        
        $videos = db_get_rows($sql, $params);
    }
    
    return array_column($videos, 'id');
}

/**
 * Получение популярных поисковых запросов, по которым есть результаты
 * @param int $minVideos Минимальное количество видео для поискового запроса
 * @param int $limit Максимальное количество запросов
 * @return array Массив поисковых запросов
 */
function get_popular_search_queries($minVideos = 5, $limit = 20) {
    $queries = db_get_rows(
        "SELECT * FROM " . getConfig('db.prefix') . "search_queries 
        ORDER BY count DESC LIMIT 100" // Берем больше, чтобы отфильтровать
    );
    
    $result = [];
    
    foreach ($queries as $query) {
        // Проверяем количество результатов для запроса
        $videoCount = count(search_videos_for_query($query['query'], false));
        
        if ($videoCount >= $minVideos) {
            $result[] = $query;
            
            if (count($result) >= $limit) {
                break;
            }
        }
    }
    
    return $result;
}

/**
 * Генерация случайного видео URL
 * @return string URL случайного видео
 */
function get_random_video_url() {
    $video = db_get_row(
        "SELECT slug FROM " . getConfig('db.prefix') . "videos ORDER BY RAND() LIMIT 1"
    );
    
    if ($video) {
        return getConfig('site.url') . '/video/' . $video['slug'];
    }
    
    return getConfig('site.url');
}

/**
 * Получение URL страницы с тегами
 * @return string URL страницы с тегами
 */
function get_tags_page_url() {
    return getConfig('site.url') . '/tags';
}

/**
 * Получение URL страницы с популярными видео
 * @return string URL страницы с популярными видео
 */
function get_popular_videos_url() {
    return getConfig('site.url') . '/popular';
}