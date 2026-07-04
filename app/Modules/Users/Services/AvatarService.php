<?php

declare(strict_types=1);

namespace App\Modules\Users\Services;

use App\Core\Session;

/**
 * Сервис для загрузки и обработки аватаров.
 * 
 * ✅ ИЗМЕНЕНО: Session внедряется через конструктор.
 */
class AvatarService
{
    private const TARGET_SIZE = 150;
    private const JPEG_QUALITY = 85;
    private const ALLOWED_MIME_TYPES = ['image/jpeg', 'image/png', 'image/gif'];

    private Session $session;

    /**
     * ✅ ИЗМЕНЕНО: Добавлен Session в конструктор
     */
    public function __construct(Session $session)
    {
        $this->session = $session;
    }

    /**
     * Обработать загрузку аватара: валидация, ресайз, сохранение.
     */
    public function handleUpload(array $file, ?string $oldAvatarFilename = null): ?string
    {
        $fileTmpPath = $file['tmp_name'] ?? '';
        if (empty($fileTmpPath) || !file_exists($fileTmpPath)) {
            $this->session->flash('error', 'Временный файл загрузки недоступен.');
            return null;
        }

        $maxSize = config('uploads.avatar_max_size', 5242880, 'int');
        $maxSizeMb = config('uploads.avatar_max_size_mb', 5, 'int');
        
        if ($file['size'] > $maxSize) {
            $this->session->flash('error', "Размер файла не должен превышать {$maxSizeMb} МБ.");
            return null;
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $fileTmpPath);
        finfo_close($finfo);

        if (!in_array($mimeType, self::ALLOWED_MIME_TYPES)) {
            $this->session->flash('error', 'Разрешены только JPG, PNG, GIF.');
            return null;
        }

        $newFilename = bin2hex(random_bytes(16)) . '.jpg';
        $subFolder = substr($newFilename, 0, 2);
        
        $projectRoot = dirname(__FILE__, 5);
        $baseUploadDir = $projectRoot . '/public/uploads/avatars';
        $uploadTargetDir = $baseUploadDir . '/' . $subFolder;

        if (!is_dir($baseUploadDir)) mkdir($baseUploadDir, 0777, true);
        if (!is_dir($uploadTargetDir)) mkdir($uploadTargetDir, 0777, true);

        $finalPath = $uploadTargetDir . '/' . $newFilename;
        if (!$this->resizeAndSave($fileTmpPath, $mimeType, $finalPath)) {
            $this->session->flash('error', 'Не удалось обработать изображение.');
            return null;
        }

        if (!empty($oldAvatarFilename) && $oldAvatarFilename !== $newFilename) {
            $this->deleteOldAvatar($oldAvatarFilename, $baseUploadDir);
        }

        return $newFilename;
    }

    private function resizeAndSave(string $srcPath, string $mimeType, string $dstPath): bool
    {
        list($srcWidth, $srcHeight) = getimagesize($srcPath);

        $srcImage = match ($mimeType) {
            'image/png' => imagecreatefrompng($srcPath),
            'image/gif' => imagecreatefromgif($srcPath),
            default => imagecreatefromjpeg($srcPath),
        };

        if (!$srcImage) return false;

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