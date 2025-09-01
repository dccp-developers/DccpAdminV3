<?php

namespace App\Filament\Resources\Students\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class StudentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('first_name')
                    ->required(),
                TextInput::make('last_name')
                    ->required(),
                TextInput::make('middle_name')
                    ->default(null),
                TextInput::make('gender')
                    ->required(),
                DatePicker::make('birth_date')
                    ->required(),
                TextInput::make('age')
                    ->required()
                    ->numeric(),
                TextInput::make('address')
                    ->default(null),
                Textarea::make('contacts')
                    ->columnSpanFull(),
                TextInput::make('course_id')
                    ->required()
                    ->numeric(),
                TextInput::make('academic_year')
                    ->numeric(),
                TextInput::make('email')
                    ->label('Email address')
                    ->email()
                    ->default(null),
                Textarea::make('remarks')
                    ->columnSpanFull(),
                TextInput::make('profile_url')
                    ->default(null),
                TextInput::make('student_contact_id')
                    ->numeric(),
                TextInput::make('student_parent_info')
                    ->numeric(),
                TextInput::make('student_education_id')
                    ->numeric(),
                TextInput::make('student_personal_id')
                    ->numeric(),
                TextInput::make('document_location_id')
                    ->numeric(),
                TextInput::make('student_id')
                    ->numeric(),
                TextInput::make('status')
                    ->default(null),
                TextInput::make('clearance_status')
                    ->default('pending'),
                TextInput::make('year_graduated')
                    ->default(null),
                TextInput::make('special_order')
                    ->default(null),
                DatePicker::make('issued_date'),
            ]);
    }
}
