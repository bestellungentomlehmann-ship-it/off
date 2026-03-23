<?php
require_once __DIR__ . '/../../includes/handlers/AuthHandler.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../vendor/autoload.php';

AuthHandler::requireLogin();

use ZBateson\MailMimeParser\Message;

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($id <= 0) {
    http_response_code(400);
    exit('Ungültige Newsletter-ID.');
}

$db   = Database::getContentDB();
$stmt = $db->prepare("SELECT file_path FROM newsletters WHERE id = ?");
$stmt->execute([$id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    http_response_code(404);
    exit('Newsletter nicht gefunden.');
}

$uploadDir = realpath(__DIR__ . '/../../uploads/newsletters/');
$filePath  = realpath($uploadDir . DIRECTORY_SEPARATOR . basename($row['file_path']));

if ($filePath === false || $uploadDir === false || !str_starts_with($filePath, $uploadDir)) {
    http_response_code(404);
    exit('Datei nicht gefunden.');
}

$message = Message::fromFile($filePath);

$htmlContent = $message->getHtmlContent();
if ($htmlContent !== null) {
    echo $htmlContent;
} else {
    $textContent = $message->getTextContent() ?? '';
    echo nl2br(htmlspecialchars($textContent, ENT_QUOTES, 'UTF-8'));
}
