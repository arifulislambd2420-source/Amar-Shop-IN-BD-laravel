<?php

namespace App\Filament\Support;

use Filament\Actions\ActionGroup;
use Filament\Support\Icons\Heroicon;

/**
 * The "⋯" menu at the end of a table row: the less-used buttons (Preview, Copy, Delete …) tuck in here so a
 * row stays one short line and the table never needs a sideways scroll.
 */
class RowMenu
{
    public static function make(array $actions): ActionGroup
    {
        return ActionGroup::make($actions)
            ->icon(Heroicon::OutlinedEllipsisHorizontal)
            ->label(__('আরও'))
            ->tooltip(__('আরও'))
            ->color('gray');
    }
}
