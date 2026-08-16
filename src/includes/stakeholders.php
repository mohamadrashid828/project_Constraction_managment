<?php

/**
 * Single source of truth for subpart pricing metrics — used to build the
 * metric_type <select>, validate submitted values, and render a friendly
 * label for the raw key stored on project_stakeholder_subparts.
 */
function stakeholder_metric_options(): array
{
    return [
        'm²' => 'm² (square meter)',
        'm' => 'm (linear meter)',
        'per_apartment' => 'Per apartment',
        'per_unit' => 'Per unit (e.g., window, door)',
        'lump_sum' => 'Lump sum (fixed contract price)',
        'per_hour' => 'Per hour (equipment/labor)',
        'per_day' => 'Per day (equipment/labor)',
    ];
}

function stakeholder_metric_label(string $key): string
{
    $options = stakeholder_metric_options();
    return $options[$key] ?? $key;
}

function stakeholder_photo_dir(): string
{
    $dir = dirname(__DIR__, 2) . '/data/stakeholder_photos/';
    stakeholder_ensure_upload_dir($dir);
    return $dir;
}

function stakeholder_document_dir(): string
{
    $dir = dirname(__DIR__, 2) . '/data/stakeholder_docs/';
    stakeholder_ensure_upload_dir($dir);
    return $dir;
}

function stakeholder_ensure_upload_dir(string $dir): void
{
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $htaccess = $dir . '.htaccess';
    if (!is_file($htaccess)) {
        file_put_contents($htaccess, "Require all denied\n");
    }
}

/**
 * Validate and store an uploaded stakeholder profile photo (JPG, PNG, GIF or
 * WEBP, max 5MB). No file chosen is not an error — the photo is optional.
 * Content is checked by magic bytes; the on-disk name is randomly generated.
 *
 * @return array{ok: bool, message: string, filename: ?string}
 */
function store_stakeholder_photo(array $file): array
{
    if (empty($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['ok' => true, 'message' => '', 'filename' => null];
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'message' => 'The photo failed to upload. Please try again.', 'filename' => null];
    }

    $maxBytes = 5 * 1024 * 1024;
    if ($file['size'] <= 0 || $file['size'] > $maxBytes) {
        return ['ok' => false, 'message' => 'Photos must be between 1 byte and 5MB.', 'filename' => null];
    }
    if (!is_uploaded_file($file['tmp_name'])) {
        return ['ok' => false, 'message' => 'Invalid upload.', 'filename' => null];
    }

    $ext = stakeholder_detect_image_ext($file['tmp_name']);
    if ($ext === null) {
        return ['ok' => false, 'message' => 'Only JPG, PNG, GIF or WEBP images are accepted.', 'filename' => null];
    }

    $filename = 'photo_' . bin2hex(random_bytes(16)) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], stakeholder_photo_dir() . $filename)) {
        return ['ok' => false, 'message' => 'Could not save the photo.', 'filename' => null];
    }

    return ['ok' => true, 'message' => '', 'filename' => $filename];
}

/**
 * Validate and store an uploaded stakeholder document (PDF, JPG, PNG, GIF or
 * WEBP, max 10MB). Content is checked by magic bytes; the on-disk name is
 * randomly generated.
 *
 * @return array{ok: bool, message: string, filename: ?string}
 */
function store_stakeholder_document(array $file): array
{
    if (empty($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['ok' => false, 'message' => 'Please choose a file to upload.', 'filename' => null];
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'message' => 'The file failed to upload. Please try again.', 'filename' => null];
    }

    $maxBytes = 10 * 1024 * 1024;
    if ($file['size'] <= 0 || $file['size'] > $maxBytes) {
        return ['ok' => false, 'message' => 'Files must be between 1 byte and 10MB.', 'filename' => null];
    }
    if (!is_uploaded_file($file['tmp_name'])) {
        return ['ok' => false, 'message' => 'Invalid upload.', 'filename' => null];
    }

    $handle = fopen($file['tmp_name'], 'rb');
    $header = $handle ? (string)fread($handle, 8) : '';
    if ($handle) {
        fclose($handle);
    }

    $ext = null;
    if (strncmp($header, '%PDF-', 5) === 0) {
        $ext = 'pdf';
    } else {
        $ext = stakeholder_detect_image_ext($file['tmp_name']);
    }
    if ($ext === null) {
        return ['ok' => false, 'message' => 'Only PDF, JPG, PNG, GIF or WEBP files are accepted.', 'filename' => null];
    }

    $filename = 'doc_' . bin2hex(random_bytes(16)) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], stakeholder_document_dir() . $filename)) {
        return ['ok' => false, 'message' => 'Could not save the file.', 'filename' => null];
    }

    return ['ok' => true, 'message' => '', 'filename' => $filename];
}

function stakeholder_detect_image_ext(string $tmpPath): ?string
{
    $handle = fopen($tmpPath, 'rb');
    $header = $handle ? (string)fread($handle, 12) : '';
    if ($handle) {
        fclose($handle);
    }

    if (strncmp($header, "\xFF\xD8\xFF", 3) === 0) {
        return 'jpg';
    }
    if (strncmp($header, "\x89PNG\r\n\x1a\n", 8) === 0) {
        return 'png';
    }
    if (strncmp($header, 'GIF87a', 6) === 0 || strncmp($header, 'GIF89a', 6) === 0) {
        return 'gif';
    }
    if (strncmp($header, 'RIFF', 4) === 0 && substr($header, 8, 4) === 'WEBP') {
        return 'webp';
    }
    return null;
}
