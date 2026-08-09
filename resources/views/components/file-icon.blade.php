@props(['type' => 'file', 'size' => 'w-12 h-12'])

@php
$colorClass = match ($type) {
    'image'      => 'text-green-500',
    'pdf'        => 'text-red-600',
    'word'       => 'text-blue-600',
    'excel'      => 'text-green-600',
    'powerpoint' => 'text-orange-500',
    'text'       => 'text-gray-600',
    'video'      => 'text-purple-500',
    'audio'      => 'text-pink-500',
    default      => 'text-gray-400',
};
@endphp

@switch($type)
    @case('image')
        <svg class="{{ $size }} {{ $colorClass }}" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4l4-8 3 6 2-4 3 6z" clip-rule="evenodd"/>
        </svg>
        @break
    @case('pdf')
        <svg class="{{ $size }} {{ $colorClass }}" fill="currentColor" viewBox="0 0 20 20">
            <path d="M4 18h12V6l-4-4H4v16zm8-14v3h3l-3-3zM6 8h2v1H6V8zm0 2h6v1H6v-1zm0 2h6v1H6v-1z"/>
        </svg>
        @break
    @case('word')
        <svg class="{{ $size }} {{ $colorClass }}" fill="currentColor" viewBox="0 0 20 20">
            <path d="M4 2h8l4 4v10a2 2 0 01-2 2H4a2 2 0 01-2-2V4a2 2 0 012-2zm8 4V4l2 2h-2zM6 8h6v1H6V8zm0 2h6v1H6v-1zm0 2h4v1H6v-1z"/>
        </svg>
        @break
    @case('excel')
        <svg class="{{ $size }} {{ $colorClass }}" fill="currentColor" viewBox="0 0 20 20">
            <path d="M3 3h14a1 1 0 011 1v12a1 1 0 01-1 1H3a1 1 0 01-1-1V4a1 1 0 011-1zm1 2v10h12V5H4zm2 2h8v1H6V7zm0 2h8v1H6V9zm0 2h8v1H6v-1zm0 2h5v1H6v-1z"/>
        </svg>
        @break
    @case('powerpoint')
        <svg class="{{ $size }} {{ $colorClass }}" fill="currentColor" viewBox="0 0 20 20">
            <path d="M4 2h8l4 4v10a2 2 0 01-2 2H4a2 2 0 01-2-2V4a2 2 0 012-2zm8 4V4l2 2h-2zM6 8h6v4H6V8zm1 1v2h4V9H7z"/>
        </svg>
        @break
    @case('text')
        <svg class="{{ $size }} {{ $colorClass }}" fill="currentColor" viewBox="0 0 20 20">
            <path d="M4 2h8l4 4v10a2 2 0 01-2 2H4a2 2 0 01-2-2V4a2 2 0 012-2zm8 4V4l2 2h-2zM6 8h6v1H6V8zm0 2h6v1H6v-1zm0 2h6v1H6v-1zm0 2h4v1H6v-1z"/>
        </svg>
        @break
    @case('video')
        <svg class="{{ $size }} {{ $colorClass }}" fill="currentColor" viewBox="0 0 20 20">
            <path d="M2 6a2 2 0 012-2h6a2 2 0 012 2v8a2 2 0 01-2 2H4a2 2 0 01-2-2V6zM14.553 7.106A1 1 0 0014 8v4a1 1 0 00.553.894l2 1A1 1 0 0018 13V7a1 1 0 00-1.447-.894l-2 1z"/>
        </svg>
        @break
    @case('audio')
        <svg class="{{ $size }} {{ $colorClass }}" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M9.383 3.076A1 1 0 0110 4v12a1 1 0 01-1.707.707L4.586 13H2a1 1 0 01-1-1V8a1 1 0 011-1h2.586l3.707-3.707a1 1 0 011.09-.217z" clip-rule="evenodd"/>
        </svg>
        @break
    @default
        <svg class="{{ $size }} {{ $colorClass }}" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z" clip-rule="evenodd"/>
        </svg>
@endswitch
