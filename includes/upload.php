<?php
// Practical 12: validated poster upload
const POSTER_DIR      = __DIR__ . '/../uploads/posters';
const POSTER_MAX_SIZE = 2 * 1024 * 1024;                       // 2 MB
const POSTER_TYPES    = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

// Returns the stored file name, or null when nothing was uploaded / validation failed ($error explains why).
function handle_poster_upload(array $file, ?string &$error): ?string
{
    $error = null;
    $code  = $file['error'] ?? UPLOAD_ERR_NO_FILE;

    if ($code === UPLOAD_ERR_NO_FILE) {
        return null;                                            // poster is optional
    }
    if ($code === UPLOAD_ERR_INI_SIZE || $code === UPLOAD_ERR_FORM_SIZE) {
        $error = 'The poster is too large. Maximum size is 2 MB.';
        return null;
    }
    if ($code !== UPLOAD_ERR_OK) {
        $error = 'The poster could not be uploaded. Please try again.';
        return null;
    }
    if (!is_uploaded_file($file['tmp_name'])) {
        $error = 'Invalid upload.';
        return null;
    }
    if ($file['size'] > POSTER_MAX_SIZE) {
        $error = 'The poster is too large. Maximum size is 2 MB.';
        return null;
    }

    // Check the real file content, not the name or the browser-supplied type
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($file['tmp_name']);
    if (!isset(POSTER_TYPES[$mime]) || @getimagesize($file['tmp_name']) === false) {
        $error = 'Only JPG, PNG or WEBP images are allowed.';
        return null;
    }

    if (!is_dir(POSTER_DIR) && !mkdir(POSTER_DIR, 0755, true)) {
        $error = 'Upload folder is not available.';
        return null;
    }

    $name = bin2hex(random_bytes(16)) . '.' . POSTER_TYPES[$mime];   // random name: no clashes, no user-controlled path
    if (!move_uploaded_file($file['tmp_name'], POSTER_DIR . '/' . $name)) {
        $error = 'The poster could not be saved. Check folder permissions.';
        return null;
    }
    return $name;
}

function delete_poster_file(?string $name): void
{
    if ($name && preg_match('/^[a-f0-9]{32}\.(jpg|png|webp)$/', $name)) {
        $path = POSTER_DIR . '/' . $name;
        if (is_file($path)) {
            @unlink($path);
        }
    }
}
