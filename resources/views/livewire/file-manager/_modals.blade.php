{{-- All confirmation modals and toast notifications --}}

{{-- Delete single file --}}
@php $deleteTarget = $fileToDelete ?? $previewFile ?? null; @endphp
<x-file-manager::confirm-modal
    :show="$showDeleteModal && ($fileToDelete || $deletingPreviewFile)"
    title="Confirm Delete"
    confirmAction="deleteFile"
    cancelAction="cancelDelete"
    confirmLabel="Delete File"
    loadingLabel="Deleting…"
    confirmTarget="deleteFile"
>
    @if($deleteTarget)
    <h4 class="text-base font-medium text-gray-900 dark:text-white mb-2">Delete File</h4>
    <p class="text-sm text-gray-600 dark:text-neutral-400 mb-4">
        Are you sure you want to delete <strong class="text-gray-900 dark:text-neutral-200">"{{ $deleteTarget['name'] }}"</strong>?
        This action cannot be undone.
    </p>
    <div class="flex items-center p-3 bg-gray-50 dark:bg-neutral-700 rounded-lg">
        @if($deleteTarget['thumbnail'] ?? null)
        <img src="{{ $deleteTarget['thumbnail'] }}" alt="{{ $deleteTarget['name'] }}" class="w-12 h-12 object-cover rounded mr-3 border border-gray-200 dark:border-neutral-600">
        @else
        <div class="w-12 h-12 mr-3 flex items-center justify-center bg-gray-200 dark:bg-neutral-600 rounded">
            <x-file-manager::file-icon :type="$deleteTarget['type']" size="w-8 h-8" />
        </div>
        @endif
        <div>
            <div class="text-sm font-medium text-gray-900 dark:text-neutral-100">{{ $deleteTarget['name'] }}</div>
            <div class="text-xs text-gray-500 dark:text-neutral-400">
                {{ number_format($deleteTarget['size'] / 1024, 1) }} KB &middot; {{ ucfirst($deleteTarget['type']) }}
            </div>
        </div>
    </div>
    @endif
</x-file-manager::confirm-modal>

{{-- Bulk delete --}}
<x-file-manager::confirm-modal
    :show="$showBulkDeleteModal"
    title="Confirm Bulk Delete"
    confirmAction="bulkDelete"
    cancelAction="cancelBulkDelete"
    :confirmLabel="'Delete ' . count($selectedFiles) . ' Files'"
    loadingLabel="Deleting…"
    confirmTarget="bulkDelete"
>
    <h4 class="text-base font-medium text-gray-900 dark:text-white mb-2">Delete {{ count($selectedFiles) }} Files</h4>
    <p class="text-sm text-gray-600 dark:text-neutral-400 mb-3">
        Are you sure you want to delete <strong class="text-gray-900 dark:text-neutral-200">{{ count($selectedFiles) }} file(s)</strong>?
        This action cannot be undone.
    </p>
    <div class="max-h-32 overflow-y-auto text-xs text-gray-500 dark:text-neutral-400 bg-gray-50 dark:bg-neutral-700 rounded p-2 space-y-0.5">
        @foreach($selectedFiles as $path)
        <div class="truncate">{{ basename($path) }}</div>
        @endforeach
    </div>
</x-file-manager::confirm-modal>

{{-- Delete folder --}}
<x-file-manager::confirm-modal
    :show="$showFolderDeleteModal"
    title="Delete Folder"
    confirmAction="deleteFolder"
    cancelAction="cancelDeleteFolder"
    confirmLabel="Delete Folder"
    loadingLabel="Deleting…"
    confirmTarget="deleteFolder"
>
    @if($folderToDelete)
    <h4 class="text-base font-medium text-gray-900 dark:text-white mb-2">Delete Folder</h4>
    <p class="text-sm text-gray-600 dark:text-neutral-400 mb-3">
        Are you sure you want to delete <strong class="text-gray-900 dark:text-neutral-200">"{{ $folderToDelete['name'] }}"</strong>
        and all its contents? This cannot be undone.
    </p>
    <div class="flex items-center gap-2 p-3 bg-gray-50 dark:bg-neutral-700 rounded-lg">
        <svg class="w-8 h-8 text-yellow-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
            <path d="M2 6a2 2 0 012-2h5l2 2h5a2 2 0 012 2v6a2 2 0 01-2 2H4a2 2 0 01-2-2V6z"/>
        </svg>
        <span class="text-sm font-medium text-gray-900 dark:text-neutral-100">{{ $folderToDelete['name'] }}/</span>
    </div>
    @endif
</x-file-manager::confirm-modal>

{{-- Move modal --}}
@if($showMoveModal)
<div class="fixed inset-0 flex items-center justify-center bg-black/60" style="z-index: 9999999;">
    <div class="bg-white dark:bg-neutral-800 rounded-lg shadow-xl w-80 max-h-[70vh] flex flex-col">
        <div class="flex items-center justify-between px-4 py-3 border-b border-gray-200 dark:border-neutral-700">
            <h3 class="text-sm font-semibold text-gray-900 dark:text-white">
                Move {{ count($filesToMove) }} file(s)
            </h3>
            <button type="button" wire:click="closeMoveModal" class="text-gray-400 hover:text-gray-600 dark:hover:text-neutral-200">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
        <p class="px-4 pt-3 pb-1 text-xs text-gray-500 dark:text-neutral-400">Select destination folder:</p>
        <div class="flex-1 overflow-auto px-2 pb-2">
            @foreach($availableFolders as $folder)
            <button type="button" wire:click="$set('moveTargetFolder', '{{ $folder['path'] }}')"
                    class="w-full text-left px-3 py-2 text-sm rounded transition-colors flex items-center gap-2
                           {{ $moveTargetFolder === $folder['path']
                              ? 'bg-indigo-600 text-white'
                              : 'text-gray-700 dark:text-neutral-200 hover:bg-gray-100 dark:hover:bg-neutral-700' }}"
                    style="padding-left: {{ 12 + $folder['depth'] * 16 }}px">
                <svg class="w-4 h-4 flex-shrink-0 {{ $moveTargetFolder === $folder['path'] ? 'text-indigo-200' : 'text-yellow-500' }}" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M2 6a2 2 0 012-2h5l2 2h5a2 2 0 012 2v6a2 2 0 01-2 2H4a2 2 0 01-2-2V6z"/>
                </svg>
                {{ $folder['name'] }}
            </button>
            @endforeach
        </div>
        <div class="flex items-center justify-end gap-2 px-4 py-3 border-t border-gray-200 dark:border-neutral-700">
            <button type="button" wire:click="closeMoveModal"
                    class="px-3 py-1.5 text-sm bg-gray-200 dark:bg-neutral-600 text-gray-700 dark:text-neutral-200 rounded hover:bg-gray-300 dark:hover:bg-neutral-500">
                Cancel
            </button>
            <button type="button" wire:click="executeMove"
                    @disabled($moveTargetFolder === null)
                    class="px-3 py-1.5 text-sm bg-indigo-600 text-white rounded hover:bg-indigo-700 disabled:opacity-40 disabled:cursor-not-allowed"
                    wire:loading.attr="disabled" wire:target="executeMove">
                <span wire:loading.remove wire:target="executeMove">Move Here</span>
                <span wire:loading wire:target="executeMove">Moving…</span>
            </button>
        </div>
    </div>
</div>
@endif

{{-- Toast notifications --}}
@if(!empty($notification))
<div x-data="{ show: true }"
     x-init="setTimeout(() => { show = false; $wire.set('notification', []) }, 3500)"
     x-show="show"
     x-transition:leave="transition ease-in duration-300"
     x-transition:leave-start="opacity-100 translate-y-0"
     x-transition:leave-end="opacity-0 -translate-y-2"
     class="fixed top-4 right-4 px-4 py-2 rounded shadow-lg text-sm text-white {{ $notification['type'] === 'error' ? 'bg-red-500' : 'bg-green-500' }}"
     style="z-index: 1000000;">
    {{ $notification['message'] }}
</div>
@endif
