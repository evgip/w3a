<?php

declare(strict_types=1);

namespace App\Modules\Users\Services;

use App\Core\Session as AppCoreSession;

class AvatarService
{
    private const TARGET_SIZE = 150;
    private const JPEG_QUALITY = 85;
    private const ALLOWED_MIME_TYPES = ['image/jpeg', 'image/png', 'image/gif'];

    /**
     * Обработать загрузку аватара: валидация, ресайз, сохранение.
     * 
     * @param array $file Массив из $_FILES['avatar_file']
     * @param string|null $oldAvatarFilename Старый аватар для удаления
     * @return string|null Имя нового файла или null если загрузка не удалась
     */
    public function handleUpload(array $file, ?string $oldAvatarFilename = null): ?string
    {
        // 1. Проверка временного файла
        $fileTmpPath = $file['tmp_name'] ?? '';
        if (empty($fileTmpPath) || !file_exists($fileTmpPath)) {
            AppCoreSession::setFlash('error', 'Временный файл загрузки недоступен.');
            return null;
        }

        // 2. Проверка размера
        $maxSize = config('uploads.avatar_max_size', 5242880, 'int');
        $maxSizeMb = config('uploads.avatar_max_size_mb', 5, 'int');
        
        if ($file['size'] > $maxSize) {
            AppCoreSession::setFlash('error', "Размер файла не должен превышать {$maxSizeMb} МБ.");
            return null;
        }

        // 3. Проверка MIME-типа
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $fileTmpPath);
        finfo_close($finfo);

        if (!in_array($mimeType, self::ALLOWED_MIME_TYPES)) {
            AppCoreSession::setFlash('error', 'Разрешены только JPG, PNG, GIF.');
            return null;
        }

        // 4. Генерация имени файла и создание папки
        $newFilename = bin2hex(random_bytes(16)) . '.jpg';
        $subFolder = substr($newFilename, 0, 2);
        
        $projectRoot = dirname(__FILE__, 5);
        $baseUploadDir = $projectRoot . '/public/uploads/avatars';
        $uploadTargetDir = $baseUploadDir . '/' . $subFolder;

        if (!is_dir($baseUploadDir)) mkdir($baseUploadDir, 0777, true);
        if (!is_dir($uploadTargetDir)) mkdir($uploadTargetDir, 0777, true);

        // 5. Ресайз и сохранение
        $finalPath = $uploadTargetDir . '/' . $newFilename;
        if (!$this->resizeAndSave($fileTmpPath, $mimeType, $finalPath)) {
            AppCoreSession::setFlash('error', 'Не удалось обработать изображение.');
            return null;
        }

        // 6. Удаление старого аватара
        if (!empty($oldAvatarFilename) && $oldAvatarFilename !== $newFilename) {
            $this->deleteOldAvatar($oldAvatarFilename, $baseUploadDir);
        }

        return $newFilename;
    }

    /**
     * Ресайз изображения в квадрат 150x150 с кропом по центру
     */
    private function resizeAndSave(string $srcPath, string $mimeType, string $dstPath): bool
    {
        list($srcWidth, $srcHeight) = getimagesize($srcPath);

        // Создаём ресурс исходного изображения
        $srcImage = match ($mimeType) {
            'image/png' => imagecreatefrompng($srcPath),
            'image/gif' => imagecreatefromgif($srcPath),
            default => imagecreatefromjpeg($srcPath),
        };

        if (!$srcImage) return false;

        // Создаём целевое изображение
        $dstImage = imagecreatetruecolor(self::TARGET_SIZE, self::TARGET_SIZE);
        $whiteBackground = imagecolorallocate($dstImage, 255, 255, 255);
        imagefill($dstImage, 0, 0, $whiteBackground);

        // Вычисляем параметры кропа (центрируем квадрат)
        if ($srcWidth > $srcHeight) {
            $srcX = (int)(($srcWidth - $srcHeight) / 2);
            $srcY = 0;
            $srcSquareSize = $srcHeight;
        } else {
            $srcX = 0;
            $srcY = (int)(($srcHeight - $srcWidth) / 2);
            $srcSquareSize = $srcWidth;
        }

        // Ресайз с высоким качеством
        imagecopyresampled(
            $dstImage, $srcImage,
            0, 0, $srcX, $srcY,
            self::TARGET_SIZE, self::TARGET_SIZE,
            $srcSquareSize, $srcSquareSize
        );

        // Сохраняем как JPG
        $result = imagejpeg($dstImage, $dstPath, self::JPEG_QUALITY);

        imagedestroy($srcImage);
        imagedestroy($dstImage);

        return $result;
    }

    /**
     * Удалить старый аватар и пустую папку шардирования
     */
    private function deleteOldAvatar(string $oldFilename, string $baseUploadDir): void
    {
        $oldSub = substr($oldFilename, 0, 2);
        $oldFolderDir = $baseUploadDir . '/' . $oldSub;
        $oldAvatarPath = $oldFolderDir . '/' . $oldFilename;

        if (file_exists($oldAvatarPath)) {
            unlink($oldAvatarPath);
        }

        // Удаляем папку, если она пуста
        if (is_dir($oldFolderDir)) {
            $remainingFiles = array_diff(scandir($oldFolderDir), ['.', '..']);
            if (empty($remainingFiles)) {
                rmdir($oldFolderDir);
            }
        }
    }
}