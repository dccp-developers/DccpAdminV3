<?php

namespace App\Filament\Resources\Students\Schemas;

use App\Models\Student;
use Filament\Schemas\Schema;
use Illuminate\Support\Carbon;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\DatePicker;
use Filament\Schemas\Components\Fieldset;
use Filament\Forms\Components\Placeholder;
use Ysfkaya\FilamentPhoneInput\Forms\PhoneInput;

class StudentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->schema(self::getBasicInfoSection())
                    ->columns(2)
                    ->columnSpan([
                        "lg" => fn(?Student $record): int => $record instanceof
                        Student
                            ? 2
                            : 3,
                    ]),
                Section::make()
                    ->schema(self::getMetadataSection())
                    ->columnSpan(["lg" => 1])
                    ->hidden(
                        fn(?Student $record): bool => !$record instanceof
                            Student,
                    ),
                Section::make()
                    ->schema(self::getAdditionalInfoSection())
                    ->columns(2)
                    ->columnSpan([
                        "lg" => fn(?Student $record): int => $record instanceof
                        Student
                            ? 2
                            : 3,
                    ])
                    ->collapsed()
                    ->hidden(
                        fn(?Student $record): bool => !$record instanceof
                            Student,
                    )
                    ->lazy(),
            ])
            ->columns(3);
        
    }
    private static function getBasicInfoSection(): array
    {
        return [
            TextInput::make("id")
                ->label("Student ID")
                ->unique(Student::class, "id", ignoreRecord: true)
                ->numeric()
                ->required()
                ->rules(["required", "numeric"]),
            // ->disabled(fn (?Students $record) => $record !== null && !auth()->user()->can('update_student_id')),
            TextInput::make("first_name")
                ->maxLength(50)
                ->required(),
            TextInput::make("last_name")
                ->maxLength(50)
                ->required(),
            TextInput::make("middle_name")->maxLength(20),
            self::getGenderSelect(),
            self::getBirthDatePicker(),
            TextInput::make("age")
                ->readonly()
                ->numeric()
                ->required(),
            TextInput::make("email")
                ->label("Email address")
                ->email()
                ->maxLength(255),
            Select::make("course_id")
                ->relationship("course", "code")
                ->searchable()
                ->preload(),
            Select::make("academic_year")
                ->options([
                    "1" => "1st Year",
                    "2" => "2nd Year",
                    "3" => "3rd Year",
                    "4" => "4th Year",
                    "5" => "Graduate",
                ])
                ->required(),

            Textarea::make("remarks")
                ->label("Remarks")
                ->columnSpanFull(),
        ];
    }
private static function getGenderSelect(): Select
    {
        return Select::make("gender")
            ->options([
                "male" => "Male",
                "female" => "Female",
            ])
            ->required();
    }

    private static function getBirthDatePicker(): DatePicker
    {
        return DatePicker::make("birth_date")
            ->maxDate("today")
            ->live(debounce: 500)
            ->afterStateUpdated(function ($set, $state): void {
                if ($state) {
                    $age = Carbon::parse($state)->age;
                    $set("age", $age);
                }
            })
            ->required();
    }

    private static function getMetadataSection(): array
    {
        return [
            Placeholder::make("id")
                ->label("Student ID")
                ->content(fn(?Student $record): ?string => $record?->id),
            Placeholder::make("created_at")
                ->label("Created at")
                ->content(
                    fn(
                       Student  $record,
                    ): ?string => $record->created_at?->diffForHumans(),
                ),
            Placeholder::make("Course")
                ->label("Course")
                ->content(
                    fn(Student $record): ?string => $record->Course->code ??
                        null,
                ),
        ];
    }

    private static function getAdditionalInfoSection(): array
    {
        return [
            self::getGuardianContactInfo(),
            self::getParentInfo(),
            self::getEducationInfo(),
            self::getAddressInfo(),
            self::getPersonalInfo(),
        ];
    }

    private static function getGuardianContactInfo(): Fieldset
    {
        return Fieldset::make("Guardian Contact Informations")
            ->relationship("studentContactsInfo")
            ->schema([
                PhoneInput::make("personal_contact")
                    ->label("Student Contact Number")
                    ->initialCountry("ph"),
                TextInput::make(
                    "emergency_contact_name",
                )->label("Guardian Name"),
                PhoneInput::make("emergency_contact_phone")
                    ->defaultCountry("PH")
                    ->initialCountry("ph")
                    ->label("Guardian Contact Number"),
                TextInput::make(
                    "emergency_contact_address",
                )->label("Guardian Address"),
            ]);
    }

    private static function getParentInfo(): Fieldset
    {
        return Fieldset::make("Parent Information")
            ->relationship("studentParentInfo")
            ->schema([
               TextInput::make("fathers_name")->label(
                    "Father's Name",
                ),
               TextInput::make("mothers_name")->label(
                    "Mother's Name",
                ),
            ]);
    }

    private static function getEducationInfo(): Fieldset
    {
        return Fieldset::make("Education Information")
            ->relationship("studentEducationInfo")
            ->schema([
                TextInput::make("elementary_school")->label(
                    "Elementary School",
                ),
                TextInput::make(
                    "elementary_graduate_year",
                )->label("Elementary School Graduation Year"),
                TextInput::make("elementary_school_address")
                    ->columnSpanFull()
                    ->label("Elementary School Address"),
                TextInput::make(
                    "junior_high_school_name",
                )->label("Junior High School Name"),
                TextInput::make(
                    "junior_high_graduation_year",
                )->label("Junior High School Graduation Year"),
                TextInput::make("junior_high_school_address")
                    ->columnSpanFull()
                    ->label("Junior High School Address"),
                TextInput::make("senior_high_name")->label(
                    "Senior High School",
                ),
                TextInput::make(
                    "senior_high_graduate_year",
                )->label("Senior High School Graduation Year"),
                TextInput::make("senior_high_address")
                    ->columnSpanFull()
                    ->label("Senior High School Address"),
            ]);
    }

    private static function getAddressInfo(): Fieldset
    {
        return Fieldset::make("Address Information")
            ->relationship("personalInfo")
            ->schema([
                TextInput::make("current_adress")->label(
                    "Current Address",
                ),
                TextInput::make("permanent_address")->label(
                    "Permanent Address",
                ),
            ])
            ->columns(1);
    }

    private static function getPersonalInfo(): Fieldset
    {
        return Fieldset::make("Personal Information")
            ->relationship("personalInfo")
            ->schema([
                TextInput::make("birthplace")
                    ->hint("(Municipality / City)")
                    ->label("Birthplace"),
                TextInput::make("civil_status")->label(
                    "Civil Status",
                ),
                TextInput::make("citizenship")->label(
                    "Citizenship",
                ),
                TextInput::make("religion")->label("Religion"),
                TextInput::make("weight")
                    ->label("Weight")
                    ->numeric(),
                TextInput::make("height")->label("Height"),
                TextInput::make("current_adress")->label(
                    "Current Address",
                ),
                TextInput::make("permanent_address")->label(
                    "Permanent Address",
                ),
            ])
            ->columns(2);
    }

}
