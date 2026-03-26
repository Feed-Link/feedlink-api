<?php

namespace App\Filament\Admin\Resources;

use App\Models\User;
use BackedEnum;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Actions;
use Illuminate\Database\Eloquent\Builder;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationLabel = 'Users';

    protected static ?string $modelLabel = 'User';

    protected static ?string $pluralModelLabel = 'Users';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->placeholder('Enter full name'),

                Forms\Components\TextInput::make('email')
                    ->email()
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->placeholder('Enter email address'),

                Forms\Components\TextInput::make('contact')
                    ->tel()
                    ->nullable()
                    ->placeholder('Enter contact number'),

                Forms\Components\TextInput::make('password')
                    ->password()
                    ->dehydrated(fn($state) => filled($state))
                    ->required(fn(string $operation): bool => $operation === 'create')
                    ->minLength(8)
                    ->confirmed()
                    ->visible(fn(string $operation): bool => $operation === 'create'),

                Forms\Components\TextInput::make('password_confirmation')
                    ->password()
                    ->dehydrated(false)
                    ->required(fn(string $operation): bool => $operation === 'create')
                    ->visible(fn(string $operation): bool => $operation === 'create'),

                Forms\Components\Toggle::make('email_verified_at')
                    ->label('Email Verified')
                    ->helperText('Mark as verified'),

                Forms\Components\Select::make('roles')
                    ->relationship('roles', 'name')
                    ->multiple()
                    ->preload()
                    ->searchable()
                    ->label('Assign Roles'),

                \Filament\Schemas\Components\Section::make('Location')
                    ->schema([
                        Forms\Components\TextInput::make('latitude')
                            ->numeric()
                            ->step(0.00000001)
                            ->label('Latitude'),
                        Forms\Components\TextInput::make('longitude')
                            ->numeric()
                            ->step(0.00000001)
                            ->label('Longitude'),
                    ])->columns(2),
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

                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('contact')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\IconColumn::make('email_verified_at')
                    ->label('Verified')
                    ->boolean(),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->label('Created At'),

                Tables\Columns\TextColumn::make('roles.name')
                    ->badge()
                    ->label('Roles'),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('email_verified_at')
                    ->label('Verified User'),

                Tables\Filters\SelectFilter::make('roles')
                    ->relationship('roles', 'name')
                    ->label('Filter by Role'),
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
            'index' => \App\Filament\Admin\Resources\UserResource\Pages\ListUsers::route('/'),
            'create' => \App\Filament\Admin\Resources\UserResource\Pages\CreateUser::route('/create'),
            'view' => \App\Filament\Admin\Resources\UserResource\Pages\ViewUser::route('/{record}'),
            'edit' => \App\Filament\Admin\Resources\UserResource\Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
