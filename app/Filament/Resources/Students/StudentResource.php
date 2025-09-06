<?php

namespace App\Filament\Resources\Students;

use BackedEnum;
use App\Models\Student;
use Filament\Tables\Table;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Resources\Students\Pages\EditStudent;
use App\Filament\Resources\Students\Pages\ViewStudent;
use Filament\Resources\RelationManagers\RelationGroup;
use App\Filament\Resources\Students\Pages\ListStudents;
use App\Filament\Resources\Students\Pages\CreateStudent;
use App\Filament\Resources\Students\Schemas\StudentForm;
use App\Filament\Resources\Students\Tables\StudentsTable;
use App\Filament\Resources\Students\Schemas\StudentInfolist;
use App\Filament\Resources\Students\RelationManagers\AccountsRelationManager;
use App\Filament\Resources\StudentResource\RelationManagers\AccounsRelationManager;
use App\Filament\Resources\StudentResource\RelationManagers\ClassesRelationManager;
use App\Filament\Resources\StudentResource\RelationManagers\EnrolledSubjectsRelationManager;
use App\Filament\Resources\StudentResource\RelationManagers\StatementOfAccountRelationManager;
use Schmeits\FilamentPhosphorIcons\Support\Icons\Phosphor;

class StudentResource extends Resource
{
    protected static ?string $model = Student::class;

    protected static string|BackedEnum|null $navigationIcon =  Phosphor::Student;

    protected static ?string $recordTitleAttribute = 'last_name';

    public static function form(Schema $schema): Schema
    {
        return StudentForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return StudentInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return StudentsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            // AccounsRelationManager::class,
            // AccountsRelationManager::class,
            RelationGroup::make("Enrolled Subject", [
                ClassesRelationManager::class,
            ]),
             RelationGroup::make("Academic Information", [
                EnrolledSubjectsRelationManager::class,
             ]),
             relationgroup::make("financial records", [
                 StatementOfAccountRelationManager::class,
             ]),
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStudents::route('/'),
            'create' => CreateStudent::route('/create'),
            'view' => ViewStudent::route('/{record}'),
            'edit' => EditStudent::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
