{{--
    <x-empty-state icon="🎉" title="..." description="..." action="..." actionUrl="..." />
    Friendly placeholder for empty lists / search results.
--}}
@props([
    'icon' => '📭',
    'title' => 'Chưa có dữ liệu',
    'description' => null,
    'action' => null,
    'actionUrl' => null,
])

<div class="empty-state" style="text-align: center; padding: 3rem 1.5rem; color: var(--text-muted)">
    <div style="font-size: 3.5rem; margin-bottom: 1rem; opacity: 0.7">{{ $icon }}</div>
    <h3 style="font-size: 1.125rem; margin-bottom: 0.5rem; color: var(--text-main)">{{ $title }}</h3>
    @if($description)
        <p style="margin-bottom: 1.5rem; max-width: 360px; margin-left: auto; margin-right: auto">{{ $description }}</p>
    @endif
    @if($action && $actionUrl)
        <a href="{{ $actionUrl }}" class="btn btn-primary" style="padding: 0.6rem 1.2rem">{{ $action }}</a>
    @endif
</div>