<?php

namespace App\Filament\Resources\Courses\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class CourseForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('code')
                    ->required(),
                TextInput::make('title')
                    ->required(),
                Textarea::make('description')
                    ->required()
                    ->columnSpanFull(),
                TextInput::make('department')
                    ->required(),
                TextInput::make('lec_per_unit')
                    ->numeric(),
                TextInput::make('lab_per_unit')
                    ->numeric(),
                Textarea::make('remarks')
                    ->columnSpanFull(),
                TextInput::make('curriculum_year')
                    ->default(null),
                TextInput::make('miscelaneous')
                    ->numeric(),
            ]);
    }
}
