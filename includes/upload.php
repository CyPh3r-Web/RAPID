<?php
/**
 * Secure file upload helpers for device / repair media
 */

declare(strict_types=1);

/**
 * Allowed extension → MIME map for device condition media
 */
function upload_allowed_map(): array
{
    return [
        'jpg'  => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png'  => ['image/png'],
        'webp' => ['image/webp'],
        'mp4'  => ['video/mp4'],
        'mov'  => ['video/quicktime', 'video/mp4'],
    ];
}

/**
 * Normalize multi-file $_FILES['field'] into a list of single-file arrays.
 */
function normalize_files_array(array $filesField): array
{
    $out = [];
    if (!isset($filesField['name'])) {
        return $out;
    }

    if (!is_array($filesField['name'])) {
        if ($filesField['error'] === UPLOAD_ERR_NO_FILE) {
            return $out;
        }
        return [$filesField];
    }

    $count = count($filesField['name']);
    for ($i = 0; $i < $count; $i++) {
        if (($filesField['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        $out[] = [
            'name'     => $filesField['name'][$i],
            'type'     => $filesField['type'][$i],
            'tmp_name' => $filesField['tmp_name'][$i],
            'error'    => $filesField['error'][$i],
            'size'     => $filesField['size'][$i],
        ];
    }

    return $out;
}

function detect_upload_mime(string $tmpPath): string
{
    if (!is_file($tmpPath)) {
        return '';
    }
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
            $mime = finfo_file($finfo, $tmpPath);
            finfo_close($finfo);
            if (is_string($mime) && $mime !== '') {
                return strtolower($mime);
            }
        }
    }
    return '';
}

/**
 * Validate and store uploaded files under uploads/{subdir}/.
 *
 * @return array{ok:bool, files?:array, error?:string}
 */
function store_uploaded_media(array $filesField, string $subdir = 'device', int $maxFiles = 8): array
{
    $files = normalize_files_array($filesField);
    if (count($files) === 0) {
        return ['ok' => true, 'files' => []];
    }

    if (count($files) > $maxFiles) {
        return ['ok' => false, 'error' => 'You can upload a maximum of ' . $maxFiles . ' files.'];
    }

    $allowed = upload_allowed_map();
    $destDir = UPLOAD_PATH . DIRECTORY_SEPARATOR . $subdir;

    if (!is_dir($destDir) && !mkdir($destDir, 0755, true)) {
        return ['ok' => false, 'error' => 'Upload directory is not available.'];
    }

    $saved = [];
    $blockedExt = ['php', 'phtml', 'php3', 'php4', 'php5', 'phar', 'cgi', 'pl', 'py', 'asp', 'aspx', 'exe', 'sh', 'bat', 'js', 'html', 'htm', 'shtml'];

    foreach ($files as $file) {
        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'error' => 'One or more files failed to upload. Please try again.'];
        }

        if (!is_uploaded_file($file['tmp_name'])) {
            return ['ok' => false, 'error' => 'Invalid upload detected.'];
        }

        $size = (int) $file['size'];
        if ($size <= 0 || $size > UPLOAD_MAX_SIZE) {
            return ['ok' => false, 'error' => 'Each file must be under 10 MB.'];
        }

        $original = (string) $file['name'];
        $original = str_replace(["\0", '/', '\\'], '', $original);
        $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));

        if ($ext === '' || in_array($ext, $blockedExt, true) || !isset($allowed[$ext])) {
            return ['ok' => false, 'error' => 'Allowed file types: JPG, JPEG, PNG, WEBP, MP4, MOV.'];
        }

        // Reject double extensions like file.php.jpg
        $baseName = strtolower(pathinfo($original, PATHINFO_FILENAME));
        if (preg_match('/\.(php|phtml|phar|exe|js|html?)$/i', $baseName)) {
            return ['ok' => false, 'error' => 'Invalid file name.'];
        }

        $mime = detect_upload_mime($file['tmp_name']);
        if ($mime === '' || !in_array($mime, $allowed[$ext], true)) {
            return ['ok' => false, 'error' => 'File type validation failed for "' . $original . '".'];
        }

        // Extra: images must be readable as images
        if (strpos($mime, 'image/') === 0) {
            $info = @getimagesize($file['tmp_name']);
            if ($info === false) {
                return ['ok' => false, 'error' => 'Invalid image file: ' . $original];
            }
        }

        $safeName = bin2hex(random_bytes(16)) . '.' . $ext;
        $absolute = $destDir . DIRECTORY_SEPARATOR . $safeName;

        if (!move_uploaded_file($file['tmp_name'], $absolute)) {
            // Clean already saved on failure
            foreach ($saved as $prev) {
                @unlink(ROOT_PATH . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $prev['file_path']));
            }
            return ['ok' => false, 'error' => 'Could not save uploaded file. Please try again.'];
        }

        @chmod($absolute, 0644);

        $saved[] = [
            'file_name' => $original,
            'file_path' => 'uploads/' . $subdir . '/' . $safeName,
            'file_type' => $mime,
        ];
    }

    return ['ok' => true, 'files' => $saved];
}

function is_image_mime(string $mime): bool
{
    return strpos($mime, 'image/') === 0;
}

function is_video_mime(string $mime): bool
{
    return strpos($mime, 'video/') === 0;
}

function media_public_url(string $relativePath): string
{
    $relativePath = ltrim(str_replace('\\', '/', $relativePath), '/');
    // Prevent traversal in URL construction
    $relativePath = str_replace(['../', '..\\'], '', $relativePath);
    return url($relativePath);
}
