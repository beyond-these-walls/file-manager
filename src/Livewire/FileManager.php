<?php

namespace BeyondTheseWalls\FileManager\Livewire;

use BeyondTheseWalls\FileManager\Services\CloudflareImageService;
use BeyondTheseWalls\FileManager\Services\HuggingFaceImageService;
use BeyondTheseWalls\FileManager\Services\PicsartImageService;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use League\Flysystem\DirectoryAttributes;
use League\Flysystem\FileAttributes;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;

class FileManager extends Component
{
    use WithFileUploads;

    public bool $isOpen = false;

    public string $currentPath = '';

    public string $rootPath = '';

    public array $files = [];

    public array $folders = [];

    public $uploadedFiles = [];

    public $selectedFile = null;

    public string $viewMode = 'grid';

    public string $searchTerm = '';

    public bool $showCreateFolder = false;

    public string $newFolderName = '';

    public string $mediaType = 'all';

    public bool $showDeleteModal = false;

    public $fileToDelete = null;

    public bool $deletingPreviewFile = false;

    // Multi-select
    public array $selectedFiles = [];

    public bool $showBulkDeleteModal = false;

    // Rename file
    public ?array $renamingFile = null;

    public string $renameValue = '';

    // Rename folder
    public ?array $renamingFolder = null;

    public string $renameFolderValue = '';

    // Delete folder
    public ?array $folderToDelete = null;

    public bool $showFolderDeleteModal = false;

    // Move
    public bool $showMoveModal = false;

    public array $filesToMove = [];

    public ?string $moveTargetFolder = null;

    public array $availableFolders = [];

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

    public string $lastAiPrompt = '';

    public string $lastAiProvider = 'cloudflare';

    public string $lastAiImageSize = '1024x1024';

    public bool $isAiGeneratedPreview = false;

    public array $notification = [];

    // Image editor state
    public ?array $previewFile = null;

    public ?int $editResizeWidth = null;

    public ?int $editResizeHeight = null;

    public bool $editKeepAspect = true;

    public int $editVersion = 0;

    public array $originalDimensions = [];

    // Default folder mapping for different media types
    protected array $mediaTypeFolders = [
        'image' => 'images',
        'media' => 'videos',
        'file'  => 'files',
        'all'   => '',
    ];

    // ── Config helpers ────────────────────────────────────────────────────────

    protected function disk(): Filesystem
    {
        return Storage::disk(config('file-manager.disk', 'public'));
    }

    protected function basePath(string $suffix = ''): string
    {
        $base = rtrim(config('file-manager.base_path', 'uploads'), '/');

        return $suffix === '' ? $base : $base.'/'.ltrim($suffix, '/');
    }

    protected function thumbnailStoragePath(string $filename): string
    {
        return $this->basePath('thumbnails/'.$filename);
    }

    private function allowedFileTypes(): array
    {
        return config('file-manager.allowed_file_types', [
            'image' => [
                'extensions' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'],
                'mimes'      => ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/bmp'],
                'max_size'   => 10240,
            ],
            'media' => [
                'extensions' => ['mp4', 'avi', 'mov', 'wmv', 'flv', 'mkv', 'webm', 'mp3', 'wav', 'ogg', 'flac', 'aac'],
                'mimes'      => [
                    'video/mp4', 'video/avi', 'video/quicktime', 'video/x-ms-wmv',
                    'video/x-flv', 'video/x-matroska', 'video/webm',
                    'audio/mpeg', 'audio/wav', 'audio/ogg', 'audio/flac', 'audio/aac',
                ],
                'max_size'   => 102400,
            ],
            'file' => [
                'extensions' => ['pdf', 'doc', 'docx', 'txt', 'rtf', 'odt', 'xls', 'xlsx', 'csv', 'ppt', 'pptx'],
                'mimes'      => [
                    'application/pdf', 'application/msword',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'text/plain', 'application/rtf',
                    'application/vnd.oasis.opendocument.text',
                    'application/vnd.ms-excel',
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    'text/csv', 'application/vnd.ms-powerpoint',
                    'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                ],
                'max_size'   => 51200,
            ],
        ]);
    }

    private function dangerousExtensions(): array
    {
        return config('file-manager.dangerous_extensions', [
            'php', 'php3', 'php4', 'php5', 'phtml', 'asp', 'aspx', 'jsp', 'jspx',
            'exe', 'bat', 'cmd', 'com', 'scr', 'vbs', 'vbe', 'js', 'jar',
            'sh', 'bash', 'pl', 'py', 'rb', 'cgi', 'htaccess', 'htpasswd',
        ]);
    }

    // ── Authorization ─────────────────────────────────────────────────────────

    protected function checkAccess(): void
    {
        abort_unless(auth()->check(), 403);

        $gate = config('file-manager.auth_gate');
        if ($gate && ! Gate::check($gate)) {
            abort(403);
        }
    }

    private function notify(string $message, string $type = 'success'): void
    {
        $this->notification = ['message' => $message, 'type' => $type];
    }

    // ── Lifecycle ─────────────────────────────────────────────────────────────

    public function mount(): void
    {
        // Defer filesystem scan until the modal is actually opened
    }

    #[On('openFileManager')]
    public function open(string $mediaType = 'all', ?string $path = null): void
    {
        $this->isOpen    = true;
        $this->mediaType = $mediaType;
        $this->currentPath = $path ?? ($this->mediaTypeFolders[$mediaType] ?? '');
        $this->rootPath  = $this->currentPath;
        $this->loadFiles();
    }

    #[On('closeFileManager')]
    public function close(): void
    {
        $this->isOpen             = false;
        $this->selectedFile       = null;
        $this->mediaType          = 'all';
        $this->currentPath        = '';
        $this->rootPath           = '';
        $this->showDeleteModal    = false;
        $this->fileToDelete       = null;
        $this->previewFile        = null;
        $this->isAiGeneratedPreview = false;
        $this->lastAiPrompt       = '';
        $this->selectedFiles      = [];
        $this->showBulkDeleteModal = false;
        $this->currentPage        = 1;
        $this->renamingFile       = null;
        $this->renameValue        = '';
        $this->renamingFolder     = null;
        $this->renameFolderValue  = '';
        $this->folderToDelete     = null;
        $this->showFolderDeleteModal = false;
        $this->showMoveModal      = false;
        $this->filesToMove        = [];
        $this->moveTargetFolder   = null;
        $this->files              = [];
        $this->folders            = [];
        $this->showAiGenerator    = false;
        $this->aiGenerating       = false;
        $this->aiError            = '';
        $this->aiPrompt           = '';
        $this->notification       = [];
        $this->resetEdits();
    }

    // ── File listing ──────────────────────────────────────────────────────────

    protected function loadFiles(): void
    {
        $path        = $this->basePath(trim($this->currentPath, '/'));
        $searchLower = $this->searchTerm ? strtolower($this->searchTerm) : null;
        $isSearching = $searchLower !== null;

        $rawFiles   = [];
        $rawFolders = [];

        // Deep scan when searching so results cross subdirectory boundaries.
        foreach ($this->disk()->listContents($path, $isSearching) as $item) {
            if ($item instanceof FileAttributes) {
                $rawFiles[] = $item;
            } elseif ($item instanceof DirectoryAttributes && ! $isSearching) {
                $rawFolders[] = $item;
            }
        }

        usort($rawFiles, fn (FileAttributes $a, FileAttributes $b)
            => ($b->lastModified() ?? 0) <=> ($a->lastModified() ?? 0));

        $filteredFiles = collect($rawFiles)->map(function (FileAttributes $item) use ($searchLower, $path, $isSearching) {
            $filename = basename($item->path());

            if ($searchLower && ! str_contains(strtolower($filename), $searchLower)) {
                return null;
            }

            $fileType = $this->getFileType($filename);

            if ($this->mediaType !== 'all' && ! $this->isFileTypeMatch($fileType, $this->mediaType)) {
                return null;
            }

            $modified  = $item->lastModified() ?? 0;
            $size      = $item->fileSize() ?? 0;
            $filePath  = $item->path();

            // Show the relative subdirectory as context during a recursive search.
            $subfolder = '';
            if ($isSearching) {
                $dir       = dirname($filePath);
                $subfolder = $dir !== $path ? ltrim(substr($dir, strlen($path)), '/') : '';
            }

            return [
                'name'      => $filename,
                'path'      => $filePath,
                'url'       => $this->disk()->url($filePath).'?t='.$modified,
                'size'      => $size,
                'type'      => $fileType,
                'modified'  => $modified,
                'thumbnail' => $this->getThumbnailUrl($filePath, $modified ?: null),
                'subfolder' => $subfolder,
            ];
        })->filter()->values();

        $this->totalFiles = $filteredFiles->count();
        $maxPage          = max(1, (int) ceil($this->totalFiles / $this->perPage));
        if ($this->currentPage > $maxPage) {
            $this->currentPage = $maxPage;
        }
        $this->files = $filteredFiles
            ->slice(($this->currentPage - 1) * $this->perPage, $this->perPage)
            ->values()
            ->toArray();

        // Folders are not meaningful during a recursive search.
        if ($isSearching) {
            $this->folders = [];
            return;
        }

        $this->folders = collect($rawFolders)
            ->sortBy(fn (DirectoryAttributes $d) => basename($d->path()))
            ->map(fn (DirectoryAttributes $item) => [
                'name'      => basename($item->path()),
                'path'      => $item->path(),
                'fileCount' => count($this->disk()->files($item->path())),
            ])
            ->values()
            ->toArray();
    }

    public function navigateToFolder(string $folderPath): void
    {
        $base    = $this->basePath().'/';
        $stripped = str_starts_with($folderPath, $base)
            ? substr($folderPath, strlen($base))
            : $folderPath;

        $this->currentPath  = $this->sanitizePath($stripped);
        $this->currentPage  = 1;
        $this->selectedFiles = [];
        $this->loadFiles();
    }

    public function goBack(): void
    {
        if ($this->currentPath && $this->currentPath !== $this->rootPath) {
            $pathParts = explode('/', trim($this->currentPath, '/'));
            array_pop($pathParts);
            $parent = $this->sanitizePath(implode('/', $pathParts));
            $root   = $this->sanitizePath($this->rootPath);

            if ($root && ! str_starts_with($parent.'/', $root.'/') && $parent !== $root) {
                $this->currentPath = $root;
            } else {
                $this->currentPath = $parent;
            }

            $this->currentPage   = 1;
            $this->selectedFiles = [];
            $this->loadFiles();
        }
    }

    public function goToMediaFolder(string $mediaType): void
    {
        if (isset($this->mediaTypeFolders[$mediaType])) {
            $this->mediaType    = $mediaType;
            $this->currentPath  = $this->mediaTypeFolders[$mediaType];
            $this->currentPage  = 1;
            $this->selectedFiles = [];
            $this->loadFiles();
        }
    }

    public function selectFile(array $file): void
    {
        $this->selectedFile = $file;
        $this->dispatch('fileSelected', $file);
        $this->close();
    }

    // ── Upload ────────────────────────────────────────────────────────────────

    public function updatedUploadedFiles(): void
    {
        $this->checkAccess();

        $rateLimitKey = 'file-upload:'.auth()->id();
        $limit        = config('file-manager.rate_limits.upload', 20);

        if (RateLimiter::tooManyAttempts($rateLimitKey, $limit)) {
            $seconds = RateLimiter::availableIn($rateLimitKey);
            $this->notify("Too many uploads. Please wait {$seconds}s before uploading more files.", 'error');
            $this->uploadedFiles = [];

            return;
        }

        $uploadedCount = 0;

        foreach ($this->uploadedFiles as $file) {
            RateLimiter::hit($rateLimitKey, 60);

            try {
                $this->validateFile($file);
                $this->performSecurityChecks($file);

                $originalName        = $file->getClientOriginalName();
                $extension           = strtolower($file->getClientOriginalExtension());
                $nameWithoutExtension = pathinfo($originalName, PATHINFO_FILENAME);

                $nameWithoutExtension = preg_replace('/\s+/', '-', $nameWithoutExtension);
                $nameWithoutExtension = preg_replace('/[^a-zA-Z0-9\-_]/', '', $nameWithoutExtension);
                $nameWithoutExtension = trim($nameWithoutExtension, '-');
                if (empty($nameWithoutExtension)) {
                    $nameWithoutExtension = 'file';
                }

                $filename = $nameWithoutExtension.'_'.now()->format('YmdHis').'_'.Str::random(6).'.'.$extension;
                $diskName = config('file-manager.disk', 'public');
                $subPath  = $this->basePath(trim($this->currentPath, '/'));

                $storedPath = $file->storeAs($subPath, $filename, $diskName);

                if ($this->isImageFile($file)) {
                    $this->createThumbnail($storedPath);
                }

                $uploadedCount++;

                logger()->info('File uploaded successfully', [
                    'original_name' => $originalName,
                    'stored_name'   => $filename,
                    'size'          => $file->getSize(),
                    'mime'          => $file->getMimeType(),
                ]);

            } catch (ValidationException $e) {
                $this->notify('Upload failed: '.$e->getMessage(), 'error');

                continue;
            } catch (\Exception $e) {
                $this->notify('Upload failed: '.$e->getMessage(), 'error');
                logger()->error('File upload failed', [
                    'file'  => $file->getClientOriginalName(),
                    'error' => $e->getMessage(),
                ]);

                continue;
            }
        }

        $this->uploadedFiles = [];
        $this->loadFiles();

        if ($uploadedCount > 0) {
            $this->notify($uploadedCount === 1
                ? 'File uploaded successfully!'
                : "{$uploadedCount} files uploaded successfully!");
        }
    }

    protected function validateFile($file): void
    {
        $extension    = strtolower($file->getClientOriginalExtension());
        $mimeType     = $file->getMimeType();
        $sizeKb       = $file->getSize() / 1024;
        $allowedTypes = $this->allowedFileTypes();

        if (in_array($extension, $this->dangerousExtensions())) {
            throw new \Exception("File type '{$extension}' is not allowed for security reasons.");
        }

        if ($this->mediaType !== 'all') {
            $typeConfig = $allowedTypes[$this->mediaType] ?? null;

            if (! $typeConfig) {
                throw new \Exception('Invalid media type.');
            }

            if (! in_array($extension, $typeConfig['extensions'])) {
                throw new \Exception("File extension '{$extension}' is not allowed for {$this->mediaType} files.");
            }

            if (! in_array($mimeType, $typeConfig['mimes'])) {
                throw new \Exception("File type '{$mimeType}' is not allowed for {$this->mediaType} files.");
            }

            if ($sizeKb > $typeConfig['max_size']) {
                $maxMb = round($typeConfig['max_size'] / 1024, 1);
                throw new \Exception("File size exceeds maximum allowed size of {$maxMb}MB for {$this->mediaType} files.");
            }

            return;
        }

        // 'all' mode: find the category by extension and cross-check MIME
        $matchedType = null;
        foreach ($allowedTypes as $type => $typeConfig) {
            if (in_array($extension, $typeConfig['extensions'])) {
                $matchedType = $type;
                break;
            }
        }

        if ($matchedType === null) {
            throw new \Exception("File extension '{$extension}' is not in the allowed list.");
        }

        if (! in_array($mimeType, $allowedTypes[$matchedType]['mimes'])) {
            throw new \Exception("File MIME type does not match the file extension '{$extension}'.");
        }

        $maxSize = $allowedTypes[$matchedType]['max_size'];
        if ($sizeKb > $maxSize) {
            $maxMb = round($maxSize / 1024, 1);
            throw new \Exception("File size exceeds maximum allowed size of {$maxMb}MB.");
        }
    }

    protected function performSecurityChecks($file): void
    {
        $filename  = $file->getClientOriginalName();
        $dangerous = $this->dangerousExtensions();

        if (preg_match('/\.('.implode('|', $dangerous).')\./i', $filename)) {
            throw new \Exception('Files with multiple extensions are not allowed.');
        }

        $suspiciousPatterns = [
            '/\.(php|asp|jsp|exe|bat|cmd|sh)$/i',
            '/^\.ht/i',
            '/\.\./i',
            '/[<>:"|?*]/i',
        ];

        foreach ($suspiciousPatterns as $pattern) {
            if (preg_match($pattern, $filename)) {
                throw new \Exception('Filename contains suspicious patterns.');
            }
        }

        if ($this->isImageFile($file)) {
            $this->validateImageFile($file);
        }
    }

    protected function validateImageFile($file): void
    {
        $imageInfo = getimagesize($file->getPathname());

        if ($imageInfo === false) {
            throw new \Exception('File is not a valid image.');
        }

        if ($imageInfo[0] > 10000 || $imageInfo[1] > 10000) {
            throw new \Exception('Image dimensions are too large (max 10000×10000).');
        }
    }

    // ── Delete (single file) ──────────────────────────────────────────────────

    public function deleteFile(): void
    {
        $this->checkAccess();

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
            $this->fileToDelete   = null;

            return;
        }

        $this->disk()->delete($safePath);
        $thumbPath = $this->thumbnailStoragePath(basename($safePath));
        if ($this->disk()->exists($thumbPath)) {
            $this->disk()->delete($thumbPath);
        }

        $this->loadFiles();
        $this->showDeleteModal = false;
        $this->fileToDelete   = null;
        $this->notify('File deleted successfully!');
    }

    public function confirmDelete(array $file): void
    {
        $this->fileToDelete    = $file;
        $this->showDeleteModal = true;
    }

    public function confirmDeletePreview(): void
    {
        if (! $this->previewFile) {
            return;
        }
        $this->deletingPreviewFile = true;
        $this->showDeleteModal     = true;
    }

    public function cancelDelete(): void
    {
        $this->showDeleteModal     = false;
        $this->fileToDelete        = null;
        $this->deletingPreviewFile = false;
    }

    // ── Create folder ─────────────────────────────────────────────────────────

    public function createFolder(): void
    {
        $this->checkAccess();

        $this->validate([
            'newFolderName' => 'required|string|max:255|regex:/^[a-zA-Z0-9\-_\s]+$/',
        ]);

        $safeCurrent = $this->sanitizePath($this->currentPath);
        $folderPath  = $this->basePath($safeCurrent.'/'.$this->newFolderName);

        if (! $this->disk()->exists($folderPath)) {
            $this->disk()->makeDirectory($folderPath);
            $this->loadFiles();
            $this->notify('Folder created successfully!');
        } else {
            $this->notify('Folder already exists!', 'error');
        }

        $this->showCreateFolder = false;
        $this->newFolderName    = '';
    }

    public function cancelCreateFolder(): void
    {
        $this->showCreateFolder = false;
        $this->newFolderName    = '';
    }

    // ── Search & pagination ───────────────────────────────────────────────────

    public function updatedSearchTerm(): void
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

    // ── Multi-select ──────────────────────────────────────────────────────────

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
        $this->checkAccess();

        $deleted = 0;
        foreach ($this->selectedFiles as $path) {
            $safePath = $this->resolveSecurePath($path);
            if ($safePath && $this->disk()->exists($safePath)) {
                $this->disk()->delete($safePath);
                $thumbPath = $this->thumbnailStoragePath(basename($safePath));
                if ($this->disk()->exists($thumbPath)) {
                    $this->disk()->delete($thumbPath);
                }
                $deleted++;
            }
        }

        $this->selectedFiles      = [];
        $this->showBulkDeleteModal = false;
        $this->loadFiles();

        if ($deleted > 0) {
            $this->notify("{$deleted} file(s) deleted successfully!");
        }
    }

    // ── Rename file ───────────────────────────────────────────────────────────

    public function startRename(array $file): void
    {
        $this->renamingFile = $file;
        $this->renameValue  = pathinfo($file['name'], PATHINFO_FILENAME);
    }

    public function cancelRename(): void
    {
        $this->renamingFile = null;
        $this->renameValue  = '';
    }

    public function applyRename(): void
    {
        $this->checkAccess();

        if (! $this->renamingFile) {
            return;
        }

        $this->validate([
            'renameValue' => 'required|string|max:200|regex:/^[a-zA-Z0-9\-_\s]+$/',
        ]);

        $oldPath = $this->resolveSecurePath($this->renamingFile['path']);
        if (! $oldPath) {
            return;
        }

        $extension  = strtolower(pathinfo($oldPath, PATHINFO_EXTENSION));
        $newBasename = trim($this->renameValue);
        $newBasename = preg_replace('/\s+/', '-', $newBasename);
        $newBasename = preg_replace('/[^a-zA-Z0-9\-_]/', '', $newBasename);

        if (empty($newBasename)) {
            $this->notify('Invalid filename.', 'error');

            return;
        }

        $newName = $newBasename.($extension ? '.'.$extension : '');
        $newPath = dirname($oldPath).'/'.$newName;

        if ($oldPath === $newPath) {
            $this->cancelRename();

            return;
        }

        if ($this->disk()->exists($newPath)) {
            $this->notify("A file named '{$newName}' already exists.", 'error');

            return;
        }

        $this->disk()->move($oldPath, $newPath);

        $oldThumb = $this->thumbnailStoragePath(basename($oldPath));
        $newThumb = $this->thumbnailStoragePath($newName);
        if ($this->disk()->exists($oldThumb)) {
            $this->disk()->move($oldThumb, $newThumb);
        }

        $this->cancelRename();
        $this->loadFiles();
        $this->notify("File renamed to '{$newName}'.");
    }

    // ── Rename folder ─────────────────────────────────────────────────────────

    public function startRenameFolder(array $folder): void
    {
        $this->renamingFolder     = $folder;
        $this->renameFolderValue  = $folder['name'];
    }

    public function cancelRenameFolder(): void
    {
        $this->renamingFolder    = null;
        $this->renameFolderValue = '';
    }

    public function applyFolderRename(): void
    {
        $this->checkAccess();

        if (! $this->renamingFolder) {
            return;
        }

        $this->validate([
            'renameFolderValue' => 'required|string|max:200|regex:/^[a-zA-Z0-9\-_\s]+$/',
        ]);

        $oldPath   = $this->sanitizePath($this->renamingFolder['path']);
        $basePrefix = $this->basePath().'/';

        if (! str_starts_with($oldPath, $basePrefix)) {
            $this->notify('Invalid folder path.', 'error');

            return;
        }

        $newName = preg_replace('/\s+/', '-', trim($this->renameFolderValue));
        $newName = preg_replace('/[^a-zA-Z0-9\-_]/', '', $newName);

        if (empty($newName)) {
            $this->notify('Invalid folder name.', 'error');

            return;
        }

        $parent  = dirname($oldPath);
        $newPath = $parent.'/'.$newName;

        if ($oldPath === $newPath) {
            $this->cancelRenameFolder();

            return;
        }

        if ($this->disk()->exists($newPath)) {
            $this->notify("A folder named '{$newName}' already exists.", 'error');

            return;
        }

        $oldAbsPath = $this->disk()->path($oldPath);
        $newAbsPath = $this->disk()->path($newPath);

        if (! rename($oldAbsPath, $newAbsPath)) {
            $this->notify('Failed to rename folder.', 'error');

            return;
        }

        // Update currentPath if it was inside the renamed folder
        $base    = $this->basePath();
        $oldRel  = ltrim(str_replace($base.'/', '', $oldPath), '/');
        $newRel  = ltrim(str_replace($base.'/', '', $newPath), '/');
        if ($this->currentPath === $oldRel || str_starts_with($this->currentPath, $oldRel.'/')) {
            $this->currentPath = $newRel.substr($this->currentPath, strlen($oldRel));
        }

        $this->cancelRenameFolder();
        $this->loadFiles();
        $this->notify("Folder renamed to '{$newName}'.");
    }

    // ── Delete folder ─────────────────────────────────────────────────────────

    public function confirmDeleteFolder(array $folder): void
    {
        $this->folderToDelete       = $folder;
        $this->showFolderDeleteModal = true;
    }

    public function cancelDeleteFolder(): void
    {
        $this->folderToDelete       = null;
        $this->showFolderDeleteModal = false;
    }

    public function deleteFolder(): void
    {
        $this->checkAccess();

        if (! $this->folderToDelete) {
            return;
        }

        $safePath   = $this->sanitizePath($this->folderToDelete['path']);
        $folderName = $this->folderToDelete['name'];
        $base       = $this->basePath();

        if (! str_starts_with($safePath, $base.'/') || $safePath === $base) {
            $this->cancelDeleteFolder();

            return;
        }

        $this->disk()->deleteDirectory($safePath);

        $relPath = ltrim(str_replace($base.'/', '', $safePath), '/');
        if ($this->currentPath === $relPath || str_starts_with($this->currentPath, $relPath.'/')) {
            $this->currentPath = $this->rootPath;
        }

        $this->folderToDelete       = null;
        $this->showFolderDeleteModal = false;
        $this->loadFiles();
        $this->notify("Folder '{$folderName}' deleted.");
    }

    // ── Move ──────────────────────────────────────────────────────────────────

    public function openMoveModal(?array $file = null): void
    {
        $this->filesToMove = $file ? [$file['path']] : $this->selectedFiles;

        if (empty($this->filesToMove)) {
            return;
        }

        $this->moveTargetFolder = null;
        $this->loadAvailableFolders();
        $this->showMoveModal = true;
    }

    public function closeMoveModal(): void
    {
        $this->showMoveModal    = false;
        $this->filesToMove      = [];
        $this->moveTargetFolder = null;
    }

    protected function loadAvailableFolders(): void
    {
        $base    = $this->basePath();
        $allDirs = $this->disk()->allDirectories($base);

        $this->availableFolders = collect($allDirs)
            ->map(function (string $dir) use ($base) {
                $relative = ltrim(str_replace($base, '', $dir), '/');
                $depth    = $relative ? substr_count($relative, '/') + 1 : 0;

                return [
                    'path'         => $relative,
                    'storage_path' => $dir,
                    'name'         => basename($dir),
                    'depth'        => $depth,
                ];
            })
            ->prepend([
                'path'         => '',
                'storage_path' => $base,
                'name'         => basename($base).' (root)',
                'depth'        => 0,
            ])
            ->values()
            ->toArray();
    }

    public function executeMove(): void
    {
        $this->checkAccess();

        if (empty($this->filesToMove) || $this->moveTargetFolder === null) {
            $this->closeMoveModal();

            return;
        }

        $targetDir = $this->basePath(ltrim($this->sanitizePath($this->moveTargetFolder), '/'));
        $targetDir = rtrim($targetDir, '/');

        if (! $this->disk()->exists($targetDir)) {
            $this->notify('Target folder does not exist.', 'error');

            return;
        }

        $moved = 0;
        foreach ($this->filesToMove as $filePath) {
            $safePath = $this->resolveSecurePath($filePath);
            if (! $safePath || ! $this->disk()->exists($safePath)) {
                continue;
            }

            $filename = basename($safePath);
            $newPath  = $targetDir.'/'.$filename;

            if ($safePath === $newPath) {
                continue;
            }

            if ($this->disk()->exists($newPath)) {
                $ext      = pathinfo($filename, PATHINFO_EXTENSION);
                $basename = pathinfo($filename, PATHINFO_FILENAME);
                $newPath  = $targetDir.'/'.$basename.'_'.Str::random(4).($ext ? '.'.$ext : '');
            }

            $this->disk()->move($safePath, $newPath);

            $oldThumb = $this->thumbnailStoragePath($filename);
            if ($this->disk()->exists($oldThumb)) {
                $this->disk()->move($oldThumb, $this->thumbnailStoragePath(basename($newPath)));
            }

            $moved++;
        }

        $this->selectedFiles = array_values(array_diff($this->selectedFiles, $this->filesToMove));
        $this->closeMoveModal();
        $this->loadFiles();

        if ($moved > 0) {
            $this->notify("{$moved} file(s) moved successfully.");
        }
    }

    public function moveFile(string $filePath, string $targetFolderStoragePath): void
    {
        $this->checkAccess();

        $safePath = $this->resolveSecurePath($filePath);
        if (! $safePath) {
            return;
        }

        $base      = $this->basePath();
        $targetDir = $this->sanitizePath($targetFolderStoragePath);

        if (! str_starts_with($targetDir, $base.'/') && $targetDir !== $base) {
            $targetDir = $this->basePath($this->sanitizePath($targetFolderStoragePath));
        }

        $targetDir = rtrim($targetDir, '/');

        if (! $this->disk()->exists($targetDir)) {
            return;
        }

        $filename = basename($safePath);
        $newPath  = $targetDir.'/'.$filename;

        if ($safePath === $newPath) {
            return;
        }

        if ($this->disk()->exists($newPath)) {
            $ext      = pathinfo($filename, PATHINFO_EXTENSION);
            $basename = pathinfo($filename, PATHINFO_FILENAME);
            $newPath  = $targetDir.'/'.$basename.'_'.Str::random(4).($ext ? '.'.$ext : '');
        }

        $this->disk()->move($safePath, $newPath);

        $oldThumb = $this->thumbnailStoragePath($filename);
        if ($this->disk()->exists($oldThumb)) {
            $this->disk()->move($oldThumb, $this->thumbnailStoragePath(basename($newPath)));
        }

        $this->selectedFiles = array_values(array_diff($this->selectedFiles, [$filePath]));
        $this->loadFiles();
        $this->notify("'{$filename}' moved.");
    }

    // ── File type helpers ─────────────────────────────────────────────────────

    private function isFileTypeMatch(string $fileType, string $mediaType): bool
    {
        return match ($mediaType) {
            'image' => $fileType === 'image',
            'media' => in_array($fileType, ['video', 'audio']),
            'file'  => in_array($fileType, ['pdf', 'word', 'excel', 'powerpoint', 'text', 'document']),
            default => true,
        };
    }

    private function getFileType(string $filePath): string
    {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        return match ($extension) {
            'jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'             => 'image',
            'pdf'                                                    => 'pdf',
            'doc', 'docx'                                            => 'word',
            'txt', 'rtf'                                             => 'text',
            'xls', 'xlsx', 'csv'                                     => 'excel',
            'ppt', 'pptx'                                            => 'powerpoint',
            'mp4', 'avi', 'mov', 'wmv', 'flv', 'mkv', 'webm'       => 'video',
            'mp3', 'wav', 'ogg', 'flac', 'aac'                      => 'audio',
            default                                                  => 'file',
        };
    }

    // ── Thumbnail helpers ─────────────────────────────────────────────────────

    private function getThumbnailUrl(string $filePath, ?int $modified = null): ?string
    {
        if ($this->getFileType($filePath) !== 'image') {
            return null;
        }

        $ts        = $modified ?? $this->disk()->lastModified($filePath);
        $thumbPath = $this->thumbnailStoragePath(basename($filePath));

        if ($this->disk()->exists($thumbPath)) {
            return $this->disk()->url($thumbPath).'?t='.$ts;
        }

        return $this->disk()->url($filePath).'?t='.$ts;
    }

    private function createThumbnail(string $filePath): void
    {
        try {
            $fullPath      = $this->disk()->path($filePath);
            $thumbPath     = $this->thumbnailStoragePath(basename($filePath));
            $thumbFullPath = $this->disk()->path($thumbPath);
            $thumbDir      = dirname($thumbFullPath);

            if (! is_dir($thumbDir)) {
                mkdir($thumbDir, 0755, true);
            }

            $w = config('file-manager.thumbnail.width', 150);
            $h = config('file-manager.thumbnail.height', 150);

            $manager = new ImageManager(new Driver);
            $image   = $manager->read($fullPath);
            $image->cover($w, $h);
            $image->save($thumbFullPath);

        } catch (\Exception $e) {
            logger()->error('Thumbnail creation failed: '.$e->getMessage());
        }
    }

    private function isImageFile($file): bool
    {
        return in_array(
            strtolower($file->getClientOriginalExtension()),
            ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp']
        );
    }

    // ── AI image generation ───────────────────────────────────────────────────

    public function toggleAiGenerator(): void
    {
        $this->showAiGenerator = ! $this->showAiGenerator;
        $this->aiError         = '';
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
                $this->aiQuota = [
                    'label' => 'Free tier: 32–300 images/day depending on size',
                    'pct'   => null,
                ];
            } elseif ($this->aiProvider === 'picsart') {
                $this->aiQuota = [
                    'label' => 'Picsart Gen AI — free tier: 100 credits/day',
                    'pct'   => null,
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
                    $plan          = $response->json('plan') ?? 'free';
                    $this->aiQuota = [
                        'label' => 'Plan: '.ucfirst($plan).' — rate limited',
                        'pct'   => null,
                    ];
                }
            }
        } catch (\Exception) {
            // Non-critical — silently ignore
        }
    }

    public function generateAiImage(): void
    {
        $this->checkAccess();

        $this->validate(['aiPrompt' => 'required|string|min:3|max:500']);

        $rateLimitKey = 'ai-generate:'.auth()->id();
        $limit        = config('file-manager.rate_limits.ai_generate', 10);

        if (RateLimiter::tooManyAttempts($rateLimitKey, $limit)) {
            $this->aiError = 'Too many requests. Please wait before generating more images.';

            return;
        }
        RateLimiter::hit($rateLimitKey, 60);

        $this->aiGenerating = true;
        $this->aiError      = '';

        try {
            $file = $this->runAiGeneration($this->aiProvider, $this->aiPrompt, $this->aiImageSize);

            $this->lastAiPrompt     = $this->aiPrompt;
            $this->lastAiProvider   = $this->aiProvider;
            $this->lastAiImageSize  = $this->aiImageSize;
            $this->aiPrompt         = '';
            $this->showAiGenerator  = false;
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
        $this->checkAccess();

        if (! $this->lastAiPrompt || ! $this->previewFile || ! $this->isAiGeneratedPreview) {
            return;
        }

        $rateLimitKey = 'ai-generate:'.auth()->id();
        $limit        = config('file-manager.rate_limits.ai_generate', 10);

        if (RateLimiter::tooManyAttempts($rateLimitKey, $limit)) {
            $this->aiError = 'Too many requests. Please wait before generating more images.';

            return;
        }
        RateLimiter::hit($rateLimitKey, 60);

        $this->aiGenerating = true;
        $this->aiError      = '';

        // Delete the existing preview file so they don't accumulate
        $oldPath = $this->sanitizePath($this->previewFile['path']);
        if (str_starts_with($oldPath, $this->basePath().'/') && $this->disk()->exists($oldPath)) {
            $this->disk()->delete($oldPath);
            $thumbPath = $this->thumbnailStoragePath(basename($oldPath));
            if ($this->disk()->exists($thumbPath)) {
                $this->disk()->delete($thumbPath);
            }
        }

        try {
            $file = $this->runAiGeneration($this->lastAiProvider, $this->lastAiPrompt, $this->lastAiImageSize);

            $this->previewImage($file);
            $this->isAiGeneratedPreview = true;
            $this->notify('Image regenerated.');
        } catch (\RuntimeException $e) {
            $this->aiError   = $e->getMessage();
            $this->previewFile = null;
            $this->loadFiles();
        } finally {
            $this->aiGenerating = false;
        }
    }

    private function runAiGeneration(string $provider, string $prompt, string $imageSize): array
    {
        [$width, $height] = array_map('intval', explode('x', $imageSize));

        $service = match ($provider) {
            'huggingface' => app(HuggingFaceImageService::class),
            'picsart'     => app(PicsartImageService::class),
            default       => app(CloudflareImageService::class),
        };

        $targetFolder = ltrim($this->currentPath, '/') ?: 'images';
        $file         = $service->generate($prompt, $targetFolder, $width, $height);

        $this->createThumbnail($file['path']);

        // Add thumbnail URL so the delete modal and preview panel can use it
        $thumbPath        = $this->thumbnailStoragePath(basename($file['path']));
        $file['thumbnail'] = $this->disk()->exists($thumbPath)
            ? $this->disk()->url($thumbPath).'?t='.time()
            : $file['url'];

        $this->loadFiles();

        return $file;
    }

    // ── Image editor ──────────────────────────────────────────────────────────

    public function previewImage(array $file): void
    {
        $this->previewFile        = $file;
        $this->isAiGeneratedPreview = false;
        $this->resetEdits();

        try {
            $fullPath = $this->disk()->path($file['path']);
            $info     = @getimagesize($fullPath);
            if ($info) {
                $this->originalDimensions = ['width' => $info[0], 'height' => $info[1]];
                $this->editResizeWidth    = $info[0];
                $this->editResizeHeight   = $info[1];
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
        $this->checkAccess();

        if (! $this->previewFile) {
            return;
        }

        $safePath = $this->resolveSecurePath($this->previewFile['path']);
        if (! $safePath) {
            return;
        }

        $this->disk()->delete($safePath);
        $thumbPath = $this->thumbnailStoragePath(basename($safePath));
        if ($this->disk()->exists($thumbPath)) {
            $this->disk()->delete($thumbPath);
        }

        $this->previewFile         = null;
        $this->showDeleteModal     = false;
        $this->deletingPreviewFile = false;
        $this->resetEdits();
        $this->loadFiles();
        $this->notify('Image deleted.');
    }

    public function insertPreviewFile(): void
    {
        if ($this->previewFile) {
            $this->dispatch('fileSelected', $this->previewFile);
            $this->close();
        }
    }

    public function applyCrop(int $x, int $y, int $width, int $height): void
    {
        $this->checkAccess();

        if (! $this->previewFile) {
            return;
        }

        $width  = max(1, min(10000, $width));
        $height = max(1, min(10000, $height));
        $x      = max(0, $x);
        $y      = max(0, $y);

        $storagePath = $this->resolveSecurePath($this->previewFile['path']);
        if (! $storagePath) {
            return;
        }

        $fullPath = $this->disk()->path($storagePath);

        if (! file_exists($fullPath)) {
            $this->notify('Source file not found.', 'error');

            return;
        }

        try {
            $manager = new ImageManager(new Driver);
            $image   = $manager->read($fullPath);

            $imgW = $image->width();
            $imgH = $image->height();

            $x      = min($x, max(0, $imgW - 1));
            $y      = min($y, max(0, $imgH - 1));
            $width  = min($width, $imgW - $x);
            $height = min($height, $imgH - $y);

            $image->crop($width, $height, $x, $y);
            $image->save($fullPath, 90);

            $this->refreshPreviewAfterSave($storagePath, $fullPath);
            $this->notify('Image cropped.');

        } catch (\Exception $e) {
            logger()->error('Image crop failed', ['error' => $e->getMessage()]);
            $this->notify('Crop failed: '.$e->getMessage(), 'error');
        }
    }

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

    public function saveOverwrite(int $rotate = 0, bool $flipH = false, bool $flipV = false, int $brightness = 0, int $contrast = 0, int $quality = 85, bool $greyscale = false, int $blur = 0, int $sharpen = 0): void
    {
        $this->checkAccess();

        if (! $this->previewFile) {
            return;
        }

        $storagePath = $this->resolveSecurePath($this->previewFile['path']);
        if (! $storagePath) {
            return;
        }

        $fullPath = $this->disk()->path($storagePath);

        if (! file_exists($fullPath)) {
            $this->notify('Source file not found.', 'error');

            return;
        }

        try {
            $image = $this->applyImageEdits($fullPath, $rotate, $flipH, $flipV, $brightness, $contrast, $quality, $greyscale, $blur, $sharpen);
            $image->save($fullPath, max(10, min(100, $quality)));

            $this->refreshPreviewAfterSave($storagePath, $fullPath);
            $this->notify('Image saved.');

        } catch (\Exception $e) {
            logger()->error('Image overwrite failed', ['error' => $e->getMessage()]);
            $this->notify('Save failed: '.$e->getMessage(), 'error');
        }
    }

    public function saveEditedCopy(int $rotate = 0, bool $flipH = false, bool $flipV = false, int $brightness = 0, int $contrast = 0, int $quality = 85, bool $greyscale = false, int $blur = 0, int $sharpen = 0): void
    {
        $this->checkAccess();

        if (! $this->previewFile) {
            return;
        }

        $storagePath = $this->resolveSecurePath($this->previewFile['path']);
        if (! $storagePath) {
            return;
        }

        $sourcePath = $this->disk()->path($storagePath);

        if (! file_exists($sourcePath)) {
            $this->notify('Source file not found.', 'error');

            return;
        }

        try {
            $image = $this->applyImageEdits($sourcePath, $rotate, $flipH, $flipV, $brightness, $contrast, $quality, $greyscale, $blur, $sharpen);

            $ext     = strtolower(pathinfo($storagePath, PATHINFO_EXTENSION));
            $base    = preg_replace('/_edited_[a-z0-9]+$/', '', pathinfo($storagePath, PATHINFO_FILENAME));
            $newName = $base.'_edited_'.Str::random(8).'.'.$ext;
            $newPath = dirname($storagePath).'/'.$newName;
            $fullNew = $this->disk()->path($newPath);

            $image->save($fullNew, max(10, min(100, $quality)));

            $this->refreshPreviewAfterSave($newPath, $fullNew, $newName);
            $this->notify('Image saved as copy.');

        } catch (\Exception $e) {
            logger()->error('Image edit save failed', ['error' => $e->getMessage()]);
            $this->notify('Save failed: '.$e->getMessage(), 'error');
        }
    }

    private function applyImageEdits(string $fullPath, int $rotate, bool $flipH, bool $flipV, int $brightness, int $contrast, int $quality, bool $greyscale, int $blur, int $sharpen): \Intervention\Image\Interfaces\ImageInterface
    {
        $manager = new ImageManager(new Driver);
        $image   = $manager->read($fullPath);

        $origW = $image->width();
        $origH = $image->height();
        $w     = max(1, min(10000, (int) ($this->editResizeWidth ?: $origW)));
        $h     = max(1, min(10000, (int) ($this->editResizeHeight ?: $origH)));
        if ($w !== $origW || $h !== $origH) {
            $this->editKeepAspect
                ? $image->scale(width: $w)
                : $image->resize($w, $h);
        }

        $rotate = $rotate % 360;
        if ($rotate) {
            $image->rotate(-$rotate);
        }

        if ($flipH) {
            $image->flip();
        }
        if ($flipV) {
            $image->flop();
        }

        $brightness = max(-100, min(100, $brightness));
        $contrast   = max(-100, min(100, $contrast));
        $blur       = max(0, min(100, $blur));
        $sharpen    = max(0, min(100, $sharpen));

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

    private function resolveSecurePath(string $path): ?string
    {
        $safe   = $this->sanitizePath($path);
        $prefix = $this->basePath().'/';

        if (! str_starts_with($safe, $prefix)) {
            $this->notify('Invalid file path.', 'error');

            return null;
        }

        return $safe;
    }

    private function refreshPreviewAfterSave(string $storagePath, string $fullPath, ?string $newName = null): void
    {
        $this->createThumbnail($storagePath);

        $info = @getimagesize($fullPath);
        $this->originalDimensions = $info ? ['width' => $info[0], 'height' => $info[1]] : [];
        $this->editResizeWidth    = $info[0] ?? null;
        $this->editResizeHeight   = $info[1] ?? null;

        $savedAt        = time();
        $urlWithBust    = $this->disk()->url($storagePath).'?t='.$savedAt;
        $thumbPath      = $this->thumbnailStoragePath(basename($storagePath));
        $thumbUrl       = $this->disk()->exists($thumbPath)
            ? $this->disk()->url($thumbPath).'?t='.$savedAt
            : $urlWithBust;

        if ($newName) {
            $this->previewFile = [
                'name'      => $newName,
                'path'      => $storagePath,
                'url'       => $urlWithBust,
                'size'      => filesize($fullPath),
                'type'      => 'image',
                'modified'  => $savedAt,
                'thumbnail' => $thumbUrl,
            ];
        } else {
            $this->previewFile = array_merge($this->previewFile, [
                'url'       => $urlWithBust,
                'size'      => filesize($fullPath),
                'modified'  => $savedAt,
                'thumbnail' => $thumbUrl,
            ]);
        }

        $this->editVersion++;
        $this->isAiGeneratedPreview = false;
        $this->loadFiles();
    }

    protected function resetEdits(): void
    {
        $this->editResizeWidth  = null;
        $this->editResizeHeight = null;
        $this->editKeepAspect   = true;
        $this->editVersion      = 0;
        $this->originalDimensions = [];
    }

    private function sanitizePath(string $path): string
    {
        $path  = str_replace('\\', '/', $path);
        $parts = array_filter(
            explode('/', $path),
            fn ($p) => $p !== '' && $p !== '.' && $p !== '..'
        );

        return implode('/', $parts);
    }

    public function render()
    {
        return view('file-manager::livewire.file-manager');
    }
}
