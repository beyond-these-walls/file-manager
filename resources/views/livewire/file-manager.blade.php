{{-- resources/views/livewire/file-manager.blade.php --}}
<div>
    @if($isOpen)
    <div class="fixed inset-0 flex items-center justify-center bg-gray-500/70 dark:bg-neutral-900/80" style="z-index: 999999;"
         x-data
         @keydown.escape.window="
            if ($wire.showDeleteModal) { $wire.cancelDelete(); }
            else if ($wire.showBulkDeleteModal) { $wire.cancelBulkDelete(); }
            else if ($wire.showMoveModal) { $wire.closeMoveModal(); }
            else if ($wire.showFolderDeleteModal) { $wire.cancelDeleteFolder(); }
            else if ($wire.renamingFile) { $wire.cancelRename(); }
            else if ($wire.renamingFolder) { $wire.cancelRenameFolder(); }
            else if ($wire.previewFile) { $wire.backToGallery(); }
            else { $wire.close(); }
         ">
        <div class="bg-white dark:bg-neutral-800 rounded-lg shadow-xl w-5/6 flex flex-col overflow-hidden" style="height: 92vh;">

            {{-- Modal header --}}
            <div class="flex items-center justify-between p-4 border-b border-gray-200 dark:border-neutral-700 flex-shrink-0">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">File Manager</h3>
                <button type="button" wire:click="close" class="text-gray-500 dark:text-neutral-400 hover:text-gray-700 dark:hover:text-neutral-200">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>

            @if($previewFile)
            {{-- ── Image editor ── --}}
            @include('file-manager::livewire.file-manager._editor')

            @else
            {{-- ── File browser ── --}}

            {{-- Toolbar --}}
            <div class="flex items-center justify-between p-4 border-b border-gray-200 dark:border-neutral-700 bg-gray-50 dark:bg-neutral-900/40 flex-shrink-0">
                <div class="flex items-center space-x-4">
                    @if($currentPath !== $rootPath)
                    <button type="button" wire:click="goBack" class="px-3 py-1 bg-indigo-500 text-white rounded hover:bg-indigo-600 text-sm">
                        ← Back
                    </button>
                    @endif
                    <span class="text-sm text-gray-600 dark:text-neutral-400 font-mono">
                        /{{ config('file-manager.base_path', 'uploads') }}/{{ $currentPath }}
                    </span>
                </div>
                <div class="flex items-center space-x-4">
                    <input
                        wire:model.live.debounce.400ms="searchTerm"
                        type="text"
                        placeholder="Search files..."
                        class="px-3 py-1 text-sm border border-gray-300 dark:border-neutral-600 rounded bg-white dark:bg-neutral-700 text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-neutral-500 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    >
                    <div class="flex border border-gray-300 dark:border-neutral-600 rounded overflow-hidden">
                        <button type="button" wire:click="$set('viewMode', 'grid')"
                            class="px-3 py-1 text-sm {{ $viewMode === 'grid' ? 'bg-indigo-500 text-white' : 'bg-white dark:bg-neutral-700 text-gray-700 dark:text-neutral-200 hover:bg-gray-50 dark:hover:bg-neutral-600' }}">
                            Grid
                        </button>
                        <button type="button" wire:click="$set('viewMode', 'list')"
                            class="px-3 py-1 text-sm {{ $viewMode === 'list' ? 'bg-indigo-500 text-white' : 'bg-white dark:bg-neutral-700 text-gray-700 dark:text-neutral-200 hover:bg-gray-50 dark:hover:bg-neutral-600' }}">
                            List
                        </button>
                    </div>
                </div>
            </div>

            {{-- Upload area + AI generator --}}
            @include('file-manager::livewire.file-manager._upload-area')

            {{-- Multi-select toolbar --}}
            @if(config('file-manager.features.multi_select', true) && count($selectedFiles) > 0)
            <div class="flex items-center justify-between px-4 py-2 border-b border-indigo-200 dark:border-indigo-800 bg-indigo-50 dark:bg-indigo-900/20 flex-shrink-0">
                <div class="flex items-center gap-3">
                    <span class="text-sm font-medium text-indigo-700 dark:text-indigo-300">
                        {{ count($selectedFiles) }} file(s) selected
                    </span>
                    <button type="button" wire:click="selectAllVisible"
                            class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline">
                        Select all on page
                    </button>
                    <button type="button" wire:click="deselectAll"
                            class="text-xs text-gray-500 dark:text-neutral-400 hover:underline">
                        Deselect all
                    </button>
                </div>
                <div class="flex items-center gap-2">
                    <button type="button" wire:click="openMoveModal"
                            class="px-3 py-1.5 bg-indigo-600 text-white text-sm rounded hover:bg-indigo-700 transition-colors flex items-center gap-1.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>
                        </svg>
                        Move Selected
                    </button>
                    <button type="button" wire:click="confirmBulkDelete"
                            class="px-3 py-1.5 bg-red-600 text-white text-sm rounded hover:bg-red-700 transition-colors flex items-center gap-1.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                        Delete Selected
                    </button>
                </div>
            </div>
            @endif

            {{-- File browser with drag-and-drop --}}
            <div class="flex-1 overflow-auto p-4 bg-white dark:bg-neutral-800 relative"
                 x-data="{ dragging: false, draggedFilePath: null, dragOverFolder: null }"
                 x-on:dragover.prevent="if (!draggedFilePath) dragging = true"
                 x-on:dragleave.self="if (!draggedFilePath) dragging = false"
                 x-on:drop.prevent="
                    if (!draggedFilePath) {
                        dragging = false;
                        const input = document.getElementById('fileInput');
                        if (input && $event.dataTransfer.files.length) {
                            input.files = $event.dataTransfer.files;
                            input.dispatchEvent(new Event('change', { bubbles: true }));
                        }
                    }
                    draggedFilePath = null;
                    dragOverFolder = null;
                 ">

                {{-- Drag overlay --}}
                <div x-show="dragging" x-transition.opacity
                     class="absolute inset-0 z-10 flex items-center justify-center bg-indigo-500/10 dark:bg-indigo-400/10 border-2 border-dashed border-indigo-400 dark:border-indigo-500 rounded-lg pointer-events-none"
                     style="display: none;">
                    <div class="text-center">
                        <svg class="w-12 h-12 mx-auto text-indigo-500 dark:text-indigo-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                        </svg>
                        <p class="text-sm font-medium text-indigo-600 dark:text-indigo-400">Drop files here to upload</p>
                    </div>
                </div>

                @if($viewMode === 'grid')
                @include('file-manager::livewire.file-manager._file-grid')
                @else
                @include('file-manager::livewire.file-manager._file-list')
                @endif

                @if(empty($files) && empty($folders))
                <div class="text-center text-gray-500 dark:text-neutral-400 py-12">
                    @if($searchTerm)
                        No {{ $mediaType === 'all' ? 'files' : $mediaType.'s' }} match your search.
                    @else
                        <div class="flex flex-col items-center">
                            <svg class="w-16 h-16 text-gray-300 dark:text-neutral-600 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                            </svg>
                            <p>This folder is empty.</p>
                            <p class="text-xs mt-1">Drag and drop files here or click Upload.</p>
                        </div>
                    @endif
                </div>
                @endif
            </div>

            {{-- Pagination --}}
            @if($this->getTotalPages() > 1)
            <div class="flex items-center justify-between px-4 py-2 border-t border-gray-200 dark:border-neutral-700 bg-gray-50 dark:bg-neutral-900/40 flex-shrink-0">
                <span class="text-xs text-gray-500 dark:text-neutral-400">
                    Showing {{ ($currentPage - 1) * $perPage + 1 }}–{{ min($currentPage * $perPage, $totalFiles) }} of {{ $totalFiles }} files
                </span>
                <div class="flex items-center gap-1">
                    <button type="button" wire:click="goToPage(1)" @disabled($currentPage <= 1)
                            class="px-2 py-1 text-xs rounded border border-gray-300 dark:border-neutral-600 bg-white dark:bg-neutral-700 text-gray-700 dark:text-neutral-200 hover:bg-gray-100 dark:hover:bg-neutral-600 disabled:opacity-40 disabled:cursor-not-allowed">&laquo;</button>
                    <button type="button" wire:click="goToPage({{ $currentPage - 1 }})" @disabled($currentPage <= 1)
                            class="px-2 py-1 text-xs rounded border border-gray-300 dark:border-neutral-600 bg-white dark:bg-neutral-700 text-gray-700 dark:text-neutral-200 hover:bg-gray-100 dark:hover:bg-neutral-600 disabled:opacity-40 disabled:cursor-not-allowed">&lsaquo;</button>
                    @php
                        $totalPages = $this->getTotalPages();
                        $start = max(1, $currentPage - 2);
                        $end = min($totalPages, $currentPage + 2);
                    @endphp
                    @for($p = $start; $p <= $end; $p++)
                    <button type="button" wire:click="goToPage({{ $p }})"
                            class="px-2.5 py-1 text-xs rounded border {{ $p === $currentPage ? 'bg-indigo-500 text-white border-indigo-500' : 'border-gray-300 dark:border-neutral-600 bg-white dark:bg-neutral-700 text-gray-700 dark:text-neutral-200 hover:bg-gray-100 dark:hover:bg-neutral-600' }}">
                        {{ $p }}
                    </button>
                    @endfor
                    <button type="button" wire:click="goToPage({{ $currentPage + 1 }})" @disabled($currentPage >= $totalPages)
                            class="px-2 py-1 text-xs rounded border border-gray-300 dark:border-neutral-600 bg-white dark:bg-neutral-700 text-gray-700 dark:text-neutral-200 hover:bg-gray-100 dark:hover:bg-neutral-600 disabled:opacity-40 disabled:cursor-not-allowed">&rsaquo;</button>
                    <button type="button" wire:click="goToPage({{ $totalPages }})" @disabled($currentPage >= $totalPages)
                            class="px-2 py-1 text-xs rounded border border-gray-300 dark:border-neutral-600 bg-white dark:bg-neutral-700 text-gray-700 dark:text-neutral-200 hover:bg-gray-100 dark:hover:bg-neutral-600 disabled:opacity-40 disabled:cursor-not-allowed">&raquo;</button>
                </div>
            </div>
            @endif
            @endif {{-- /previewFile @else --}}

        </div>{{-- /modal panel --}}
    </div>{{-- /modal overlay --}}
    @endif

    @include('file-manager::livewire.file-manager._modals')

    @once
    {{-- Fix 3: Cropper.js with Subresource Integrity to prevent CDN supply-chain attacks --}}
    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.css"
          integrity="sha384-6LFfkTKLRlzFtgx8xsWyBdKGpcMMQTkv+dB7rAbugeJAu1Ym2q1Aji1cjHBG12Xh"
          crossorigin="anonymous">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.js"
            integrity="sha384-jrOgQzBlDeUNdmQn3rUt/PZD+pdcRBdWd/HWRqRo+n2OR2QtGyjSaJC0GiCeH+ir"
            crossorigin="anonymous"></script>
    @endonce
</div>
