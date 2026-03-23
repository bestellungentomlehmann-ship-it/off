<?php
/**
 * Newsletter Model
 * Manages the internal newsletter archive (.eml / .msg files)
 */

class Newsletter {

    /** Allowed file extensions for newsletter uploads */
    const ALLOWED_EXTENSIONS = ['eml', 'msg'];

    /** Maximum upload size in bytes (20 MB) */
    const MAX_FILE_SIZE = 20971520;

    /**
     * Retrieve all newsletters, newest first, with optional keyword search.
     *
     * @param string $search Optional search term (title / month_year).
     * @return array
     */
    public static function getAll(string $search = ''): array {
        $db = Database::getContentDB();
        if ($search !== '') {
            $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search);
            $like    = '%' . $escaped . '%';
            $stmt    = $db->prepare(
                "SELECT n.*, u.first_name, u.last_name
                 FROM newsletters n
                 LEFT JOIN users u ON u.id = n.uploaded_by
                 WHERE n.title LIKE ? OR n.month_year LIKE ?
                 ORDER BY n.created_at DESC"
            );
            $stmt->execute([$like, $like]);
        } else {
            $stmt = $db->query(
                "SELECT n.*, u.first_name, u.last_name
                 FROM newsletters n
                 LEFT JOIN users u ON u.id = n.uploaded_by
                 ORDER BY n.created_at DESC"
            );
        }
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Retrieve a single newsletter by ID.
     *
     * @param int $id
     * @return array|false
     */
    public static function getById(int $id) {
        $db   = Database::getContentDB();
        $stmt = $db->prepare(
            "SELECT n.*, u.first_name, u.last_name
             FROM newsletters n
             LEFT JOIN users u ON u.id = n.uploaded_by
             WHERE n.id = ?"
        );
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Persist a new newsletter record.
     *
     * @param array $data {title, month_year, file_path, uploaded_by}
     * @return int  New record ID.
     */
    public static function create(array $data): int {
        $db   = Database::getContentDB();
        $stmt = $db->prepare(
            "INSERT INTO newsletters
                 (title, month_year, file_path, uploaded_by)
             VALUES (?, ?, ?, ?)"
        );
        $stmt->execute([
            $data['title'],
            $data['month_year'] ?? null,
            $data['file_path'],
            (int) $data['uploaded_by'],
        ]);
        return (int) $db->lastInsertId();
    }

    /**
     * Remove a newsletter record and its associated file from disk.
     *
     * @param int $id
     * @return bool
     */
    public static function delete(int $id): bool {
        $newsletter = self::getById($id);
        if (!$newsletter) {
            return false;
        }

        // Delete the file from disk first
        $uploadDir = __DIR__ . '/../../uploads/newsletters/';
        $filePath  = realpath($uploadDir . basename($newsletter['file_path']));
        if ($filePath !== false && str_starts_with($filePath, realpath($uploadDir))) {
            @unlink($filePath);
        }

        $db   = Database::getContentDB();
        $stmt = $db->prepare("DELETE FROM newsletters WHERE id = ?");
        return $stmt->execute([$id]);
    }

    /**
     * Whether the given role may upload / delete newsletters.
     *
     * Board members and section leads (ressortleiter) have manage rights.
     *
     * @param string $role
     * @return bool
     */
    public static function canManage(string $role): bool {
        return in_array($role, array_merge(Auth::BOARD_ROLES, ['ressortleiter']), true);
    }

    /**
     * Validate an uploaded file and move it to the newsletters upload folder.
     *
     * @param array $file  $_FILES entry.
     * @return array {success: bool, path?: string, error?: string}
     */
    public static function handleUpload(array $file): array {
        // Basic upload error check
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'error' => 'Fehler beim Hochladen der Datei (Code ' . $file['error'] . ').'];
        }

        // File size limit
        if ($file['size'] > self::MAX_FILE_SIZE) {
            return ['success' => false, 'error' => 'Die Datei überschreitet die maximale Größe von 20 MB.'];
        }

        // Extension whitelist
        $originalName = $file['name'];
        $ext          = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if (!in_array($ext, self::ALLOWED_EXTENSIONS, true)) {
            return ['success' => false, 'error' => 'Nur .eml- und .msg-Dateien sind erlaubt.'];
        }

        // Generate a secure, unique filename
        $secureFilename = bin2hex(random_bytes(16)) . '.' . $ext;
        $uploadDir      = __DIR__ . '/../../uploads/newsletters/';
        $destination    = $uploadDir . $secureFilename;

        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            return ['success' => false, 'error' => 'Die Datei konnte nicht gespeichert werden.'];
        }

        return ['success' => true, 'file_path' => $secureFilename, 'original_filename' => $originalName];
    }
}
