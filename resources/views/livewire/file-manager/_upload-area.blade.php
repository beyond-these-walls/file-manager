{{-- Upload bar, create-folder form, and AI generator panel --}}
@php
    $allowedTypes = config('file-manager.allowed_file_types', []);
    $acceptMap = [
        'image' => 'image/*',
        'media' => 'video/*,audio/*',
        'file'  => collect($allowedTypes['file']['extensions'] ?? [])->map(fn($e) => '.'.$e)->implode(','),
        'all'   => collect($allowedTypes)->flatMap(fn($t) => collect($t['extensions'] ?? [])->map(fn($e) => '.'.$e))->implode(','),
    ];
    $acceptAttr = $acceptMap[$mediaType] ?? '';
@endphp
<div class="p-4 border-b border-gray-200 dark:border-neutral-700 bg-gray-50 dark:bg-neutral-900/40 flex-shrink-0">
    <div class="flex items-center space-x-4">
        <input type="file" wire:model="uploadedFiles" multiple class="hidden" id="fileInput"
            @if($acceptAttr) accept="{{ $acceptAttr }}" @endif>
        <label for="fileInput" class="px-4 py-2 bg-green-500 text-white rounded cursor-pointer hover:bg-green-600 text-sm">
            Upload {{ $mediaType === 'all' ? 'Files' : ucfirst($mediaType).'s' }}
        </label>
        <button type="button" wire:click="$set('showCreateFolder', true)"
            class="px-4 py-2 bg-indigo-500 text-white rounded hover:bg-indigo-600 text-sm">
            New Folder
        </button>
        @if(config('file-manager.features.ai_generator', true))
        <button type="button" wire:click="toggleAiGenerator"
            class="px-4 py-2 rounded text-sm font-medium flex items-center gap-1.5
                {{ $showAiGenerator ? 'bg-sky-600 text-white' : 'bg-sky-100 dark:bg-sky-900/30 text-sky-700 dark:text-sky-300 hover:bg-sky-200 dark:hover:bg-sky-800/40' }}">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z"/>
            </svg>
            AI Generate
        </button>
        @endif
        @if($uploadedFiles)
        <span class="text-sm text-gray-600 dark:text-neutral-400">{{ count($uploadedFiles) }} file(s) selected</span>
        @endif
    </div>

    <div wire:loading wire:target="uploadedFiles" class="mt-2 text-sm text-indigo-600 dark:text-indigo-400">
        Uploading files…
    </div>

    @if($showCreateFolder)
    <div class="mt-4 p-4 bg-indigo-50 dark:bg-indigo-900/20 border border-indigo-200 dark:border-indigo-800 rounded">
        <h4 class="text-sm font-medium text-gray-900 dark:text-white mb-3">Create New Folder</h4>
        <div class="flex items-center space-x-3">
            <input wire:model.defer="newFolderName" type="text" placeholder="Folder name…"
                   class="flex-1 px-3 py-2 text-sm border border-gray-300 dark:border-neutral-600 rounded bg-white dark:bg-neutral-700 text-gray-900 dark:text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                   wire:keydown.enter="createFolder" autofocus>
            <button type="button" wire:click="createFolder" class="px-4 py-2 text-sm bg-indigo-500 text-white rounded hover:bg-indigo-600 disabled:opacity-50"
                    wire:loading.attr="disabled" wire:target="createFolder">
                <span wire:loading.remove wire:target="createFolder">Create</span>
                <span wire:loading wire:target="createFolder">Creating…</span>
            </button>
            <button type="button" wire:click="cancelCreateFolder" class="px-4 py-2 text-sm bg-gray-500 dark:bg-neutral-600 text-white rounded hover:bg-gray-600 dark:hover:bg-neutral-500">
                Cancel
            </button>
        </div>
        @error('newFolderName')
        <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
        @enderror
    </div>
    @endif

    @if(config('file-manager.features.ai_generator', true) && $showAiGenerator)
    <div class="mt-4 p-4 bg-sky-50 dark:bg-sky-900/20 rounded-lg">
        <div class="flex items-center gap-2 mb-3">
            <svg class="w-4 h-4 text-sky-600 dark:text-sky-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z"/>
            </svg>
            <h4 class="text-sm font-medium text-sky-900 dark:text-sky-200">Generate Image with AI</h4>
            <select wire:model.live="aiProvider" class="ml-2 text-xs rounded px-2 py-1 bg-white dark:bg-sky-900 text-gray-800">
                @foreach(config('file-manager.ai_providers', []) as $key => $label)
                <option value="{{ $key }}">{{ $label }}</option>
                @endforeach
            </select>
            @if($aiQuota)
                <span class="ml-3 text-xs text-sky-600 dark:text-sky-400 flex items-center gap-1.5">
                    @if($aiQuota['pct'] !== null)
                        <span class="inline-block w-16 h-1.5 rounded-full bg-sky-200 dark:bg-sky-800 overflow-hidden">
                            <span class="block h-full rounded-full {{ $aiQuota['pct'] > 20 ? 'bg-sky-500' : 'bg-red-500' }}"
                                  style="width: {{ $aiQuota['pct'] }}%"></span>
                        </span>
                    @endif
                    {{ $aiQuota['label'] }}
                </span>
            @endif
        </div>

        <div class="flex items-center gap-2 mb-3">
            <label class="text-xs text-sky-700 dark:text-sky-300 font-medium shrink-0">Size</label>
            <select wire:model="aiImageSize" class="text-xs border border-sky-300 dark:border-sky-700 rounded px-2 py-1 bg-white dark:bg-sky-900 text-gray-800 dark:text-neutral-200">
                <option value="512x512">512 × 512 — Square SM</option>
                <option value="768x768">768 × 768 — Square MD</option>
                <option value="1024x1024">1024 × 1024 — Square LG</option>
                <option value="1280x720">1280 × 720 — Landscape 720p</option>
                <option value="1920x1080">1920 × 1080 — Landscape 1080p</option>
                <option value="720x1280">720 × 1280 — Portrait 720p</option>
                <option value="1080x1920">1080 × 1920 — Portrait 1080p</option>
            </select>
        </div>

        <div class="flex items-start gap-3">
            <textarea
                wire:model="aiPrompt"
                rows="2"
                placeholder="Describe the image you want to generate… e.g. 'A serene mountain lake at sunrise'"
                class="flex-1 px-3 py-2 text-sm rounded bg-white dark:bg-neutral-700 text-gray-900 dark:text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-sky-500 resize-none"
                wire:keydown.ctrl.enter="generateAiImage"
            ></textarea>
            <div class="flex flex-col gap-2 shrink-0">
                <button type="button"
                    wire:click="generateAiImage"
                    wire:loading.attr="disabled"
                    wire:target="generateAiImage"
                    class="px-4 py-2 text-sm font-medium bg-sky-600 text-white rounded hover:bg-sky-700 disabled:opacity-60 flex items-center gap-1.5 whitespace-nowrap">
                    <span wire:loading.remove wire:target="generateAiImage">Generate</span>
                    <span wire:loading wire:target="generateAiImage">Generating…</span>
                </button>
                <button type="button" wire:click="toggleAiGenerator"
                    class="px-4 py-2 text-sm bg-gray-200 dark:bg-neutral-600 text-gray-700 dark:text-neutral-200 rounded hover:bg-gray-300 dark:hover:bg-neutral-500">
                    Cancel
                </button>
            </div>
        </div>

        @if($aiError)
        <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $aiError }}</p>
        @endif

        @error('aiPrompt')
        <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
        @enderror

        <p class="mt-2 text-xs text-sky-500 dark:text-sky-400">
            Saved to current folder &middot; Ctrl+Enter to generate
        </p>
    </div>
    @endif
</div>
