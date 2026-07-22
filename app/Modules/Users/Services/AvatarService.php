<?php

declare(strict_types=1);

namespace App\Modules\Users\Services;

use App\Modules\Users\Exceptions\AvatarUploadException;

/**
 * Сервис для загрузки и обработки аватаров.
 * Не зависит от HTTP или сессий, выполняет только работу с файлами.
 */
class AvatarService
{
    private const TARGET_SIZE = 150;
    private const JPEG_QUALITY = 85;
    private const ALLOWED_MIME_TYPES = ['image/jpeg', 'image/png', 'image/gif'];

    /**
     * Обрабатывает загрузку аватара: валидация, ресайз, сохранение.
     *
     * @throws AvatarUploadException Если файл невалиден или не удалось его обработать
     */
    public function handleUpload(array $file, ?string $oldAvatarFilename = null): string
    {
        $fileTmpPath = $file['tmp_name'] ?? '';
        if (empty($fileTmpPath) || !file_exists($fileTmpPath)) {
            throw new AvatarUploadException('Временный файл загрузки недоступен.');
        }

        $maxSize = config('uploads.avatar_max_size', 5242880, 'int');
        $maxSizeMb = config('uploads.avatar_max_size_mb', 5, 'int');
        
        if ($file['size'] > $maxSize) {
            throw new AvatarUploadException("Размер файла не должен превышать {$maxSizeMb} МБ.");
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $fileTmpPath);
        finfo_close($finfo);

        if (!in_array($mimeType, self::ALLOWED_MIME_TYPES)) {
            throw new AvatarUploadException('Разрешены только форматы JPG, PNG, GIF.');
        }

        $newFilename = bin2hex(random_bytes(16)) . '.jpg';
        $subFolder = substr($newFilename, 0, 2);
        
        $projectRoot = dirname(__FILE__, 5);
        $baseUploadDir = $projectRoot . '/public/uploads/avatars';
        $uploadTargetDir = $baseUploadDir . '/' . $subFolder;

        if (!is_dir($baseUploadDir)) {
            mkdir($baseUploadDir, 0777, true);
        }
        if (!is_dir($uploadTargetDir)) {
            mkdir($uploadTargetDir, 0777, true);
        }

        $finalPath = $uploadTargetDir . '/' . $newFilename;
        if (!$this->resizeAndSave($fileTmpPath, $mimeType, $finalPath)) {
            throw new AvatarUploadException('Не удалось обработать и сохранить изображение.');
        }

        if (!empty($oldAvatarFilename) && $oldAvatarFilename !== $newFilename) {
            $this->deleteOldAvatar($oldAvatarFilename, $baseUploadDir);
        }

        return $newFilename;
    }

    private function resizeAndSave(string $srcPath, string $mimeType, string $dstPath): bool
    {
        $imageInfo = getimagesize($srcPath);
        if (!$imageInfo) {
            return false;
        }
        
        list($srcWidth, $srcHeight) = $imageInfo;

        $srcImage = match ($mimeType) {
            'image/png' => imagecreatefrompng($srcPath),
            'image/gif' => imagecreatefromgif($srcPath),
            default => imagecreatefromjpeg($srcPath),
        };

        if (!$srcImage) {
            return false;
        }

        $dstImage = imagecreatetruecolor(self::TARGET_SIZE, self::TARGET_SIZE);
        $whiteBackground = imagecolorallocate($dstImage, 255, 255, 255);
        imagefill($dstImage, 0, 0, $whiteBackground);

        if ($srcWidth > $srcHeight) {
            $srcX = (int)(($srcWidth - $srcHeight) / 2);
            $srcY = 0;
            $srcSquareSize = $srcHeight;
        } else {
            $srcX = 0;
            $srcY = (int)(($srcHeight - $srcWidth) / 2);
            $srcSquareSize = $srcWidth;
        }

        imagecopyresampled(
            $dstImage, $srcImage,
            0, 0, $srcX, $srcY,
            self::TARGET_SIZE, self::TARGET_SIZE,
            $srcSquareSize, $srcSquareSize
        );

        $result = imagejpeg($dstImage, $dstPath, self::JPEG_QUALITY);

        imagedestroy($srcImage);
        imagedestroy($dstImage);

        return $result;
    }

    private function deleteOldAvatar(string $oldFilename, string $baseUploadDir): void
    {
        $oldSub = substr($oldFilename, 0, 2);
        $oldFolderDir = $baseUploadDir . '/' . $oldSub;
        $oldAvatarPath = $oldFolderDir . '/' . $oldFilename;

        if (file_exists($oldAvatarPath)) {
            unlink($oldAvatarPath);
        }

        if (is_dir($oldFolderDir)) {
            $remainingFiles = array_diff(scandir($oldFolderDir), ['.', '..']);
            if (empty($remainingFiles)) {
                rmdir($oldFolderDir);
            }
        }
    }
}