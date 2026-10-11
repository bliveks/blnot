<?php
require_once __DIR__ . '/config.php';

session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => SECURE_COOKIES,
    'httponly' => true,
    'samesite' => 'Strict',
]);
ini_set('session.gc_maxlifetime', SESSION_LIFETIME);

$sessionDir = __DIR__ . '/sessions';
if (!file_exists($sessionDir)) {
    @mkdir($sessionDir, 0700, true);
}
if (is_dir($sessionDir) && is_writable($sessionDir)) {
    session_save_path($sessionDir);
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function generateCSRFToken() {
    if (!isset($_SESSION[CSRF_TOKEN_NAME])) {
        $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
    }
    return $_SESSION[CSRF_TOKEN_NAME];
}

function validateCSRFToken($token) {
    return isset($_SESSION[CSRF_TOKEN_NAME])
        && is_string($token)
        && hash_equals($_SESSION[CSRF_TOKEN_NAME], $token);
}

generateCSRFToken();

function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function errorResponse($message, $statusCode = 400) {
    jsonResponse(['success' => false, 'error' => $message], $statusCode);
}

function getDefaultCategories() {
    return [
        ['id' => 'general', 'label' => 'Ümumi', 'icon' => 'fa-folder', 'color' => '#5f73c9'],
        ['id' => 'work', 'label' => 'İş', 'icon' => 'fa-briefcase', 'color' => '#f59e0b'],
        ['id' => 'personal', 'label' => 'Şəxsi', 'icon' => 'fa-user', 'color' => '#10b981'],
        ['id' => 'shopping', 'label' => 'Alış-veriş', 'icon' => 'fa-cart-shopping', 'color' => '#ef4444'],
        ['id' => 'ideas', 'label' => 'Fikirlər', 'icon' => 'fa-lightbulb', 'color' => '#8b5cf6'],
    ];
}

function db() {
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME);

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    } catch (PDOException $e) {
        $serverDsn = sprintf('mysql:host=%s;port=%d;charset=utf8mb4', DB_HOST, DB_PORT);
        $serverPdo = new PDO($serverDsn, DB_USER, DB_PASS, $options);
        $dbNameEscaped = str_replace('`', '``', DB_NAME);
        $serverPdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbNameEscaped}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    }

    return $pdo;
}

function initDatabase() {
    $pdo = db();

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS users (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(64) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            role ENUM(\'admin\',\'user\') NOT NULL DEFAULT \'user\',
            active TINYINT(1) NOT NULL DEFAULT 1,
            last_login_at DATETIME NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN role ENUM('admin','user') NOT NULL DEFAULT 'user' AFTER password_hash");
    } catch (Throwable $e) {
        // role column already exists
    }
    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN active TINYINT(1) NOT NULL DEFAULT 1 AFTER role");
    } catch (Throwable $e) {
        // active column already exists
    }
    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN last_login_at DATETIME NULL AFTER active");
    } catch (Throwable $e) {
        // last_login_at column already exists
    }

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS categories (
            user_id BIGINT UNSIGNED NOT NULL,
            id VARCHAR(50) NOT NULL,
            label VARCHAR(50) NOT NULL,
            icon VARCHAR(50) NOT NULL,
            color CHAR(7) NOT NULL,
            position INT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (user_id, id),
            INDEX idx_categories_user (user_id),
            INDEX idx_categories_user_position (user_id, position)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    try {
        $pdo->exec('ALTER TABLE categories ADD COLUMN position INT NOT NULL DEFAULT 0 AFTER color');
    } catch (Throwable $e) {
        // position column already exists
    }
    try {
        $pdo->exec('ALTER TABLE categories ADD INDEX idx_categories_user_position (user_id, position)');
    } catch (Throwable $e) {
        // position index already exists
    }

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS notes (
            user_id BIGINT UNSIGNED NOT NULL,
            id VARCHAR(64) NOT NULL,
            content MEDIUMTEXT NOT NULL,
            category VARCHAR(50) NULL,
            completed TINYINT(1) NOT NULL DEFAULT 0,
            position INT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (user_id, id),
            INDEX idx_notes_user_position (user_id, position),
            INDEX idx_notes_user_category (user_id, category)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS note_attachments (
            id CHAR(32) NOT NULL PRIMARY KEY,
            user_id BIGINT UNSIGNED NOT NULL,
            note_id VARCHAR(64) NOT NULL,
            storage_key VARCHAR(80) NOT NULL UNIQUE,
            original_name VARCHAR(255) NOT NULL,
            mime_type VARCHAR(100) NOT NULL,
            size_bytes INT UNSIGNED NOT NULL,
            position INT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            INDEX idx_attachments_user_note (user_id, note_id),
            INDEX idx_attachments_user_note_position (user_id, note_id, position),
            CONSTRAINT fk_attachments_note
                FOREIGN KEY (user_id, note_id) REFERENCES notes (user_id, id)
                ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    try {
        $pdo->exec('ALTER TABLE note_attachments ADD COLUMN position INT NOT NULL DEFAULT 0 AFTER size_bytes');
    } catch (Throwable $e) {
        // position column already exists
    }
    try {
        $pdo->exec('ALTER TABLE note_attachments ADD INDEX idx_attachments_user_note_position (user_id, note_id, position)');
    } catch (Throwable $e) {
        // position index already exists
    }
}

function seedDefaultCategories($userId) {
    $pdo = db();
    $now = date('Y-m-d H:i:s');

    $ins = $pdo->prepare(
        'INSERT INTO categories (user_id, id, label, icon, color, position, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
            label = VALUES(label),
            icon = VALUES(icon),
            color = VALUES(color),
            position = VALUES(position),
            updated_at = VALUES(updated_at)'
    );

    foreach (getDefaultCategories() as $position => $cat) {
        $ins->execute([
            $userId,
            $cat['id'],
            $cat['label'],
            $cat['icon'],
            $cat['color'],
            $position,
            $now,
            $now,
        ]);
    }
}

function isLoggedIn() {
    if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        return false;
    }

    if (!isset($_SESSION['user_id']) || !is_int($_SESSION['user_id'])) {
        return false;
    }

    if (isset($_SESSION['login_time'])) {
        $elapsed = time() - (int)$_SESSION['login_time'];
        if ($elapsed > SESSION_LIFETIME) {
            session_destroy();
            return false;
        }
    }

    try {
        $stmt = db()->prepare('SELECT id, username, role, active FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([(int)$_SESSION['user_id']]);
        $user = $stmt->fetch();
    } catch (Throwable $e) {
        return false;
    }

    if (!$user || (int)($user['active'] ?? 1) !== 1) {
        $_SESSION = [];
        session_destroy();
        return false;
    }

    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['role'] = $user['role'] === 'admin' ? 'admin' : 'user';

    return true;
}

function getCurrentUserId() {
    return isset($_SESSION['user_id']) && is_int($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;
}

function isAdmin() {
    if (!isLoggedIn()) {
        return false;
    }
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function startUserSession($userId, $username, $role) {
    $stmt = db()->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?');
    $stmt->execute([(int)$userId]);

    session_regenerate_id(true);
    $_SESSION['logged_in'] = true;
    $_SESSION['login_time'] = time();
    $_SESSION['user_id'] = (int)$userId;
    $_SESSION['username'] = (string)$username;
    $_SESSION['role'] = $role === 'admin' ? 'admin' : 'user';
    generateCSRFToken();
}

function requireAdmin() {
    if (!isLoggedIn()) {
        errorResponse('Giriş edilmədi', 401);
    }
    if (!isAdmin()) {
        errorResponse('Bu əməliyyat üçün admin səlahiyyəti tələb olunur', 403);
    }
}

function createUser($username, $password, $role = 'user') {
    $username = trim((string)$username);
    $password = (string)$password;
    $role = $role === 'admin' ? 'admin' : 'user';

    if ($username === '' || mb_strlen($username) < 3 || mb_strlen($username) > 64) {
        throw new RuntimeException('Username 3-64 simvol olmalıdır');
    }
    if (!preg_match('/^[a-zA-Z0-9_.-]+$/', $username)) {
        throw new RuntimeException('Username yalnız hərf, rəqəm, _, -, . ola bilər');
    }
    if (mb_strlen($password) < 6) {
        throw new RuntimeException('Şifrə ən az 6 simvol olmalıdır');
    }

    $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    $stmt = db()->prepare('INSERT INTO users (username, password_hash, role) VALUES (?, ?, ?)');
    $stmt->execute([$username, $hash, $role]);

    return (int)db()->lastInsertId();
}

function getUsersList() {
    $stmt = db()->query('SELECT id, username, role, active, last_login_at, created_at FROM users ORDER BY created_at DESC');
    $users = $stmt->fetchAll() ?: [];

    foreach ($users as &$user) {
        $user['canEdit'] = canManageUser($user, 'update');
        $user['canDelete'] = canManageUser($user, 'delete');
    }
    unset($user);

    return $users;
}

function getAdminStats() {
    $pdo = db();

    return [
        'totalUsers' => (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn(),
        'adminUsers' => (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn(),
        'regularUsers' => (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'user'")->fetchColumn(),
        'totalNotes' => (int)$pdo->query('SELECT COUNT(*) FROM notes')->fetchColumn(),
        'notesLast7Days' => (int)$pdo->query("SELECT COUNT(*) FROM notes WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn(),
    ];
}

function getUserDetailsList() {
    $stmt = db()->query(
        'SELECT
            u.id,
            u.username,
            u.role,
            u.active,
            u.created_at,
            COUNT(DISTINCT n.id) AS notes_count,
            COUNT(DISTINCT c.id) AS categories_count
         FROM users u
         LEFT JOIN notes n ON n.user_id = u.id
         LEFT JOIN categories c ON c.user_id = u.id
         GROUP BY u.id, u.username, u.role, u.active, u.created_at
         ORDER BY u.created_at DESC'
    );

    return $stmt->fetchAll() ?: [];
}

function validateUsernameValue($username) {
    $username = trim((string)$username);

    if ($username === '' || mb_strlen($username) < 3 || mb_strlen($username) > 64) {
        throw new RuntimeException('İstifadəçi adı 3-64 simvol olmalıdır');
    }
    if (!preg_match('/^[a-zA-Z0-9_.-]+$/', $username)) {
        throw new RuntimeException('İstifadəçi adı yalnız hərf, rəqəm, _, -, . ola bilər');
    }
}

function userFriendlyError(Throwable $e) {
    $message = $e->getMessage();

    if ($e instanceof PDOException && $e->getCode() === '23000' && stripos($message, 'users.username') !== false) {
        return 'Bu istifadəçi adı artıq mövcuddur';
    }

    if (stripos($message, 'Duplicate entry') !== false && stripos($message, 'username') !== false) {
        return 'Bu istifadəçi adı artıq mövcuddur';
    }

    return $message;
}

function roleRank($role) {
    return $role === 'admin' ? 2 : 1;
}

function getUserById($userId) {
    $stmt = db()->prepare('SELECT id, username, role, created_at FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([(int)$userId]);
    $user = $stmt->fetch();
    return $user ?: null;
}

function canManageUser($targetUser, $operation) {
    if (!isLoggedIn() || !isAdmin() || !is_array($targetUser)) {
        return false;
    }

    $currentId = getCurrentUserId();
    $currentRole = $_SESSION['role'] ?? 'user';
    $targetId = (int)$targetUser['id'];
    $targetRole = $targetUser['role'] ?? 'user';

    if ($currentId === $targetId) {
        return false;
    }

    if ($currentId === 1) {
        return true;
    }

    return roleRank($currentRole) > roleRank($targetRole);
}

function updateUser($userId, $username, $role) {
    $target = getUserById($userId);
    if (!$target) {
        throw new RuntimeException('İstifadəçi tapılmadı');
    }
    if (!canManageUser($target, 'update')) {
        throw new RuntimeException('Bu istifadəçini dəyişmək icazəniz yoxdur');
    }

    $username = trim((string)$username);
    $role = $role === 'admin' ? 'admin' : 'user';
    if ($role === 'admin' && getCurrentUserId() !== 1) {
        throw new RuntimeException('Admin rolunu yalnız əsas admin verə bilər');
    }
    validateUsernameValue($username);

    $stmt = db()->prepare('UPDATE users SET username = ?, role = ? WHERE id = ?');
    $stmt->execute([$username, $role, (int)$userId]);

    if ((int)$userId === getCurrentUserId()) {
        $_SESSION['username'] = $username;
        $_SESSION['role'] = $role;
    }
}

function deleteUserById($userId) {
    $target = getUserById($userId);
    if (!$target) {
        throw new RuntimeException('İstifadəçi tapılmadı');
    }
    if (!canManageUser($target, 'delete')) {
        throw new RuntimeException('Bu istifadəçini silmək icazəniz yoxdur');
    }

    $pdo = db();
    $pdo->beginTransaction();

    try {
        $files = $pdo->prepare('SELECT storage_key FROM note_attachments WHERE user_id = ?');
        $files->execute([(int)$userId]);
        $storageKeys = $files->fetchAll(PDO::FETCH_COLUMN);

        $stmt = $pdo->prepare('DELETE FROM notes WHERE user_id = ?');
        $stmt->execute([(int)$userId]);

        $stmt = $pdo->prepare('DELETE FROM categories WHERE user_id = ?');
        $stmt->execute([(int)$userId]);

        $stmt = $pdo->prepare('DELETE FROM users WHERE id = ?');
        $stmt->execute([(int)$userId]);

        $pdo->commit();
        deleteStoredFiles($storageKeys);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function normalizeDateTime($value, $fallbackNow = false) {
    if (is_string($value) && trim($value) !== '') {
        $ts = strtotime($value);
        if ($ts !== false) {
            return date('Y-m-d H:i:s', $ts);
        }
    }

    return $fallbackNow ? date('Y-m-d H:i:s') : null;
}

function toIsoTime($mysqlDatetime) {
    $ts = strtotime((string)$mysqlDatetime);
    if ($ts === false) {
        return date(DATE_ATOM);
    }
    return date(DATE_ATOM, $ts);
}

function validateNoteData($note) {
    if (!is_array($note)) {
        return false;
    }

    if (!isset($note['content']) || !is_string($note['content']) || trim($note['content']) === '') {
        return false;
    }

    if (mb_strlen($note['content']) > 10000) {
        return false;
    }

    if (isset($note['category']) && (!is_string($note['category']) || mb_strlen($note['category']) > 50)) {
        return false;
    }

    if (preg_match('/<\s*script/i', $note['content']) || preg_match('/javascript\s*:/i', $note['content'])) {
        return false;
    }

    return true;
}

function attachmentResponse($attachment) {
    return [
        'id' => $attachment['id'],
        'name' => $attachment['original_name'],
        'mimeType' => $attachment['mime_type'],
        'sizeBytes' => (int)$attachment['size_bytes'],
        'url' => 'functions.php?action=downloadAttachment&id=' . rawurlencode($attachment['id']),
    ];
}

function getAttachmentPath($storageKey) {
    if (!is_string($storageKey) || !preg_match('/^[a-f0-9]{64}\.(?:jpg|png|gif|webp|pdf|txt|doc|docx|xls|xlsx|ppt|pptx)$/D', $storageKey)) {
        return null;
    }

    return UPLOAD_DIR . DIRECTORY_SEPARATOR . $storageKey;
}

function ensureUploadDirectory() {
    if (!is_dir(UPLOAD_DIR) && !@mkdir(UPLOAD_DIR, 0700, true)) {
        throw new RuntimeException('Fayl yaddaşı yaradıla bilmədi');
    }

    if (!is_writable(UPLOAD_DIR)) {
        throw new RuntimeException('Fayl yaddaşına yazmaq mümkün deyil');
    }
}

function sanitizeAttachmentName($name) {
    $name = str_replace('\\', '/', is_string($name) ? $name : '');
    $name = basename($name);
    $name = preg_replace('/[^\pL\pN._ ()-]+/u', '_', $name);
    $name = trim((string)$name, " ._");

    if ($name === '') {
        $name = 'attachment';
    }

    return mb_substr($name, 0, 200);
}

function deleteStoredFiles($storageKeys) {
    $failedStorageKeys = [];

    foreach (array_unique($storageKeys) as $storageKey) {
        $path = getAttachmentPath($storageKey);
        if (!$path || !is_file($path)) {
            continue;
        }

        if (!unlink($path) && is_file($path)) {
            error_log('Attachment file could not be removed: ' . $storageKey);
            $failedStorageKeys[] = $storageKey;
        }
    }

    if ($failedStorageKeys) {
        throw new RuntimeException('Fayl serverdən silinə bilmədi. Açıqdırsa, faylı bağlayıb yenidən cəhd edin.');
    }
}

function findUserNote($userId, $noteId) {
    if (!is_string($noteId) || $noteId === '' || mb_strlen($noteId) > 64) {
        return false;
    }

    $stmt = db()->prepare('SELECT 1 FROM notes WHERE user_id = ? AND id = ? LIMIT 1');
    $stmt->execute([$userId, $noteId]);
    return (bool)$stmt->fetchColumn();
}

function validateUploadedAttachment($file) {
    if (!is_array($file) || !isset($file['error']) || (int)$file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Fayl yüklənə bilmədi');
    }

    $size = (int)($file['size'] ?? 0);
    if ($size < 1 || $size > UPLOAD_MAX_BYTES) {
        throw new RuntimeException('Faylın həcmi 10 MB-dan çox ola bilməz');
    }

    $tmpName = $file['tmp_name'] ?? '';
    if (!is_string($tmpName) || !is_uploaded_file($tmpName)) {
        throw new RuntimeException('Yüklənən fayl təsdiqlənmədi');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($tmpName);
    if (!is_string($mimeType) || !isset(UPLOAD_ALLOWED_MIME_TYPES[$mimeType])) {
        throw new RuntimeException('Bu fayl növünə icazə verilmir');
    }

    return [$mimeType, UPLOAD_ALLOWED_MIME_TYPES[$mimeType], $size];
}

function uploadAttachment($userId, $noteId, $file) {
    if (!findUserNote($userId, $noteId)) {
        throw new RuntimeException('Qeyd tapılmadı');
    }

    list($mimeType, $extension, $size) = validateUploadedAttachment($file);
    ensureUploadDirectory();

    do {
        $storageKey = bin2hex(random_bytes(32)) . '.' . $extension;
        $path = getAttachmentPath($storageKey);
    } while ($path === null || file_exists($path));

    if (!move_uploaded_file($file['tmp_name'], $path)) {
        throw new RuntimeException('Fayl yaddaşa yazıla bilmədi');
    }
    @chmod($path, 0600);

    $positionStatement = db()->prepare(
        'SELECT COALESCE(MAX(position), -1) + 1 FROM note_attachments WHERE user_id = ? AND note_id = ?'
    );
    $positionStatement->execute([$userId, $noteId]);
    $position = (int)$positionStatement->fetchColumn();

    $attachment = [
        'id' => bin2hex(random_bytes(16)),
        'original_name' => sanitizeAttachmentName($file['name'] ?? ''),
        'mime_type' => $mimeType,
        'size_bytes' => $size,
    ];

    try {
        $stmt = db()->prepare(
            'INSERT INTO note_attachments (id, user_id, note_id, storage_key, original_name, mime_type, size_bytes, position, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([
            $attachment['id'],
            $userId,
            $noteId,
            $storageKey,
            $attachment['original_name'],
            $attachment['mime_type'],
            $attachment['size_bytes'],
            $position,
        ]);
    } catch (Throwable $e) {
        @unlink($path);
        throw $e;
    }

    return attachmentResponse($attachment);
}

function saveAttachmentOrder($userId, $noteId, $attachmentIds) {
    if (!findUserNote($userId, $noteId)) {
        throw new RuntimeException('Qeyd tapılmadı');
    }
    if (!is_array($attachmentIds)) {
        throw new RuntimeException('Fayl sırası yanlışdır');
    }

    $ids = [];
    foreach ($attachmentIds as $attachmentId) {
        if (!is_string($attachmentId) || !preg_match('/^[a-f0-9]{32}$/D', $attachmentId) || isset($ids[$attachmentId])) {
            throw new RuntimeException('Fayl sırası yanlışdır');
        }
        $ids[$attachmentId] = true;
    }
    $orderedIds = array_keys($ids);

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $existing = $pdo->prepare(
            'SELECT id FROM note_attachments WHERE user_id = ? AND note_id = ? FOR UPDATE'
        );
        $existing->execute([$userId, $noteId]);
        $existingIds = $existing->fetchAll(PDO::FETCH_COLUMN);

        if (count($existingIds) !== count($orderedIds) || array_diff($existingIds, $orderedIds) || array_diff($orderedIds, $existingIds)) {
            throw new RuntimeException('Fayl siyahısı yenilənib. Səhifəni yeniləyib yenidən cəhd edin.');
        }

        $update = $pdo->prepare(
            'UPDATE note_attachments SET position = ? WHERE id = ? AND user_id = ? AND note_id = ?'
        );
        foreach ($orderedIds as $position => $attachmentId) {
            $update->execute([$position, $attachmentId, $userId, $noteId]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function deleteAttachmentForUser($userId, $attachmentId) {
    if (!is_string($attachmentId) || !preg_match('/^[a-f0-9]{32}$/D', $attachmentId)) {
        throw new RuntimeException('Fayl identifikatoru yanlışdır');
    }

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT storage_key FROM note_attachments WHERE id = ? AND user_id = ? FOR UPDATE');
        $stmt->execute([$attachmentId, $userId]);
        $storageKey = $stmt->fetchColumn();

        if ($storageKey !== false) {
            deleteStoredFiles([$storageKey]);
            $delete = $pdo->prepare('DELETE FROM note_attachments WHERE id = ? AND user_id = ?');
            $delete->execute([$attachmentId, $userId]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function streamAttachmentForUser($userId, $attachmentId) {
    if (!is_string($attachmentId) || !preg_match('/^[a-f0-9]{32}$/D', $attachmentId)) {
        errorResponse('Fayl tapılmadı', 404);
    }

    $stmt = db()->prepare(
        'SELECT id, storage_key, original_name, mime_type, size_bytes
         FROM note_attachments
         WHERE id = ? AND user_id = ?
         LIMIT 1'
    );
    $stmt->execute([$attachmentId, $userId]);
    $attachment = $stmt->fetch();
    $path = $attachment ? getAttachmentPath($attachment['storage_key']) : null;

    if (!$path || !is_file($path)) {
        errorResponse('Fayl tapılmadı', 404);
    }

    $name = sanitizeAttachmentName($attachment['original_name']);
    $disposition = strpos($attachment['mime_type'], 'image/') === 0 ? 'inline' : 'attachment';
    header('Content-Type: ' . $attachment['mime_type'], true);
    header('Content-Length: ' . filesize($path));
    header('Content-Disposition: ' . $disposition . "; filename*=UTF-8''" . rawurlencode($name));
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, no-store');
    readfile($path);
    exit;
}

function getNotes($userId) {
    $pdo = db();

    $stmt = $pdo->prepare(
        'SELECT id, content, category, completed, created_at, updated_at
         FROM notes
         WHERE user_id = ?
         ORDER BY position ASC, created_at ASC'
    );
    $stmt->execute([$userId]);

    $notes = [];
    $attachmentsByNote = [];
    while ($row = $stmt->fetch()) {
        $notes[] = [
            'id' => $row['id'],
            'content' => $row['content'],
            'category' => $row['category'] ?? '',
            'completed' => (bool)$row['completed'],
            'createdAt' => toIsoTime($row['created_at']),
            'updatedAt' => toIsoTime($row['updated_at']),
            'attachments' => [],
        ];
    }

    if (count($notes) === 0) {
        return $notes;
    }

    $attachments = $pdo->prepare(
        'SELECT id, note_id, original_name, mime_type, size_bytes
         FROM note_attachments
         WHERE user_id = ?
         ORDER BY note_id ASC, position ASC, created_at ASC, id ASC'
    );
    $attachments->execute([$userId]);
    while ($attachment = $attachments->fetch()) {
        $attachmentsByNote[$attachment['note_id']][] = attachmentResponse($attachment);
    }

    foreach ($notes as &$note) {
        $note['attachments'] = $attachmentsByNote[$note['id']] ?? [];
    }
    unset($note);

    return $notes;
}

function saveNotes($userId, $notes) {
    if (!is_array($notes)) {
        return false;
    }

    $pdo = db();
    $pdo->beginTransaction();

    try {
        $incomingIds = [];

        $upsert = $pdo->prepare(
            'INSERT INTO notes (user_id, id, content, category, completed, position, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                content = VALUES(content),
                category = VALUES(category),
                completed = VALUES(completed),
                position = VALUES(position),
                updated_at = VALUES(updated_at)'
        );

        $now = date('Y-m-d H:i:s');
        $categoryPositions = [];

        foreach ($notes as $note) {
            if (!validateNoteData($note)) {
                throw new RuntimeException('Not məlumatları yanlışdır');
            }

            $id = isset($note['id']) && is_string($note['id']) && $note['id'] !== ''
                ? substr($note['id'], 0, 64)
                : ('note_' . bin2hex(random_bytes(8)));

            $createdAt = normalizeDateTime($note['createdAt'] ?? null, true);
            $updatedAt = normalizeDateTime($note['updatedAt'] ?? null, true);

            $category = '';
            if (isset($note['category']) && is_string($note['category'])) {
                $category = trim($note['category']);
            }
            if ($category === '') {
                $category = null;
            }

            $categoryKey = $category ?? '';
            $position = $categoryPositions[$categoryKey] ?? 0;
            $categoryPositions[$categoryKey] = $position + 1;
            $completed = !empty($note['completed']) ? 1 : 0;

            $upsert->execute([
                $userId,
                $id,
                $note['content'],
                $category,
                $completed,
                $position,
                $createdAt ?: $now,
                $updatedAt ?: $now,
            ]);

            $incomingIds[] = $id;
        }

        if (count($incomingIds) > 0) {
            $placeholders = implode(',', array_fill(0, count($incomingIds), '?'));
            $params = array_merge([$userId], $incomingIds);
            $files = $pdo->prepare(
                "SELECT a.storage_key FROM note_attachments a
                 INNER JOIN notes n ON n.user_id = a.user_id AND n.id = a.note_id
                 WHERE n.user_id = ? AND n.id NOT IN ($placeholders)"
            );
            $files->execute($params);
            $deletedStorageKeys = $files->fetchAll(PDO::FETCH_COLUMN);

            $del = $pdo->prepare("DELETE FROM notes WHERE user_id = ? AND id NOT IN ($placeholders)");
            $del->execute($params);
        } else {
            $files = $pdo->prepare('SELECT storage_key FROM note_attachments WHERE user_id = ?');
            $files->execute([$userId]);
            $deletedStorageKeys = $files->fetchAll(PDO::FETCH_COLUMN);

            $del = $pdo->prepare('DELETE FROM notes WHERE user_id = ?');
            $del->execute([$userId]);
        }

        $pdo->commit();
        deleteStoredFiles($deletedStorageKeys);
        return true;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return false;
    }
}

function getCategories($userId) {
    $pdo = db();

    $stmt = $pdo->prepare(
        'SELECT id, label, icon, color
         FROM categories
         WHERE user_id = ?
         ORDER BY position ASC, created_at ASC, id ASC'
    );
    $stmt->execute([$userId]);

    $cats = $stmt->fetchAll();
    if (!$cats || count($cats) === 0) {
        seedDefaultCategories($userId);
        $stmt->execute([$userId]);
        $cats = $stmt->fetchAll();
    }

    return $cats ?: [];
}

function saveCategoryOrder($userId, $categoryIds) {
    if (!is_array($categoryIds)) {
        return false;
    }

    $pdo = db();
    $pdo->beginTransaction();

    try {
        $uniqueIds = [];
        foreach ($categoryIds as $categoryId) {
            if (!is_string($categoryId) || !preg_match('/^[a-zA-Z0-9_]+$/', $categoryId) || isset($uniqueIds[$categoryId])) {
                throw new RuntimeException('Kateqoriya sırası yanlışdır');
            }
            $uniqueIds[$categoryId] = true;
        }

        $existingStmt = $pdo->prepare('SELECT id FROM categories WHERE user_id = ? FOR UPDATE');
        $existingStmt->execute([$userId]);
        $existingIds = $existingStmt->fetchAll(PDO::FETCH_COLUMN);

        if (count($categoryIds) !== count($existingIds)
            || array_diff($categoryIds, $existingIds)
            || array_diff($existingIds, $categoryIds)) {
            throw new RuntimeException('Kateqoriya sırası uyğun deyil');
        }

        $update = $pdo->prepare('UPDATE categories SET position = ?, updated_at = ? WHERE user_id = ? AND id = ?');
        $now = date('Y-m-d H:i:s');
        foreach ($categoryIds as $position => $categoryId) {
            $update->execute([$position, $now, $userId, $categoryId]);
        }

        $pdo->commit();
        return true;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return false;
    }
}

function saveCategories($userId, $categories) {
    if (!is_array($categories)) {
        return false;
    }

    $pdo = db();
    $pdo->beginTransaction();

    try {
        $now = date('Y-m-d H:i:s');
        $incomingIds = [];

        $upsert = $pdo->prepare(
            'INSERT INTO categories (user_id, id, label, icon, color, position, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                label = VALUES(label),
                icon = VALUES(icon),
                color = VALUES(color),
                position = VALUES(position),
                updated_at = VALUES(updated_at)'
        );

        $position = 0;
        foreach ($categories as $cat) {
            if (!isset($cat['id'], $cat['label'], $cat['icon'], $cat['color'])) {
                throw new RuntimeException('Kateqoriya məlumatları yanlışdır');
            }

            if (!is_string($cat['id']) || !preg_match('/^[a-zA-Z0-9_]+$/', $cat['id']) || mb_strlen($cat['id']) > 50) {
                throw new RuntimeException('Kateqoriya ID yanlışdır');
            }

            if (!is_string($cat['label']) || mb_strlen(trim($cat['label'])) === 0 || mb_strlen($cat['label']) > 50) {
                throw new RuntimeException('Kateqoriya adı yanlışdır');
            }

            if (!is_string($cat['icon']) || mb_strlen($cat['icon']) > 50) {
                throw new RuntimeException('İkon yanlışdır');
            }

            if (!is_string($cat['color']) || !preg_match('/^#[0-9a-fA-F]{6}$/', $cat['color'])) {
                throw new RuntimeException('Rəng yanlışdır');
            }

            $upsert->execute([
                $userId,
                $cat['id'],
                $cat['label'],
                $cat['icon'],
                $cat['color'],
                $position++,
                $now,
                $now,
            ]);

            $incomingIds[] = $cat['id'];
        }

        if (count($incomingIds) > 0) {
            $placeholders = implode(',', array_fill(0, count($incomingIds), '?'));
            $params = array_merge([$userId], $incomingIds);
            $del = $pdo->prepare("DELETE FROM categories WHERE user_id = ? AND id NOT IN ($placeholders)");
            $del->execute($params);

            $nullParams = array_merge([$userId], $incomingIds);
            $nullStmt = $pdo->prepare("UPDATE notes SET category = NULL WHERE user_id = ? AND category IS NOT NULL AND category NOT IN ($placeholders)");
            $nullStmt->execute($nullParams);
        } else {
            $del = $pdo->prepare('DELETE FROM categories WHERE user_id = ?');
            $del->execute([$userId]);

            $nullStmt = $pdo->prepare('UPDATE notes SET category = NULL WHERE user_id = ?');
            $nullStmt->execute([$userId]);
        }

        $pdo->commit();
        return true;
    } catch (Throwable $e) {
        $pdo->rollBack();
        return false;
    }
}

function handleAPIRequests() {
    $method = $_SERVER['REQUEST_METHOD'];
    $action = isset($_GET['action']) ? preg_replace('/[^a-zA-Z_]/', '', $_GET['action']) : '';

    if (!$action) {
        errorResponse('Action tələb olunur', 400);
    }

    header('Content-Type: application/json');

    try {
        initDatabase();
    } catch (Throwable $e) {
        errorResponse('Database connection/config error', 500);
    }

    if ($method === 'GET') {
        switch ($action) {
            case 'getNotes':
                if (!isLoggedIn()) errorResponse('Giriş edilmədi', 401);
                jsonResponse(getNotes(getCurrentUserId()));
                break;

            case 'downloadAttachment':
                if (!isLoggedIn()) errorResponse('Giriş edilmədi', 401);
                streamAttachmentForUser(getCurrentUserId(), $_GET['id'] ?? '');
                break;

            case 'checkSession':
                $response = ['loggedIn' => isLoggedIn()];
                if (isLoggedIn()) {
                    $response['csrf_token'] = $_SESSION[CSRF_TOKEN_NAME] ?? '';
                    $response['username'] = $_SESSION['username'] ?? '';
                    $response['role'] = $_SESSION['role'] ?? 'user';
                    $response['isAdmin'] = isAdmin();
                }
                jsonResponse($response);
                break;

            case 'getCategories':
                if (!isLoggedIn()) errorResponse('Giriş edilmədi', 401);
                jsonResponse(getCategories(getCurrentUserId()));
                break;

            case 'getUsers':
                requireAdmin();
                jsonResponse(getUsersList());
                break;

            default:
                errorResponse('Naməlum action', 400);
                break;
        }
    }

    if ($method === 'POST') {
        if ($action === 'uploadAttachment') {
            $data = $_POST;
        } else {
            $raw = file_get_contents('php://input');
            $data = ($raw === false || $raw === '') ? [] : (json_decode($raw, true) ?? []);
        }

        switch ($action) {
            case 'login': {
                $username = is_string($data['username'] ?? null) ? trim($data['username']) : '';
                $password = is_string($data['password'] ?? null) ? $data['password'] : '';

                if ($username === '' || $password === '') {
                    errorResponse('Username və şifrə tələb olunur', 400);
                }

                $stmt = db()->prepare('SELECT id, username, password_hash, role, active FROM users WHERE username = ? LIMIT 1');
                $stmt->execute([$username]);
                $user = $stmt->fetch();

                if ($user && (int)($user['active'] ?? 1) !== 1) {
                    errorResponse('Hesab deaktiv edilib', 403);
                }

                if ($user && password_verify($password, $user['password_hash'])) {
                    startUserSession((int)$user['id'], $user['username'], $user['role']);

                    jsonResponse([
                        'success' => true,
                        'message' => 'Uğurlu giriş',
                        'csrf_token' => $_SESSION[CSRF_TOKEN_NAME],
                    ]);
                }

                jsonResponse(['success' => false, 'error' => 'Yanlış username və ya şifrə']);
                break;
            }

            case 'createUser':
                requireAdmin();

                $token = $data['csrf_token'] ?? '';
                if (!validateCSRFToken($token)) {
                    errorResponse('CSRF token yanlışdır', 403);
                }

                $username = is_string($data['username'] ?? null) ? trim($data['username']) : '';
                $password = is_string($data['password'] ?? null) ? $data['password'] : '';
                $role = is_string($data['role'] ?? null) ? $data['role'] : 'user';
                if ($role === 'admin' && getCurrentUserId() !== 1) {
                    errorResponse('Admin rolunda istifadəçi yaratmaq icazəniz yoxdur', 403);
                }

                try {
                    createUser($username, $password, $role);
                    jsonResponse(['success' => true, 'message' => 'İstifadəçi yaradıldı']);
                } catch (Throwable $e) {
                    errorResponse(userFriendlyError($e), 400);
                }
                break;

            case 'updateUser':
                requireAdmin();

                $token = $data['csrf_token'] ?? '';
                if (!validateCSRFToken($token)) {
                    errorResponse('CSRF token yanlışdır', 403);
                }

                $userId = (int)($data['id'] ?? 0);
                $username = is_string($data['username'] ?? null) ? trim($data['username']) : '';
                $role = is_string($data['role'] ?? null) ? $data['role'] : 'user';

                try {
                    updateUser($userId, $username, $role);
                    jsonResponse(['success' => true, 'message' => 'İstifadəçi yeniləndi']);
                } catch (Throwable $e) {
                    errorResponse(userFriendlyError($e), 400);
                }
                break;

            case 'deleteUser':
                requireAdmin();

                $token = $data['csrf_token'] ?? '';
                if (!validateCSRFToken($token)) {
                    errorResponse('CSRF token yanlışdır', 403);
                }

                $userId = (int)($data['id'] ?? 0);

                try {
                    deleteUserById($userId);
                    jsonResponse(['success' => true, 'message' => 'İstifadəçi silindi']);
                } catch (Throwable $e) {
                    errorResponse(userFriendlyError($e), 400);
                }
                break;

            case 'logout':
                $token = $data['csrf_token'] ?? '';
                if (!validateCSRFToken($token)) {
                    errorResponse('CSRF token yanlışdır', 403);
                }

                $_SESSION = [];
                if (ini_get('session.use_cookies')) {
                    $params = session_get_cookie_params();
                    setcookie(
                        session_name(),
                        '',
                        time() - 42000,
                        $params['path'],
                        $params['domain'],
                        $params['secure'],
                        $params['httponly']
                    );
                }
                session_destroy();
                jsonResponse(['success' => true, 'message' => 'Çıxış edildi']);
                break;

            case 'uploadAttachment':
                if (!isLoggedIn()) errorResponse('Giriş edilmədi', 401);

                $token = $data['csrf_token'] ?? '';
                if (!validateCSRFToken($token)) {
                    errorResponse('CSRF token yanlışdır', 403);
                }

                try {
                    $noteId = is_string($data['note_id'] ?? null) ? $data['note_id'] : '';
                    $attachment = uploadAttachment(getCurrentUserId(), $noteId, $_FILES['attachment'] ?? null);
                    jsonResponse(['success' => true, 'attachment' => $attachment]);
                } catch (Throwable $e) {
                    errorResponse($e->getMessage(), 400);
                }
                break;

            case 'saveAttachmentOrder':
                if (!isLoggedIn()) errorResponse('Giriş edilmədi', 401);

                $token = $data['csrf_token'] ?? '';
                if (!validateCSRFToken($token)) {
                    errorResponse('CSRF token yanlışdır', 403);
                }

                try {
                    $noteId = is_string($data['noteId'] ?? null) ? $data['noteId'] : '';
                    $attachmentIds = $data['attachmentIds'] ?? null;
                    saveAttachmentOrder(getCurrentUserId(), $noteId, $attachmentIds);
                    jsonResponse(['success' => true]);
                } catch (Throwable $e) {
                    errorResponse($e->getMessage(), 400);
                }
                break;

            case 'deleteAttachment':
                if (!isLoggedIn()) errorResponse('Giriş edilmədi', 401);

                $token = $data['csrf_token'] ?? '';
                if (!validateCSRFToken($token)) {
                    errorResponse('CSRF token yanlışdır', 403);
                }

                try {
                    $attachmentId = is_string($data['id'] ?? null) ? $data['id'] : '';
                    deleteAttachmentForUser(getCurrentUserId(), $attachmentId);
                    jsonResponse(['success' => true]);
                } catch (Throwable $e) {
                    errorResponse($e->getMessage(), 400);
                }
                break;

            case 'saveNotes':
                if (!isLoggedIn()) errorResponse('Giriş edilmədi', 401);

                $token = $data['csrf_token'] ?? '';
                if (!validateCSRFToken($token)) {
                    errorResponse('CSRF token yanlışdır', 403);
                }

                if (!isset($data['notes']) || !is_array($data['notes'])) {
                    errorResponse('Notlar array olmalıdır', 400);
                }

                if (saveNotes(getCurrentUserId(), $data['notes'])) {
                    jsonResponse(['success' => true, 'message' => 'Qeydlər saxlandı']);
                }

                errorResponse('Əməliyyat uğursuz oldu', 500);
                break;

            case 'saveCategoryOrder':
                if (!isLoggedIn()) errorResponse('Giriş edilmədi', 401);

                $token = $data['csrf_token'] ?? '';
                if (!validateCSRFToken($token)) {
                    errorResponse('CSRF token yanlışdır', 403);
                }

                if (!isset($data['categoryIds']) || !is_array($data['categoryIds'])) {
                    errorResponse('Kateqoriya sırası array olmalıdır', 400);
                }

                if (saveCategoryOrder(getCurrentUserId(), $data['categoryIds'])) {
                    jsonResponse(['success' => true, 'message' => 'Kateqoriya sırası saxlandı']);
                }

                errorResponse('Əməliyyat uğursuz oldu', 500);
                break;

            case 'saveCategories':
                if (!isLoggedIn()) errorResponse('Giriş edilmədi', 401);

                $token = $data['csrf_token'] ?? '';
                if (!validateCSRFToken($token)) {
                    errorResponse('CSRF token yanlışdır', 403);
                }

                if (!isset($data['categories']) || !is_array($data['categories'])) {
                    errorResponse('Kateqoriyalar array olmalıdır', 400);
                }

                if (saveCategories(getCurrentUserId(), $data['categories'])) {
                    jsonResponse(['success' => true, 'message' => 'Kateqoriyalar saxlandı']);
                }

                errorResponse('Əməliyyat uğursuz oldu', 500);
                break;

            default:
                errorResponse('Naməlum action', 400);
                break;
        }
    }

    errorResponse('Method dəstəklənmir', 405);
}

if (isset($_GET['action'])) {
    handleAPIRequests();
}
