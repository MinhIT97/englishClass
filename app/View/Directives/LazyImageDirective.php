<?php

namespace App\View\Directives;

use Illuminate\Support\Facades\Blade;

/**
 * Tiny inline helpers used by Blade templates. Keeps view code terse
 * and lets us evolve the implementation in one place (e.g. swap
 * skeleton strategy, change lazy-loading root margin, etc).
 *
 * The directives are registered in AppServiceProvider::boot().
 */
class LazyImageDirective
{
    public static function register(): void
    {
        // <x-lazy-image src="..." alt="..." /> renders an <img> with
        // loading="lazy", decoding="async", and explicit width/height
        // to avoid CLS. Adds a placeholder background while the image
        // fetches.
        Blade::component('components.ui.lazy-image', 'lazy-image');

        // <x-empty-state icon="🎉" title="..." description="..." />
        // Used wherever a list/collection is empty (no students, no
        // lessons, etc.) — keeps the UI friendly instead of blank.
        Blade::component('components.ui.empty-state', 'empty-state');

        // <x-toast /> global toast slot — JS reads `window.__toasts`
        // and renders them on page load.
        Blade::component('components.ui.toast-host', 'toast-host');
    }
}