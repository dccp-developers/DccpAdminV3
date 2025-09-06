<?php

declare(strict_types=1);

namespace App\Filament\Resources\StudentResource\RelationManagers;

use Filament\Tables;
use App\Enums\GradeEnum;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Filament\Actions\Action;
use Filament\Schemas\Schema;
// use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use App\Models\SubjectEnrollment;
use Filament\Actions\ActionGroup;
use App\Enums\SubjectEnrolledEnum;
// use Filament\Tables\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\ExportAction;
// use Filament\Tables\Actions\ActionGroup;
use Livewire\Component as Livewire;
use Filament\Actions\BulkActionGroup;
// use Filament\Tables\Actions\ExportAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ExportBulkAction;
use Filament\Forms\Components\Textarea;
use Illuminate\Support\Facades\Session;
use Filament\Forms\Components\TextInput;
// use Filament\Tables\Actions\ExportBulkAction;
use Illuminate\Database\Eloquent\Builder;
use App\Filament\Exports\SubjectEnrollmentExporter;
use Filament\Resources\RelationManagers\RelationManager;

final class EnrolledSubjectsRelationManager extends RelationManager
{
    public ?array $data = [];

    protected static string $relationship = 'subjectEnrolled';

    protected static bool $isLazy = false;

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('classification')
                    ->options(SubjectEnrolledEnum::class)
                    ->reactive()
                    ->default(SubjectEnrolledEnum::INTERNAL->value)
                    ->required()
                    ->afterStateUpdated(function ($set, $state): void {
                        if ($state !== SubjectEnrolledEnum::CREDITED->value) {
                            $set('school_name', null);
                            $set('credited_subject_id', null);
                        }
                    })
                    ->enum(SubjectEnrolledEnum::class),

                TextInput::make('school_name')
                    ->label('Previous School Name')
                    ->visible(fn ($get): bool => in_array($get('classification'), [
                        SubjectEnrolledEnum::CREDITED->value,
                        SubjectEnrolledEnum::NON_CREDITED->value,
                    ]))
                    ->required(fn ($get): bool => in_array($get('classification'), [
                        SubjectEnrolledEnum::CREDITED->value,
                        SubjectEnrolledEnum::NON_CREDITED->value,
                    ]))
                    ->datalist(SubjectEnrollment::distinct()->pluck('school_name')->toArray()),
                Select::make('subject_id')
                    ->label('Subject')
                    ->options(function (Livewire $livewire, $get) {
                        $classification = $get('classification');

                        if ($classification === SubjectEnrolledEnum::NON_CREDITED->value) {
                            return \App\Models\Subject::where('is_credited', false)
                                ->pluck('title', 'id');
                        }
                        $courseId = $livewire->ownerRecord->course_id;
                        $enrolledSubjectIds = $livewire->ownerRecord->subjectEnrolled
                            ->filter(fn ($subject): bool => $subject->grade === null ||
                                ($subject->grade >= 1.00 && $subject->grade <= 4.00) ||
                                $subject->grade >= 75)
                            ->pluck('subject_id')
                            ->toArray();
                        $query = \App\Models\Subject::whereNotIn('id', $enrolledSubjectIds)
                            ->where('course_id', $courseId);

                        return $query->get()
                            ->mapWithKeys(fn ($subject) => [
                                $subject->id => "{$subject->code} - {$subject->title}",
                            ])
                            ->filter();

                    })
                    ->loadingMessage('Loading subjects...')
                    ->noSearchResultsMessage('No subjects found.')
                    ->searchable()
                    ->required(),
                Select::make('credited_subject_id')
                    ->label('Credited Subject')
                    ->options(fn () => \App\Models\Subject::where('is_credited', true)
                        ->get()
                        ->mapWithKeys(fn ($subject) => [$subject->id => "{$subject->code} - {$subject->title}"]))
                    ->searchable()
                    ->createOptionForm([
                        TextInput::make('code')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('title')
                            ->required()
                            ->maxLength(255),
                        Textarea::make('description')
                            ->required()
                            ->columnSpanFull(),
                        // Select::make('course_id')
                        //     ->label('Course')
                        //     ->options(function () {
                        //         return \App\Models\Courses::pluck('title', 'id');
                        //     }),
                        TextInput::make('units')
                            ->numeric()
                            ->required(),
                        TextInput::make('lecture')
                            ->numeric()
                            ->required(),
                        TextInput::make('laboratory')
                            ->numeric(),
                        Hidden::make('is_credited')
                            ->default(1),
                    ])
                    ->createOptionUsing(fn ($data) => \App\Models\Subject::create($data))
                    ->visible(fn ($get): bool => $get('classification') === SubjectEnrolledEnum::CREDITED->value)
                    ->required(fn ($get): bool => $get('classification') === SubjectEnrolledEnum::CREDITED->value)
                    ->nullable(),

                TextInput::make('grade')
                    ->numeric()
                    ->placeholder('Ex: 85.5')
                    ->label('Grade')
                    ->minValue(1.0)
                    ->maxValue(100.0)
                    ->step(0.01),
                TextInput::make('instructor')
                    ->placeholder('Ex: John Doe')
                    ->maxLength(255)
                    ->label('Instructor Name'),
                Select::make('academic_year')
                    ->required()
                    ->label('Academic Year')
                    ->placeholder('Ex: 1st Year')
                    ->options([
                        1 => '1st Year',
                        2 => '2nd Year',
                        3 => '3rd Year',
                        4 => '4th Year',
                    ])
                    ->default(1),
                Select::make('school_year')
                    ->required()
                    ->placeholder('Ex: 2024 - 2025')
                    ->label('School Year')
                    ->options(function (): array {
                        $startYear = 2000;
                        $endYear = now()->year;
                        $years = [];
                        for ($year = $startYear; $year <= $endYear; $year++) {
                            $years["$year - ".($year + 1)] =
                                "$year - ".($year + 1);
                        }

                        return $years;

                    })
                    ->default(now()->year.' - '.(now()->year + 1)),
                Select::make('semester')
                    ->required()
                    ->placeholder('Ex: 1st Semester')
                    ->label('Semester')
                    ->options([
                        1 => '1st Semester',
                        2 => '2nd Semester',
                        3 => 'Summer',
                    ])
                    ->default(1),
                Textarea::make('remarks')
                    ->placeholder('Any remarks about the student')
                    ->label('Remarks')
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('grade')
            ->heading('List Of Enrolled Subjects')
            ->description(
                'List OF Subects that the Student Enrolled in the course, filtered by year and semester'
            )
            ->deferLoading()
            ->striped()
            ->columns([
                Tables\Columns\TextColumn::make('subject.code')
                    ->searchable(),
                Tables\Columns\TextColumn::make('subject.title')
                    ->searchable(),
                Tables\Columns\TextColumn::make('classification')
                    ->label('Classification')
                    ->badge()
                    ->color(fn ($state): string => match ($state) {
                        SubjectEnrolledEnum::CREDITED->value => 'success',
                        SubjectEnrolledEnum::NON_CREDITED->value => 'warning',
                        SubjectEnrolledEnum::INTERNAL->value => 'primary',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('grade')
                    ->badge()
                    ->color(fn ($state): string => $state ? GradeEnum::fromGrade($state)->getColor() : 'gray')
                    ->formatStateUsing(function ($state): ?string {
                        if ($state === null) {
                            return null;
                        }

                        return number_format((float) $state, 2);
                    }),

                Tables\Columns\TextColumn::make('instructor')
                    ->label('Instructor Name')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('academic_year'),
                Tables\Columns\TextColumn::make('school_year'),
                Tables\Columns\TextColumn::make('semester'),
                Tables\Columns\TextColumn::make('subject.pre_riquisite')
                    ->label('Pre-Requisite')
                    ->badge()
                    ->icon('heroicon-o-check'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('classification')
                    ->options(SubjectEnrolledEnum::class)
                    ->label('Classification'),
                Tables\Filters\SelectFilter::make('academic_year')
                    ->options([
                        '1' => '1st Year',
                        '2' => '2nd Year',
                        '3' => '3rd Year',
                        '4' => '4th Year',
                    ])
                    ->label('Academic Year'),
                Tables\Filters\SelectFilter::make('semester')
                    ->options([
                        1 => '1st Semester',
                        2 => '2nd Semester',
                        3 => 'Summer',
                    ])
                    ->label('Semester'),
                Tables\Filters\SelectFilter::make('grade')
                    ->options(collect(GradeEnum::cases())->mapWithKeys(
                        fn (GradeEnum $enum) => [$enum->value => $enum->getLabel()]
                    ))
                    ->query(function ($query, array $data) {
                        if ($data['value']) {
                            $enum = GradeEnum::from($data['value']);
                            $ranges = $enum->getGradeRanges();

                            return $query->where(function ($q) use ($ranges): void {
                                $q->whereBetween('grade', $ranges['point'])
                                    ->orWhereBetween('grade', $ranges['percentage']);
                            });
                        }
                    })
                    ->label('Grade'),
            ])
            ->headerActions([
                CreateAction::make()->after(function ($data): void {
                    $this->setSessionData($data);
                }),

                ActionGroup::make([
                    // Export All Subjects
                    ExportAction::make('export_all')
                        ->label('All Subjects')
                        ->icon('heroicon-o-document-arrow-down')
                        ->color('warning')
                        ->exporter(SubjectEnrollmentExporter::class)
                        ->fileName(fn (): string => 'Student-AllSubjects-'.now()->format('Y-m-d'))
                        ->modifyQueryUsing(fn (Builder $query, $livewire) => $query->where('student_id', $livewire->ownerRecord->id)),

                    Action::make('first_year_divider')
                        ->label('First Year')
                        ->disabled()
                        ->icon('heroicon-o-academic-cap'),

                    // Export First Year - First Semester
                    ExportAction::make('export_1y_1s')
                        ->label('1st Semester')
                        ->icon('heroicon-o-document-arrow-down')
                        ->exporter(SubjectEnrollmentExporter::class)
                        ->fileName(fn (): string => 'Student-1stYear-1stSemester-'.now()->format('Y-m-d'))
                        ->modifyQueryUsing(fn (Builder $query, $livewire) => $query->where('student_id', $livewire->ownerRecord->id)
                            ->where('academic_year', 1)
                            ->where('semester', 1)),

                    // Export First Year - Second Semester
                    ExportAction::make('export_1y_2s')
                        ->label('2nd Semester')
                        ->icon('heroicon-o-document-arrow-down')
                        ->exporter(SubjectEnrollmentExporter::class)
                        ->fileName(fn (): string => 'Student-1stYear-2ndSemester-'.now()->format('Y-m-d'))
                        ->modifyQueryUsing(fn (Builder $query, $livewire) => $query->where('student_id', $livewire->ownerRecord->id)
                            ->where('academic_year', 1)
                            ->where('semester', 2)),

                    Action::make('second_year_divider')
                        ->label('Second Year')
                        ->disabled()
                        ->icon('heroicon-o-academic-cap'),

                    // Export Second Year - First Semester
                    ExportAction::make('export_2y_1s')
                        ->label('1st Semester')
                        ->icon('heroicon-o-document-arrow-down')
                        ->exporter(SubjectEnrollmentExporter::class)
                        ->fileName(fn (): string => 'Student-2ndYear-1stSemester-'.now()->format('Y-m-d'))
                        ->modifyQueryUsing(fn (Builder $query, $livewire) => $query->where('student_id', $livewire->ownerRecord->id)
                            ->where('academic_year', 2)
                            ->where('semester', 1)),

                    // Export Second Year - Second Semester
                    ExportAction::make('export_2y_2s')
                        ->label('2nd Semester')
                        ->icon('heroicon-o-document-arrow-down')
                        ->exporter(SubjectEnrollmentExporter::class)
                        ->fileName(fn (): string => 'Student-2ndYear-2ndSemester-'.now()->format('Y-m-d'))
                        ->modifyQueryUsing(fn (Builder $query, $livewire) => $query->where('student_id', $livewire->ownerRecord->id)
                            ->where('academic_year', 2)
                            ->where('semester', 2)),

                    Action::make('third_year_divider')
                        ->label('Third Year')
                        ->disabled()
                        ->icon('heroicon-o-academic-cap'),

                    // Export Third Year - First Semester
                    ExportAction::make('export_3y_1s')
                        ->label('1st Semester')
                        ->icon('heroicon-o-document-arrow-down')
                        ->exporter(SubjectEnrollmentExporter::class)
                        ->fileName(fn (): string => 'Student-3rdYear-1stSemester-'.now()->format('Y-m-d'))
                        ->modifyQueryUsing(fn (Builder $query, $livewire) => $query->where('student_id', $livewire->ownerRecord->id)
                            ->where('academic_year', 3)
                            ->where('semester', 1)),

                    // Export Third Year - Second Semester
                    ExportAction::make('export_3y_2s')
                        ->label('2nd Semester')
                        ->icon('heroicon-o-document-arrow-down')
                        ->exporter(SubjectEnrollmentExporter::class)
                        ->fileName(fn (): string => 'Student-3rdYear-2ndSemester-'.now()->format('Y-m-d'))
                        ->modifyQueryUsing(fn (Builder $query, $livewire) => $query->where('student_id', $livewire->ownerRecord->id)
                            ->where('academic_year', 3)
                            ->where('semester', 2)),

                    Action::make('fourth_year_divider')
                        ->label('Fourth Year')
                        ->disabled()
                        ->icon('heroicon-o-academic-cap'),

                    // Export Fourth Year - First Semester
                    ExportAction::make('export_4y_1s')
                        ->label('1st Semester')
                        ->icon('heroicon-o-document-arrow-down')
                        ->exporter(SubjectEnrollmentExporter::class)
                        ->fileName(fn (): string => 'Student-4thYear-1stSemester-'.now()->format('Y-m-d'))
                        ->modifyQueryUsing(fn (Builder $query, $livewire) => $query->where('student_id', $livewire->ownerRecord->id)
                            ->where('academic_year', 4)
                            ->where('semester', 1)),

                    // Export Fourth Year - Second Semester
                    ExportAction::make('export_4y_2s')
                        ->label('2nd Semester')
                        ->icon('heroicon-o-document-arrow-down')
                        ->exporter(SubjectEnrollmentExporter::class)
                        ->fileName(fn (): string => 'Student-4thYear-2ndSemester-'.now()->format('Y-m-d'))
                        ->modifyQueryUsing(fn (Builder $query, $livewire) => $query->where('student_id', $livewire->ownerRecord->id)
                            ->where('academic_year', 4)
                            ->where('semester', 2)),
                ])
                    ->label('Export Subjects')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('success')
                    ->button(),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ExportBulkAction::make()
                        ->label('Export Selected')
                        ->icon('heroicon-o-document-arrow-down')
                        ->color('success')
                        ->exporter(SubjectEnrollmentExporter::class)
                        ->fileName(fn (): string => 'Selected-Subjects-Export-'.now()->format('Y-m-d-H-i-s'))
                        ->formats([
                            'xlsx',
                            'csv',
                        ]),
                ]),
            ]);
    }

    private function mutateFormDataBeforeCreate(array $data): array
    {
        if (! isset($data['classification'])) {
            $data['classification'] = SubjectEnrolledEnum::INTERNAL->value;
        }

        if ($data['classification'] !== SubjectEnrolledEnum::CREDITED->value) {
            $data['credited_subject_id'] = null;
        }

        return $data;
    }

    private function getSessionData(): array
    {
        return Session::get('enrolled_subjects_form_data', []);
    }

    private function setSessionData(array $data): void
    {
        Session::put(
            'enrolled_subjects_form_data',
            array_merge($data, [
                'subject_id' => null, // Reset unique fields
            ])
        );
    }
}
