<?php
require_once __DIR__ . '/i18n.php';

function category_image_dir(): string
{
    $dir = dirname(__DIR__, 2) . '/data/category_images/';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $htaccess = $dir . '.htaccess';
    if (!is_file($htaccess)) {
        file_put_contents($htaccess, "Require all denied\n");
    }
    return $dir;
}

/**
 * Validate and store an uploaded work-category image (JPG, PNG, GIF or WEBP,
 * max 5MB). No file chosen is not an error — the image is optional. Content
 * is checked by magic bytes — the client-supplied name/type is never trusted
 * — and the on-disk name is randomly generated.
 *
 * @return array{ok: bool, message: string, filename: ?string}
 */
function store_category_image(array $file): array
{
    if (empty($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['ok' => true, 'message' => '', 'filename' => null];
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'message' => t('image_upload_failed', 'The image failed to upload. Please try again.'), 'filename' => null];
    }

    $maxBytes = 5 * 1024 * 1024;
    if ($file['size'] <= 0 || $file['size'] > $maxBytes) {
        return ['ok' => false, 'message' => t('image_size_invalid', 'Images must be between 1 byte and 5MB.'), 'filename' => null];
    }
    if (!is_uploaded_file($file['tmp_name'])) {
        return ['ok' => false, 'message' => t('invalid_upload', 'Invalid upload.'), 'filename' => null];
    }

    $handle = fopen($file['tmp_name'], 'rb');
    $header = $handle ? (string)fread($handle, 12) : '';
    if ($handle) {
        fclose($handle);
    }

    $ext = null;
    if (strncmp($header, "\xFF\xD8\xFF", 3) === 0) {
        $ext = 'jpg';
    } elseif (strncmp($header, "\x89PNG\r\n\x1a\n", 8) === 0) {
        $ext = 'png';
    } elseif (strncmp($header, 'GIF87a', 6) === 0 || strncmp($header, 'GIF89a', 6) === 0) {
        $ext = 'gif';
    } elseif (strncmp($header, 'RIFF', 4) === 0 && substr($header, 8, 4) === 'WEBP') {
        $ext = 'webp';
    }
    if ($ext === null) {
        return ['ok' => false, 'message' => t('image_type_invalid', 'Only JPG, PNG, GIF or WEBP images are accepted.'), 'filename' => null];
    }

    $filename = 'cat_' . bin2hex(random_bytes(16)) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], category_image_dir() . $filename)) {
        return ['ok' => false, 'message' => t('image_save_failed', 'Could not save the image.'), 'filename' => null];
    }

    return ['ok' => true, 'message' => '', 'filename' => $filename];
}
