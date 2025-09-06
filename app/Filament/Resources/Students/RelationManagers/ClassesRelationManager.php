<?php

declare(strict_types=1);

namespace App\Filament\Resources\StudentResource\RelationManagers;
/* 
use Filament\Tables;
use Filament\Forms */;
use App\Models\Student;
// use Filament\Forms\Form;
use App\Models\Students;
use Filament\Tables\Table;
use Filament\Actions\Action;
use Filament\Schemas\Schema;
use Filament\Actions\ViewAction;
use Filament\Infolists\Infolist;
use Filament\Actions\BulkActionGroup;
use Filament\Schemas\Components\Tabs;
// use Filament\Infolists\Components\Tabs;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\RelationManagers\RelationManager;

class ClassesRelationManager extends RelationManager
{
    protected static string $relationship = 'Classes';

    protected static bool $isLazy = false;

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('id')
                    ->required()
                    ->maxLength(255),

            ]);
    }

    public function table(Table $table): Table
    {
        $record = $this->getOwnerRecord();

        return $table
            ->recordTitleAttribute('id')
            ->columns([

                TextColumn::make('class.subject.code')
                    ->label('Subject Code'),

                TextColumn::make('class.subject.title')
                    ->label('Subject Title'),
                TextColumn::make('class.subject.units')
                    ->label('Units'),
                TextColumn::make('class.section')
                    ->label('Section'),
                TextColumn::make('class.Schedule.room.name')
                    ->label('Room'),
                TextColumn::make('class.academic_year')
                    ->label('Academic Year'),

            ])
            ->filters([
                //
            ])
            ->headerActions([
                // Tables\Actions\CreateAction::make(),
                Action::make('ReEnroll Class')
                    ->action(function ($record): void {
                        $student = Student::find($this->getOwnerRecord()->id);
                        $student->autoEnrollInClasses();
                    }),

            ])
            ->actions([
                ViewAction::make(),

            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Tabs')
                    ->tabs([
                        Tabs\Tab::make('Subject Details')
                            ->schema([
                                TextEntry::make('class.subject.code')
                                    ->label('Subject Code'),
                                TextEntry::make('class.subject.title')
                                    ->label('Subject Title'),
                            ]),
                        Tabs\Tab::make('Class Details')
                            ->schema([
                                TextEntry::make('class.section')
                                    ->label('Section'),
                                TextEntry::make('class.academic_year')
                                    ->label('Academic Year'),
                            ]),
                        Tabs\Tab::make('Schedule Details')
                            ->schema([
                                TextEntry::make('class.Schedule.room.name')
                                    ->label('Room'),
                                TextEntry::make('class.Schedule.day_of_week')
                                    ->label('Schedule Day'),
                                TextEntry::make('class.Schedule.start_time')
                                    ->label('Start Time'),
                                TextEntry::make('class.Schedule.end_time')
                                    ->label('End Time'),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    public function isReadOnly(): bool
    {
        return false;
    }
}
