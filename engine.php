<?php
/**
 * Основной движок для обработки видео
 * Отвечает за парсинг XML-фидов, обработку и сохранение видео
 */

// Предотвращение прямого доступа к файлу
if (!defined('VIDEOSYSTEM')) {
    die('Прямой доступ к этому файлу запрещен!');
}

/**
 * Класс для работы с видео
 */
class VideoEngine {
    /**
     * Импорт видео из XML фида
     * @param string $url URL XML фида
     * @return array Результат импорта
     */
    public function importFromXml($url) {
        $result = [
            'total' => 0,
            'imported' => 0,
            'errors' => 0,
            'skipped' => 0
        ];
        
        // Получаем данные из XML
        $videos = parse_video_xml($url);
        
        if (empty($videos)) {
            log_error('Empty XML feed or parsing error: ' . $url);
            return $result;
        }
        
        $result['total'] = count($videos);
        
        // Проходим по всем видео
        foreach ($videos as $video) {
            try {
                // Проверяем, существует ли видео с таким ID
                $existingVideo = $this->getVideoById($video['id']);
                
                if ($existingVideo) {
                    $result['skipped']++;
                    continue;
                }
                
                // Подготавливаем данные для сохранения
                $videoData = $this->prepareVideoData($video);
                
                // Сохраняем видео в базу данных
                $videoId = $this->saveVideo($videoData);
                
                if ($videoId) {
                    $result['imported']++;
                    
                    // Индексируем видео в Elasticsearch
                    if (getConfig('elasticsearch.enabled')) {
                        es_index_video($videoData);
                    }
                } else {
                    $result['errors']++;
                    log_error('Failed to save video ID: ' . $video['id']);
                }
            } catch (Exception $e) {
                $result['errors']++;
                log_error('Error processing video: ' . $e->getMessage());
            }
        }
        
        // Очищаем кэш
        if ($result['imported'] > 0 || $result['errors'] > 0) {
            cache_clear();
        }
        
        return $result;
    }
    
    /**
     * Подготовка данных видео перед сохранением
     * @param array $video Данные видео из XML
     * @return array Подготовленные данные
     */
    private function prepareVideoData($video) {
        // Основные данные
        $data = [
            'id' => $video['id'],
            'title' => $video['title'],
            'dir' => $video['dir'],
            'description' => $video['description'],
            'original_link' => $video['link'],
            'embed_code' => $video['embed'],
            'rating' => $video['rating'],
            'votes' => $video['votes'],
            'popularity' => $video['popularity'],
            'post_date' => $video['post_date'],
            'added_date' => date('Y-m-d H:i:s'),
            'duration' => $video['duration'] ?? 0,
            'width' => $video['width'] ?? 0,
            'height' => $video['height'] ?? 0,
            'filesize' => $video['filesize'] ?? 0,
            'file_url' => $video['file_url'] ?? '',
            'processed' => 0
        ];
        
        // Получаем и обрабатываем основной скриншот
        $mainScreenNumber = isset($video['main_screen']) ? $video['main_screen'] : 10; // Скриншот №10 по умолчанию
        if (isset($video['screens'][$mainScreenNumber])) {
            $data['thumb_url'] = $video['screens'][$mainScreenNumber];
            $data['thumb_processed'] = process_thumbnail($data['thumb_url'], $data['id']);
        } elseif (!empty($video['screens'])) {
            // Если скриншот №10 не найден, берем первый доступный
            $firstScreen = reset($video['screens']);
            $data['thumb_url'] = $firstScreen;
            $data['thumb_processed'] = process_thumbnail($data['thumb_url'], $data['id']);
        }
        
        // Генерируем слаг (ЧПУ)
        $data['slug'] = $video['dir'] ? $video['dir'] : translit($video['title']);
        
        // Генерируем защищенную ссылку на видео
        if (getConfig('videos.use_hotlink') && !empty($data['file_url'])) {
            $data['secure_url'] = generate_video_url($data['file_url'], $data['id']);
        } else {
            $data['secure_url'] = $data['file_url'];
        }
        
        // Автоматически подбираем категории
        $data['categories'] = auto_categorize_video($data['title'], $data['description']);
        
        // Автоматически генерируем теги
        $data['tags'] = advanced_auto_generate_tags([
            'title' => $data['title'],
            'description' => $data['description']
        ]);
        
        return $data;
    }
    
    /**
     * Сохранение видео в базу данных
     * @param array $data Данные видео
     * @return int|bool ID видео или false в случае ошибки
     */
    public function saveVideo($data) {
        try {
            // Основные данные видео
            $videoData = [
                'id' => $data['id'],
                'title' => $data['title'],
                'slug' => $data['slug'],
                'description' => $data['description'],
                'original_link' => $data['original_link'],
                'embed_code' => $data['embed_code'],
                'thumb_url' => $data['thumb_url'],
                'thumb_processed' => $data['thumb_processed'],
                'file_url' => $data['file_url'],
                'secure_url' => $data['secure_url'],
                'duration' => $data['duration'],
                'width' => $data['width'],
                'height' => $data['height'],
                'filesize' => $data['filesize'],
                'rating' => $data['rating'],
                'votes' => $data['votes'],
                'popularity' => $data['popularity'],
                'post_date' => $data['post_date'],
                'added_date' => $data['added_date'],
                'processed' => $data['processed']
            ];
            
            // Сохраняем видео
            $videoId = db_insert('videos', $videoData);
            
            if (!$videoId) {
                log_error('Failed to insert video into database, ID: ' . $data['id']);
                return false;
            }
            
            // Сохраняем категории
            if (!empty($data['categories'])) {
                foreach ($data['categories'] as $categoryId) {
                    db_insert('video_categories', [
                        'video_id' => $videoId,
                        'category_id' => $categoryId
                    ]);
                }
            }
            
            // Сохраняем теги
            if (!empty($data['tags'])) {
                foreach ($data['tags'] as $tagId) {
                    // Связываем тег с видео
                    db_insert('video_tags', [
                        'video_id' => $videoId,
                        'tag_id' => $tagId
                    ]);
                }
            }
            
            return $videoId;
        } catch (Exception $e) {
            log_error('Error saving video: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Импорт тегов из файла
     * @param string $filePath Путь к файлу
     * @return array Результат импорта
     */
    public function importTagsFromFile($filePath) {
        $result = [
            'total' => 0,
            'imported' => 0,
            'errors' => 0,
            'skipped' => 0
        ];
        
        try {
            // Получаем содержимое файла
            $content = file_get_contents($filePath);
            if ($content === false) {
                throw new Exception('Unable to read file: ' . $filePath);
            }
            
            // Разбиваем на строки
            $lines = explode("\n", $content);
            $result['total'] = count($lines);
            
            foreach ($lines as $line) {
                $tag = trim($line);
                if (empty($tag)) {
                    continue;
                }
                
                // Проверяем существование тега
                $tagId = db_get_var(
                    "SELECT id FROM " . getConfig('db.prefix') . "tags WHERE name = ?",
                    [$tag]
                );
                
                if ($tagId) {
                    $result['skipped']++;
                    continue;
                }
                
                // Создаем тег
                $tagId = db_insert('tags', [
                    'name' => $tag,
                    'slug' => translit($tag)
                ]);
                
                if ($tagId) {
                    $result['imported']++;
                } else {
                    $result['errors']++;
                }
            }
            
            return $result;
        } catch (Exception $e) {
            log_error('Error importing tags: ' . $e->getMessage());
            return [
                'total' => 0,
                'imported' => 0,
                'errors' => 1,
                'skipped' => 0,
                'error_message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Импорт поисковых запросов из файла
     * @param string $filePath Путь к файлу
     * @return array Результат импорта
     */
    public function importSearchQueriesFromFile($filePath) {
        $result = [
            'total' => 0,
            'imported' => 0,
            'errors' => 0,
            'skipped' => 0
        ];
        
        try {
            // Получаем содержимое файла
            $content = file_get_contents($filePath);
            if ($content === false) {
                throw new Exception('Unable to read file: ' . $filePath);
            }
            
            // Разбиваем на строки
            $lines = explode("\n", $content);
            $result['total'] = count($lines);
            
            foreach ($lines as $line) {
                $query = trim($line);
                if (empty($query)) {
                    continue;
                }
                
                // Фильтруем запрос
                $query = sanitize_search_query($query);
                
                // Проверяем существование запроса
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
                    
                    $result['skipped']++;
                } else {
                    // Добавляем новый запрос
                    $queryId = db_insert('search_queries', [
                        'query' => $query,
                        'count' => mt_rand(10, 1000), // Случайное количество запросов
                        'last_search' => date('Y-m-d H:i:s')
                    ]);
                    
                    if ($queryId) {
                        $result['imported']++;
                    } else {
                        $result['errors']++;
                    }
                }
            }
            
            return $result;
        } catch (Exception $e) {
            log_error('Error importing search queries: ' . $e->getMessage());
            return [
                'total' => 0,
                'imported' => 0,
                'errors' => 1,
                'skipped' => 0,
                'error_message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Получение видео по ID
     * @param int $id ID видео
     * @return array|null Данные видео или null, если видео не найдено
     */
    public function getVideoById($id) {
        $video = db_get_row(
            "SELECT * FROM " . getConfig('db.prefix') . "videos WHERE id = ?",
            [$id]
        );
        
        if (!$video) {
            return null;
        }
        
        // Получаем категории видео
        $video['categories'] = $this->getVideoCategories($video['id']);
        
        // Получаем теги видео
        $video['tags'] = $this->getVideoTags($video['id']);
        
        return $video;
    }
    
    /**
     * Получение видео по слагу
     * @param string $slug Слаг видео
     * @return array|null Данные видео или null, если видео не найдено
     */
    public function getVideoBySlug($slug) {
        $video = db_get_row(
            "SELECT * FROM " . getConfig('db.prefix') . "videos WHERE slug = ?",
            [$slug]
        );
        
        if (!$video) {
            return null;
        }
        
        // Получаем категории видео
        $video['categories'] = $this->getVideoCategories($video['id']);
        
        // Получаем теги видео
        $video['tags'] = $this->getVideoTags($video['id']);
        
        return $video;
    }
    
    /**
     * Получение категорий видео
     * @param int $videoId ID видео
     * @return array Массив с ID категорий
     */
    public function getVideoCategories($videoId) {
        $categories = db_get_rows(
            "SELECT c.id, c.name, c.slug FROM " . getConfig('db.prefix') . "video_categories vc
            JOIN " . getConfig('db.prefix') . "categories c ON vc.category_id = c.id
            WHERE vc.video_id = ?",
            [$videoId]
        );
        
        return $categories;
    }
    
    /**
     * Получение тегов видео
     * @param int $videoId ID видео
     * @return array Массив с тегами
     */
    public function getVideoTags($videoId) {
        $tags = db_get_rows(
            "SELECT t.id, t.name, t.slug FROM " . getConfig('db.prefix') . "video_tags vt
            JOIN " . getConfig('db.prefix') . "tags t ON vt.tag_id = t.id
            WHERE vt.video_id = ?",
            [$videoId]
        );
        
        return $tags;
    }
    
    /**
     * Обновление видео
     * @param int $videoId ID видео
     * @param array $data Данные для обновления
     * @return bool Результат обновления
     */
    public function updateVideo($videoId, $data) {
        try {
            // Обновляем основные данные видео
            $result = db_update('videos', $data, ['id' => $videoId]);
            
            if ($result) {
                // Обновляем категории, если они переданы
                if (isset($data['categories'])) {
                    // Удаляем старые связи
                    db_query(
                        "DELETE FROM " . getConfig('db.prefix') . "video_categories WHERE video_id = ?",
                        [$videoId]
                    );
                    
                    // Добавляем новые связи
                    foreach ($data['categories'] as $categoryId) {
                        db_insert('video_categories', [
                            'video_id' => $videoId,
                            'category_id' => $categoryId
                        ]);
                    }
                }
                
                // Обновляем теги, если они переданы
                if (isset($data['tags'])) {
                    // Удаляем старые связи
                    db_query(
                        "DELETE FROM " . getConfig('db.prefix') . "video_tags WHERE video_id = ?",
                        [$videoId]
                    );
                    
                    // Добавляем новые связи
                    foreach ($data['tags'] as $tag) {
                        // Проверяем существование тега
                        $tagId = db_get_var(
                            "SELECT id FROM " . getConfig('db.prefix') . "tags WHERE name = ?",
                            [$tag]
                        );
                        
                        if (!$tagId) {
                            // Если тег не существует, создаем его
                            $tagId = db_insert('tags', [
                                'name' => $tag,
                                'slug' => translit($tag)
                            ]);
                        }
                        
                        if ($tagId) {
                            // Связываем тег с видео
                            db_insert('video_tags', [
                                'video_id' => $videoId,
                                'tag_id' => $tagId
                            ]);
                        }
                    }
                }
                
                // Индексируем обновленное видео в Elasticsearch
                if (getConfig('elasticsearch.enabled')) {
                    $updatedVideo = $this->getVideoById($videoId);
                    es_index_video($updatedVideo);
                }
                
                return true;
            }
            
            return false;
        } catch (Exception $e) {
            log_error('Error updating video: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Удаление видео
     * @param int $videoId ID видео
     * @return bool Результат удаления
     */
    public function deleteVideo($videoId) {
        try {
            // Удаляем связи с категориями
            db_query(
                "DELETE FROM " . getConfig('db.prefix') . "video_categories WHERE video_id = ?",
                [$videoId]
            );
            
            // Удаляем связи с тегами
            db_query(
                "DELETE FROM " . getConfig('db.prefix') . "video_tags WHERE video_id = ?",
                [$videoId]
            );
            
            // Удаляем видео
            $result = db_delete('videos', ['id' => $videoId]);
            
            return $result;
        } catch (Exception $e) {
            log_error('Error deleting video: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Удаление набора видео
     * @param array $videoIds Массив ID видео для удаления
     * @return array Результат удаления ['success' => количество успешно удаленных, 'errors' => количество ошибок]
     */
    public function deleteVideos($videoIds) {
        $result = ['success' => 0, 'errors' => 0];
        
        if (empty($videoIds)) {
            return $result;
        }
        
        foreach ($videoIds as $videoId) {
            $deleteResult = $this->deleteVideo($videoId);
            
            if ($deleteResult) {
                $result['success']++;
            } else {
                $result['errors']++;
            }
        }
        
        return $result;
    }
    
    /**
     * Поиск видео
     * @param string $query Поисковый запрос
     * @param int $page Номер страницы
     * @param int $perPage Количество видео на странице
     * @param array $filters Дополнительные фильтры
     * @return array Результаты поиска
     */
    public function searchVideos($query, $page = 1, $perPage = 24, $filters = []) {
        // Используем Elasticsearch, если он включен
        if (getConfig('elasticsearch.enabled')) {
            $from = ($page - 1) * $perPage;
            return es_search_videos($query, $from, $perPage, $filters);
        }
        
        // Иначе используем поиск через MySQL
        $conditions = [];
        $params = [];
        
        // Поисковый запрос
        if (!empty($query)) {
            // Проверяем наличие полнотекстового индекса
            $prefix = getConfig('db.prefix');
            $hasFulltextIndex = db_get_var(
                "SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
                WHERE table_schema = DATABASE() 
                AND table_name = '{$prefix}videos' 
                AND index_name = 'title' 
                AND index_type = 'FULLTEXT'"
            ) > 0;
            
            if ($hasFulltextIndex) {
                // Используем полнотекстовый поиск только по названиям
                $conditions[] = "MATCH(title) AGAINST (? IN NATURAL LANGUAGE MODE)";
                $params[] = $query;
            } else {
                // Используем LIKE для названий
                $conditions[] = "(title LIKE ?)";
                $params[] = '%' . $query . '%';
            }
        }
        
        // Дополнительные фильтры
        if (!empty($filters['category'])) {
            $conditions[] = "id IN (SELECT video_id FROM " . getConfig('db.prefix') . "video_categories WHERE category_id = ?)";
            $params[] = $filters['category'];
        }
        
        if (!empty($filters['tag'])) {
            $conditions[] = "id IN (SELECT video_id FROM " . getConfig('db.prefix') . "video_tags WHERE tag_id = ?)";
            $params[] = $filters['tag'];
        }
        
        if (!empty($filters['date_from'])) {
            $conditions[] = "post_date >= ?";
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $conditions[] = "post_date <= ?";
            $params[] = $filters['date_to'];
        }
        
        // Формируем запрос
        $whereClause = !empty($conditions) ? "WHERE " . implode(' AND ', $conditions) : "";
        
        // Определяем сортировку
        $orderBy = "post_date DESC"; // По умолчанию сортировка по дате
        
        if (!empty($filters['sort'])) {
            if ($filters['sort'] === 'popularity') {
                $orderBy = "popularity DESC";
            } elseif ($filters['sort'] === 'rating') {
                $orderBy = "rating DESC";
            } elseif ($filters['sort'] === 'title') {
                $orderBy = "title ASC";
            }
        }
        
        // Получаем общее количество видео
        $totalSql = "SELECT COUNT(*) FROM " . getConfig('db.prefix') . "videos {$whereClause}";
        $total = db_get_var($totalSql, $params);
        
        // Получаем видео с пагинацией
        $offset = ($page - 1) * $perPage;
        $sql = "SELECT * FROM " . getConfig('db.prefix') . "videos {$whereClause} ORDER BY {$orderBy} LIMIT {$offset}, {$perPage}";
        $videos = db_get_rows($sql, $params);
        
        // Получаем дополнительные данные для каждого видео
        foreach ($videos as &$video) {
            $video['categories'] = $this->getVideoCategories($video['id']);
            $video['tags'] = $this->getVideoTags($video['id']);
        }
        
        return [
            'total' => $total,
            'videos' => $videos
        ];
    }
    
    /**
     * Получение последних видео
     * @param int $limit Количество видео
     * @param int $offset Смещение
     * @return array Массив с видео
     */
    public function getLatestVideos($limit = 24, $offset = 0) {
        $sql = "SELECT * FROM " . getConfig('db.prefix') . "videos ORDER BY post_date DESC LIMIT {$offset}, {$limit}";
        $videos = db_get_rows($sql);
        
        // Получаем дополнительные данные для каждого видео
        foreach ($videos as &$video) {
            $video['categories'] = $this->getVideoCategories($video['id']);
            $video['tags'] = $this->getVideoTags($video['id']);
        }
        
        return $videos;
    }
    
    /**
     * Получение популярных видео
     * @param int $limit Количество видео
     * @param int $offset Смещение
     * @return array Массив с видео
     */
    public function getPopularVideos($limit = 24, $offset = 0) {
        $sql = "SELECT * FROM " . getConfig('db.prefix') . "videos ORDER BY popularity DESC LIMIT {$offset}, {$limit}";
        $videos = db_get_rows($sql);
        
        // Получаем дополнительные данные для каждого видео
        foreach ($videos as &$video) {
            $video['categories'] = $this->getVideoCategories($video['id']);
            $video['tags'] = $this->getVideoTags($video['id']);
        }
        
        return $videos;
    }
    
    /**
     * Получение видео по категории
     * @param int $categoryId ID категории
     * @param int $page Номер страницы
     * @param int $perPage Количество видео на странице
     * @return array Массив с видео и информацией о пагинации
     */
    public function getVideosByCategory($categoryId, $page = 1, $perPage = 24) {
        // Получаем общее количество видео в категории
        $totalSql = "SELECT COUNT(*) FROM " . getConfig('db.prefix') . "video_categories WHERE category_id = ?";
        $total = db_get_var($totalSql, [$categoryId]);
        
        // Получаем видео с пагинацией
        $offset = ($page - 1) * $perPage;
        $sql = "SELECT v.* FROM " . getConfig('db.prefix') . "videos v
                JOIN " . getConfig('db.prefix') . "video_categories vc ON v.id = vc.video_id
                WHERE vc.category_id = ?
                ORDER BY v.post_date DESC
                LIMIT {$offset}, {$perPage}";
        
        $videos = db_get_rows($sql, [$categoryId]);
        
        // Получаем дополнительные данные для каждого видео
        foreach ($videos as &$video) {
            $video['categories'] = $this->getVideoCategories($video['id']);
            $video['tags'] = $this->getVideoTags($video['id']);
        }
        
        return [
            'total' => $total,
            'videos' => $videos
        ];
    }
    
    /**
     * Получение видео по тегу
     * @param int $tagId ID тега
     * @param int $page Номер страницы
     * @param int $perPage Количество видео на странице
     * @return array Массив с видео и информацией о пагинации
     */
    public function getVideosByTag($tagId, $page = 1, $perPage = 24) {
        // Получаем общее количество видео с тегом
        $totalSql = "SELECT COUNT(*) FROM " . getConfig('db.prefix') . "video_tags WHERE tag_id = ?";
        $total = db_get_var($totalSql, [$tagId]);
        
        // Получаем видео с пагинацией
        $offset = ($page - 1) * $perPage;
        $sql = "SELECT v.* FROM " . getConfig('db.prefix') . "videos v
                JOIN " . getConfig('db.prefix') . "video_tags vt ON v.id = vt.video_id
                WHERE vt.tag_id = ?
                ORDER BY v.post_date DESC
                LIMIT {$offset}, {$perPage}";
        
        $videos = db_get_rows($sql, [$tagId]);
        
        // Получаем дополнительные данные для каждого видео
        foreach ($videos as &$video) {
            $video['categories'] = $this->getVideoCategories($video['id']);
            $video['tags'] = $this->getVideoTags($video['id']);
        }
        
        return [
            'total' => $total,
            'videos' => $videos
        ];
    }
    
    /**
     * Получение случайных видео
     * @param int $limit Количество видео
     * @return array Массив с видео
     */
    public function getRandomVideos($limit = 24) {
        $sql = "SELECT * FROM " . getConfig('db.prefix') . "videos ORDER BY RAND() LIMIT {$limit}";
        $videos = db_get_rows($sql);
        
        // Получаем дополнительные данные для каждого видео
        foreach ($videos as &$video) {
            $video['categories'] = $this->getVideoCategories($video['id']);
            $video['tags'] = $this->getVideoTags($video['id']);
        }
        
        return $videos;
    }
    
    /**
     * Получение всех тегов с количеством видео
     * @return array Список тегов с количеством видео
     */
    public function getAllTags() {
        $sql = "SELECT t.*, COUNT(vt.video_id) as video_count 
               FROM " . getConfig('db.prefix') . "tags t
               LEFT JOIN " . getConfig('db.prefix') . "video_tags vt ON t.id = vt.tag_id
               GROUP BY t.id
               ORDER BY t.name ASC";
        
        return db_get_rows($sql);
    }
    
    /**
     * Получение тегов, которые имеют видео
     * @param int $minVideos Минимальное количество видео для тега
     * @return array Список тегов с количеством видео
     */
    public function getTagsWithVideos($minVideos = 1) {
        $sql = "SELECT t.*, COUNT(vt.video_id) as video_count 
               FROM " . getConfig('db.prefix') . "tags t
               LEFT JOIN " . getConfig('db.prefix') . "video_tags vt ON t.id = vt.tag_id
               GROUP BY t.id
               HAVING COUNT(vt.video_id) >= ?
               ORDER BY t.name ASC";
        
        return db_get_rows($sql, [$minVideos]);
    }
    
    /**
     * Генерация описания для сайта через ChatGPT
     * @return string Сгенерированное описание или пустая строка в случае ошибки
     */
    public function generateSiteDescription() {
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
     * Генерация описания для категории через ChatGPT
     * @param int $categoryId ID категории
     * @return string Сгенерированное описание или пустая строка в случае ошибки
     */
    public function generateCategoryDescription($categoryId) {
        if (!getConfig('chatgpt.enabled')) {
            return '';
        }
        
        // Получаем категорию
        $category = db_get_row(
            "SELECT * FROM " . getConfig('db.prefix') . "categories WHERE id = ?",
            [$categoryId]
        );
        
        if (!$category) {
            return '';
        }
        
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
            db_update('categories', ['description' => $description], ['id' => $categoryId]);
            
            // Очищаем кэш
            cache_clear();
        }
        
        return $description;
    }
    
    /**
     * Генерация описания для тега через ChatGPT
     * @param int $tagId ID тега
     * @return string Сгенерированное описание или пустая строка в случае ошибки
     */
    public function generateTagDescription($tagId) {
        if (!getConfig('chatgpt.enabled')) {
            return '';
        }
        
        // Получаем тег
        $tag = db_get_row(
            "SELECT * FROM " . getConfig('db.prefix') . "tags WHERE id = ?",
            [$tagId]
        );
        
        if (!$tag) {
            return '';
        }
        
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
            db_update('tags', ['description' => $description], ['id' => $tagId]);
            
            // Очищаем кэш
            cache_clear();
        }
        
        return $description;
    }
    
    /**
     * Получение статуса обработки видео через ChatGPT
     * @return array Информация о процессе
     */
    public function getChatGPTProcessingStatus() {
        $status = [
            'total' => db_get_var("SELECT COUNT(*) FROM " . getConfig('db.prefix') . "videos"),
            'processed_titles' => db_get_var("SELECT COUNT(*) FROM " . getConfig('db.prefix') . "videos WHERE processed_title = 1"),
            'processed_descriptions' => db_get_var("SELECT COUNT(*) FROM " . getConfig('db.prefix') . "videos WHERE processed_description = 1"),
            'last_processed_id' => db_get_var("SELECT value FROM " . getConfig('db.prefix') . "settings WHERE name = 'chatgpt_last_processed_id'") ?? 0
        ];
        
        return $status;
    }
    
    /**
     * Перезапуск процесса обработки видео через ChatGPT
     * @return bool Успешность перезапуска
     */
    public function resetChatGPTProcessing() {
        // Сбрасываем флаги обработки для всех видео
        db_query("UPDATE " . getConfig('db.prefix') . "videos SET processed_title = 0, processed_description = 0");
        
        // Сбрасываем последний обработанный ID
        db_query(
            "INSERT INTO " . getConfig('db.prefix') . "settings (name, value) 
            VALUES ('chatgpt_last_processed_id', '0') ON DUPLICATE KEY UPDATE value = '0'"
        );
        
        return true;
    }
    
    /**
     * Рерайт заголовков видео через ChatGPT
     * @param int $limit Количество видео для обработки
     * @return array Результаты обработки
     */
    public function processVideoTitlesWithChatGPT($limit = 10) {
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
        $lastId = db_get_var("SELECT value FROM " . getConfig('db.prefix') . "settings WHERE name = 'chatgpt_last_processed_id'") ?? 0;
        
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
            
            // Обновляем последний обработанный ID
            $lastProcessedId = $video['id'];
        }
        
        // Сохраняем последний обработанный ID
        db_query(
            "INSERT INTO " . getConfig('db.prefix') . "settings (name, value) 
            VALUES ('chatgpt_last_processed_id', ?) ON DUPLICATE KEY UPDATE value = ?",
            [$lastProcessedId, $lastProcessedId]
        );
        
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
    public function processVideoDescriptionsWithChatGPT($limit = 10) {
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
            
            // Обновляем последний обработанный ID
            $lastProcessedId = $video['id'];
        }
        
        // Сохраняем последний обработанный ID для описаний
        db_query(
            "INSERT INTO " . getConfig('db.prefix') . "settings (name, value) 
            VALUES ('chatgpt_last_description_id', ?) ON DUPLICATE KEY UPDATE value = ?",
            [$lastProcessedId, $lastProcessedId]
        );
        
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
    public function generateAllCategoryDescriptions() {
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
            $description = $this->generateCategoryDescription($category['id']);
            
            if (!empty($description)) {
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
    public function generateAllTagDescriptions() {
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
            $description = $this->generateTagDescription($tag['id']);
            
            if (!empty($description)) {
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
     * Автоматический подбор тегов для всех видео
     * @param int $limit Количество видео для обработки
     * @return array Результаты обработки
     */
    public function autoTagAllVideos($limit = 50) {
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
     * Запуск фонового поиска видео по поисковым запросам
     * @param int $minVideos Минимальное количество видео для поискового запроса
     * @return array Результаты обработки
     */
    public function runBackgroundSearch($minVideos = 5) {
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
}