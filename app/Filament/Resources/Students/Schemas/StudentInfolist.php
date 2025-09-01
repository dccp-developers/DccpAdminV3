<?php

namespace App\Filament\Resources\Students\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class StudentInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('id')
                    ->label('ID')
                    ->numeric(),
                TextEntry::make('first_name'),
                TextEntry::make('last_name'),
                TextEntry::make('middle_name'),
                TextEntry::make('gender'),
                TextEntry::make('birth_date')
                    ->date(),
                TextEntry::make('age')
                    ->numeric(),
                TextEntry::make('address'),
                TextEntry::make('course_id')
                    ->numeric(),
                TextEntry::make('academic_year')
                    ->numeric(),
                TextEntry::make('email')
                    ->label('Email address'),
                TextEntry::make('created_at')
                    ->dateTime(),
                TextEntry::make('updated_at')
                    ->dateTime(),
                TextEntry::make('profile_url'),
                TextEntry::make('student_contact_id')
                    ->numeric(),
                TextEntry::make('student_parent_info')
                    ->numeric(),
                TextEntry::make('student_education_id')
                    ->numeric(),
                TextEntry::make('student_personal_id')
                    ->numeric(),
                TextEntry::make('document_location_id')
                    ->numeric(),
                TextEntry::make('deleted_at')
                    ->dateTime(),
                TextEntry::make('student_id')
                    ->numeric(),
                TextEntry::make('status'),
                TextEntry::make('clearance_status'),
                TextEntry::make('year_graduated'),
                TextEntry::make('special_order'),
                TextEntry::make('issued_date')
                    ->date(),
            ]);
    }
}
