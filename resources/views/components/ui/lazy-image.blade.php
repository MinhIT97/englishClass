{{--
    <x-lazy-image src="..." alt="..." width="..." height="..." />
    Lazy-loaded image with built-in skeleton placeholder.
--}}
@props([
    'src' => '',
    'alt' => '',
    'width' => null,
    'height' => null,
    'aspect' => null, // e.g. '16/9'
])

<img
    src="{{ $src }}"
    alt="{{ $alt }}"
    loading="lazy"
    decoding="async"
    @if($width) width="{{ $width }}" @endif
    @if($height) height="{{ $height }}" @endif
    @if($aspect) style="aspect-ratio: {{ $aspect }}; object-fit: cover; background: rgba(0,0,0,0.05);" @endif
    {{ $attributes->merge(['class' => 'lazy-image']) }}
>