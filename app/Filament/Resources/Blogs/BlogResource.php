<?php

namespace App\Filament\Resources\Blogs;

use App\Filament\Concerns\HasAdminArea;
use App\Filament\Resources\Blogs\Pages\ManageBlogs;
use App\Filament\Support\ImageUpload;
use App\Models\Blog;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use UnitEnum;

class BlogResource extends Resource
{
    use HasAdminArea;

    protected static ?string $model = Blog::class;

    public static function getModelLabel(): string
    {

        return Str::singular(__('ব্লগ পোস্ট'));

    }

    public static function getPluralModelLabel(): string
    {

        return __('ব্লগ পোস্ট');

    }

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedNewspaper;

    public static function getNavigationGroup(): string|UnitEnum|null
    {

        return __('কনটেন্ট');

    }

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('পোস্ট'))
                    ->columns(2)
                    ->schema([
                        TextInput::make('title')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (string $operation, $state, callable $set) {
                                if ($operation === 'create') {
                                    $set('slug', Str::slug($state));
                                }
                            }),
                        TextInput::make('slug')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                        TextInput::make('category')
                            ->maxLength(255),
                        TextInput::make('read_time')
                            ->label(__('পড়ার সময় (মিনিট)'))
                            ->numeric()
                            ->minValue(1),
                        ImageUpload::make('cover')
                            ->label(__('কভার ছবি'))
                            ->columnSpanFull(),
                        DateTimePicker::make('published_at'),
                    ]),
                Section::make(__('লেখা'))
                    ->schema([
                        ImageUpload::configureRichEditor(RichEditor::make('content'))
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('cover')
                    ->label(__('কভার')),
                TextColumn::make('title')
                    ->searchable()
                    ->sortable()
                    ->limit(50),
                TextColumn::make('category')
                    ->badge()
                    ->searchable(),
                TextColumn::make('read_time')
                    ->label(__('পড়া (মিনিট)'))
                    ->sortable(),
                TextColumn::make('published_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('published_at', 'desc')
            ->recordActions([
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
            'index' => ManageBlogs::route('/'),
        ];
    }
}
