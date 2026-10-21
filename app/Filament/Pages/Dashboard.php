<?php

namespace App\Filament\Pages;

use App\Support\ReportRange;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\ToggleButtons;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

/**
 * The admin dashboard, with one date filter for every report on it: a small row of buttons at the top right —
 * Today, 7 days, 30 days, This month, Custom (then a from / to pair). The widgets read it as their page filters
 * (see App\Support\ReportRange / DashboardReport).
 */
class Dashboard extends BaseDashboard
{
    use HasFiltersForm;

    public function filtersForm(Schema $schema): Schema
    {
        return $schema->components([
            Group::make()
                ->columnSpanFull()
                ->extraAttributes(['class' => 'dash-filter'])
                ->schema([
                    DatePicker::make('from')
                        ->hiddenLabel()
                        ->placeholder(__('শুরুর তারিখ'))
                        ->native(false)
                        ->maxDate(now()->addDay())
                        ->live()
                        ->visible(fn (Get $get): bool => $get('range') === ReportRange::CUSTOM),
                    DatePicker::make('until')
                        ->hiddenLabel()
                        ->placeholder(__('শেষ তারিখ'))
                        ->native(false)
                        ->maxDate(now()->addDay())
                        ->live()
                        ->visible(fn (Get $get): bool => $get('range') === ReportRange::CUSTOM),
                    ToggleButtons::make('range')
                        ->hiddenLabel()
                        ->options(array_map('__', ReportRange::buttons()))
                        ->default(ReportRange::DEFAULT)
                        ->inline()
                        ->grouped()
                        ->live(),
                ]),
        ]);
    }
}
