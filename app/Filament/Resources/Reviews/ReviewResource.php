<?php

namespace App\Filament\Resources\Reviews;

use App\Filament\Concerns\HasAdminArea;
use App\Filament\Resources\Reviews\Pages\ManageReviews;
use App\Models\Review;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use UnitEnum;

class ReviewResource extends Resource
{
    use HasAdminArea;

    protected static ?string $model = Review::class;

    protected static ?string $modelLabel = 'রিভিউ';

    protected static ?string $pluralModelLabel = 'রিভিউ';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedStar;

    protected static string|UnitEnum|null $navigationGroup = 'ক্যাটালগ';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('product_id')
                    ->label('পণ্য')
                    ->relationship('product', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                TextInput::make('customer_name')
                    ->required()
                    ->maxLength(255),
                Select::make('rating')
                    ->options([1 => '1', 2 => '2', 3 => '3', 4 => '4', 5 => '5'])
                    ->required(),
                Textarea::make('comment')
                    ->rows(4)
                    ->columnSpanFull(),
                Toggle::make('approved')
                    ->label('অনুমোদিত'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('product.name')
                    ->label('পণ্য')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('customer_name')
                    ->searchable(),
                TextColumn::make('rating')
                    ->formatStateUsing(fn (int $state): string => str_repeat('★', $state).str_repeat('☆', 5 - $state))
                    ->sortable(),
                TextColumn::make('comment')
                    ->visibleFrom('md')
                    ->limit(50)
                    ->wrap(),
                IconColumn::make('approved')
                    ->boolean()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->visibleFrom('md')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                TernaryFilter::make('approved'),
                SelectFilter::make('product_id')
                    ->label('পণ্য')
                    ->relationship('product', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                Action::make('approve')
                    ->label('অনুমোদন করুন')
                    ->icon(Heroicon::OutlinedCheck)
                    ->color('success')
                    ->visible(fn (Review $record): bool => ! $record->approved)
                    ->action(fn (Review $record) => $record->update(['approved' => true])),
                Action::make('unapprove')
                    ->label('অনুমোদন তুলে নিন')
                    ->icon(Heroicon::OutlinedXMark)
                    ->color('warning')
                    ->visible(fn (Review $record): bool => (bool) $record->approved)
                    ->action(fn (Review $record) => $record->update(['approved' => false])),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageReviews::route('/'),
        ];
    }
}
