@props([
    'name' => 'content',
    'value' => '',
    'height' => '500',
    'menubar' => false,
    'toolbar' => 'undo redo | blocks | bold italic backcolor | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | removeformat | image media link | code help',
    'plugins' => 'advlist autolink lists link image charmap preview anchor searchreplace visualblocks code fullscreen insertdatetime media table help wordcount code',
    'fileManager' => true,
    'statusbar' => false,
    'relativeUrls' => true,
    'readonly' => false,
])

@php
    $wireModel = $attributes->whereStartsWith('wire:model')->first();
    $wireModelValue = $wireModel ? str_replace(['wire:model=', 'wire:model.live=', 'wire:model.defer='], '', $wireModel) : null;
    $uniqueId = 'tinymce_' . $name . '_' . uniqid();
@endphp

<div>
    {{-- File manager rendered once per page even with multiple editors --}}
    @if($fileManager)
        @once
            @livewire('file-manager')
        @endonce
    @endif

    <div wire:ignore>
        <textarea
            id="{{ $uniqueId }}"
            name="{{ $name }}"
            {{ $attributes->whereDoesntStartWith('wire:model') }}
        ></textarea>
    </div>
</div>

{{-- CDN script loads once; init script loads per-instance — both go into the stack so CDN is guaranteed loaded first --}}
@pushonce('scripts')
<script src="https://cdn.jsdelivr.net/npm/tinymce@7/tinymce.min.js" referrerpolicy="origin"></script>
@endpushonce

@push('scripts')
<script>
(function () {
    tinymce.init({
        selector: '#{{ $uniqueId }}',
        license_key: 'gpl',
        height: {{ $height }},
        menubar: {{ $menubar ? 'true' : 'false' }},
        toolbar: {!! $readonly ? 'false' : "'" . $toolbar . "'" !!},
        plugins: '{{ $plugins }}',
        branding: false,
        promotion: false,
        statusbar: {{ $statusbar ? 'true' : 'false' }},
        resize: true,
        skin: localStorage.getItem('theme') === 'dark' ? 'oxide-dark' : 'oxide',
        content_css: localStorage.getItem('theme') === 'dark' ? 'dark' : 'default',
        content_style: localStorage.getItem('theme') === 'dark'
            ? 'body { font-family: Helvetica, Arial, sans-serif; font-size: 16px; background-color: #333333; color: #e5e5e5; }'
            : 'body { font-family: Helvetica, Arial, sans-serif; font-size: 16px; }',
        convert_urls: {{ $relativeUrls ? 'true' : 'false' }},
        relative_urls: {{ $relativeUrls ? 'true' : 'false' }},
        remove_script_host: {{ $relativeUrls ? 'true' : 'false' }},
        document_base_url: '{{ config('app.url') }}/',

        @if($fileManager)
        file_picker_callback: function (callback, value, meta) {
            let mediaType = 'all';
            switch (meta.filetype) {
                case 'image': mediaType = 'image'; break;
                case 'media': mediaType = 'media'; break;
                case 'file':  mediaType = 'file';  break;
            }
            Livewire.dispatch('openFileManager', [mediaType]);
            window.tinyMceCallback_{{ $uniqueId }} = callback;
            window.tinyMceMeta_{{ $uniqueId }} = meta;
        },
        @endif

        @if($readonly)
        readonly: 1,
        @endif

        setup: function (editor) {
            editor.on('init', function () {
                @if($wireModelValue)
                try {
                    let content = @this.get('{{ $wireModelValue }}') || '';
                    editor.setContent(content);
                } catch (e) {
                    console.error('TinyMCE: error getting Livewire content', e);
                }
                @else
                const initialContent = {!! Js::from($value) !!} || '';
                editor.setContent(initialContent);
                document.getElementById('{{ $uniqueId }}').value = initialContent;

                // Sync all TinyMCE editors to their textareas before the form submits
                const form = document.getElementById('{{ $uniqueId }}').closest('form');
                if (form && !form.dataset.tinymceSyncBound) {
                    form.dataset.tinymceSyncBound = '1';
                    form.addEventListener('submit', function () {
                        tinymce.triggerSave();
                    });
                }
                @endif
            });

            editor.on('change keyup paste input', function () {
                @if($wireModelValue)
                try {
                    @this.set('{{ $wireModelValue }}', editor.getContent());
                } catch (e) {
                    console.error('TinyMCE: error syncing to Livewire', e);
                }
                @else
                document.getElementById('{{ $uniqueId }}').value = editor.getContent();
                @endif
            });
        },
    });
}());

@if($fileManager)
document.addEventListener('livewire:init', () => {
    Livewire.on('fileSelected', (file) => {
        if (window.tinyMceCallback_{{ $uniqueId }}) {
            let url = file[0].url;
            @if($relativeUrls)
            try {
                const parsed = new URL(url);
                url = parsed.pathname; // strip domain and ?t= cache-buster
            } catch (e) {}
            @endif
            window.tinyMceCallback_{{ $uniqueId }}(url, {
                alt: file[0].name,
                title: file[0].name,
            });
            window.tinyMceCallback_{{ $uniqueId }} = null;
            window.tinyMceMeta_{{ $uniqueId }} = null;
        }
    });
});
@endif
</script>
@endpush
