{{-- Grid view — folders then files --}}
<div class="grid grid-cols-6 gap-4">
    @foreach($folders as $folder)
    <div @dragover.prevent.stop="if (draggedFilePath) { dragOverFolder = '{{ $folder['path'] }}'; $event.dataTransfer.dropEffect = 'move'; }"
         @dragleave.stop="dragOverFolder = null"
         @drop.prevent.stop="if (draggedFilePath) { $wire.moveFile(draggedFilePath, '{{ $folder['path'] }}'); draggedFilePath = null; dragOverFolder = null; }"
         :class="dragOverFolder === '{{ $folder['path'] }}' ? 'ring-2 ring-indigo-400 bg-indigo-50 dark:bg-indigo-900/20 scale-105' : ''"
         class="group relative flex flex-col items-center p-3 border border-gray-200 dark:border-neutral-700 rounded hover:bg-gray-100 dark:hover:bg-neutral-700 transition-all">

        @if(!($renamingFolder && $renamingFolder['path'] === $folder['path']))
        <div wire:click="navigateToFolder('{{ $folder['path'] }}')" class="flex flex-col items-center w-full cursor-pointer">
            <div class="w-full h-28 mb-1 flex items-center justify-center">
                <svg class="w-20 h-20 text-yellow-500" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M2 6a2 2 0 012-2h5l2 2h5a2 2 0 012 2v6a2 2 0 01-2 2H4a2 2 0 01-2-2V6z"></path>
                </svg>
            </div>
            <span class="text-sm text-center text-gray-700 dark:text-neutral-200">{{ $folder['name'] }}</span>
            <span class="text-xs text-gray-400 dark:text-neutral-500 mt-0.5">{{ $folder['fileCount'] }} {{ Str::plural('file', $folder['fileCount']) }}</span>
        </div>
        @else
        <div class="w-full h-28 mb-1 flex items-center justify-center">
            <svg class="w-20 h-20 text-yellow-500" fill="currentColor" viewBox="0 0 20 20">
                <path d="M2 6a2 2 0 012-2h5l2 2h5a2 2 0 012 2v6a2 2 0 01-2 2H4a2 2 0 01-2-2V6z"></path>
            </svg>
        </div>
        <div class="w-full" @click.stop @dragstart.stop>
            <input type="text" wire:model.defer="renameFolderValue"
                   class="w-full px-1 py-0.5 text-xs border border-indigo-400 rounded bg-white dark:bg-neutral-700 text-gray-900 dark:text-white focus:outline-none focus:ring-1 focus:ring-indigo-500 text-center"
                   @keydown.enter.stop="$wire.applyFolderRename()"
                   @keydown.escape.stop="$wire.cancelRenameFolder()"
                   autofocus>
        </div>
        @endif

        @if($renamingFolder && $renamingFolder['path'] === $folder['path'])
        <div class="flex gap-1 mt-1 w-full px-1" @click.stop>
            <button type="button" @click.stop="$wire.applyFolderRename()"
                    class="flex-1 py-0.5 bg-green-500 text-white text-xs rounded hover:bg-green-600">✓ Save</button>
            <button type="button" @click.stop="$wire.cancelRenameFolder()"
                    class="flex-1 py-0.5 bg-gray-400 text-white text-xs rounded hover:bg-gray-500">✗</button>
        </div>
        @else
        <div class="mt-2 flex items-center justify-center gap-1.5 opacity-0 group-hover:opacity-100 transition-opacity">
            <button type="button" wire:click.stop="startRenameFolder({{ json_encode($folder) }})"
                    title="Rename folder"
                    class="size-7 rounded bg-amber-500 text-white hover:bg-amber-600 grid place-items-center shrink-0">
                <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                </svg>
            </button>
            <button type="button" wire:click.stop="confirmDeleteFolder({{ json_encode($folder) }})"
                    title="Delete folder"
                    class="size-7 rounded bg-red-500 text-white hover:bg-red-600 grid place-items-center shrink-0">
                <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
        @endif
    </div>
    @endforeach

    @foreach($files as $file)
    <div class="flex flex-col items-center p-3 border rounded cursor-pointer hover:bg-gray-100 dark:hover:bg-neutral-700 transition-colors group relative
                {{ in_array($file['path'], $selectedFiles) ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-900/20' : 'border-gray-200 dark:border-neutral-700' }}"
         draggable="true"
         @dragstart="draggedFilePath = '{{ $file['path'] }}'; $event.dataTransfer.effectAllowed = 'move'"
         @dragend="draggedFilePath = null; dragOverFolder = null">

        <label class="absolute top-1.5 left-1.5 z-[1]" @click.stop>
            <input type="checkbox"
                   wire:click="toggleSelectFile('{{ $file['path'] }}')"
                   @checked(in_array($file['path'], $selectedFiles))
                   class="w-4 h-4 rounded border-gray-300 dark:border-neutral-600 text-indigo-600 focus:ring-indigo-500 opacity-0 group-hover:opacity-100 transition-opacity {{ in_array($file['path'], $selectedFiles) ? '!opacity-100' : '' }}">
        </label>

        <div @if($file['type'] === 'image')
                wire:click="previewImage({{ json_encode($file) }})"
             @else
                wire:click="selectFile({{ json_encode($file) }})"
             @endif
             class="flex flex-col items-center w-full">
            @if($file['thumbnail'])
            <img src="{{ $file['thumbnail'] }}" alt="{{ $file['name'] }}" class="w-full h-28 object-cover mb-2 rounded border border-gray-200 dark:border-neutral-600" draggable="false">
            @else
            <div class="w-full h-28 mb-2 flex items-center justify-center bg-gray-50 dark:bg-neutral-700/50 rounded border border-gray-200 dark:border-neutral-600">
                <x-file-manager::file-icon :type="$file['type']" />
            </div>
            @endif
            @if($renamingFile && $renamingFile['path'] === $file['path'])
            <div class="w-full" @click.stop @dragstart.stop>
                <input type="text" wire:model.defer="renameValue"
                       class="w-full px-1 py-0.5 text-xs border border-indigo-400 rounded bg-white dark:bg-neutral-700 text-gray-900 dark:text-white focus:outline-none focus:ring-1 focus:ring-indigo-500 text-center"
                       @keydown.enter.stop="$wire.applyRename()"
                       @keydown.escape.stop="$wire.cancelRename()"
                       autofocus>
            </div>
            @else
            <span class="text-sm text-center leading-tight text-gray-700 dark:text-neutral-200">{{ Str::limit($file['name'], 20) }}</span>
            @endif
            <span class="text-xs text-gray-500 dark:text-neutral-400 mt-1">{{ number_format($file['size'] / 1024, 1) }}KB</span>
            @if(!empty($file['subfolder']))
            <span class="text-xs text-indigo-400 dark:text-indigo-500 font-mono truncate w-full text-center" title="{{ $file['subfolder'] }}/">{{ $file['subfolder'] }}/</span>
            @endif
        </div>

        @if($file['type'] === 'image' && !($renamingFile && $renamingFile['path'] === $file['path']))
        <span class="absolute top-1 right-1 text-xs bg-indigo-500/80 text-white px-1 rounded opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none">Edit</span>
        @endif

        @if($renamingFile && $renamingFile['path'] === $file['path'])
        <div class="flex gap-1 mt-1 w-full px-1" @click.stop>
            <button type="button" @click.stop="$wire.applyRename()"
                    class="flex-1 py-0.5 bg-green-500 text-white text-xs rounded hover:bg-green-600">✓ Save</button>
            <button type="button" @click.stop="$wire.cancelRename()"
                    class="flex-1 py-0.5 bg-gray-400 text-white text-xs rounded hover:bg-gray-500">✗</button>
        </div>
        @else
        <div class="mt-2 flex items-center justify-center gap-1.5 opacity-0 group-hover:opacity-100 transition-opacity">
            <button type="button" wire:click.stop="startRename({{ json_encode($file) }})"
                    title="Rename"
                    class="size-7 rounded bg-amber-500 text-white hover:bg-amber-600 grid place-items-center shrink-0">
                <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                </svg>
            </button>
            <button type="button" wire:click.stop="openMoveModal({{ json_encode($file) }})"
                    title="Move"
                    class="size-7 rounded bg-indigo-500 text-white hover:bg-indigo-600 grid place-items-center shrink-0">
                <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7h18M3 12h18M3 17h18"/>
                </svg>
            </button>
            <button type="button" wire:click.stop="confirmDelete({{ json_encode($file) }})"
                    title="Delete"
                    class="size-7 rounded bg-red-500 text-white hover:bg-red-600 grid place-items-center shrink-0">
                <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
        @endif
    </div>
    @endforeach
</div>
