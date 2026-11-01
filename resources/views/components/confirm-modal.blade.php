@props([
    'show' => false,
    'title' => 'Confirm',
    'confirmAction' => '',
    'cancelAction' => '',
    'confirmLabel' => 'Confirm',
    'loadingLabel' => 'Please wait…',
    'confirmTarget' => '',
])

@if($show)
<div class="fixed inset-0 flex items-center justify-center bg-black/60" style="z-index: 9999999;">
    <div class="bg-white dark:bg-neutral-800 rounded-lg shadow-xl w-full max-w-md mx-4">
        <div class="flex items-center gap-3 px-5 py-4 border-b border-gray-200 dark:border-neutral-700">
            <div class="flex-shrink-0 flex items-center justify-center w-9 h-9 rounded-full bg-red-100 dark:bg-red-900/30">
                <svg class="w-5 h-5 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
            </div>
            <h3 class="text-base font-semibold text-gray-900 dark:text-white">{{ $title }}</h3>
        </div>

        <div class="px-5 py-4 text-sm text-gray-600 dark:text-neutral-300">
            {{ $slot }}
        </div>

        <div class="flex items-center justify-end gap-2 px-5 py-3 border-t border-gray-200 dark:border-neutral-700">
            <button
                type="button"
                wire:click="{{ $cancelAction }}"
                class="px-4 py-2 text-sm font-medium rounded bg-gray-200 dark:bg-neutral-600 text-gray-700 dark:text-neutral-200 hover:bg-gray-300 dark:hover:bg-neutral-500 transition-colors"
            >
                Cancel
            </button>
            <button
                type="button"
                wire:click="{{ $confirmAction }}"
                wire:loading.attr="disabled"
                @if($confirmTarget) wire:target="{{ $confirmTarget }}" @endif
                class="px-4 py-2 text-sm font-medium rounded bg-red-600 text-white hover:bg-red-700 disabled:opacity-60 disabled:cursor-not-allowed transition-colors"
            >
                <span wire:loading.remove @if($confirmTarget) wire:target="{{ $confirmTarget }}" @endif>{{ $confirmLabel }}</span>
                <span wire:loading @if($confirmTarget) wire:target="{{ $confirmTarget }}" @endif>{{ $loadingLabel }}</span>
            </button>
        </div>
    </div>
</div>
@endif
