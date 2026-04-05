{{-- resources/views/livewire/file-manager.blade.php --}}
<div>
    @if($isOpen)
    <div class="fixed inset-0 flex items-center justify-center bg-gray-500/70 dark:bg-neutral-900/80" style="z-index: 999999;"
         x-data
         @keydown.escape.window="
            if ($wire.showDeleteModal) { $wire.cancelDelete(); }
            else if ($wire.showBulkDeleteModal) { $wire.cancelBulkDelete(); }
            else if ($wire.previewFile) { $wire.backToGallery(); }
            else { $wire.close(); }
         ">
        <div class="bg-white dark:bg-neutral-800 rounded-lg shadow-xl w-5/6 h-5/6 flex flex-col overflow-hidden">

            {{-- ── Modal header (always visible) ── --}}
            <div class="flex items-center justify-between p-4 border-b border-gray-200 dark:border-neutral-700 flex-shrink-0">
                <div class="flex items-center space-x-3">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">File Manager</h3>

                </div>
                <button wire:click="close" class="text-gray-500 dark:text-neutral-400 hover:text-gray-700 dark:hover:text-neutral-200">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>

            {{-- ═══════════════════════════════════════════════════ --}}
            {{-- IMAGE EDITOR (shown when a file is being previewed) --}}
            {{-- ═══════════════════════════════════════════════════ --}}
            @if($previewFile)
            <div
                x-data="{
                    rotate:     0,
                    flipH:      false,
                    flipV:      false,
                    brightness: 0,
                    contrast:   0,
                    quality:    85,
                    greyscale:  false,
                    blur:       0,
                    sharpen:    0,
                    cropMode:   false,
                    cropper:    null,
                    cropAspect: NaN,
                    cropData:   { x: 0, y: 0, width: 0, height: 0 },
                    get imgStyle() {
                        const sx = this.flipH ? -1 : 1;
                        const sy = this.flipV ? -1 : 1;
                        const filters = [
                            'brightness('+(100+parseInt(this.brightness))+'%)',
                            'contrast('+(100+parseInt(this.contrast))+'%)',
                            'grayscale('+(this.greyscale ? 100 : 0)+'%)',
                            'blur('+(parseInt(this.blur)*0.6)+'px)',
                        ].join(' ');
                        return 'transform:rotate('+this.rotate+'deg) scaleX('+sx+') scaleY('+sy+');'
                             + 'filter:'+filters+';'
                             + 'max-width:100%;max-height:100%;object-fit:contain;transition:filter 0.15s,transform 0.15s';
                    },
                    enableCrop() {
                        this.cropMode = true;
                        this.$nextTick(() => {
                            const img = this.$refs.cropImg;
                            if (!img) return;
                            if (this.cropper) { this.cropper.destroy(); this.cropper = null; }
                            this.cropper = new Cropper(img, {
                                viewMode: 1,
                                aspectRatio: this.cropAspect,
                                movable: true,
                                zoomable: true,
                                scalable: false,
                                crop: (event) => {
                                    this.cropData = {
                                        x:      Math.round(event.detail.x),
                                        y:      Math.round(event.detail.y),
                                        width:  Math.round(event.detail.width),
                                        height: Math.round(event.detail.height),
                                    };
                                }
                            });
                        });
                    },
                    cancelCrop() {
                        if (this.cropper) { this.cropper.destroy(); this.cropper = null; }
                        this.cropMode = false;
                    },
                    setCropAspect(ratio) {
                        this.cropAspect = ratio;
                        if (this.cropper) this.cropper.setAspectRatio(ratio);
                    },
                    applyCrop() {
                        const d = this.cropData;
                        if (d.width < 1 || d.height < 1) return;
                        $wire.applyCrop(d.x, d.y, d.width, d.height);
                    },
                    init() {
                        // After save/crop, editVersion increments → reset all local state
                        this.$wire.$watch('editVersion', (v) => {
                            if (v > 0) {
                                this.cancelCrop();
                                this.rotate = 0; this.flipH = false; this.flipV = false;
                                this.brightness = 0; this.contrast = 0; this.quality = 85;
                                this.greyscale = false; this.blur = 0; this.sharpen = 0;
                            }
                        });
                    }
                }"
                class="flex flex-col flex-1 min-h-0"
            >
                {{-- Editor toolbar --}}
                <div class="flex items-center justify-between px-4 py-2 border-b border-gray-200 dark:border-neutral-700 bg-gray-50 dark:bg-neutral-900/40 flex-shrink-0">
                    <button wire:click="backToGallery"
                            class="flex items-center gap-1 text-sm px-3 py-1.5 bg-amber-600 text-white text-sm rounded hover:bg-amber-700 disabled:opacity-50 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                        </svg>
                        Back to Gallery
                    </button>

                    <div class="flex-1 mx-4 text-sm text-gray-500 dark:text-neutral-400 truncate text-center">
                        {{ $previewFile['name'] }}
                        @if($originalDimensions)
                            <span class="text-xs ml-2 text-gray-400 dark:text-neutral-500">
                                {{ $originalDimensions['width'] }} × {{ $originalDimensions['height'] }} px
                            </span>
                        @endif
                    </div>

                    <div class="flex items-center gap-2 flex-shrink-0">
                        {{-- Normal mode action buttons --}}
                        <div x-show="!cropMode" class="flex items-center gap-2">
                            @if($isAiGeneratedPreview)
                            <button wire:click="regenerateAiImage"
                                    wire:loading.attr="disabled" wire:target="regenerateAiImage"
                                    class="px-3 py-1.5 bg-purple-600 text-white text-sm rounded hover:bg-purple-700 disabled:opacity-50 transition-colors flex items-center gap-1.5"
                                    title="Regenerate: {{ $lastAiPrompt }}">
                                <svg wire:loading.remove wire:target="regenerateAiImage" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                                </svg>
                                <svg wire:loading wire:target="regenerateAiImage" class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
                                </svg>
                                <span wire:loading.remove wire:target="regenerateAiImage">Regenerate</span>
                                <span wire:loading wire:target="regenerateAiImage">Generating…</span>
                            </button>
                            @endif
                            <button
                                @click="$wire.saveOverwrite(rotate, flipH, flipV, parseInt(brightness), parseInt(contrast), parseInt(quality), greyscale, parseInt(blur), parseInt(sharpen))"
                                class="px-3 py-1.5 bg-amber-600 text-white text-sm rounded hover:bg-amber-700 disabled:opacity-50 transition-colors"
                                wire:loading.attr="disabled" wire:target="saveOverwrite">
                                <span wire:loading.remove wire:target="saveOverwrite">Save</span>
                                <span wire:loading wire:target="saveOverwrite">Saving…</span>
                            </button>
                            <button
                                @click="$wire.saveEditedCopy(rotate, flipH, flipV, parseInt(brightness), parseInt(contrast), parseInt(quality), greyscale, parseInt(blur), parseInt(sharpen))"
                                class="px-3 py-1.5 bg-blue-600 text-white text-sm rounded hover:bg-blue-700 disabled:opacity-50 transition-colors"
                                wire:loading.attr="disabled" wire:target="saveEditedCopy">
                                <span wire:loading.remove wire:target="saveEditedCopy">Save Copy</span>
                                <span wire:loading wire:target="saveEditedCopy">Saving…</span>
                            </button>
                            <button @click="enableCrop()"
                                    class="px-3 py-1.5 bg-violet-600 text-white text-sm rounded hover:bg-violet-700 transition-colors flex items-center gap-1.5">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 3v4m0 0H3m4 0h10v10m0 0h4m-4 0V7M7 17H3"/>
                                </svg>
                                Crop
                            </button>
                            <button wire:click="confirmDeletePreview"
                                    class="px-3 py-1.5 bg-red-600 text-white text-sm rounded hover:bg-red-700 transition-colors">
                                Delete
                            </button>
                            <button
                                @click="$wire.saveAndInsert(rotate, flipH, flipV, parseInt(brightness), parseInt(contrast), parseInt(quality), greyscale, parseInt(blur), parseInt(sharpen))"
                                class="px-3 py-1.5 bg-green-600 text-white text-sm rounded hover:bg-green-700 disabled:opacity-50 transition-colors"
                                wire:loading.attr="disabled" wire:target="saveAndInsert">
                                <span wire:loading.remove wire:target="saveAndInsert">Insert</span>
                                <span wire:loading wire:target="saveAndInsert">Saving…</span>
                            </button>
                        </div>

                        {{-- Crop mode action buttons --}}
                        <div x-show="cropMode" class="flex items-center gap-2" style="display:none;">
                            <span class="text-xs text-gray-400 dark:text-neutral-500 mr-1"
                                  x-text="cropData.width > 0 ? cropData.width + ' × ' + cropData.height + ' px' : 'Select area'"></span>
                            <button @click="applyCrop()"
                                    :disabled="cropData.width < 1 || cropData.height < 1"
                                    class="px-3 py-1.5 bg-violet-600 text-white text-sm rounded hover:bg-violet-700 disabled:opacity-50 transition-colors"
                                    wire:loading.attr="disabled" wire:target="applyCrop">
                                <span wire:loading.remove wire:target="applyCrop">Apply Crop</span>
                                <span wire:loading wire:target="applyCrop">Cropping…</span>
                            </button>
                            <button @click="cancelCrop()"
                                    class="px-3 py-1.5 bg-gray-500 text-white text-sm rounded hover:bg-gray-600 transition-colors">
                                Cancel
                            </button>
                        </div>
                    </div>
                </div>

                {{-- Editor body --}}
                <div class="flex flex-1 min-h-0">

                    {{-- Preview canvas --}}
                    <div class="flex-1 bg-neutral-900 overflow-hidden relative">
                        {{-- Normal preview --}}
                        <div x-show="!cropMode" class="flex items-center justify-center w-full h-full p-6">
                            <img
                                src="{{ $previewFile['url'] }}"
                                :style="imgStyle"
                                alt="{{ $previewFile['name'] }}"
                                class="select-none"
                                draggable="false"
                            >
                        </div>
                        {{-- Cropper.js canvas --}}
                        <div x-show="cropMode" class="absolute inset-0" style="display:none;">
                            <img
                                x-ref="cropImg"
                                src="{{ $previewFile['url'] }}"
                                alt="{{ $previewFile['name'] }}"
                                style="display:block;max-width:100%;"
                            >
                        </div>
                    </div>

                    {{-- Edit panel --}}
                    <div class="w-64 flex-shrink-0 border-l border-gray-200 dark:border-neutral-700 overflow-y-auto bg-white dark:bg-neutral-800 flex flex-col text-sm">

                        {{-- Crop panel (visible in crop mode) --}}
                        <div x-show="cropMode" class="p-3 border-b border-gray-200 dark:border-neutral-700 bg-violet-50 dark:bg-violet-950/20" style="display:none;">
                            <div class="text-xs font-semibold text-violet-600 dark:text-violet-400 uppercase tracking-wider mb-2">Crop</div>

                            {{-- Aspect ratio presets --}}
                            <div class="mb-3">
                                <div class="text-xs text-gray-500 dark:text-neutral-400 mb-1.5">Aspect Ratio</div>
                                <div class="grid grid-cols-4 gap-1">
                                    <button @click="setCropAspect(NaN)"
                                            :class="isNaN(cropAspect) ? 'ring-1 ring-violet-400 bg-violet-100 dark:bg-violet-900/40 text-violet-700 dark:text-violet-300' : 'bg-gray-100 dark:bg-neutral-700 text-gray-700 dark:text-neutral-200'"
                                            class="px-1 py-1 text-xs rounded transition-colors">Free</button>
                                    <button @click="setCropAspect(1)"
                                            :class="cropAspect === 1 ? 'ring-1 ring-violet-400 bg-violet-100 dark:bg-violet-900/40 text-violet-700 dark:text-violet-300' : 'bg-gray-100 dark:bg-neutral-700 text-gray-700 dark:text-neutral-200'"
                                            class="px-1 py-1 text-xs rounded transition-colors">1:1</button>
                                    <button @click="setCropAspect(16/9)"
                                            :class="Math.abs(cropAspect - 16/9) < 0.01 ? 'ring-1 ring-violet-400 bg-violet-100 dark:bg-violet-900/40 text-violet-700 dark:text-violet-300' : 'bg-gray-100 dark:bg-neutral-700 text-gray-700 dark:text-neutral-200'"
                                            class="px-1 py-1 text-xs rounded transition-colors">16:9</button>
                                    <button @click="setCropAspect(9/16)"
                                            :class="Math.abs(cropAspect - 9/16) < 0.01 ? 'ring-1 ring-violet-400 bg-violet-100 dark:bg-violet-900/40 text-violet-700 dark:text-violet-300' : 'bg-gray-100 dark:bg-neutral-700 text-gray-700 dark:text-neutral-200'"
                                            class="px-1 py-1 text-xs rounded transition-colors">9:16</button>
                                    <button @click="setCropAspect(4/3)"
                                            :class="Math.abs(cropAspect - 4/3) < 0.01 ? 'ring-1 ring-violet-400 bg-violet-100 dark:bg-violet-900/40 text-violet-700 dark:text-violet-300' : 'bg-gray-100 dark:bg-neutral-700 text-gray-700 dark:text-neutral-200'"
                                            class="px-1 py-1 text-xs rounded transition-colors">4:3</button>
                                    <button @click="setCropAspect(3/4)"
                                            :class="Math.abs(cropAspect - 3/4) < 0.01 ? 'ring-1 ring-violet-400 bg-violet-100 dark:bg-violet-900/40 text-violet-700 dark:text-violet-300' : 'bg-gray-100 dark:bg-neutral-700 text-gray-700 dark:text-neutral-200'"
                                            class="px-1 py-1 text-xs rounded transition-colors">3:4</button>
                                    <button @click="setCropAspect(3/2)"
                                            :class="Math.abs(cropAspect - 3/2) < 0.01 ? 'ring-1 ring-violet-400 bg-violet-100 dark:bg-violet-900/40 text-violet-700 dark:text-violet-300' : 'bg-gray-100 dark:bg-neutral-700 text-gray-700 dark:text-neutral-200'"
                                            class="px-1 py-1 text-xs rounded transition-colors">3:2</button>
                                    <button @click="setCropAspect(2/3)"
                                            :class="Math.abs(cropAspect - 2/3) < 0.01 ? 'ring-1 ring-violet-400 bg-violet-100 dark:bg-violet-900/40 text-violet-700 dark:text-violet-300' : 'bg-gray-100 dark:bg-neutral-700 text-gray-700 dark:text-neutral-200'"
                                            class="px-1 py-1 text-xs rounded transition-colors">2:3</button>
                                </div>
                            </div>

                            {{-- Live crop dimensions --}}
                            <div x-show="cropData.width > 0"
                                 class="text-xs font-mono text-gray-500 dark:text-neutral-400 bg-gray-100 dark:bg-neutral-700 rounded px-2 py-1.5 mb-2">
                                <span x-text="cropData.width + ' × ' + cropData.height + ' px'"></span>
                                <span class="text-gray-400 dark:text-neutral-500 ml-1" x-text="'(' + cropData.x + ', ' + cropData.y + ')'"></span>
                            </div>

                            <p class="text-xs text-gray-400 dark:text-neutral-500">Crop overwrites the saved file.</p>
                        </div>

                        {{-- Transform --}}
                        <div class="p-3 border-b border-gray-200 dark:border-neutral-700">
                            <div class="text-xs font-semibold text-gray-400 dark:text-neutral-500 uppercase tracking-wider mb-2">Transform</div>

                            <div class="grid grid-cols-2 gap-1.5 mb-1.5">
                                <button
                                    @click="rotate = (rotate - 90 + 360) % 360"
                                    class="flex items-center justify-center gap-1 px-2 py-1.5 bg-gray-100 dark:bg-neutral-700 hover:bg-gray-200 dark:hover:bg-neutral-600 rounded text-gray-700 dark:text-neutral-200 text-xs transition-colors">
                                    ↺ Rotate L
                                </button>
                                <button
                                    @click="rotate = (rotate + 90) % 360"
                                    class="flex items-center justify-center gap-1 px-2 py-1.5 bg-gray-100 dark:bg-neutral-700 hover:bg-gray-200 dark:hover:bg-neutral-600 rounded text-gray-700 dark:text-neutral-200 text-xs transition-colors">
                                    ↻ Rotate R
                                </button>
                            </div>

                            <div class="grid grid-cols-2 gap-1.5">
                                <button
                                    @click="flipH = !flipH"
                                    :class="flipH ? 'bg-blue-100 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300 ring-1 ring-blue-300' : 'bg-gray-100 dark:bg-neutral-700 text-gray-700 dark:text-neutral-200'"
                                    class="flex items-center justify-center gap-1 px-2 py-1.5 rounded text-xs transition-colors">
                                    ↔ Flip H
                                </button>
                                <button
                                    @click="flipV = !flipV"
                                    :class="flipV ? 'bg-blue-100 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300 ring-1 ring-blue-300' : 'bg-gray-100 dark:bg-neutral-700 text-gray-700 dark:text-neutral-200'"
                                    class="flex items-center justify-center gap-1 px-2 py-1.5 rounded text-xs transition-colors">
                                    ↕ Flip V
                                </button>
                            </div>

                            <div class="mt-2 text-center text-xs text-gray-400 dark:text-neutral-500" x-text="rotate + '°'"></div>
                        </div>

                        {{-- Resize --}}
                        <div class="p-3 border-b border-gray-200 dark:border-neutral-700">
                            <div class="text-xs font-semibold text-gray-400 dark:text-neutral-500 uppercase tracking-wider mb-2">Resize</div>
                            <div class="grid grid-cols-2 gap-2 mb-2">
                                <div>
                                    <label class="block text-xs text-gray-500 dark:text-neutral-400 mb-1">Width (px)</label>
                                    <input type="number" wire:model.blur="editResizeWidth" min="1" max="10000"
                                           class="w-full px-2 py-1 text-xs border border-gray-300 dark:border-neutral-600 rounded bg-white dark:bg-neutral-700 text-gray-900 dark:text-white focus:outline-none focus:ring-1 focus:ring-blue-500">
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-500 dark:text-neutral-400 mb-1">Height (px)</label>
                                    <input type="number" wire:model.blur="editResizeHeight" min="1" max="10000"
                                           class="w-full px-2 py-1 text-xs border border-gray-300 dark:border-neutral-600 rounded bg-white dark:bg-neutral-700 text-gray-900 dark:text-white focus:outline-none focus:ring-1 focus:ring-blue-500">
                                </div>
                            </div>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" wire:model="editKeepAspect" class="rounded border-gray-300 dark:border-neutral-600 text-blue-600">
                                <span class="text-xs text-gray-600 dark:text-neutral-400">Keep aspect ratio</span>
                            </label>
                        </div>

                        {{-- Adjustments --}}
                        <div class="p-3 border-b border-gray-200 dark:border-neutral-700">
                            <div class="text-xs font-semibold text-gray-400 dark:text-neutral-500 uppercase tracking-wider mb-3">Adjustments</div>

                            <div class="mb-3">
                                <div class="flex items-center justify-between mb-1">
                                    <label class="text-xs text-gray-600 dark:text-neutral-400">Brightness</label>
                                    <span class="text-xs font-mono text-gray-500 dark:text-neutral-400"
                                          x-text="brightness > 0 ? '+'+brightness : brightness"></span>
                                </div>
                                <input type="range" min="-100" max="100" step="5"
                                       x-model="brightness"
                                       class="w-full accent-blue-600 cursor-pointer">
                                <div class="flex justify-between text-xs text-gray-300 dark:text-neutral-600 mt-0.5">
                                    <span>Dark</span><span>Bright</span>
                                </div>
                            </div>

                            <div>
                                <div class="flex items-center justify-between mb-1">
                                    <label class="text-xs text-gray-600 dark:text-neutral-400">Contrast</label>
                                    <span class="text-xs font-mono text-gray-500 dark:text-neutral-400"
                                          x-text="contrast > 0 ? '+'+contrast : contrast"></span>
                                </div>
                                <input type="range" min="-100" max="100" step="5"
                                       x-model="contrast"
                                       class="w-full accent-blue-600 cursor-pointer">
                                <div class="flex justify-between text-xs text-gray-300 dark:text-neutral-600 mt-0.5">
                                    <span>Flat</span><span>Sharp</span>
                                </div>
                            </div>
                        </div>

                        {{-- Effects --}}
                        <div class="p-3 border-b border-gray-200 dark:border-neutral-700">
                            <div class="text-xs font-semibold text-gray-400 dark:text-neutral-500 uppercase tracking-wider mb-3">Effects</div>

                            {{-- Greyscale --}}
                            <div class="mb-3">
                                <button
                                    @click="greyscale = !greyscale"
                                    :class="greyscale ? 'bg-blue-100 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300 ring-1 ring-blue-300' : 'bg-gray-100 dark:bg-neutral-700 text-gray-700 dark:text-neutral-200'"
                                    class="w-full flex items-center justify-center gap-2 px-3 py-1.5 rounded text-xs transition-colors">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9" stroke-width="2"/><path d="M12 3v18M3 12h18" stroke-width="2"/></svg>
                                    <span x-text="greyscale ? 'Greyscale ON' : 'Greyscale'"></span>
                                </button>
                            </div>

                            {{-- Blur --}}
                            <div class="mb-3">
                                <div class="flex items-center justify-between mb-1">
                                    <label class="text-xs text-gray-600 dark:text-neutral-400">Blur</label>
                                    <span class="text-xs font-mono text-gray-500 dark:text-neutral-400" x-text="blur > 0 ? blur : 'Off'"></span>
                                </div>
                                <input type="range" min="0" max="20" step="1"
                                       x-model="blur"
                                       class="w-full accent-blue-600 cursor-pointer">
                                <div class="flex justify-between text-xs text-gray-300 dark:text-neutral-600 mt-0.5">
                                    <span>None</span><span>Strong</span>
                                </div>
                            </div>

                            {{-- Sharpen --}}
                            <div>
                                <div class="flex items-center justify-between mb-1">
                                    <label class="text-xs text-gray-600 dark:text-neutral-400">Sharpen</label>
                                    <span class="text-xs font-mono text-gray-500 dark:text-neutral-400" x-text="sharpen > 0 ? sharpen : 'Off'"></span>
                                </div>
                                <input type="range" min="0" max="100" step="5"
                                       x-model="sharpen"
                                       class="w-full accent-blue-600 cursor-pointer">
                                <div class="flex justify-between text-xs text-gray-300 dark:text-neutral-600 mt-0.5">
                                    <span>None</span><span>Sharp</span>
                                </div>
                                <p x-show="sharpen > 0" class="mt-1 text-xs text-amber-500 dark:text-amber-400">Applies on save</p>
                            </div>
                        </div>

                        {{-- Quality --}}
                        <div class="p-3 border-b border-gray-200 dark:border-neutral-700">
                            <div class="text-xs font-semibold text-gray-400 dark:text-neutral-500 uppercase tracking-wider mb-2">Output Quality</div>
                            <div class="flex items-center justify-between mb-1">
                                <span class="text-xs text-gray-500 dark:text-neutral-400">JPEG / WebP</span>
                                <span class="text-xs font-mono text-gray-500 dark:text-neutral-400" x-text="quality + '%'"></span>
                            </div>
                            <input type="range" min="10" max="100" step="5"
                                   x-model="quality"
                                   class="w-full accent-blue-600 cursor-pointer">
                            <div class="flex justify-between text-xs text-gray-300 dark:text-neutral-600 mt-0.5">
                                <span>Smaller</span><span>Best</span>
                            </div>
                        </div>

                        {{-- File info --}}
                        <div class="p-3 mt-auto text-xs text-gray-400 dark:text-neutral-500">
                            <div class="font-medium text-gray-600 dark:text-neutral-300 mb-1 truncate">{{ $previewFile['name'] }}</div>
                            <div>{{ number_format($previewFile['size'] / 1024, 1) }} KB</div>
                            @if($originalDimensions)
                            <div>{{ $originalDimensions['width'] }} × {{ $originalDimensions['height'] }} px</div>
                            @endif
                            <div class="mt-1">{{ date('M j, Y', $previewFile['modified']) }}</div>
                        </div>

                    </div>{{-- /edit panel --}}
                </div>{{-- /editor body --}}
            </div>{{-- /x-data editor --}}

            @else
            {{-- ════════════════════════════════════ --}}
            {{-- NORMAL FILE BROWSER                  --}}
            {{-- ════════════════════════════════════ --}}


            {{-- Toolbar --}}
            <div class="flex items-center justify-between p-4 border-b border-gray-200 dark:border-neutral-700 bg-gray-50 dark:bg-neutral-900/40 flex-shrink-0">
                <div class="flex items-center space-x-4">
                    @if($currentPath !== $rootPath)
                    <button wire:click="goBack" class="px-3 py-1 bg-blue-500 text-white rounded hover:bg-blue-600 text-sm">
                        ← Back
                    </button>
                    @endif
                    <span class="text-sm text-gray-600 dark:text-neutral-400 font-mono">
                        /uploads/{{ $currentPath }}
                    </span>
                </div>

                <div class="flex items-center space-x-4">
                    <input
                        wire:model.live="searchTerm"
                        type="text"
                        placeholder="Search files..."
                        class="px-3 py-1 text-sm border border-gray-300 dark:border-neutral-600 rounded bg-white dark:bg-neutral-700 text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-neutral-500 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:focus:ring-indigo-500"
                    >
                    <div class="flex border border-gray-300 dark:border-neutral-600 rounded overflow-hidden">
                        <button wire:click="$set('viewMode', 'grid')"
                            class="px-3 py-1 text-sm {{ $viewMode === 'grid' ? 'bg-blue-500 text-white' : 'bg-white dark:bg-neutral-700 text-gray-700 dark:text-neutral-200 hover:bg-gray-50 dark:hover:bg-neutral-600' }}">
                            Grid
                        </button>
                        <button wire:click="$set('viewMode', 'list')"
                            class="px-3 py-1 text-sm {{ $viewMode === 'list' ? 'bg-blue-500 text-white' : 'bg-white dark:bg-neutral-700 text-gray-700 dark:text-neutral-200 hover:bg-gray-50 dark:hover:bg-neutral-600' }}">
                            List
                        </button>
                    </div>
                </div>
            </div>

            {{-- Upload Area --}}
            <div class="p-4 border-b border-gray-200 dark:border-neutral-700 bg-gray-50 dark:bg-neutral-900/40 flex-shrink-0">
                <div class="flex items-center space-x-4">
                    <input type="file" wire:model="uploadedFiles" multiple class="hidden" id="fileInput"
                        @if($mediaType === 'image') accept="image/*" @endif
                        @if($mediaType === 'media') accept="video/*,audio/*" @endif
                        @if($mediaType === 'file') accept=".pdf,.doc,.docx,.txt,.rtf" @endif>
                    <label for="fileInput" class="px-4 py-2 bg-green-500 text-white rounded cursor-pointer hover:bg-green-600 text-sm">
                        Upload {{ $mediaType === 'all' ? 'Files' : ucfirst($mediaType) . 's' }}
                    </label>
                    <button wire:click="$set('showCreateFolder', true)"
                        class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600 text-sm">
                        New Folder
                    </button>
                    <button wire:click="toggleAiGenerator"
                        class="px-4 py-2 rounded text-sm font-medium flex items-center gap-1.5
                            {{ $showAiGenerator ? 'bg-violet-600 text-white' : 'bg-violet-100 dark:bg-violet-900/30 text-violet-700 dark:text-violet-300 hover:bg-violet-200 dark:hover:bg-violet-800/40' }}">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z"/>
                        </svg>
                        AI Generate
                    </button>
                    @if($uploadedFiles)
                    <span class="text-sm text-gray-600 dark:text-neutral-400">{{ count($uploadedFiles) }} file(s) selected</span>
                    @endif
                </div>

                <div wire:loading wire:target="uploadedFiles" class="mt-2 text-sm text-blue-600 dark:text-blue-400">
                    Uploading files…
                </div>

                @if($showCreateFolder)
                <div class="mt-4 p-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded">
                    <h4 class="text-sm font-medium text-gray-900 dark:text-white mb-3">Create New Folder</h4>
                    <div class="flex items-center space-x-3">
                        <input wire:model.defer="newFolderName" type="text" placeholder="Folder name…"
                               class="flex-1 px-3 py-2 text-sm border border-gray-300 dark:border-neutral-600 rounded bg-white dark:bg-neutral-700 text-gray-900 dark:text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500"
                               wire:keydown.enter="createFolder" autofocus>
                        <button wire:click="createFolder" class="px-4 py-2 text-sm bg-blue-500 text-white rounded hover:bg-blue-600 disabled:opacity-50"
                                wire:loading.attr="disabled" wire:target="createFolder">
                            <span wire:loading.remove wire:target="createFolder">Create</span>
                            <span wire:loading wire:target="createFolder">Creating…</span>
                        </button>
                        <button wire:click="cancelCreateFolder" class="px-4 py-2 text-sm bg-gray-500 dark:bg-neutral-600 text-white rounded hover:bg-gray-600 dark:hover:bg-neutral-500">
                            Cancel
                        </button>
                    </div>
                    @error('newFolderName')
                    <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>
                @endif

                {{-- AI Image Generator Panel --}}
                @if($showAiGenerator)
                <div class="mt-4 p-4 bg-violet-50 dark:bg-violet-900/20 border border-violet-200 dark:border-violet-800 rounded-lg">
                    <div class="flex items-center gap-2 mb-3">
                        <svg class="w-4 h-4 text-violet-600 dark:text-violet-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z"/>
                        </svg>
                        <h4 class="text-sm font-medium text-violet-900 dark:text-violet-200">Generate Image with AI</h4>
                        <select wire:model.live="aiProvider" class="ml-2 text-xs border border-violet-300 dark:border-violet-700 rounded px-2 py-0.5 bg-white dark:bg-neutral-700 text-violet-700 dark:text-violet-300">
                            <option value="cloudflare">Cloudflare Workers AI (FLUX)</option>
                            <option value="huggingface">Hugging Face FLUX</option>
                            <option value="picsart">Picsart Gen AI</option>
                        </select>

                        @if($aiQuota)
                            <span class="ml-3 text-xs text-violet-600 dark:text-violet-400 flex items-center gap-1.5">
                                @if($aiQuota['pct'] !== null)
                                    <span class="inline-block w-16 h-1.5 rounded-full bg-violet-200 dark:bg-violet-800 overflow-hidden">
                                        <span class="block h-full rounded-full {{ $aiQuota['pct'] > 20 ? 'bg-violet-500' : 'bg-red-500' }}"
                                              style="width: {{ $aiQuota['pct'] }}%"></span>
                                    </span>
                                @endif
                                {{ $aiQuota['label'] }}
                            </span>
                        @endif
                    </div>

                    <div class="flex items-center gap-2 mb-3">
                        <label class="text-xs text-violet-700 dark:text-violet-300 font-medium shrink-0">Size</label>
                        <select wire:model="aiImageSize" class="text-xs border border-violet-300 dark:border-violet-700 rounded px-2 py-1 bg-white dark:bg-neutral-700 text-gray-800 dark:text-neutral-200">
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
                            placeholder="Describe the image you want to generate… e.g. 'A serene mountain lake at sunrise with mist rising from the water'"
                            class="flex-1 px-3 py-2 text-sm border border-violet-300 dark:border-violet-700 rounded bg-white dark:bg-neutral-700 text-gray-900 dark:text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-violet-500 resize-none"
                            wire:keydown.ctrl.enter="generateAiImage"
                        ></textarea>
                        <div class="flex flex-col gap-2 shrink-0">
                            <button
                                wire:click="generateAiImage"
                                wire:loading.attr="disabled"
                                wire:target="generateAiImage"
                                class="px-4 py-2 text-sm font-medium bg-violet-600 text-white rounded hover:bg-violet-700 disabled:opacity-60 flex items-center gap-1.5 whitespace-nowrap">
                                <span wire:loading.remove wire:target="generateAiImage">
                                    <svg class="w-3.5 h-3.5 inline -mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z"/>
                                    </svg>
                                    Generate
                                </span>
                                <span wire:loading wire:target="generateAiImage">Generating…</span>
                            </button>
                            <button wire:click="toggleAiGenerator"
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

                    <p class="mt-2 text-xs text-violet-500 dark:text-violet-400">
                        Images saved to <code class="font-mono">uploads/images/</code> &middot; Ctrl+Enter to generate
                    </p>
                </div>
                @endif
            </div>

            {{-- Multi-select toolbar --}}
            @if(count($selectedFiles) > 0)
            <div class="flex items-center justify-between px-4 py-2 border-b border-blue-200 dark:border-blue-800 bg-blue-50 dark:bg-blue-900/20 flex-shrink-0">
                <div class="flex items-center gap-3">
                    <span class="text-sm font-medium text-blue-700 dark:text-blue-300">
                        {{ count($selectedFiles) }} file(s) selected
                    </span>
                    <button wire:click="selectAllVisible"
                            class="text-xs text-blue-600 dark:text-blue-400 hover:underline">
                        Select all on page
                    </button>
                    <button wire:click="deselectAll"
                            class="text-xs text-gray-500 dark:text-neutral-400 hover:underline">
                        Deselect all
                    </button>
                </div>
                <button wire:click="confirmBulkDelete"
                        class="px-3 py-1.5 bg-red-600 text-white text-sm rounded hover:bg-red-700 transition-colors flex items-center gap-1.5">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                    </svg>
                    Delete Selected
                </button>
            </div>
            @endif

            {{-- File Browser with drag-and-drop --}}
            <div class="flex-1 overflow-auto p-4 bg-white dark:bg-neutral-800 relative"
                 x-data="{ dragging: false }"
                 x-on:dragover.prevent="dragging = true"
                 x-on:dragleave.self="dragging = false"
                 x-on:drop.prevent="
                    dragging = false;
                    const input = document.getElementById('fileInput');
                    if (input && $event.dataTransfer.files.length) {
                        input.files = $event.dataTransfer.files;
                        input.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                 ">

                {{-- Drag overlay --}}
                <div x-show="dragging" x-transition.opacity
                     class="absolute inset-0 z-10 flex items-center justify-center bg-blue-500/10 dark:bg-blue-400/10 border-2 border-dashed border-blue-400 dark:border-blue-500 rounded-lg pointer-events-none"
                     style="display: none;">
                    <div class="text-center">
                        <svg class="w-12 h-12 mx-auto text-blue-500 dark:text-blue-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                        </svg>
                        <p class="text-sm font-medium text-blue-600 dark:text-blue-400">Drop files here to upload</p>
                    </div>
                </div>

                @if($viewMode === 'grid')
                {{-- Grid View --}}
                <div class="grid grid-cols-6 gap-4">
                    @foreach($folders as $folder)
                    <div wire:click="navigateToFolder('{{ $folder['path'] }}')"
                         class="flex flex-col items-center p-3 border border-gray-200 dark:border-neutral-700 rounded cursor-pointer hover:bg-gray-100 dark:hover:bg-neutral-700 transition-colors">
                        <svg class="w-12 h-12 text-yellow-500 mb-2" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M2 6a2 2 0 012-2h5l2 2h5a2 2 0 012 2v6a2 2 0 01-2 2H4a2 2 0 01-2-2V6z"></path>
                        </svg>
                        <span class="text-sm text-center text-gray-700 dark:text-neutral-200">{{ $folder['name'] }}</span>
                    </div>
                    @endforeach

                    @foreach($files as $file)
                    <div class="flex flex-col items-center p-3 border rounded cursor-pointer hover:bg-gray-100 dark:hover:bg-neutral-700 transition-colors group relative
                                {{ in_array($file['path'], $selectedFiles) ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20' : 'border-gray-200 dark:border-neutral-700' }}">
                        {{-- Selection checkbox --}}
                        <label class="absolute top-1.5 left-1.5 z-[1]" @click.stop>
                            <input type="checkbox"
                                   wire:click="toggleSelectFile('{{ $file['path'] }}')"
                                   @checked(in_array($file['path'], $selectedFiles))
                                   class="w-4 h-4 rounded border-gray-300 dark:border-neutral-600 text-blue-600 focus:ring-blue-500 opacity-0 group-hover:opacity-100 transition-opacity {{ in_array($file['path'], $selectedFiles) ? '!opacity-100' : '' }}">
                        </label>

                        <div @if($file['type'] === 'image')
                                wire:click="previewImage({{ json_encode($file) }})"
                            @else
                                wire:click="selectFile({{ json_encode($file) }})"
                            @endif
                            class="flex flex-col items-center w-full">
                            @if($file['thumbnail'])
                            <img src="{{ $file['thumbnail'] }}" alt="{{ $file['name'] }}" class="w-32 h-16 object-cover mb-2 rounded border border-gray-200 dark:border-neutral-600">
                            @else
                            <div class="w-16 h-16 mb-2 flex items-center justify-center">
                                {!! $this->getFileIcon($file['type']) !!}
                            </div>
                            @endif
                            <span class="text-sm text-center leading-tight text-gray-700 dark:text-neutral-200">{{ Str::limit($file['name'], 20) }}</span>
                            <span class="text-xs text-gray-500 dark:text-neutral-400 mt-1">{{ number_format($file['size'] / 1024, 1) }}KB</span>
                        </div>

                        @if($file['type'] === 'image')
                        <span class="absolute top-1 right-1 text-xs bg-blue-500/80 text-white px-1 rounded opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none">Edit</span>
                        @endif

                        <button wire:click.stop="confirmDelete({{ json_encode($file) }})"
                                class="mt-2 px-2 py-1 bg-red-500 text-white text-xs rounded opacity-0 group-hover:opacity-100 transition-opacity">
                            Delete
                        </button>
                    </div>
                    @endforeach
                </div>

                @else
                {{-- List View --}}
                <div class="space-y-1">
                    @foreach($folders as $folder)
                    <div wire:click="navigateToFolder('{{ $folder['path'] }}')"
                         class="flex items-center p-3 border border-gray-200 dark:border-neutral-700 rounded cursor-pointer hover:bg-gray-100 dark:hover:bg-neutral-700 transition-colors">
                        <svg class="w-6 h-6 text-yellow-500 mr-3 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M2 6a2 2 0 012-2h5l2 2h5a2 2 0 012 2v6a2 2 0 01-2 2H4a2 2 0 01-2-2V6z"></path>
                        </svg>
                        <span class="text-sm text-gray-700 dark:text-neutral-200">{{ $folder['name'] }}</span>
                    </div>
                    @endforeach

                    @foreach($files as $file)
                    <div class="flex items-center justify-between p-3 border rounded hover:bg-gray-100 dark:hover:bg-neutral-700 transition-colors group
                                {{ in_array($file['path'], $selectedFiles) ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20' : 'border-gray-200 dark:border-neutral-700' }}">
                        {{-- Selection checkbox --}}
                        <label class="mr-3 flex-shrink-0" @click.stop>
                            <input type="checkbox"
                                   wire:click="toggleSelectFile('{{ $file['path'] }}')"
                                   @checked(in_array($file['path'], $selectedFiles))
                                   class="w-4 h-4 rounded border-gray-300 dark:border-neutral-600 text-blue-600 focus:ring-blue-500">
                        </label>

                        <div
                            @if($file['type'] === 'image')
                                wire:click="previewImage({{ json_encode($file) }})"
                            @else
                                wire:click="selectFile({{ json_encode($file) }})"
                            @endif
                            class="flex items-center cursor-pointer flex-1 min-w-0">
                            @if($file['thumbnail'])
                            <img src="{{ $file['thumbnail'] }}" alt="{{ $file['name'] }}" class="w-10 h-10 object-cover mr-3 rounded border border-gray-200 dark:border-neutral-600 flex-shrink-0">
                            @else
                            <div class="w-10 h-10 mr-3 flex items-center justify-center bg-gray-100 dark:bg-neutral-700 rounded flex-shrink-0">
                                {!! str_replace(['w-12 h-12'], ['w-6 h-6'], $this->getFileIcon($file['type'])) !!}
                            </div>
                            @endif
                            <div class="min-w-0">
                                <div class="text-sm font-medium text-gray-900 dark:text-neutral-100 truncate">{{ $file['name'] }}</div>
                                <div class="text-xs text-gray-500 dark:text-neutral-400">
                                    {{ number_format($file['size'] / 1024, 2) }} KB &middot;
                                    {{ date('M j, Y', $file['modified']) }}
                                    @if($file['type'] === 'image')
                                    <span class="ml-1 text-blue-500 dark:text-blue-400">· Click to edit</span>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <button wire:click="confirmDelete({{ json_encode($file) }})"
                                class="ml-3 px-3 py-1 bg-red-500 text-white text-xs rounded opacity-0 group-hover:opacity-100 transition-opacity flex-shrink-0">
                            Delete
                        </button>
                    </div>
                    @endforeach
                </div>
                @endif

                @if(empty($files) && empty($folders))
                <div class="text-center text-gray-500 dark:text-neutral-400 py-12">
                    @if($searchTerm)
                        No {{ $mediaType === 'all' ? 'files' : $mediaType . 's' }} match your search.
                    @else
                        @if($mediaType !== 'all')
                            No {{ $mediaType }}s found in this folder.
                            <br><small class="text-xs">Try uploading some {{ $mediaType }}s or navigate to a different folder.</small>
                        @else
                            <div class="flex flex-col items-center">
                                <svg class="w-16 h-16 text-gray-300 dark:text-neutral-600 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                                </svg>
                                <p>This folder is empty.</p>
                                <p class="text-xs mt-1">Drag and drop files here or click Upload.</p>
                            </div>
                        @endif
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
                    <button wire:click="goToPage(1)"
                            @disabled($currentPage <= 1)
                            class="px-2 py-1 text-xs rounded border border-gray-300 dark:border-neutral-600 bg-white dark:bg-neutral-700 text-gray-700 dark:text-neutral-200 hover:bg-gray-100 dark:hover:bg-neutral-600 disabled:opacity-40 disabled:cursor-not-allowed">
                        &laquo;
                    </button>
                    <button wire:click="goToPage({{ $currentPage - 1 }})"
                            @disabled($currentPage <= 1)
                            class="px-2 py-1 text-xs rounded border border-gray-300 dark:border-neutral-600 bg-white dark:bg-neutral-700 text-gray-700 dark:text-neutral-200 hover:bg-gray-100 dark:hover:bg-neutral-600 disabled:opacity-40 disabled:cursor-not-allowed">
                        &lsaquo;
                    </button>

                    @php
                        $totalPages = $this->getTotalPages();
                        $start = max(1, $currentPage - 2);
                        $end = min($totalPages, $currentPage + 2);
                    @endphp
                    @for($p = $start; $p <= $end; $p++)
                    <button wire:click="goToPage({{ $p }})"
                            class="px-2.5 py-1 text-xs rounded border {{ $p === $currentPage ? 'bg-blue-500 text-white border-blue-500' : 'border-gray-300 dark:border-neutral-600 bg-white dark:bg-neutral-700 text-gray-700 dark:text-neutral-200 hover:bg-gray-100 dark:hover:bg-neutral-600' }}">
                        {{ $p }}
                    </button>
                    @endfor

                    <button wire:click="goToPage({{ $currentPage + 1 }})"
                            @disabled($currentPage >= $totalPages)
                            class="px-2 py-1 text-xs rounded border border-gray-300 dark:border-neutral-600 bg-white dark:bg-neutral-700 text-gray-700 dark:text-neutral-200 hover:bg-gray-100 dark:hover:bg-neutral-600 disabled:opacity-40 disabled:cursor-not-allowed">
                        &rsaquo;
                    </button>
                    <button wire:click="goToPage({{ $totalPages }})"
                            @disabled($currentPage >= $totalPages)
                            class="px-2 py-1 text-xs rounded border border-gray-300 dark:border-neutral-600 bg-white dark:bg-neutral-700 text-gray-700 dark:text-neutral-200 hover:bg-gray-100 dark:hover:bg-neutral-600 disabled:opacity-40 disabled:cursor-not-allowed">
                        &raquo;
                    </button>
                </div>
            </div>
            @endif
            @endif {{-- /previewFile @else --}}

        </div>{{-- /modal panel --}}
    </div>{{-- /modal overlay --}}
    @endif

    {{-- Delete Confirmation Modal (single file) --}}
    @php $deleteTarget = $fileToDelete ?? $previewFile ?? null; @endphp
    <x-confirm-modal
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
                {!! str_replace(['w-12 h-12'], ['w-8 h-8'], $this->getFileIcon($deleteTarget['type'])) !!}
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
    </x-confirm-modal>

    {{-- Bulk Delete Confirmation Modal --}}
    <x-confirm-modal
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
    </x-confirm-modal>

    @if (session()->has('message'))
    <div class="fixed top-4 right-4 bg-green-500 text-white px-4 py-2 rounded shadow-lg text-sm" style="z-index: 1000000;">
        {{ session('message') }}
    </div>
    @endif

    @if (session()->has('error'))
    <div class="fixed top-4 right-4 bg-red-500 text-white px-4 py-2 rounded shadow-lg text-sm" style="z-index: 1000000;">
        {{ session('error') }}
    </div>
    @endif

    @once
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.js"></script>
    @endonce
</div>
