<?php

namespace App\Filament\Resources\Students\Schemas;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Grid;
use Filament\Support\Enums\FontWeight;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Infolists\Components\ImageEntry;
use Filament\Schemas\Components\Tabs;
use Ysfkaya\FilamentPhoneInput\Infolists\PhoneEntry;
use Ysfkaya\FilamentPhoneInput\PhoneInputNumberType;
use App\Models\Student;

class StudentInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make("Student Details")
                ->columns([
                    "default" => 1,
                    "md" => 2,
                    "lg" => 3,
                ])
                ->columnSpan(4)
                ->schema([
                    Grid::make([
                        "default" => 1,
                        "md" => 2,
                        "lg" => 3,
                    ])
                    ->columnSpan(["md" => 2 ])
                        ->schema([
                            TextEntry::make("full_name")->label("Full Name"),
                            TextEntry::make("email")->label("Email"),
                            PhoneEntry::make(
                                "studentContactsInfo.personal_contact",
                            )
                                ->displayFormat(PhoneInputNumberType::NATIONAL)
                                ->label("Phone"),
                            TextEntry::make("birth_date")->label("Birth Date"),
                            ImageEntry::make("DocumentLocation.picture_1x1")
                                ->label("Student Picture")
                                ->circular()
                                ->defaultImageUrl(
                                    "https://via.placeholder.com/150",
                                )
                                ->visibility("private"),
                        ]),
                    Section::make("Student info")
                        ->columnSpan(["md" => 1])
                        ->collapsible()
                        ->schema([
                            TextEntry::make("id")
                                ->badge()
                                ->icon("heroicon-m-user")
                                ->iconColor("warning")
                                ->weight(FontWeight::Bold)
                                ->copyable()
                                ->copyMessage("Copied!")
                                ->label("Student ID"),
                            TextEntry::make("created_at")
                                ->since()
                                ->label("Created at"),

                            TextEntry::make("updated_at")
                                ->since()
                                ->label("Last modified at"),
                            TextEntry::make("Course.code")
                                ->badge()

                                ->label("Course"),
                        ]),

                    Fieldset::make("Address Information")->schema([
                        TextEntry::make("personalInfo.current_adress")->label(
                            "Current Address",
                        ),
                        TextEntry::make(
                            "personalInfo.permanent_address",
                        )->label("Permanent Address"),
                    ]),
                    Tabs::make("Additional Details")
                        ->columnSpan(["md" => 3])
                        ->tabs([
                            Tab::make("Academic Information")->schema([
                                TextEntry::make("academic_year")->label(
                                    "Academic Year",
                                ),
                                TextEntry::make("course.code")->label("Course"),
                            ]),
                            Tab::make("Parent Information")->schema([
                                TextEntry::make(
                                    "studentParentInfo.fathers_name",
                                )->label("Father Name"),
                                TextEntry::make(
                                    "studentParentInfo.mothers_name",
                                )->label("Mother Name"),
                            ]),
                            Tab::make("School Information")->schema([
                                TextEntry::make(
                                    "studentEducationInfo.elementary_school",
                                )->label("Elementary School"),
                                TextEntry::make(
                                    "studentEducationInfo.elementary_graduate_year",
                                )->label("Elementary School Graduation Year"),
                                TextEntry::make(
                                    "studentEducationInfo.elementary_school_address",
                                )->label("Elementary School Address"),
                                TextEntry::make(
                                    "studentEducationInfo.senior_high_name",
                                )->label("Senior High School"),
                                TextEntry::make(
                                    "studentEducationInfo.senior_high_graduate_year",
                                )->label("Senior High School Graduation Year"),
                                TextEntry::make(
                                    "studentEducationInfo.senior_high_address",
                                )->label("Senior High School Address"),
                            ]),
                            Tab::make("Contact Information")->schema([
                                TextEntry::make(
                                    "studentContactsInfo.personal_contact",
                                )->label("Phone Number"),
                                TextEntry::make(
                                    "studentContactsInfo.emergency_contact_name",
                                )->label("Emergency Contact Name"),
                                TextEntry::make(
                                    "studentContactsInfo.emergency_contact_phone",
                                )->label("Emergency Contact Phone"),
                                TextEntry::make(
                                    "studentContactsInfo.emergency_contact_address",
                                )->label("Emergency Contact Address"),
                                TextEntry::make(
                                    "studentContactsInfo.facebook_contact",
                                )->label("Facebook Contact"),
                            ]),
                        Tab::make("Clearance Status")->schema([
                                TextEntry::make("clearances")
                                    ->label("Current Clearance Status")
                                    ->state(
                                        fn(
                                            $record,
                                        ): string => $record->hasCurrentClearance()
                                            ? "Cleared"
                                            : "Not Cleared",
                                    )
                                    ->badge()
                                    ->color(
                                        fn(string $state): string => match (
                                            $state
                                        ) {
                                            "Cleared" => "success",
                                            "Not Cleared" => "danger",
                                            default => "gray",
                                        },
                                    ),
                                TextEntry::make(
                                    "getCurrentClearanceRecord.cleared_by",
                                )
                                    ->label("Cleared By")
                                    ->visible(
                                        fn(
                                            $record,
                                        ): bool => (bool) $record->hasCurrentClearance(),
                                    ),
                                TextEntry::make(
                                    "getCurrentClearanceRecord.cleared_at",
                                )
                                    ->label("Cleared At")
                                    ->dateTime()
                                    ->visible(
                                        fn(
                                            $record,
                                        ): bool => (bool) $record->hasCurrentClearance(),
                                    ),
                                TextEntry::make(
                                    "getCurrentClearanceRecord.remarks",
                                )
                                    ->label("Remarks")
                                    ->markdown()
                                    ->visible(
                                        fn(
                                            $record,
                                        ): bool => (bool) $record->getCurrentClearanceModel(),
                                    ),
                                TextEntry::make(
                                    "getCurrentClearanceRecord.academic_year",
                                )
                                    ->label("Academic Year")
                                    ->visible(
                                        fn(
                                            $record,
                                        ): bool => (bool) $record->getCurrentClearanceModel(),
                                    ),
                                TextEntry::make(
                                    "getCurrentClearanceRecord.formatted_semester",
                                )
                                    ->label("Semester")
                                    ->visible(
                                        fn(
                                            $record,
                                        ): bool => (bool) $record->getCurrentClearanceModel(),
                                    ),
                            ]),
                            Tab::make("Tuition Information")
                                ->schema([
                                    TextEntry::make(
                                        "getCurrentTuitionRecord.formatted_total_tuition",
                                    )
                                        ->label("Total Tuition")
                                        ->placeholder(
                                            "No tuition record found",
                                        ),
                                    TextEntry::make(
                                        "getCurrentTuitionRecord.formatted_total_lectures",
                                    )
                                        ->label("Lecture Fees")
                                        ->placeholder(
                                            "No tuition record found",
                                        ),
                                    TextEntry::make(
                                        "getCurrentTuitionRecord.formatted_total_laboratory",
                                    )
                                        ->label("Laboratory Fees")
                                        ->placeholder(
                                            "No tuition record found",
                                        ),
                                    TextEntry::make(
                                        "getCurrentTuitionRecord.formatted_total_miscelaneous_fees",
                                    )
                                        ->label("Miscellaneous Fees")
                                        ->placeholder(
                                            "No tuition record found",
                                        ),
                                    TextEntry::make(
                                        "getCurrentTuitionRecord.formatted_overall_tuition",
                                    )
                                        ->label("Overall Tuition")
                                        ->placeholder(
                                            "No tuition record found",
                                        ),
                                    TextEntry::make(
                                        "getCurrentTuitionRecord.formatted_downpayment",
                                    )
                                        ->label("Downpayment")
                                        ->placeholder(
                                            "No tuition record found",
                                        ),
                                    TextEntry::make(
                                        "getCurrentTuitionRecord.formatted_total_balance",
                                    )
                                        ->label("Balance")
                                        ->placeholder("No tuition record found")
                                        ->badge()
                                        ->color(function ($record): string {
                                            $tuition = $record->getCurrentTuitionModel();
                                            if (!$tuition) {
                                                return "gray";
                                            }

                                            return $tuition->total_balance <= 0
                                                ? "success"
                                                : "danger";
                                        }),
                                    TextEntry::make(
                                        "getCurrentTuitionRecord.formatted_discount",
                                    )
                                        ->label("Discount")
                                        ->placeholder(
                                            "No tuition record found",
                                        ),
                                    TextEntry::make(
                                        "getCurrentTuitionRecord.payment_status",
                                    )
                                        ->label("Payment Status")
                                        ->placeholder("No tuition record found")
                                        ->badge()
                                        ->color(function ($record): string {
                                            $tuition = $record->getCurrentTuitionModel();
                                            if (!$tuition) {
                                                return "gray";
                                            }

                                            return $tuition->total_balance <= 0
                                                ? "success"
                                                : "warning";
                                        }),
                                    TextEntry::make(
                                        "getCurrentTuitionRecord.formatted_semester",
                                    )
                                        ->label("Semester")
                                        ->placeholder(
                                            "No tuition record found",
                                        ),
                                    TextEntry::make(
                                        "getCurrentTuitionRecord.academic_year",
                                    )
                                        ->label("Academic Year")
                                        ->placeholder(
                                            "No tuition record found",
                                        ),
                                ])
                                ->columns(2),
                            Tab::make("Current Enrolled Subjects")->schema(
                                [
                                    \Filament\Infolists\Components\RepeatableEntry::make(
                                        "subjectEnrolled",
                                    )
                                        ->label("Enrolled Subjects")
                                        ->schema([
                                            TextEntry::make(
                                                "subject.code",
                                            )->label("Subject Code"),
                                            TextEntry::make(
                                                "subject.title",
                                            )->label("Subject Title"),
                                            TextEntry::make(
                                                "subject.units",
                                            )->label("Units"),
                                            TextEntry::make(
                                                "class.section",
                                            )->label("Section"),
                                        ])
                                        ->columns(4)
                                        ->columnSpan(2),
                                ],
                            ),
                            Tab::make("Document Location")
                                ->columns([
                                    "default" => 1,
                                    "md" => 2,
                                    "lg" => 3,
                                ])
                                ->schema([
                                    ImageEntry::make(
                                        "DocumentLocation.transcript_records",
                                    )
                                        ->label("Transcript Records")

                                        ->defaultImageUrl(
                                            "https://via.placeholder.com/150",
                                        )
                                        ->visibility("private"),
                                    ImageEntry::make(
                                        "DocumentLocation.transfer_credentials",
                                    )
                                        ->label("Transfer Credentials")

                                        ->defaultImageUrl(
                                            "https://via.placeholder.com/150",
                                        )
                                        ->visibility("private"),
                                    ImageEntry::make(
                                        "DocumentLocation.good_moral_cert",
                                    )
                                        ->label("Good Moral Certificate")

                                        ->defaultImageUrl(
                                            "https://via.placeholder.com/150",
                                        )
                                        ->visibility("private"),
                                    ImageEntry::make(
                                        "DocumentLocation.form_137",
                                    )
                                        ->label("Form 137")

                                        ->defaultImageUrl(
                                            "https://via.placeholder.com/150",
                                        )
                                        ->visibility("private"),
                                    ImageEntry::make(
                                        "DocumentLocation.form_138",
                                    )
                                        ->label("Form 138")

                                        ->defaultImageUrl(
                                            "https://via.placeholder.com/150",
                                        )
                                        ->visibility("private"),
                                    ImageEntry::make(
                                        "DocumentLocation.birth_certificate",
                                    )
                                        ->label("Birth Certificate")

                                        ->defaultImageUrl(
                                            "https://via.placeholder.com/150",
                                        )
                                        ->visibility("private"),
                                ]),
                        ]),

                   
                ]),
            Section::make('Checklist')
    ->columnSpanFull()
    ->schema([
        Tabs::make('Checklist')
            ->tabs(function (Student $record): array {
                $tabs = [];
                $groupedSubjects = $record->subjects()->orderBy('academic_year')->orderBy('semester')->get()->groupBy('academic_year');
                $subjectEnrolled = $record->subjectEnrolled->keyBy('subject_id');

                $academicYears = $groupedSubjects->keys()->sort()->values();
                $yearLevelMap = $academicYears->mapWithKeys(function ($year, $index) {
                    return [$year => $index + 1];
                });

                foreach ($groupedSubjects as $year => $subjectsForYear) {
                    $yearLevel = $yearLevelMap[$year];
                    $yearName = match ($yearLevel) {
                        1 => '1st Year',
                        2 => '2nd Year',
                        3 => '3rd Year',
                        4 => '4th Year',
                        default => "{$yearLevel}th Year",
                    };

                    $tabs[] = Tab::make($yearName)
                        ->schema([
                            Tabs::make('Semesters')
                                ->tabs(function () use ($subjectsForYear, $subjectEnrolled): array {
                                    $semesterTabs = [];
                                    $semesters = $subjectsForYear->groupBy('semester');

                                    foreach ($semesters as $semester => $subjects) {
                                        $semesterName = match ((int) $semester) {
                                            1 => '1st Semester',
                                            2 => '2nd Semester',
                                            3 => 'Summer',
                                            default => "Semester {$semester}",
                                        };
                                        $semesterTabs[] = Tab::make($semesterName)
                                            ->schema([
                                                TextEntry::make('subject_table')
                                                    ->label('')
                                                    ->html()
                                                    ->state(function() use ($subjects, $subjectEnrolled): string {
                                                        $html = '<table class="w-full text-sm text-left rtl:text-right fi-table">';
                                                        $html .= '<thead class="fi-table-header">';
                                                        $html .= '<tr>';
                                                        $html .= '<th class="fi-table-header-cell p-2">Code</th>';
                                                        $html .= '<th class="fi-table-header-cell p-2">Title</th>';
                                                        $html .= '<th class="fi-table-header-cell p-2 text-right">Units</th>';
                                                        $html .= '<th class="fi-table-header-cell p-2">Status</th>';
                                                        $html .= '<th class="fi-table-header-cell p-2">Grade</th>';
                                                        $html .= '</tr>';
                                                        $html .= '</thead>';
                                                        $html .= '<tbody class="fi-table-body">';

                                                        foreach ($subjects as $subject) {
                                                            $enrolledSubject = $subjectEnrolled->get($subject->id);
                                                            $status = 'Not Completed';
                                                            $statusColor = 'danger';
                                                            $grade = '-';
                                                            $gradeColor = 'gray';

                                                            if ($enrolledSubject) {
                                                                if ($enrolledSubject->grade) {
                                                                    $status = 'Completed';
                                                                    $statusColor = 'success';
                                                                    $grade = number_format($enrolledSubject->grade, 2);
                                                                    $gradeColor = \App\Enums\GradeEnum::fromGrade($enrolledSubject->grade)->getColor();
                                                                } else {
                                                                    $status = 'In Progress';
                                                                    $statusColor = 'warning';
                                                                }
                                                            }
                                                            
                                                            $statusBadge = view('filament::components.badge', ['color' => $statusColor, 'slot' => $status])->render();
                                                            $gradeBadge = view('filament::components.badge', ['color' => $gradeColor, 'slot' => $grade])->render();

                                                            $html .= '<tr class="fi-table-row">';
                                                            $html .= '<td class="fi-table-cell p-2">' . e($subject->code) . '</td>';
                                                            $html .= '<td class="fi-table-cell p-2">' . e($subject->title) . '</td>';
                                                            $html .= '<td class="fi-table-cell p-2 text-right">' . e($subject->units) . '</td>';
                                                            $html .= '<td class="fi-table-cell p-2">' . $statusBadge . '</td>';
                                                            $html .= '<td class="fi-table-cell p-2">' . $gradeBadge . '</td>';
                                                            $html .= '</tr>';
                                                        }

                                                        $html .= '</tbody></table>';
                                                        return $html;
                                                    })
                                            ]);
                                    }
                                    return $semesterTabs;
                                })
                        ]);
                }
                return $tabs;
            })
    ])
        
            ]);
    }
}
