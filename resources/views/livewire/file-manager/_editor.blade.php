{{-- Image editor panel — shown when $previewFile is set --}}
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
        <button type="button" wire:click="backToGallery"
                class="flex items-center gap-1 text-sm px-3 py-1.5 bg-amber-600 text-white rounded hover:bg-amber-700 disabled:opacity-50 transition-colors">
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
            {{-- Normal mode buttons --}}
            <div x-show="!cropMode" class="flex items-center gap-2">
                @if($isAiGeneratedPreview)
                <button type="button" wire:click="regenerateAiImage"
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
                <button type="button"
                    @click="$wire.saveOverwrite(rotate, flipH, flipV, parseInt(brightness), parseInt(contrast), parseInt(quality), greyscale, parseInt(blur), parseInt(sharpen))"
                    class="px-3 py-1.5 bg-amber-600 text-white text-sm rounded hover:bg-amber-700 disabled:opacity-50 transition-colors"
                    wire:loading.attr="disabled" wire:target="saveOverwrite">
                    <span wire:loading.remove wire:target="saveOverwrite">Save</span>
                    <span wire:loading wire:target="saveOverwrite">Saving…</span>
                </button>
                <button type="button"
                    @click="$wire.saveEditedCopy(rotate, flipH, flipV, parseInt(brightness), parseInt(contrast), parseInt(quality), greyscale, parseInt(blur), parseInt(sharpen))"
                    class="px-3 py-1.5 bg-indigo-600 text-white text-sm rounded hover:bg-indigo-700 disabled:opacity-50 transition-colors"
                    wire:loading.attr="disabled" wire:target="saveEditedCopy">
                    <span wire:loading.remove wire:target="saveEditedCopy">Save Copy</span>
                    <span wire:loading wire:target="saveEditedCopy">Saving…</span>
                </button>
                <button type="button" @click="enableCrop()"
                        class="px-3 py-1.5 bg-sky-600 text-white text-sm rounded hover:bg-sky-700 transition-colors flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 3v4m0 0H3m4 0h10v10m0 0h4m-4 0V7M7 17H3"/>
                    </svg>
                    Crop
                </button>
                <button type="button" wire:click="confirmDeletePreview"
                        class="px-3 py-1.5 bg-red-600 text-white text-sm rounded hover:bg-red-700 transition-colors">
                    Delete
                </button>
                <button type="button"
                    @click="$wire.saveAndInsert(rotate, flipH, flipV, parseInt(brightness), parseInt(contrast), parseInt(quality), greyscale, parseInt(blur), parseInt(sharpen))"
                    class="px-3 py-1.5 bg-green-600 text-white text-sm rounded hover:bg-green-700 disabled:opacity-50 transition-colors"
                    wire:loading.attr="disabled" wire:target="saveAndInsert">
                    <span wire:loading.remove wire:target="saveAndInsert">Insert</span>
                    <span wire:loading wire:target="saveAndInsert">Saving…</span>
                </button>
            </div>

            {{-- Crop mode buttons --}}
            <div x-show="cropMode" class="flex items-center gap-2" style="display:none;">
                <span class="text-xs text-gray-400 dark:text-neutral-500 mr-1"
                      x-text="cropData.width > 0 ? cropData.width + ' × ' + cropData.height + ' px' : 'Select area'"></span>
                <button type="button" @click="applyCrop()"
                        :disabled="cropData.width < 1 || cropData.height < 1"
                        class="px-3 py-1.5 bg-sky-600 text-white text-sm rounded hover:bg-sky-700 disabled:opacity-50 transition-colors"
                        wire:loading.attr="disabled" wire:target="applyCrop">
                    <span wire:loading.remove wire:target="applyCrop">Apply Crop</span>
                    <span wire:loading wire:target="applyCrop">Cropping…</span>
                </button>
                <button type="button" @click="cancelCrop()"
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
            <div x-show="!cropMode" class="flex items-center justify-center w-full h-full p-6">
                <img
                    src="{{ $previewFile['url'] }}"
                    :style="imgStyle"
                    alt="{{ $previewFile['name'] }}"
                    class="select-none"
                    draggable="false"
                >
            </div>
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

            {{-- Crop preset panel --}}
            <div x-show="cropMode" class="p-3 border-b border-gray-200 dark:border-neutral-700 bg-sky-50 dark:bg-sky-950/20" style="display:none;">
                <div class="text-xs font-semibold text-sky-600 dark:text-sky-400 uppercase tracking-wider mb-2">Crop</div>
                <div class="mb-3">
                    <div class="text-xs text-gray-500 dark:text-neutral-400 mb-1.5">Aspect Ratio</div>
                    <div class="grid grid-cols-4 gap-1">
                        @foreach([
                            ['label' => 'Free', 'value' => 'NaN',  'check' => 'isNaN(cropAspect)'],
                            ['label' => '1:1',  'value' => '1',    'check' => 'cropAspect === 1'],
                            ['label' => '16:9', 'value' => '16/9', 'check' => 'Math.abs(cropAspect - 16/9) < 0.01'],
                            ['label' => '9:16', 'value' => '9/16', 'check' => 'Math.abs(cropAspect - 9/16) < 0.01'],
                            ['label' => '4:3',  'value' => '4/3',  'check' => 'Math.abs(cropAspect - 4/3) < 0.01'],
                            ['label' => '3:4',  'value' => '3/4',  'check' => 'Math.abs(cropAspect - 3/4) < 0.01'],
                            ['label' => '3:2',  'value' => '3/2',  'check' => 'Math.abs(cropAspect - 3/2) < 0.01'],
                            ['label' => '2:3',  'value' => '2/3',  'check' => 'Math.abs(cropAspect - 2/3) < 0.01'],
                        ] as $preset)
                        <button type="button"
                                @click="setCropAspect({{ $preset['value'] }})"
                                :class="{{ $preset['check'] }} ? 'ring-1 ring-sky-400 bg-sky-100 dark:bg-sky-900/40 text-sky-700 dark:text-sky-300' : 'bg-gray-100 dark:bg-neutral-700 text-gray-700 dark:text-neutral-200'"
                                class="px-1 py-1 text-xs rounded transition-colors">{{ $preset['label'] }}</button>
                        @endforeach
                    </div>
                </div>
                <div x-show="cropData.width > 0" class="text-xs font-mono text-gray-500 dark:text-neutral-400 bg-gray-100 dark:bg-neutral-700 rounded px-2 py-1.5 mb-2">
                    <span x-text="cropData.width + ' × ' + cropData.height + ' px'"></span>
                    <span class="text-gray-400 dark:text-neutral-500 ml-1" x-text="'(' + cropData.x + ', ' + cropData.y + ')'"></span>
                </div>
                <p class="text-xs text-gray-400 dark:text-neutral-500">Crop overwrites the saved file.</p>
            </div>

            {{-- Transform --}}
            <div class="p-3 border-b border-gray-200 dark:border-neutral-700">
                <div class="text-xs font-semibold text-gray-400 dark:text-neutral-500 uppercase tracking-wider mb-2">Transform</div>
                <div class="grid grid-cols-2 gap-1.5 mb-1.5">
                    <button type="button" @click="rotate = (rotate - 90 + 360) % 360"
                        class="flex items-center justify-center gap-1 px-2 py-1.5 bg-gray-100 dark:bg-neutral-700 hover:bg-gray-200 dark:hover:bg-neutral-600 rounded text-gray-700 dark:text-neutral-200 text-xs transition-colors">
                        ↺ Rotate L
                    </button>
                    <button type="button" @click="rotate = (rotate + 90) % 360"
                        class="flex items-center justify-center gap-1 px-2 py-1.5 bg-gray-100 dark:bg-neutral-700 hover:bg-gray-200 dark:hover:bg-neutral-600 rounded text-gray-700 dark:text-neutral-200 text-xs transition-colors">
                        ↻ Rotate R
                    </button>
                </div>
                <div class="grid grid-cols-2 gap-1.5">
                    <button type="button" @click="flipH = !flipH"
                        :class="flipH ? 'bg-indigo-100 dark:bg-indigo-900/40 text-indigo-700 dark:text-indigo-300 ring-1 ring-indigo-300' : 'bg-gray-100 dark:bg-neutral-700 text-gray-700 dark:text-neutral-200'"
                        class="flex items-center justify-center gap-1 px-2 py-1.5 rounded text-xs transition-colors">
                        ↔ Flip H
                    </button>
                    <button type="button" @click="flipV = !flipV"
                        :class="flipV ? 'bg-indigo-100 dark:bg-indigo-900/40 text-indigo-700 dark:text-indigo-300 ring-1 ring-indigo-300' : 'bg-gray-100 dark:bg-neutral-700 text-gray-700 dark:text-neutral-200'"
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
                               class="w-full px-2 py-1 text-xs border border-gray-300 dark:border-neutral-600 rounded bg-white dark:bg-neutral-700 text-gray-900 dark:text-white focus:outline-none focus:ring-1 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 dark:text-neutral-400 mb-1">Height (px)</label>
                        <input type="number" wire:model.blur="editResizeHeight" min="1" max="10000"
                               class="w-full px-2 py-1 text-xs border border-gray-300 dark:border-neutral-600 rounded bg-white dark:bg-neutral-700 text-gray-900 dark:text-white focus:outline-none focus:ring-1 focus:ring-indigo-500">
                    </div>
                </div>
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" wire:model="editKeepAspect" class="rounded border-gray-300 dark:border-neutral-600 text-indigo-600">
                    <span class="text-xs text-gray-600 dark:text-neutral-400">Keep aspect ratio</span>
                </label>
            </div>

            {{-- Adjustments --}}
            <div class="p-3 border-b border-gray-200 dark:border-neutral-700">
                <div class="text-xs font-semibold text-gray-400 dark:text-neutral-500 uppercase tracking-wider mb-3">Adjustments</div>
                <div class="mb-3">
                    <div class="flex items-center justify-between mb-1">
                        <label class="text-xs text-gray-600 dark:text-neutral-400">Brightness</label>
                        <span class="text-xs font-mono text-gray-500 dark:text-neutral-400" x-text="brightness > 0 ? '+'+brightness : brightness"></span>
                    </div>
                    <input type="range" min="-100" max="100" step="5" x-model="brightness" class="w-full accent-indigo-600 cursor-pointer">
                    <div class="flex justify-between text-xs text-gray-300 dark:text-neutral-600 mt-0.5"><span>Dark</span><span>Bright</span></div>
                </div>
                <div>
                    <div class="flex items-center justify-between mb-1">
                        <label class="text-xs text-gray-600 dark:text-neutral-400">Contrast</label>
                        <span class="text-xs font-mono text-gray-500 dark:text-neutral-400" x-text="contrast > 0 ? '+'+contrast : contrast"></span>
                    </div>
                    <input type="range" min="-100" max="100" step="5" x-model="contrast" class="w-full accent-indigo-600 cursor-pointer">
                    <div class="flex justify-between text-xs text-gray-300 dark:text-neutral-600 mt-0.5"><span>Flat</span><span>Sharp</span></div>
                </div>
            </div>

            {{-- Effects --}}
            <div class="p-3 border-b border-gray-200 dark:border-neutral-700">
                <div class="text-xs font-semibold text-gray-400 dark:text-neutral-500 uppercase tracking-wider mb-3">Effects</div>
                <div class="mb-3">
                    <button type="button" @click="greyscale = !greyscale"
                        :class="greyscale ? 'bg-indigo-100 dark:bg-indigo-900/40 text-indigo-700 dark:text-indigo-300 ring-1 ring-indigo-300' : 'bg-gray-100 dark:bg-neutral-700 text-gray-700 dark:text-neutral-200'"
                        class="w-full flex items-center justify-center gap-2 px-3 py-1.5 rounded text-xs transition-colors">
                        <span x-text="greyscale ? 'Greyscale ON' : 'Greyscale'"></span>
                    </button>
                </div>
                <div class="mb-3">
                    <div class="flex items-center justify-between mb-1">
                        <label class="text-xs text-gray-600 dark:text-neutral-400">Blur</label>
                        <span class="text-xs font-mono text-gray-500 dark:text-neutral-400" x-text="blur > 0 ? blur : 'Off'"></span>
                    </div>
                    <input type="range" min="0" max="20" step="1" x-model="blur" class="w-full accent-indigo-600 cursor-pointer">
                    <div class="flex justify-between text-xs text-gray-300 dark:text-neutral-600 mt-0.5"><span>None</span><span>Strong</span></div>
                </div>
                <div>
                    <div class="flex items-center justify-between mb-1">
                        <label class="text-xs text-gray-600 dark:text-neutral-400">Sharpen</label>
                        <span class="text-xs font-mono text-gray-500 dark:text-neutral-400" x-text="sharpen > 0 ? sharpen : 'Off'"></span>
                    </div>
                    <input type="range" min="0" max="100" step="5" x-model="sharpen" class="w-full accent-indigo-600 cursor-pointer">
                    <div class="flex justify-between text-xs text-gray-300 dark:text-neutral-600 mt-0.5"><span>None</span><span>Sharp</span></div>
                    <p x-show="sharpen > 0" class="mt-1 text-xs text-amber-500 dark:text-amber-400">Applies on save</p>
                </div>
            </div>

            {{-- Output quality --}}
            <div class="p-3 border-b border-gray-200 dark:border-neutral-700">
                <div class="text-xs font-semibold text-gray-400 dark:text-neutral-500 uppercase tracking-wider mb-2">Output Quality</div>
                <div class="flex items-center justify-between mb-1">
                    <span class="text-xs text-gray-500 dark:text-neutral-400">JPEG / WebP</span>
                    <span class="text-xs font-mono text-gray-500 dark:text-neutral-400" x-text="quality + '%'"></span>
                </div>
                <input type="range" min="10" max="100" step="5" x-model="quality" class="w-full accent-indigo-600 cursor-pointer">
                <div class="flex justify-between text-xs text-gray-300 dark:text-neutral-600 mt-0.5"><span>Smaller</span><span>Best</span></div>
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
</div>
