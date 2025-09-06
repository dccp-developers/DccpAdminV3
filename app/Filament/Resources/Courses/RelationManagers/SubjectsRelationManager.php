<?php

declare(strict_types=1);

namespace App\Filament\Resources\CourseResource\RelationManagers;

use Filament\Forms;
use Filament\Tables;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Filament\Schemas\Schema;
use Filament\Facades\Filament;
use Filament\Actions\EditAction;
// use Filament\Forms\Components\Tabs;
// use Filament\Forms\Components\Section;
use App\Enums\SubjectEnrolledEnum;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Schemas\Components\Tabs;
use Filament\Actions\DeleteBulkAction;
use Filament\Schemas\Components\Section;
use Filament\Resources\RelationManagers\RelationManager;

final class SubjectsRelationManager extends RelationManager
{
    protected static string $relationship = 'subjects';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                // Subject Form (Similar to SubjectResource, but within the context of a Course)
                Tabs::make('Subject Details')
                    ->tabs([
                        Tabs\Tab::make('Basic Information')
                            ->schema([
                                Section::make('Subject Information')
                                    ->description('Enter the core details for the subject.')
                                    ->schema([
                                        Forms\Components\TextInput::make('code')
                                            ->required()
                                            ->maxLength(255)
                                            ->label('Subject Code')
                                            ->helperText('Unique code for the subject (e.g., IT101).'),
                                        Forms\Components\TextInput::make('title')
                                            ->required()
                                            ->maxLength(255)
                                            ->label('Subject Title')
                                            ->helperText('Full title of the subject.'),
                                        Forms\Components\Textarea::make('description')
                                            ->maxLength(255)
                                            ->label('Description')
                                            ->helperText('Brief description of the subject.')
                                            ->columnSpanFull(),
                                        Forms\Components\Select::make('classification')
                                            ->required()
                                            ->options(SubjectEnrolledEnum::class)
                                            ->label('Classification')
                                            ->helperText('Classification of the subject.'),
                                        Forms\Components\TextInput::make('units')
                                            ->required()
                                            ->numeric()
                                            ->label('Units')
                                            ->helperText('Number of units for the subject.'),
                                        Forms\Components\TextInput::make('lecture')
                                            ->numeric()
                                            ->label('Lecture Hours')
                                            ->helperText('Number of lecture hours.'),
                                        Forms\Components\TextInput::make('laboratory')
                                            ->numeric()
                                            ->label('Laboratory Hours')
                                            ->helperText('Number of laboratory hours.'),
                                        Forms\Components\TextInput::make('pre_riquisite')
                                            ->label('Pre-requisite')
                                            ->helperText('Subject ID of Pre-requisite'),
                                        Forms\Components\Checkbox::make('is_credited')
                                            ->label('Is Credited')
                                            ->helperText('Check if this subject is credited.'),
                                    ])->columns(2),
                            ]),
                        Tabs\Tab::make('Scheduling')
                            ->schema([
                                Section::make('Academic Details')
                                    ->description('Specify academic year, semester, and grouping.')
                                    ->schema([
                                        Forms\Components\Select::make('academic_year')
                                            ->options([
                                                1 => '1st Year',
                                                2 => '2nd Year',
                                                3 => '3rd Year',
                                                4 => '4th Year',
                                            ])
                                            ->label('Academic Year')
                                            ->helperText('Academic year for the subject.'),
                                        Forms\Components\Select::make('semester')
                                            ->options([
                                                1 => '1st Semester',
                                                2 => '2nd Semester',
                                                3 => 'Summer',
                                            ])
                                            ->label('Semester')
                                            ->helperText('Semester for the subject.'),
                                        Forms\Components\TextInput::make('group')
                                            ->maxLength(255)
                                            ->label('Group')
                                            ->helperText('Group, if applicable.'),
                                    ])->columns(3),
                            ]),
                    ])->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('code')
            ->columns([
                Tables\Columns\TextColumn::make('code')->label('Code'),
                Tables\Columns\TextColumn::make('title')->label('Title'),
                Tables\Columns\TextColumn::make('units')->label('Units'),
                Tables\Columns\TextColumn::make('academic_year')->label('Academic Year'),
                Tables\Columns\TextColumn::make('semester')->label('Semester'),
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
