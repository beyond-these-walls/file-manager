<?php

namespace BeyondTheseWalls\FileManager\Livewire;

use BeyondTheseWalls\FileManager\Services\CloudflareImageService;
use BeyondTheseWalls\FileManager\Services\HuggingFaceImageService;
use BeyondTheseWalls\FileManager\Services\PicsartImageService;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use Livewire\Component;
use Livewire\WithFileUploads;

class FileManager extends Component
{
    use WithFileUploads;

    public $isOpen = false;

    public $currentPath = '';

    public $rootPath = '';

    public $files = [];

    public $folders = [];

    public $uploadedFiles = [];

    public $selectedFile = null;

    public $viewMode = 'grid';

    public $searchTerm = '';

    public $showCreateFolder = false;

    public $newFolderName = '';

    public $mediaType = 'all';

    public $showDeleteModal = false;

    public $fileToDelete = null;

    public bool $deletingPreviewFile = false;

    // Multi-select
    public array $selectedFiles = [];

    public bool $showBulkDeleteModal = false;

    // Pagination
    public int $perPage = 48;

    public int $currentPage = 1;

    public int $totalFiles = 0;

    // AI image generation
    public string $aiPrompt = '';

    public bool $aiGenerating = false;

    public string $aiError = '';

    public bool $showAiGenerator = false;

    public string $aiProvider = 'cloudflare';

    public string $aiImageSize = '1024x1024';

    public ?array $aiQuota = null;

    // Stored for regeneration from preview
    public string $lastAiPrompt = '';

    public string $lastAiProvider = 'cloudflare';

    public string $lastAiImageSize = '1024x1024';

    public bool $isAiGeneratedPreview = false;

    // Image editor state (rotate/flip/brightness/contrast/quality live in Alpine only)
    public ?array $previewFile = null;

    public ?int $editResizeWidth = null;

    public ?int $editResizeHeight = null;

    public bool $editKeepAspect = true;

    public int $editVersion = 0;

    public array $originalDimensions = [];

    // SECURITY: Define allowed file types and their MIME types
    protected $allowedFileTypes = [
        'image' => [
            'extensions' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'],
            'mimes' => ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/bmp'],
            'max_size' => 10240, // 10MB in KB
        ],
        'media' => [
            'extensions' => ['mp4', 'avi', 'mov', 'wmv', 'flv', 'mkv', 'webm', 'mp3', 'wav', 'ogg', 'flac', 'aac'],
            'mimes' => ['video/mp4', 'video/avi', 'video/quicktime', 'video/x-ms-wmv', 'video/x-flv', 'video/x-matroska', 'video/webm', 'audio/mpeg', 'audio/wav', 'audio/ogg', 'audio/flac', 'audio/aac'],
            'max_size' => 102400, // 100MB in KB
        ],
        'file' => [
            'extensions' => ['pdf', 'doc', 'docx', 'txt', 'rtf', 'odt', 'xls', 'xlsx', 'csv', 'ppt', 'pptx'],
            'mimes' => ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'text/plain', 'application/rtf', 'application/vnd.oasis.opendocument.text', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'text/csv', 'application/vnd.ms-powerpoint', 'application/vnd.openxmlformats-officedocument.presentationml.presentation'],
            'max_size' => 51200, // 50MB in KB
        ],
    ];

    // SECURITY: Dangerous file extensions that should NEVER be allowed
    protected $dangerousExtensions = [
        'php', 'php3', 'php4', 'php5', 'phtml', 'asp', 'aspx', 'jsp', 'jspx',
        'exe', 'bat', 'cmd', 'com', 'scr', 'vbs', 'vbe', 'js', 'jar',
        'sh', 'bash', 'pl', 'py', 'rb', 'cgi', 'htaccess', 'htpasswd',
    ];

    // Default folder mapping for different media types
    protected $mediaTypeFolders = [
        'image' => 'images',
        'media' => 'videos',
        'file' => 'files',
        'all' => '',
    ];

    protected $listeners = [
        'openFileManager' => 'open',
        'closeFileManager' => 'close',
    ];

    public function mount()
    {
        $this->loadFiles();
    }

    public function open($mediaType = 'all', $path = null)
    {
        $this->isOpen = true;
        $this->mediaType = $mediaType;
        $this->currentPath = $path ?? ($this->mediaTypeFolders[$mediaType] ?? '');
        $this->rootPath = $this->currentPath;
        $this->loadFiles();
    }

    public function close()
    {
        $this->isOpen = false;
        $this->selectedFile = null;
        $this->mediaType = 'all';
        $this->currentPath = '';
        $this->rootPath = '';
        $this->showDeleteModal = false;
        $this->fileToDelete = null;
        $this->previewFile = null;
        $this->isAiGeneratedPreview = false;
        $this->lastAiPrompt = '';
        $this->selectedFiles = [];
        $this->showBulkDeleteModal = false;
        $this->currentPage = 1;
        $this->resetEdits();
    }

    public function loadFiles()
    {
        $path = 'uploads/'.trim($this->currentPath, '/');

        $allFiles = Storage::disk('public')->files($path);
        $allFolders = Storage::disk('public')->directories($path);

        $filteredFiles = collect($allFiles)->map(function ($file) {
            $filename = basename($file);

            if ($this->searchTerm && ! Str::contains(strtolower($filename), strtolower($this->searchTerm))) {
                return null;
            }

            $fileType = $this->getFileType($file);

            if ($this->mediaType !== 'all' && ! $this->isFileTypeMatch($fileType, $this->mediaType)) {
                return null;
            }

            $modified = Storage::disk('public')->lastModified($file);

            return [
                'name' => $filename,
                'path' => $file,
                'url' => Storage::disk('public')->url($file).'?t='.$modified,
                'size' => Storage::disk('public')->size($file),
                'type' => $fileType,
                'modified' => $modified,
                'thumbnail' => $this->getThumbnailUrl($file, $modified),
            ];
        })->filter()->values();

        // Pagination
        $this->totalFiles = $filteredFiles->count();
        $maxPage = max(1, (int) ceil($this->totalFiles / $this->perPage));
        if ($this->currentPage > $maxPage) {
            $this->currentPage = $maxPage;
        }
        $this->files = $filteredFiles->slice(($this->currentPage - 1) * $this->perPage, $this->perPage)->values()->toArray();

        $this->folders = collect($allFolders)->map(function ($folder) {
            $folderName = basename($folder);

            if ($this->searchTerm && ! Str::contains(strtolower($folderName), strtolower($this->searchTerm))) {
                return null;
            }

            return [
                'name' => $folderName,
                'path' => $folder,
            ];
        })->filter()->values()->toArray();
    }

    public function navigateToFolder($folderPath)
    {
        $stripped = str_replace('uploads/', '', $folderPath);
        $this->currentPath = $this->sanitizePath($stripped);
        $this->currentPage = 1;
        $this->selectedFiles = [];
        $this->loadFiles();
    }

    public function goBack()
    {
        if ($this->currentPath && $this->currentPath !== $this->rootPath) {
            $pathParts = explode('/', trim($this->currentPath, '/'));
            array_pop($pathParts);
            $parent = $this->sanitizePath(implode('/', $pathParts));
            // Never navigate above the root path for this media type
            $root = $this->sanitizePath($this->rootPath);
            if ($root && ! str_starts_with($parent.'/', $root.'/') && $parent !== $root) {
                $this->currentPath = $root;
            } else {
                $this->currentPath = $parent;
            }
            $this->currentPage = 1;
            $this->selectedFiles = [];
            $this->loadFiles();
        }
    }

    public function goToMediaFolder($mediaType)
    {
        if (isset($this->mediaTypeFolders[$mediaType])) {
            $this->mediaType = $mediaType;
            $this->currentPath = $this->mediaTypeFolders[$mediaType]; // '' for 'all'
            $this->currentPage = 1;
            $this->selectedFiles = [];
            $this->loadFiles();
        }
    }

    public function selectFile($file)
    {
        $this->selectedFile = $file;
        $this->dispatch('fileSelected', $file);
        $this->close();
    }

    // SECURITY: Enhanced file upload with comprehensive validation
    public function updatedUploadedFiles()
    {
        abort_unless(auth()->check(), 403);

        // Rate limiting: max 20 uploads per minute per user
        $rateLimitKey = 'file-upload:'.auth()->id();
        if (RateLimiter::tooManyAttempts($rateLimitKey, 20)) {
            $seconds = RateLimiter::availableIn($rateLimitKey);
            session()->flash('error', "Too many uploads. Please wait {$seconds}s before uploading more files.");
            $this->uploadedFiles = [];

            return;
        }

        $uploadedCount = 0;

        foreach ($this->uploadedFiles as $file) {
            RateLimiter::hit($rateLimitKey, 60);

            try {
                // SECURITY CHECK 1: Validate file using Laravel validation
                $this->validateFile($file);

                // SECURITY CHECK 2: Additional security checks
                $this->performSecurityChecks($file);

                // Generate safe filename — spaces → hyphens, then strip unsafe chars,
                // append Str::random(16) to prevent enumeration and collisions
                $originalName = $file->getClientOriginalName();
                $extension = strtolower($file->getClientOriginalExtension());
                $nameWithoutExtension = pathinfo($originalName, PATHINFO_FILENAME);

                // Sanitize filename: replace spaces/whitespace with hyphens, then strip unsafe chars
                $nameWithoutExtension = preg_replace('/\s+/', '-', $nameWithoutExtension);
                $nameWithoutExtension = preg_replace('/[^a-zA-Z0-9\-_]/', '', $nameWithoutExtension);
                $nameWithoutExtension = trim($nameWithoutExtension, '-');
                if (empty($nameWithoutExtension)) {
                    $nameWithoutExtension = 'file';
                }

                $filename = $nameWithoutExtension.'_'.now()->format('YmdHis').'_'.Str::random(6).'.'.$extension;

                // Store file in safe location
                $storedPath = $file->storeAs('uploads/'.trim($this->currentPath, '/'), $filename, 'public');

                // Create thumbnail for images
                if ($this->isImageFile($file)) {
                    $this->createThumbnail('uploads/'.trim($this->currentPath, '/').'/'.$filename);
                }

                $uploadedCount++;

                logger()->info('File uploaded successfully', [
                    'original_name' => $originalName,
                    'stored_name' => $filename,
                    'size' => $file->getSize(),
                    'mime' => $file->getMimeType(),
                ]);

            } catch (ValidationException $e) {
                session()->flash('error', 'Upload failed: '.$e->getMessage());

                continue;
            } catch (\Exception $e) {
                session()->flash('error', 'Upload failed: '.$e->getMessage());
                logger()->error('File upload failed', [
                    'file' => $file->getClientOriginalName(),
                    'error' => $e->getMessage(),
                ]);

                continue;
            }
        }

        $this->uploadedFiles = [];
        $this->loadFiles();

        if ($uploadedCount > 0) {
            session()->flash('message', $uploadedCount === 1
                ? 'File uploaded successfully!'
                : "{$uploadedCount} files uploaded successfully!");
        }
    }

    // SECURITY: Comprehensive file validation
    protected function validateFile($file)
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $mimeType = $file->getMimeType();
        $size = $file->getSize() / 1024; // Convert to KB

        // Check 1: Block dangerous extensions
        if (in_array($extension, $this->dangerousExtensions)) {
            throw new \Exception("File type '{$extension}' is not allowed for security reasons.");
        }

        // Check 2: Validate against media type restrictions
        if ($this->mediaType !== 'all') {
            $allowedTypes = $this->allowedFileTypes[$this->mediaType] ?? null;

            if (! $allowedTypes) {
                throw new \Exception('Invalid media type.');
            }

            // Extension check
            if (! in_array($extension, $allowedTypes['extensions'])) {
                throw new \Exception("File extension '{$extension}' is not allowed for {$this->mediaType} files.");
            }

            // MIME type check
            if (! in_array($mimeType, $allowedTypes['mimes'])) {
                throw new \Exception("File type '{$mimeType}' is not allowed for {$this->mediaType} files.");
            }

            // Size check
            if ($size > $allowedTypes['max_size']) {
                $maxSizeMB = round($allowedTypes['max_size'] / 1024, 1);
                throw new \Exception("File size exceeds maximum allowed size of {$maxSizeMB}MB for {$this->mediaType} files.");
            }
        } else {
            // For 'all' mode, find the type implied by the extension and verify the
            // MIME type matches that same category — prevents extension/content mismatches
            $matchedType = null;
            foreach ($this->allowedFileTypes as $type => $typeConfig) {
                if (in_array($extension, $typeConfig['extensions'])) {
                    $matchedType = $type;
                    break;
                }
            }

            if ($matchedType === null) {
                throw new \Exception("File extension '{$extension}' is not in the allowed list.");
            }

            if (! in_array($mimeType, $this->allowedFileTypes[$matchedType]['mimes'])) {
                throw new \Exception("File MIME type does not match the file extension '{$extension}'.");
            }

            $maxSize = $this->allowedFileTypes[$matchedType]['max_size'];
            if ($size > $maxSize) {
                $maxSizeMB = round($maxSize / 1024, 1);
                throw new \Exception("File size exceeds maximum allowed size of {$maxSizeMB}MB.");
            }
        }
    }

    // SECURITY: Additional security checks
    protected function performSecurityChecks($file)
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $filename = $file->getClientOriginalName();

        // Check for double extensions (like .php.jpg)
        if (preg_match('/\.('.implode('|', $this->dangerousExtensions).')\./i', $filename)) {
            throw new \Exception('Files with multiple extensions are not allowed.');
        }

        // Check for suspicious filenames
        $suspiciousPatterns = [
            '/\.(php|asp|jsp|exe|bat|cmd|sh)$/i',
            '/^\.ht/i', // .htaccess, .htpasswd
            '/\.\./i',  // Directory traversal
            '/[<>:"|?*]/i', // Invalid filename characters
        ];

        foreach ($suspiciousPatterns as $pattern) {
            if (preg_match($pattern, $filename)) {
                throw new \Exception('Filename contains suspicious patterns.');
            }
        }

        // For images, perform additional validation
        if ($this->isImageFile($file)) {
            $this->validateImageFile($file);
        }
    }

    // SECURITY: Image-specific validation
    protected function validateImageFile($file)
    {
        try {
            // Try to read the image to verify it's actually an image
            $imageInfo = getimagesize($file->getPathname());

            if ($imageInfo === false) {
                throw new \Exception('File is not a valid image.');
            }

            // Check image dimensions (prevent extremely large images)
            if ($imageInfo[0] > 10000 || $imageInfo[1] > 10000) {
                throw new \Exception('Image dimensions are too large (max 10000x10000).');
            }

        } catch (\Exception $e) {
            throw new \Exception('Image validation failed: '.$e->getMessage());
        }
    }

    public function deleteFile(): void
    {
        abort_unless(auth()->check(), 403);

        if ($this->deletingPreviewFile) {
            $this->deletePreviewFile();

            return;
        }

        if (! $this->fileToDelete) {
            return;
        }

        $safePath = $this->resolveSecurePath($this->fileToDelete['path']);
        if (! $safePath) {
            $this->showDeleteModal = false;
            $this->fileToDelete = null;

            return;
        }

        Storage::disk('public')->delete($safePath);
        $this->loadFiles();
        $this->showDeleteModal = false;
        $this->fileToDelete = null;
        session()->flash('message', 'File deleted successfully!');
    }

    public function confirmDelete($file)
    {
        $this->fileToDelete = $file;
        $this->showDeleteModal = true;
    }

    public function confirmDeletePreview(): void
    {
        if (! $this->previewFile) {
            return;
        }
        $this->deletingPreviewFile = true;
        $this->showDeleteModal = true;
    }

    public function cancelDelete()
    {
        $this->showDeleteModal = false;
        $this->fileToDelete = null;
        $this->deletingPreviewFile = false;
    }

    public function createFolder()
    {
        abort_unless(auth()->check(), 403);

        $this->validate([
            'newFolderName' => 'required|string|max:255|regex:/^[a-zA-Z0-9\-_\s]+$/',
        ]);

        $safeCurrent = $this->sanitizePath($this->currentPath);
        $folderPath = 'uploads/'.trim($safeCurrent, '/').'/'.$this->newFolderName;

        if (! Storage::disk('public')->exists($folderPath)) {
            Storage::disk('public')->makeDirectory($folderPath);
            $this->loadFiles();
            session()->flash('message', 'Folder created successfully!');
        } else {
            session()->flash('error', 'Folder already exists!');
        }

        $this->showCreateFolder = false;
        $this->newFolderName = '';
    }

    public function cancelCreateFolder()
    {
        $this->showCreateFolder = false;
        $this->newFolderName = '';
    }

    public function updatedSearchTerm()
    {
        $this->currentPage = 1;
        $this->loadFiles();
    }

    public function goToPage(int $page): void
    {
        $this->currentPage = max(1, $page);
        $this->loadFiles();
    }

    public function getTotalPages(): int
    {
        return max(1, (int) ceil($this->totalFiles / $this->perPage));
    }

    // ── Multi-select ─────────────────────────────────────────────────────────

    public function toggleSelectFile(string $path): void
    {
        if (in_array($path, $this->selectedFiles)) {
            $this->selectedFiles = array_values(array_diff($this->selectedFiles, [$path]));
        } else {
            $this->selectedFiles[] = $path;
        }
    }

    public function selectAllVisible(): void
    {
        $this->selectedFiles = array_unique(array_merge(
            $this->selectedFiles,
            array_column($this->files, 'path')
        ));
    }

    public function deselectAll(): void
    {
        $this->selectedFiles = [];
    }

    public function confirmBulkDelete(): void
    {
        if (empty($this->selectedFiles)) {
            return;
        }
        $this->showBulkDeleteModal = true;
    }

    public function cancelBulkDelete(): void
    {
        $this->showBulkDeleteModal = false;
    }

    public function bulkDelete(): void
    {
        abort_unless(auth()->check(), 403);

        $deleted = 0;
        foreach ($this->selectedFiles as $path) {
            $safePath = $this->resolveSecurePath($path);
            if ($safePath && Storage::disk('public')->exists($safePath)) {
                Storage::disk('public')->delete($safePath);
                $deleted++;
            }
        }

        $this->selectedFiles = [];
        $this->showBulkDeleteModal = false;
        $this->loadFiles();

        if ($deleted > 0) {
            session()->flash('message', "{$deleted} file(s) deleted successfully!");
        }
    }

    private function isFileTypeMatch($fileType, $mediaType)
    {
        switch ($mediaType) {
            case 'image':
                return $fileType === 'image';
            case 'media':
                return in_array($fileType, ['video', 'audio']);
            case 'file':
                return in_array($fileType, ['pdf', 'word', 'excel', 'powerpoint', 'text', 'document']);
            default:
                return true;
        }
    }

    private function getFileType($filePath)
    {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        $fileTypes = [
            'jpg' => 'image', 'jpeg' => 'image', 'png' => 'image', 'gif' => 'image',
            'webp' => 'image', 'bmp' => 'image',
            'pdf' => 'pdf', 'doc' => 'word', 'docx' => 'word', 'txt' => 'text', 'rtf' => 'text',
            'xls' => 'excel', 'xlsx' => 'excel', 'csv' => 'excel',
            'ppt' => 'powerpoint', 'pptx' => 'powerpoint',
            'mp4' => 'video', 'avi' => 'video', 'mov' => 'video', 'wmv' => 'video', 'flv' => 'video', 'mkv' => 'video', 'webm' => 'video',
            'mp3' => 'audio', 'wav' => 'audio', 'ogg' => 'audio', 'flac' => 'audio', 'aac' => 'audio',
        ];

        return $fileTypes[$extension] ?? 'file';
    }

    public function getFileIcon($fileType)
    {
        switch ($fileType) {
            case 'image':
                return '<svg class="w-12 h-12 text-green-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4l4-8 3 6 2-4 3 6z" clip-rule="evenodd"></path></svg>';
            case 'pdf':
                return '<svg class="w-12 h-12 text-red-600" fill="currentColor" viewBox="0 0 20 20"><path d="M4 18h12V6l-4-4H4v16zm8-14v3h3l-3-3zM6 8h2v1H6V8zm0 2h6v1H6v-1zm0 2h6v1H6v-1z"></path></svg>';
            case 'word':
                return '<svg class="w-12 h-12 text-blue-600" fill="currentColor" viewBox="0 0 20 20"><path d="M4 2h8l4 4v10a2 2 0 01-2 2H4a2 2 0 01-2-2V4a2 2 0 012-2zm8 4V4l2 2h-2zM6 8h6v1H6V8zm0 2h6v1H6v-1zm0 2h4v1H6v-1z"></path></svg>';
            case 'excel':
                return '<svg class="w-12 h-12 text-green-600" fill="currentColor" viewBox="0 0 20 20"><path d="M3 3h14a1 1 0 011 1v12a1 1 0 01-1 1H3a1 1 0 01-1-1V4a1 1 0 011-1zm1 2v10h12V5H4zm2 2h8v1H6V7zm0 2h8v1H6V9zm0 2h8v1H6v-1zm0 2h5v1H6v-1z"></path></svg>';
            case 'powerpoint':
                return '<svg class="w-12 h-12 text-orange-500" fill="currentColor" viewBox="0 0 20 20"><path d="M4 2h8l4 4v10a2 2 0 01-2 2H4a2 2 0 01-2-2V4a2 2 0 012-2zm8 4V4l2 2h-2zM6 8h6v4H6V8zm1 1v2h4V9H7z"></path></svg>';
            case 'text':
                return '<svg class="w-12 h-12 text-gray-600" fill="currentColor" viewBox="0 0 20 20"><path d="M4 2h8l4 4v10a2 2 0 01-2 2H4a2 2 0 01-2-2V4a2 2 0 012-2zm8 4V4l2 2h-2zM6 8h6v1H6V8zm0 2h6v1H6v-1zm0 2h6v1H6v-1zm0 2h4v1H6v-1z"></path></svg>';
            case 'video':
                return '<svg class="w-12 h-12 text-purple-500" fill="currentColor" viewBox="0 0 20 20"><path d="M2 6a2 2 0 012-2h6a2 2 0 012 2v8a2 2 0 01-2 2H4a2 2 0 01-2-2V6zM14.553 7.106A1 1 0 0014 8v4a1 1 0 00.553.894l2 1A1 1 0 0018 13V7a1 1 0 00-1.447-.894l-2 1z"></path></svg>';
            case 'audio':
                return '<svg class="w-12 h-12 text-pink-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M9.383 3.076A1 1 0 0110 4v12a1 1 0 01-1.707.707L4.586 13H2a1 1 0 01-1-1V8a1 1 0 011-1h2.586l3.707-3.707a1 1 0 011.09-.217z" clip-rule="evenodd"></path></svg>';
            default:
                return '<svg class="w-12 h-12 text-gray-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z" clip-rule="evenodd"></path></svg>';
        }
    }

    private function getThumbnailUrl($filePath, ?int $modified = null)
    {
        if ($this->getFileType($filePath) === 'image') {
            $ts = $modified ?? Storage::disk('public')->lastModified($filePath);

            return Storage::disk('public')->url($filePath).'?t='.$ts;
        }

        return null;
    }

    private function sanitizePath(string $path): string
    {
        // Normalise separators and strip every directory-traversal segment
        $path = str_replace('\\', '/', $path);
        $parts = array_filter(
            explode('/', $path),
            fn ($p) => $p !== '' && $p !== '.' && $p !== '..'
        );

        return implode('/', $parts);
    }

    private function isImageFile($file)
    {
        return in_array(strtolower($file->getClientOriginalExtension()),
            ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp']);
    }

    private function createThumbnail($filePath)
    {
        try {
            $fullPath = Storage::disk('public')->path($filePath);
            $thumbnailDir = Storage::disk('public')->path('uploads/thumbnails');

            if (! is_dir($thumbnailDir)) {
                mkdir($thumbnailDir, 0755, true);
            }

            $thumbnailPath = $thumbnailDir.'/'.basename($filePath);

            $manager = new ImageManager(new Driver);
            $image = $manager->read($fullPath);
            $image->cover(150, 150);
            $image->save($thumbnailPath);

        } catch (\Exception $e) {
            logger()->error('Thumbnail creation failed: '.$e->getMessage());
        }
    }

    // ── AI Image Generation ─────────────────────────────────────────────────────

    public function toggleAiGenerator(): void
    {
        $this->showAiGenerator = ! $this->showAiGenerator;
        $this->aiError = '';
        if ($this->showAiGenerator) {
            $this->fetchAiQuota();
        }
    }

    public function updatedAiProvider(): void
    {
        $this->aiQuota = null;
        $this->fetchAiQuota();
    }

    public function fetchAiQuota(): void
    {
        $this->aiQuota = null;

        try {
            if ($this->aiProvider === 'cloudflare') {
                // Cloudflare does not expose a quota API — show free tier info
                $this->aiQuota = [
                    'label' => 'Free tier: 32 - 300 images per day depending on size',
                    'pct' => null,
                ];
            } elseif ($this->aiProvider === 'picsart') {
                $this->aiQuota = [
                    'label' => 'Picsart Gen AI — free tier: 100 credits/day',
                    'pct' => null,
                ];
            } elseif ($this->aiProvider === 'huggingface') {
                $token = config('services.huggingface.key');
                if (! $token) {
                    return;
                }

                $response = \Illuminate\Support\Facades\Http::timeout(8)
                    ->withToken($token)
                    ->get('https://huggingface.co/api/whoami-v2');

                if ($response->ok()) {
                    $plan = $response->json('plan') ?? 'free';
                    $this->aiQuota = [
                        'label' => 'Plan: '.ucfirst($plan).' — rate limited',
                        'pct' => null,
                    ];
                }
            }
        } catch (\Exception) {
            // Quota fetch is non-critical — silently ignore errors
        }
    }

    public function generateAiImage(): void
    {
        abort_unless(auth()->check(), 403);

        $this->validate(['aiPrompt' => 'required|string|min:3|max:500']);

        $rateLimitKey = 'ai-generate:'.auth()->id();
        if (RateLimiter::tooManyAttempts($rateLimitKey, 10)) {
            $this->aiError = 'Too many requests. Please wait before generating more images.';

            return;
        }
        RateLimiter::hit($rateLimitKey, 60);

        $this->aiGenerating = true;
        $this->aiError = '';

        try {
            $service = match ($this->aiProvider) {
                'huggingface' => app(HuggingFaceImageService::class),
                'picsart' => app(PicsartImageService::class),
                default => app(CloudflareImageService::class),
            };
            [$width, $height] = array_map('intval', explode('x', $this->aiImageSize));
            $file = $service->generate($this->aiPrompt, 'images', $width, $height);

            $this->createThumbnail($file['path']);
            $this->loadFiles();
            $this->lastAiPrompt = $this->aiPrompt;
            $this->lastAiProvider = $this->aiProvider;
            $this->lastAiImageSize = $this->aiImageSize;
            $this->aiPrompt = '';
            $this->showAiGenerator = false;
            $this->previewImage($file);
            $this->isAiGeneratedPreview = true;
        } catch (\RuntimeException $e) {
            $this->aiError = $e->getMessage();
        } finally {
            $this->aiGenerating = false;
        }
    }

    public function regenerateAiImage(): void
    {
        abort_unless(auth()->check(), 403);

        if (! $this->lastAiPrompt || ! $this->previewFile || ! $this->isAiGeneratedPreview) {
            return;
        }

        $rateLimitKey = 'ai-generate:'.auth()->id();
        if (RateLimiter::tooManyAttempts($rateLimitKey, 10)) {
            $this->aiError = 'Too many requests. Please wait before generating more images.';

            return;
        }
        RateLimiter::hit($rateLimitKey, 60);

        $this->aiGenerating = true;
        $this->aiError = '';

        // Delete the existing preview file and its thumbnail so they don't accumulate
        $oldPath = $this->sanitizePath($this->previewFile['path']);
        if (str_starts_with($oldPath, 'uploads/') && Storage::disk('public')->exists($oldPath)) {
            Storage::disk('public')->delete($oldPath);
            $thumbPath = 'uploads/thumbnails/'.basename($oldPath);
            if (Storage::disk('public')->exists($thumbPath)) {
                Storage::disk('public')->delete($thumbPath);
            }
        }

        try {
            $service = match ($this->lastAiProvider) {
                'huggingface' => app(HuggingFaceImageService::class),
                'picsart' => app(PicsartImageService::class),
                default => app(CloudflareImageService::class),
            };
            [$width, $height] = array_map('intval', explode('x', $this->lastAiImageSize));
            $file = $service->generate($this->lastAiPrompt, 'images', $width, $height);

            $this->createThumbnail($file['path']);
            $this->loadFiles();
            $this->previewImage($file);
            $this->isAiGeneratedPreview = true;
            session()->flash('message', 'Image regenerated.');
        } catch (\RuntimeException $e) {
            $this->aiError = $e->getMessage();
            // Reload files since we deleted the old one
            $this->previewFile = null;
            $this->loadFiles();
        } finally {
            $this->aiGenerating = false;
        }
    }

    // ── Image editor ────────────────────────────────────────────────────────────

    public function previewImage(array $file): void
    {
        $this->previewFile = $file;
        $this->isAiGeneratedPreview = false;
        $this->resetEdits();

        try {
            $fullPath = Storage::disk('public')->path($file['path']);
            $info = @getimagesize($fullPath);
            if ($info) {
                $this->originalDimensions = ['width' => $info[0], 'height' => $info[1]];
                $this->editResizeWidth = $info[0];
                $this->editResizeHeight = $info[1];
            }
        } catch (\Exception) {
            $this->originalDimensions = [];
        }
    }

    public function backToGallery(): void
    {
        $this->previewFile = null;
        $this->resetEdits();
    }

    public function deletePreviewFile(): void
    {
        abort_unless(auth()->check(), 403);

        if (! $this->previewFile) {
            return;
        }

        $safePath = $this->resolveSecurePath($this->previewFile['path']);
        if (! $safePath) {
            return;
        }

        Storage::disk('public')->delete($safePath);

        $this->previewFile = null;
        $this->resetEdits();
        $this->showDeleteModal = false;
        $this->deletingPreviewFile = false;
        $this->loadFiles();
        session()->flash('message', 'Image deleted.');
    }

    public function insertPreviewFile(): void
    {
        if ($this->previewFile) {
            $this->dispatch('fileSelected', $this->previewFile);
            $this->close();
        }
    }

    /**
     * Crop the current image to the given pixel region and overwrite in place.
     */
    public function applyCrop(int $x, int $y, int $width, int $height): void
    {
        abort_unless(auth()->check(), 403);

        if (! $this->previewFile) {
            return;
        }

        $width = max(1, min(10000, $width));
        $height = max(1, min(10000, $height));
        $x = max(0, $x);
        $y = max(0, $y);

        $storagePath = $this->resolveSecurePath($this->previewFile['path']);
        if (! $storagePath) {
            return;
        }

        $fullPath = Storage::disk('public')->path($storagePath);

        if (! file_exists($fullPath)) {
            session()->flash('error', 'Source file not found.');

            return;
        }

        try {
            $manager = new ImageManager(new Driver);
            $image = $manager->read($fullPath);

            $imgW = $image->width();
            $imgH = $image->height();

            // Clamp to actual image bounds
            $x = min($x, max(0, $imgW - 1));
            $y = min($y, max(0, $imgH - 1));
            $width = min($width, $imgW - $x);
            $height = min($height, $imgH - $y);

            $image->crop($width, $height, $x, $y);
            $image->save($fullPath, 90);

            $this->createThumbnail($storagePath);

            $savedAt = time();
            $this->previewFile = array_merge($this->previewFile, [
                'url' => Storage::disk('public')->url($storagePath).'?t='.$savedAt,
                'size' => filesize($fullPath),
                'modified' => $savedAt,
                'thumbnail' => Storage::disk('public')->url($storagePath).'?t='.$savedAt,
            ]);

            $info = @getimagesize($fullPath);
            $this->originalDimensions = $info ? ['width' => $info[0], 'height' => $info[1]] : [];
            $this->editResizeWidth = $info[0] ?? null;
            $this->editResizeHeight = $info[1] ?? null;

            $this->editVersion++;
            $this->isAiGeneratedPreview = false; // edited image is no longer a raw AI preview
            $this->loadFiles();
            session()->flash('message', 'Image cropped.');

        } catch (\Exception $e) {
            logger()->error('Image crop failed', ['error' => $e->getMessage()]);
            session()->flash('error', 'Crop failed: '.$e->getMessage());
        }
    }

    /**
     * Save any pending edits then insert (dispatch fileSelected).
     */
    public function saveAndInsert(int $rotate = 0, bool $flipH = false, bool $flipV = false, int $brightness = 0, int $contrast = 0, int $quality = 85, bool $greyscale = false, int $blur = 0, int $sharpen = 0): void
    {
        $hasEdits = $rotate !== 0 || $flipH || $flipV || $brightness !== 0 || $contrast !== 0 || $greyscale || $blur > 0 || $sharpen > 0;

        if ($hasEdits && $this->previewFile && ($this->previewFile['type'] ?? '') === 'image') {
            $this->saveOverwrite($rotate, $flipH, $flipV, $brightness, $contrast, $quality, $greyscale, $blur, $sharpen);
            if (session()->has('error')) {
                return;
            }
        }

        $this->insertPreviewFile();
    }

    /**
     * Apply edits and overwrite the current file in place.
     */
    public function saveOverwrite(int $rotate = 0, bool $flipH = false, bool $flipV = false, int $brightness = 0, int $contrast = 0, int $quality = 85, bool $greyscale = false, int $blur = 0, int $sharpen = 0): void
    {
        abort_unless(auth()->check(), 403);

        if (! $this->previewFile) {
            return;
        }

        $storagePath = $this->resolveSecurePath($this->previewFile['path']);
        if (! $storagePath) {
            return;
        }

        $fullPath = Storage::disk('public')->path($storagePath);

        if (! file_exists($fullPath)) {
            session()->flash('error', 'Source file not found.');

            return;
        }

        try {
            $image = $this->applyImageEdits($fullPath, $rotate, $flipH, $flipV, $brightness, $contrast, $quality, $greyscale, $blur, $sharpen);
            $image->save($fullPath, max(10, min(100, $quality)));

            $this->refreshPreviewAfterSave($storagePath, $fullPath);
            session()->flash('message', 'Image saved.');

        } catch (\Exception $e) {
            logger()->error('Image overwrite failed', ['error' => $e->getMessage()]);
            session()->flash('error', 'Save failed: '.$e->getMessage());
        }
    }

    /**
     * Apply edits and save as a new file.
     */
    public function saveEditedCopy(int $rotate = 0, bool $flipH = false, bool $flipV = false, int $brightness = 0, int $contrast = 0, int $quality = 85, bool $greyscale = false, int $blur = 0, int $sharpen = 0): void
    {
        abort_unless(auth()->check(), 403);

        if (! $this->previewFile) {
            return;
        }

        $storagePath = $this->resolveSecurePath($this->previewFile['path']);
        if (! $storagePath) {
            return;
        }

        $sourcePath = Storage::disk('public')->path($storagePath);

        if (! file_exists($sourcePath)) {
            session()->flash('error', 'Source file not found.');

            return;
        }

        try {
            $image = $this->applyImageEdits($sourcePath, $rotate, $flipH, $flipV, $brightness, $contrast, $quality, $greyscale, $blur, $sharpen);

            // Build output path
            $ext = strtolower(pathinfo($storagePath, PATHINFO_EXTENSION));
            $base = preg_replace('/_edited_[a-z0-9]+$/', '', pathinfo($storagePath, PATHINFO_FILENAME));
            $newName = $base.'_edited_'.Str::random(8).'.'.$ext;
            $newPath = dirname($storagePath).'/'.$newName;
            $fullNew = Storage::disk('public')->path($newPath);

            $image->save($fullNew, max(10, min(100, $quality)));

            $this->refreshPreviewAfterSave($newPath, $fullNew, $newName);
            session()->flash('message', 'Image saved as copy.');

        } catch (\Exception $e) {
            logger()->error('Image edit save failed', ['error' => $e->getMessage()]);
            session()->flash('error', 'Save failed: '.$e->getMessage());
        }
    }

    /**
     * Apply all image edits (resize, rotate, flip, adjustments) and return the image instance.
     */
    private function applyImageEdits(string $fullPath, int $rotate, bool $flipH, bool $flipV, int $brightness, int $contrast, int $quality, bool $greyscale, int $blur, int $sharpen): \Intervention\Image\Interfaces\ImageInterface
    {
        $manager = new ImageManager(new Driver);
        $image = $manager->read($fullPath);

        // Resize
        $origW = $image->width();
        $origH = $image->height();
        $w = max(1, min(10000, (int) ($this->editResizeWidth ?: $origW)));
        $h = max(1, min(10000, (int) ($this->editResizeHeight ?: $origH)));
        if ($w !== $origW || $h !== $origH) {
            $this->editKeepAspect
                ? $image->scale(width: $w)
                : $image->resize($w, $h);
        }

        // Rotate (Intervention v3: positive = counter-clockwise; we store clockwise)
        $rotate = $rotate % 360;
        if ($rotate) {
            $image->rotate(-$rotate);
        }

        // Flip
        if ($flipH) {
            $image->flip();
        }
        if ($flipV) {
            $image->flop();
        }

        // Adjustments (clamped to safe ranges)
        $brightness = max(-100, min(100, $brightness));
        $contrast = max(-100, min(100, $contrast));
        $blur = max(0, min(100, $blur));
        $sharpen = max(0, min(100, $sharpen));

        if ($brightness !== 0) {
            $image->brightness($brightness);
        }
        if ($contrast !== 0) {
            $image->contrast($contrast);
        }
        if ($greyscale) {
            $image->greyscale();
        }
        if ($blur > 0) {
            $image->blur($blur);
        }
        if ($sharpen > 0) {
            $image->sharpen($sharpen);
        }

        return $image;
    }

    /**
     * Sanitize and validate a storage path, returning null (with flash) on failure.
     */
    private function resolveSecurePath(string $path): ?string
    {
        $safe = $this->sanitizePath($path);

        if (! str_starts_with($safe, 'uploads/')) {
            session()->flash('error', 'Invalid file path.');

            return null;
        }

        return $safe;
    }

    /**
     * After saving an image, regenerate thumbnail, reload dimensions, and refresh preview state.
     */
    private function refreshPreviewAfterSave(string $storagePath, string $fullPath, ?string $newName = null): void
    {
        $this->createThumbnail($storagePath);

        $info = @getimagesize($fullPath);
        $this->originalDimensions = $info ? ['width' => $info[0], 'height' => $info[1]] : [];
        $this->editResizeWidth = $info[0] ?? null;
        $this->editResizeHeight = $info[1] ?? null;

        $savedAt = time();
        $urlWithCacheBust = Storage::disk('public')->url($storagePath).'?t='.$savedAt;

        if ($newName) {
            // Save-as-copy: replace the entire preview file entry
            $this->previewFile = [
                'name' => $newName,
                'path' => $storagePath,
                'url' => $urlWithCacheBust,
                'size' => filesize($fullPath),
                'type' => 'image',
                'modified' => $savedAt,
                'thumbnail' => $urlWithCacheBust,
            ];
        } else {
            // Overwrite: merge into existing preview
            $this->previewFile = array_merge($this->previewFile, [
                'url' => $urlWithCacheBust,
                'size' => filesize($fullPath),
                'modified' => $savedAt,
                'thumbnail' => $urlWithCacheBust,
            ]);
        }

        $this->editVersion++;
        $this->isAiGeneratedPreview = false;
        $this->loadFiles();
    }

    protected function resetEdits(): void
    {
        $this->editResizeWidth = null;
        $this->editResizeHeight = null;
        $this->editKeepAspect = true;
        $this->editVersion = 0;
        $this->originalDimensions = [];
    }

    // ────────────────────────────────────────────────────────────────────────────

    public function render()
    {
        return view('file-manager::livewire.file-manager');
    }
}
