<?php

declare(strict_types=1);

namespace App\Filament\Resources\StudentResource\RelationManagers;

use Filament\Forms;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Schemas\Schema;
use Filament\Actions\EditAction;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Resources\RelationManagers\RelationManager;

final class AccounsRelationManager extends RelationManager
{
    protected static string $relationship = 'Accounts';

    protected static ?string $recordTitleAttribute = 'Accounts';

    public function configure(Schema $schema): Schema
    {
        return $schema
        ->components([
                TextInput::make('email')
                    ->required()
                    ->email()
                    ->maxLength(255)
                    ->label('Email Address'),
                TextInput::make('password')
                    ->required()
                    ->password()
                    ->maxLength(255)
                    ->label('Password'),
                TextInput::make('name')
                    ->required()
                    ->maxLength(100)
                    ->label('user Name'),
                Select::make('role')
                    ->required()
                    ->options([
                        'student' => 'Student',
                        'faculty' => 'Faculty',
                        'admin' => 'Admin',
                    ])
                    ->label('Role'),
                Toggle::make('two_factor_auth')
                    ->label('Enable Two-Factor Authentication'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('Accounts')
            ->columns([
                Tables\Columns\TextColumn::make('name'),
                Tables\Columns\TextColumn::make('email'),
                Tables\Columns\TextColumn::make('role'),

            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
