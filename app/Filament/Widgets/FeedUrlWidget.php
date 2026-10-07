<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;

/**
 * Dashboard "copy feed URL" card — replicates the old app's dashboard
 * FeedUrlCopy.tsx affordance so admins can grab the Facebook/Google product
 * feed URL without digging through routes. Compact, last on the page.
 */
class FeedUrlWidget extends Widget
{
    use \App\Filament\Concerns\HasAdminAreaWidget;

    protected string $view = 'filament.widgets.feed-url-widget';

    protected static ?int $sort = 99;

    protected int|string|array $columnSpan = 'full';

    public function getFeedUrl(): string
    {
        return route('feed.facebook');
    }
}
