{{-- List view — folders then files --}}
<div class="space-y-1">
    @foreach($folders as $folder)
    <div @dragover.prevent.stop="if (draggedFilePath) { dragOverFolder = '{{ $folder['path'] }}'; $event.dataTransfer.dropEffect = 'move'; }"
         @dragleave.stop="dragOverFolder = null"
         @drop.prevent.stop="if (draggedFilePath) { $wire.moveFile(draggedFilePath, '{{ $folder['path'] }}'); draggedFilePath = null; dragOverFolder = null; }"
         :class="dragOverFolder === '{{ $folder['path'] }}' ? 'ring-2 ring-indigo-400 bg-indigo-50 dark:bg-indigo-900/20' : ''"
         class="group flex items-center justify-between p-3 border border-gray-200 dark:border-neutral-700 rounded hover:bg-gray-100 dark:hover:bg-neutral-700 transition-all">

        <div class="flex items-center flex-1 min-w-0">
            <svg class="w-6 h-6 text-yellow-500 mr-3 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                <path d="M2 6a2 2 0 012-2h5l2 2h5a2 2 0 012 2v6a2 2 0 01-2 2H4a2 2 0 01-2-2V6z"></path>
            </svg>
            @if($renamingFolder && $renamingFolder['path'] === $folder['path'])
            <div class="flex-1" @click.stop>
                <input type="text" wire:model.defer="renameFolderValue"
                       class="w-full px-2 py-0.5 text-sm border border-indigo-400 rounded bg-white dark:bg-neutral-700 text-gray-900 dark:text-white focus:outline-none focus:ring-1 focus:ring-indigo-500"
                       @keydown.enter.stop="$wire.applyFolderRename()"
                       @keydown.escape.stop="$wire.cancelRenameFolder()"
                       autofocus>
            </div>
            @else
            <span wire:click="navigateToFolder('{{ $folder['path'] }}')"
                  class="text-sm text-gray-700 dark:text-neutral-200 cursor-pointer flex-1">{{ $folder['name'] }}</span>
            @endif
        </div>

        <div class="ml-3 flex items-center gap-1.5 shrink-0 opacity-0 group-hover:opacity-100 transition-opacity">
            @if($renamingFolder && $renamingFolder['path'] === $folder['path'])
            <button type="button" @click.stop="$wire.applyFolderRename()"
                    class="h-6 px-2 inline-flex items-center text-xs rounded bg-green-500 text-white hover:bg-green-600">✓</button>
            <button type="button" @click.stop="$wire.cancelRenameFolder()"
                    class="h-6 px-2 inline-flex items-center text-xs rounded bg-gray-400 text-white hover:bg-gray-500">✗</button>
            @else
            <button type="button" wire:click.stop="startRenameFolder({{ json_encode($folder) }})"
                    class="h-6 px-2 inline-flex items-center text-xs rounded bg-amber-500 text-white hover:bg-amber-600">Rename</button>
            <button type="button" wire:click.stop="confirmDeleteFolder({{ json_encode($folder) }})"
                    class="h-6 px-2 inline-flex items-center text-xs rounded bg-red-500 text-white hover:bg-red-600">Delete</button>
            @endif
        </div>
    </div>
    @endforeach

    @foreach($files as $file)
    <div class="flex items-center justify-between p-3 border rounded hover:bg-gray-100 dark:hover:bg-neutral-700 transition-colors group
                {{ in_array($file['path'], $selectedFiles) ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-900/20' : 'border-gray-200 dark:border-neutral-700' }}"
         draggable="true"
         @dragstart="draggedFilePath = '{{ $file['path'] }}'; $event.dataTransfer.effectAllowed = 'move'"
         @dragend="draggedFilePath = null; dragOverFolder = null">

        <label class="mr-3 flex-shrink-0" @click.stop>
            <input type="checkbox"
                   wire:click="toggleSelectFile('{{ $file['path'] }}')"
                   @checked(in_array($file['path'], $selectedFiles))
                   class="w-4 h-4 rounded border-gray-300 dark:border-neutral-600 text-indigo-600 focus:ring-indigo-500">
        </label>

        <div @if($file['type'] === 'image')
                wire:click="previewImage({{ json_encode($file) }})"
             @else
                wire:click="selectFile({{ json_encode($file) }})"
             @endif
             class="flex items-center cursor-pointer flex-1 min-w-0">
            @if($file['thumbnail'])
            <img src="{{ $file['thumbnail'] }}" alt="{{ $file['name'] }}" class="w-10 h-10 object-cover mr-3 rounded border border-gray-200 dark:border-neutral-600 flex-shrink-0" draggable="false">
            @else
            <div class="w-10 h-10 mr-3 flex items-center justify-center bg-gray-100 dark:bg-neutral-700 rounded flex-shrink-0">
                <x-file-manager::file-icon :type="$file['type']" size="w-6 h-6" />
            </div>
            @endif
            <div class="min-w-0 flex-1">
                @if($renamingFile && $renamingFile['path'] === $file['path'])
                <div @click.stop @dragstart.stop>
                    <input type="text" wire:model.defer="renameValue"
                           class="w-full px-2 py-0.5 text-sm border border-indigo-400 rounded bg-white dark:bg-neutral-700 text-gray-900 dark:text-white focus:outline-none focus:ring-1 focus:ring-indigo-500"
                           @keydown.enter.stop="$wire.applyRename()"
                           @keydown.escape.stop="$wire.cancelRename()"
                           autofocus>
                </div>
                @else
                <div class="text-sm font-medium text-gray-900 dark:text-neutral-100 truncate">{{ $file['name'] }}</div>
                @endif
                <div class="text-xs text-gray-500 dark:text-neutral-400">
                    {{ number_format($file['size'] / 1024, 2) }} KB &middot; {{ date('M j, Y', $file['modified']) }}
                    @if(!empty($file['subfolder']))
                    <span class="ml-1 text-indigo-400 dark:text-indigo-500 font-mono">· {{ $file['subfolder'] }}/</span>
                    @endif
                    @if($file['type'] === 'image')
                    <span class="ml-1 text-indigo-500 dark:text-indigo-400">· Click to edit</span>
                    @endif
                </div>
            </div>
        </div>

        <div class="ml-3 flex items-center gap-1.5 shrink-0 opacity-0 group-hover:opacity-100 transition-opacity">
            @if($renamingFile && $renamingFile['path'] === $file['path'])
            <button type="button" @click.stop="$wire.applyRename()"
                    class="h-6 px-2 inline-flex items-center text-xs rounded bg-green-500 text-white hover:bg-green-600">✓</button>
            <button type="button" @click.stop="$wire.cancelRename()"
                    class="h-6 px-2 inline-flex items-center text-xs rounded bg-gray-400 text-white hover:bg-gray-500">✗</button>
            @else
            <button type="button" wire:click.stop="startRename({{ json_encode($file) }})"
                    class="h-6 px-2 inline-flex items-center text-xs rounded bg-amber-500 text-white hover:bg-amber-600">Rename</button>
            <button type="button" wire:click.stop="openMoveModal({{ json_encode($file) }})"
                    class="h-6 px-2 inline-flex items-center text-xs rounded bg-indigo-500 text-white hover:bg-indigo-600">Move</button>
            <button type="button" wire:click.stop="confirmDelete({{ json_encode($file) }})"
                    class="h-6 px-2 inline-flex items-center text-xs rounded bg-red-500 text-white hover:bg-red-600">Delete</button>
            @endif
        </div>
    </div>
    @endforeach
</div>
