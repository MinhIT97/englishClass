{{--
    Skeleton placeholder — drop in wherever a card/section is loading.
    Variants: card, stat, list-item, text-line, avatar, image.

    Usage:
      <x-ui.skeleton variant="card" />
      <x-ui.skeleton variant="text-line" :count="3" />
--}}
@props([
    'variant' => 'text-line', // card | stat | list-item | text-line | avatar | image
    'count' => 1,
    'width' => null,
    'height' => null,
])

@php
    use Illuminate\Support\Str;
    $uid = 'sk-' . Str::random(8);
@endphp

<style>
    .skeleton-loader {
        position: relative;
        overflow: hidden;
        background: linear-gradient(90deg,
            rgba(255,255,255,0.04) 25%,
            rgba(255,255,255,0.10) 50%,
            rgba(255,255,255,0.04) 75%);
        background-size: 200% 100%;
        animation: skeleton-shimmer 1.4s ease-in-out infinite;
        border-radius: 8px;
    }
    @keyframes skeleton-shimmer {
        0% { background-position: 200% 0; }
        100% { background-position: -200% 0; }
    }
    /* Dark mode keeps the shimmer visible against a darker base */
    [data-theme="dark"] .skeleton-loader,
    .dark .skeleton-loader {
        background: linear-gradient(90deg,
            rgba(255,255,255,0.05) 25%,
            rgba(255,255,255,0.12) 50%,
            rgba(255,255,255,0.05) 75%);
        background-size: 200% 100%;
    }
</style>

@for($i = 0; $i < $count; $i++)
    @switch($variant)
        @case('card')
            <div {{ $attributes->merge(['class' => 'skeleton-loader', 'style' => 'height: 180px; width: 100%; margin-bottom: 1rem']) }}></div>
            @break

        @case('stat')
            <div {{ $attributes->merge(['class' => 'skeleton-loader', 'style' => 'height: 96px; width: 100%; border-radius: 12px; margin-bottom: 1rem']) }}></div>
            @break

        @case('list-item')
            <div {{ $attributes->merge(['class' => 'skeleton-loader', 'style' => 'height: 64px; width: 100%; margin-bottom: 0.5rem']) }}></div>
            @break

        @case('avatar')
            <div {{ $attributes->merge(['class' => 'skeleton-loader', 'style' => 'border-radius: 50%; width: ' . ($width ?? '40px') . '; height: ' . ($height ?? '40px') . '; display: inline-block']) }}></div>
            @break

        @case('image')
            <div {{ $attributes->merge(['class' => 'skeleton-loader', 'style' => 'aspect-ratio: 16/9; width: 100%; border-radius: 12px']) }}></div>
            @break

        @default
            <div {{ $attributes->merge(['class' => 'skeleton-loader', 'style' => 'height: ' . ($height ?? '14px') . '; width: ' . ($width ?? '100%') . '; margin-bottom: 0.5rem']) }}></div>
    @endswitch
@endfor