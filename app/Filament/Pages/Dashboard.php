<?php

namespace App\Filament\Pages;

use App\Support\ReportRange;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

/**
 * The admin dashboard, with one date filter for every report on it: Today, Yesterday, Last 7 days,
 * Last 30 days, This month or a Custom range (from – to). The widgets read it as their page filters
 * (see App\Support\ReportRange / DashboardReport).
 */
class Dashboard extends BaseDashboard
{
    use HasFiltersForm;

    public function filtersForm(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->columnSpanFull()
                ->columns(['default' => 1, 'sm' => 3])
                ->schema([
                    Select::make('range')
                        ->label(__('সময়কাল'))
                        ->options(array_map('__', ReportRange::options()))
                        ->default(ReportRange::DEFAULT)
                        ->native(false)
                        ->selectablePlaceholder(false)
                        ->live(),
                    DatePicker::make('from')
                        ->label(__('শুরুর তারিখ'))
                        ->native(false)
                        ->maxDate(now()->addDay())
                        ->live()
                        ->visible(fn (Get $get): bool => $get('range') === ReportRange::CUSTOM),
                    DatePicker::make('until')
                        ->label(__('শেষ তারিখ'))
                        ->native(false)
                        ->maxDate(now()->addDay())
                        ->live()
                        ->visible(fn (Get $get): bool => $get('range') === ReportRange::CUSTOM),
                ]),
        ]);
    }
}
