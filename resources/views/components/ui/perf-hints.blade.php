{{--
    Drop into <head> for Core Web Vitals wins:
    - preconnect to known origins
    - prefetch the user's likely next route (passed as $prefetch)
    - mark critical images as fetchpriority="high"
--}}
@props(['prefetch' => null])

@if($prefetch)
    <link rel="prefetch" href="{{ $prefetch }}" as="document">
@endif