<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Output escaping
|--------------------------------------------------------------------------
*/

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}


/*
|--------------------------------------------------------------------------
| Redirect
|--------------------------------------------------------------------------
*/

function redirect(string $path): never
{
    header('Location: ' . BASE_URL . '/' . ltrim($path, '/'));
    exit;
}


/*
|--------------------------------------------------------------------------
| CSRF
|--------------------------------------------------------------------------
*/

function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['_csrf'];
}


function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' .
        e(csrf_token()) .
        '">';
}


function verify_csrf(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return;
    }

    $token = $_POST['_csrf'] ?? '';

    if (
        empty($_SESSION['_csrf']) ||
        !hash_equals($_SESSION['_csrf'], $token)
    ) {
        http_response_code(419);
        exit('Invalid CSRF token.');
    }
}


/*
|--------------------------------------------------------------------------
| Flash Messages
|--------------------------------------------------------------------------
*/

function flash(string $type, string $message): void
{
    $_SESSION['_flash'] = [
        'type' => $type,
        'message' => $message
    ];
}


function get_flash(): ?array
{
    $f = $_SESSION['_flash'] ?? null;

    unset($_SESSION['_flash']);

    return $f;
}


/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/

function is_logged_in(): bool
{
    return !empty($_SESSION['user_id']);
}


function current_user_id(): ?int
{
    return isset($_SESSION['user_id'])
        ? (int) $_SESSION['user_id']
        : null;
}


function require_login(): void
{
    if (!is_logged_in()) {
        redirect('login.php');
    }
}


function require_role(string $role): void
{
    require_login();

    if (($_SESSION['role'] ?? '') !== $role) {
        http_response_code(403);
        exit('Forbidden.');
    }
}


/*
|--------------------------------------------------------------------------
| Assets
|--------------------------------------------------------------------------
*/

function asset(string $path): string
{
    return BASE_URL . '/' . ltrim($path, '/');
}


/*
|--------------------------------------------------------------------------
| Upload Directory
|--------------------------------------------------------------------------
*/

function upload_directory(string $folder): string
{
    $relativeDir = 'uploads/' . trim($folder, '/');

    $absoluteDir =
        dirname(__DIR__) .
        DIRECTORY_SEPARATOR .
        str_replace('/', DIRECTORY_SEPARATOR, $relativeDir);

    if (!is_dir($absoluteDir)) {
        if (!mkdir($absoluteDir, 0755, true) && !is_dir($absoluteDir)) {
            throw new RuntimeException(
                'Could not create upload directory.'
            );
        }
    }

    return $absoluteDir;
}


/*
|--------------------------------------------------------------------------
| Validate Image Dimensions
|--------------------------------------------------------------------------
|
| This prevents very small profile photos from being uploaded.
|
| The image is NOT resized here.
| The original uploaded resolution is preserved.
|--------------------------------------------------------------------------
*/

function validate_profile_image_dimensions(
    string $temporaryFile,
    int $minimumWidth = 200,
    int $minimumHeight = 200
): void {
    $dimensions = @getimagesize($temporaryFile);

    if ($dimensions === false) {
        throw new RuntimeException(
            'The uploaded file is not a valid image.'
        );
    }

    $width = (int) ($dimensions[0] ?? 0);
    $height = (int) ($dimensions[1] ?? 0);

    if ($width < $minimumWidth || $height < $minimumHeight) {
        throw new RuntimeException(
            "Please upload a higher-resolution profile photo. " .
            "The minimum recommended size is {$minimumWidth} × {$minimumHeight} pixels."
        );
    }
}


/*
|--------------------------------------------------------------------------
| Upload File
|--------------------------------------------------------------------------
|
| Important:
|
| - Does NOT resize images.
| - Does NOT recompress images.
| - Does NOT convert JPEG/PNG/WebP.
| - Saves the original uploaded file.
| - Uses MIME validation.
| - Uses random filenames.
|--------------------------------------------------------------------------
*/

function upload_file(
    string $field,
    array $allowedExtensions,
    string $folder
): ?string {
    if (
        !isset($_FILES[$field]) ||
        !isset($_FILES[$field]['error'])
    ) {
        return null;
    }

    if ($_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    $file = $_FILES[$field];

    /*
    |--------------------------------------------------------------------------
    | Upload error
    |--------------------------------------------------------------------------
    */

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $uploadErrors = [
            UPLOAD_ERR_INI_SIZE =>
                'The uploaded file exceeds the server upload limit.',

            UPLOAD_ERR_FORM_SIZE =>
                'The uploaded file exceeds the allowed form size.',

            UPLOAD_ERR_PARTIAL =>
                'The file was only partially uploaded.',

            UPLOAD_ERR_NO_TMP_DIR =>
                'The server temporary upload directory is missing.',

            UPLOAD_ERR_CANT_WRITE =>
                'The server could not write the uploaded file.',

            UPLOAD_ERR_EXTENSION =>
                'The upload was stopped by a server extension.'
        ];

        $message =
            $uploadErrors[$file['error']]
            ?? 'File upload failed.';

        throw new RuntimeException($message);
    }


    /*
    |--------------------------------------------------------------------------
    | File size
    |--------------------------------------------------------------------------
    */

    $fileSize = (int) ($file['size'] ?? 0);

    if ($fileSize <= 0) {
        throw new RuntimeException(
            'The uploaded file is empty.'
        );
    }

    if ($fileSize > MAX_UPLOAD_SIZE) {
        throw new RuntimeException(
            'File exceeds the 5MB upload limit.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Extension
    |--------------------------------------------------------------------------
    */

    $originalName = (string) ($file['name'] ?? '');

    $ext = strtolower(
        pathinfo($originalName, PATHINFO_EXTENSION)
    );

    /*
    |--------------------------------------------------------------------------
    | Allowed extensions
    |--------------------------------------------------------------------------
    */

    $allowedExtensions = array_map(
        static fn($extension): string =>
            strtolower((string) $extension),
        $allowedExtensions
    );

    if (!in_array($ext, $allowedExtensions, true)) {
        throw new RuntimeException(
            'Unsupported file type.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | MIME validation
    |--------------------------------------------------------------------------
    */

    if (!class_exists('finfo')) {
        throw new RuntimeException(
            'PHP Fileinfo extension is required.'
        );
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);

    $mime = $finfo->file(
        $file['tmp_name']
    );

    $mimeMap = [
        'jpg' => [
            'image/jpeg'
        ],

        'jpeg' => [
            'image/jpeg'
        ],

        'png' => [
            'image/png'
        ],

        'webp' => [
            'image/webp'
        ],

        'pdf' => [
            'application/pdf'
        ]
    ];

    if (
        !isset($mimeMap[$ext]) ||
        !in_array($mime, $mimeMap[$ext], true)
    ) {
        throw new RuntimeException(
            'Invalid file content.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Extra validation for images
    |--------------------------------------------------------------------------
    */

    $imageExtensions = [
        'jpg',
        'jpeg',
        'png',
        'webp'
    ];

    if (in_array($ext, $imageExtensions, true)) {

        $imageInfo = @getimagesize(
            $file['tmp_name']
        );

        if ($imageInfo === false) {
            throw new RuntimeException(
                'The uploaded image is invalid or corrupted.'
            );
        }

        $width = (int) ($imageInfo[0] ?? 0);
        $height = (int) ($imageInfo[1] ?? 0);

        if ($width <= 0 || $height <= 0) {
            throw new RuntimeException(
                'Could not determine the image dimensions.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Profile photo quality protection
        |--------------------------------------------------------------------------
        |
        | Only apply the minimum dimension check to the profile folder.
        |
        */

        if (
            strtolower(trim($folder, '/')) === 'profile'
        ) {
            validate_profile_image_dimensions(
                $file['tmp_name'],
                200,
                200
            );
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Upload directory
    |--------------------------------------------------------------------------
    */

    $absoluteDir = upload_directory($folder);

    $relativeDir =
        'uploads/' . trim($folder, '/');


    /*
    |--------------------------------------------------------------------------
    | Generate secure random filename
    |--------------------------------------------------------------------------
    */

    $filename =
        bin2hex(random_bytes(16)) .
        '.' .
        $ext;

    $target =
        $absoluteDir .
        DIRECTORY_SEPARATOR .
        $filename;


    /*
    |--------------------------------------------------------------------------
    | Move original uploaded file
    |--------------------------------------------------------------------------
    |
    | IMPORTANT:
    |
    | move_uploaded_file() moves the original file.
    | It does not resize or recompress the image.
    |
    |--------------------------------------------------------------------------
    */

    if (
        !move_uploaded_file(
            $file['tmp_name'],
            $target
        )
    ) {
        throw new RuntimeException(
            'Could not save uploaded file.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Set safe permissions
    |--------------------------------------------------------------------------
    */

    @chmod($target, 0644);


    /*
    |--------------------------------------------------------------------------
    | Return relative database path
    |--------------------------------------------------------------------------
    */

    return
        $relativeDir .
        '/' .
        $filename;
}


/*
|--------------------------------------------------------------------------
| Delete Uploaded File
|--------------------------------------------------------------------------
*/

function delete_upload(?string $relativePath): void
{
    if (!$relativePath) {
        return;
    }

    /*
    |--------------------------------------------------------------------------
    | Prevent accidental absolute paths
    |--------------------------------------------------------------------------
    */

    $relativePath = str_replace(
        ['\\', "\0"],
        ['/',''],
        $relativePath
    );

    $relativePath = ltrim(
        $relativePath,
        '/'
    );

    /*
    |--------------------------------------------------------------------------
    | Only allow uploads directory
    |--------------------------------------------------------------------------
    */

    if (
        !str_starts_with(
            strtolower($relativePath),
            'uploads/'
        )
    ) {
        return;
    }

    $file =
        dirname(__DIR__) .
        DIRECTORY_SEPARATOR .
        str_replace(
            '/',
            DIRECTORY_SEPARATOR,
            $relativePath
        );

    if (is_file($file)) {
        @unlink($file);
    }
}


/*
|--------------------------------------------------------------------------
| Date Formatting
|--------------------------------------------------------------------------
*/

function format_date(?string $date): string
{
    if (!$date) {
        return '';
    }

    $time = strtotime($date);

    return $time
        ? date('M Y', $time)
        : '';
}


/*
|--------------------------------------------------------------------------
| Profile
|--------------------------------------------------------------------------
*/

function ensure_profile(int $userId): void
{
    $stmt = db()->prepare(
        'INSERT IGNORE INTO profiles (user_id) VALUES (?)'
    );

    $stmt->execute([
        $userId
    ]);
}


function get_profile(int $userId): array
{
    ensure_profile($userId);

    $stmt = db()->prepare(
        'SELECT * FROM profiles WHERE user_id=?'
    );

    $stmt->execute([
        $userId
    ]);

    return $stmt->fetch() ?: [];
}


/*
|--------------------------------------------------------------------------
| User
|--------------------------------------------------------------------------
*/

function get_user(int $userId): array
{
    $stmt = db()->prepare(
        'SELECT * FROM users WHERE id=?'
    );

    $stmt->execute([
        $userId
    ]);

    return $stmt->fetch() ?: [];
}


/*
|--------------------------------------------------------------------------
| Table Count
|--------------------------------------------------------------------------
*/

function count_table(
    string $table,
    int $userId
): int {
    $allowed = [
        'projects',
        'experience',
        'education',
        'skills',
        'certifications'
    ];

    if (!in_array($table, $allowed, true)) {
        return 0;
    }

    $stmt = db()->prepare(
        "SELECT COUNT(*) FROM {$table} WHERE user_id=?"
    );

    $stmt->execute([
        $userId
    ]);

    return (int) $stmt->fetchColumn();
}


/*
|--------------------------------------------------------------------------
| Profile Picture URL
|--------------------------------------------------------------------------
*/

function get_profile_picture(
    int $userId
): string {
    $profile = get_profile($userId);

    if (
        empty($profile['profile_image'])
    ) {
        return asset(
            'img/undraw_profile.svg'
        );
    }

    $picture =
        trim(
            (string) $profile['profile_image']
        );

    /*
    |--------------------------------------------------------------------------
    | External Google / remote image
    |--------------------------------------------------------------------------
    */

    if (
        filter_var(
            $picture,
            FILTER_VALIDATE_URL
        )
    ) {
        return $picture;
    }


    /*
    |--------------------------------------------------------------------------
    | Normalize Windows paths
    |--------------------------------------------------------------------------
    */

    $picture = str_replace(
        '\\',
        '/',
        $picture
    );

    $picture = ltrim(
        $picture,
        '/'
    );


    /*
    |--------------------------------------------------------------------------
    | Local uploaded image
    |--------------------------------------------------------------------------
    */

    return asset($picture);
}


/*
|--------------------------------------------------------------------------
| Profile Image Information
|--------------------------------------------------------------------------
|
| Useful if you want to inspect the uploaded image resolution.
|--------------------------------------------------------------------------
*/

function get_profile_image_info(
    int $userId
): ?array {
    $profile = get_profile($userId);

    if (
        empty($profile['profile_image'])
    ) {
        return null;
    }

    $picture =
        str_replace(
            '\\',
            '/',
            (string) $profile['profile_image']
        );

    $picture = ltrim(
        $picture,
        '/'
    );

    /*
    |--------------------------------------------------------------------------
    | External image
    |--------------------------------------------------------------------------
    */

    if (
        filter_var(
            $picture,
            FILTER_VALIDATE_URL
        )
    ) {
        return null;
    }

    $absolutePath =
        dirname(__DIR__) .
        DIRECTORY_SEPARATOR .
        str_replace(
            '/',
            DIRECTORY_SEPARATOR,
            $picture
        );

    if (!is_file($absolutePath)) {
        return null;
    }

    $info = @getimagesize(
        $absolutePath
    );

    if ($info === false) {
        return null;
    }

    return [
        'width' => (int) $info[0],
        'height' => (int) $info[1],
        'mime' => (string) ($info['mime'] ?? ''),
        'path' => $picture
    ];
}