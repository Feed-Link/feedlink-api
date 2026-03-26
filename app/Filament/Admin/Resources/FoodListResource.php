<?php

namespace App\Filament\Admin\Resources;

use App\Models\FoodList;
use BackedEnum;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Actions;

class FoodListResource extends Resource
{
    protected static ?string $model = FoodList::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-list-bullet';

    protected static ?string $navigationLabel = 'Food Listings';

    protected static ?string $modelLabel = 'Food Listing';

    protected static ?string $pluralModelLabel = 'Food Listings';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Forms\Components\Select::make('user_id')
                    ->relationship('user', 'name')
                    ->required()
                    ->searchable()
                    ->preload()
                    ->label('Posted By'),

                Forms\Components\TextInput::make('title')
                    ->required()
                    ->maxLength(255)
                    ->placeholder('Enter food listing title'),

                Forms\Components\Textarea::make('description')
                    ->nullable()
                    ->columnSpanFull()
                    ->placeholder('Describe the food in detail'),

                Forms\Components\TextInput::make('type')
                    ->required()
                    ->maxLength(255)
                    ->placeholder('e.g., Rice, Bread, Vegetables'),

                Forms\Components\TextInput::make('quantity')
                    ->nullable()
                    ->placeholder('e.g., 5 meals, 2kg'),

                Forms\Components\TextInput::make('weight')
                    ->numeric()
                    ->nullable()
                    ->placeholder('Weight in kg'),

                Forms\Components\TextInput::make('pickup_within')
                    ->nullable()
                    ->placeholder('e.g., 4 hours, 1 day'),

                Forms\Components\Textarea::make('instructions')
                    ->nullable()
                    ->columnSpanFull()
                    ->placeholder('Add pickup instructions'),

                Forms\Components\TextInput::make('address')
                    ->nullable()
                    ->columnSpanFull()
                    ->placeholder('Pickup location address'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->searchable()
                    ->sortable()
                    ->limit(8),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('Posted By')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('quantity')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('pickup_within')
                    ->label('Pickup Within'),

                Tables\Columns\TextColumn::make('food_requests_count')
                    ->label('Requests')
                    ->counts('food_requests'),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->label('Posted At'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        'vegetables' => 'Vegetables',
                        'fruits' => 'Fruits',
                        'cooked-food' => 'Cooked Food',
                        'baked-goods' => 'Baked Goods',
                        'dairy' => 'Dairy',
                        'other' => 'Other',
                    ]),

                Tables\Filters\SelectFilter::make('user')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->actions([
                Actions\ViewAction::make(),
                Actions\EditAction::make(),
                Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => \App\Filament\Admin\Resources\FoodListResource\Pages\ListFoodLists::route('/'),
            'create' => \App\Filament\Admin\Resources\FoodListResource\Pages\CreateFoodList::route('/create'),
            'view' => \App\Filament\Admin\Resources\FoodListResource\Pages\ViewFoodList::route('/{record}'),
            'edit' => \App\Filament\Admin\Resources\FoodListResource\Pages\EditFoodList::route('/{record}/edit'),
        ];
    }
}
