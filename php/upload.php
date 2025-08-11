<?php

require __DIR__ . '/../vendor/autoload.php';

use Google\Cloud\Storage\StorageClient;

if(file_exists(__DIR__ . '/../.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..'); // Assuming .env is in the project root
    $dotenv->load();
}

// Check if file was uploaded via POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['imageFile'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid request or no file uploaded.']);
    exit;
}

$uploadedFile = $_FILES['imageFile'];

// Initial check for PHP upload errors
if ($uploadedFile['error']!== UPLOAD_ERR_OK) {
    // Handle PHP upload errors (see section 4.6 for detailed error mapping)
    $phpFileUploadErrors = array(
        UPLOAD_ERR_OK         => 'There is no error, the file uploaded with success.',
        UPLOAD_ERR_INI_SIZE   => 'The uploaded file exceeds the upload_max_filesize directive in php.ini.',
        UPLOAD_ERR_FORM_SIZE  => 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form.',
        UPLOAD_ERR_PARTIAL    => 'The uploaded file was only partially uploaded.',
        UPLOAD_ERR_NO_FILE    => 'No file was uploaded.',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder.',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
        UPLOAD_ERR_EXTENSION  => 'A PHP extension stopped the file upload.',
    );
    $errorMessage = $phpFileUploadErrors[$uploadedFile['error']]?? 'Unknown upload error.';
    echo json_encode(['success' => false, 'message' => $errorMessage]);
    exit;
}

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $uploadedFile['tmp_name']);
finfo_close($finfo);

$allowedMimeTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp']; // Add any other types you support

if (!in_array($mimeType, $allowedMimeTypes)) {
    echo json_encode(['success' => false, 'message' => 'Invalid file type. Only images (JPEG, PNG, GIF, WEBP) are allowed.']);
    exit;
}

$maxFileSize = 5 * 1024 * 1024; // 5 MB
if ($uploadedFile['size'] > $maxFileSize) {
    echo json_encode(['success' => false, 'message' => 'File size exceeds the maximum allowed (5MB).']);
    exit;
}

function generateUniqueFileName(string $originalFileName): string {
    $extension = pathinfo($originalFileName, PATHINFO_EXTENSION);
    // Generate a cryptographically secure random string for the filename
    return bin2hex(random_bytes(16)). '.'. $extension; // [34]
}
$newFileName = generateUniqueFileName($uploadedFile['name']);

header('Content-Type: application/json');
// Configuration for Google Cloud Storage
$projectId = 'fits-n-finds-project'; // Replace with your GCP Project ID
$bucketName = 'fits-n-finds-bucket-storage'; // Replace with your GCS Bucket Name
//$keyFilePath = __DIR__ . '/../name.json'; // Path to your downloaded JSON key file



try {
    $gcsCredentialsJson = $_ENV['GOOGLE_APPLICATION_CREDENTIALS_JSON']; // Accessing the env variable

    // Check if the variable is empty (though Dotenv usually handles "not set" by not loading)
    if (!$gcsCredentialsJson) {
        // You might want a more specific HTTP status code here, e.g., 500 Internal Server Error
        error_log("Google Cloud credentials not found in environment variable GOOGLE_APPLICATION_CREDENTIALS_JSON.");
        echo json_encode(['success' => false, 'message' => 'Server configuration error: Google Cloud credentials not found.']);
        exit;
    }

    $storage = new StorageClient([
        'projectId' => $projectId,
        //'keyFilePath' => $keyFilePath
        'keyFile' => json_decode($gcsCredentialsJson, true) // Use the JSON string from the environment variable
        ]);
    $bucket = $storage->bucket($bucketName);

    // Upload the file to GCS
    // Use the temporary file path and the newly generated unique filename
    $object = $bucket->upload(
        fopen($uploadedFile['tmp_name'], 'r'),
        [
            'name' => $newFileName,
            // 'predefinedAcl' => 'publicRead'
        ]
    );

    // Get the public URL of the uploaded object
    $imageUrl = $object->info()['mediaLink']; // This is the public URL [26]

    echo json_encode(['success' => true, 'message' => 'Image uploaded successfully!', 'imageUrl' => $imageUrl]);
    //... proceed to save $imageUrl to database
} catch (Google\Cloud\Core\Exception\ServiceException $e) {
    // Specific GCS API errors (e.g., permission denied, bucket not found)
    error_log('GCS Service Error: '. $e->getMessage(). ' Code: '. $e->getCode());
    echo json_encode(['success' => false, 'message' => 'Cloud storage error: '. $e->getMessage()]);
} catch (Exception $e) {
    // Catch any other unexpected errors during GCS interaction
    error_log('General GCS Error: '. $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred while uploading the image: '. $e->getMessage()]);
}

?>