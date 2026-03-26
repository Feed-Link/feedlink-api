<?php

namespace App\Filament\Admin\Resources;

use App\Models\FoodRequest;
use BackedEnum;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Actions;

class FoodRequestResource extends Resource
{
    protected static ?string $model = FoodRequest::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-inbox-stack';

    protected static ?string $navigationLabel = 'Food Requests';

    protected static ?string $modelLabel = 'Food Request';

    protected static ?string $pluralModelLabel = 'Food Requests';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Forms\Components\Select::make('foodlist_id')
                    ->relationship('food_list', 'title')
                    ->required()
                    ->searchable()
                    ->preload()
                    ->label('Food Listing'),

                Forms\Components\Select::make('user_id')
                    ->relationship('user', 'name')
                    ->required()
                    ->searchable()
                    ->preload()
                    ->label('Requested By'),

                Forms\Components\Select::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'completed' => 'Completed',
                        'rejected' => 'Rejected',
                    ])
                    ->required()
                    ->label('Status'),

                Forms\Components\Textarea::make('comments')
                    ->nullable()
                    ->columnSpanFull()
                    ->placeholder('Add any comments or notes'),
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
                    ->label('Requested By')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('food_list.title')
                    ->label('Food Listing')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('food_list.user.name')
                    ->label('Posted By')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'completed',
                        'danger' => 'rejected',
                    ]),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->label('Requested At'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'completed' => 'Completed',
                        'rejected' => 'Rejected',
                    ]),

                Tables\Filters\SelectFilter::make('user')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('food_list')
                    ->relationship('food_list', 'title')
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
            'index' => \App\Filament\Admin\Resources\FoodRequestResource\Pages\ListFoodRequests::route('/'),
            'create' => \App\Filament\Admin\Resources\FoodRequestResource\Pages\CreateFoodRequest::route('/create'),
            'view' => \App\Filament\Admin\Resources\FoodRequestResource\Pages\ViewFoodRequest::route('/{record}'),
            'edit' => \App\Filament\Admin\Resources\FoodRequestResource\Pages\EditFoodRequest::route('/{record}/edit'),
        ];
    }
}
