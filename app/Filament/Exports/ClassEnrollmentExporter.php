<?php

declare(strict_types=1);

namespace App\Filament\Exports;

use App\Models\ClassEnrollment;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

final class ClassEnrollmentExporter extends Exporter
{
    protected static ?string $model = ClassEnrollment::class;

    public static function getColumns(): array
    {
        return [
            // Student Information
            ExportColumn::make('student.student_id')
                ->label('Student ID'),
            ExportColumn::make('student.full_name')
                ->label('Student Name'),
            ExportColumn::make('student.first_name')
                ->label('First Name'),
            ExportColumn::make('student.last_name')
                ->label('Last Name'),
            ExportColumn::make('student.email')
                ->label('Email'),
            ExportColumn::make('student.studentContactsInfo.contact_number')
                ->label('Contact Number'),
            ExportColumn::make('student.course.code')
                ->label('Course'),
            ExportColumn::make('student.academic_year')
                ->label('Year Level')
                ->formatStateUsing(fn ($state) => match ($state) {
                    '1' => '1st Year',
                    '2' => '2nd Year',
                    '3' => '3rd Year',
                    '4' => '4th Year',
                    default => $state ?? 'N/A',
                }),
            
            // Enrollment Status
            ExportColumn::make('status')
                ->label('Status')
                ->formatStateUsing(fn ($state): string => $state ? 'Active' : 'Inactive'),
            
            // Grades
            ExportColumn::make('prelim_grade')
                ->label('Prelim Grade')
                ->formatStateUsing(fn ($state): string => $state ? number_format((float) $state, 2) : ''),
            ExportColumn::make('midterm_grade')
                ->label('Midterm Grade')
                ->formatStateUsing(fn ($state): string => $state ? number_format((float) $state, 2) : ''),
            ExportColumn::make('finals_grade')
                ->label('Finals Grade')
                ->formatStateUsing(fn ($state): string => $state ? number_format((float) $state, 2) : ''),
            ExportColumn::make('total_average')
                ->label('Final Grade')
                ->formatStateUsing(fn ($state): string => $state ? number_format((float) $state, 2) : ''),
            
            // Additional Information
            ExportColumn::make('remarks')
                ->label('Remarks'),
            ExportColumn::make('created_at')
                ->label('Date Enrolled')
                ->formatStateUsing(fn ($state): string => $state ? $state->format('Y-m-d H:i:s') : 'N/A'),
            
            // Class Information
            ExportColumn::make('class.subject_code')
                ->label('Subject Code'),
            ExportColumn::make('class.subject_title')
                ->label('Subject Title'),
            ExportColumn::make('class.section')
                ->label('Section'),
            ExportColumn::make('class.semester')
                ->label('Semester')
                ->formatStateUsing(fn ($state) => match ($state) {
                    '1st' => '1st Semester',
                    '2nd' => '2nd Semester',
                    'summer' => 'Summer',
                    default => $state ?? 'N/A',
                }),
            ExportColumn::make('class.school_year')
                ->label('School Year'),
            ExportColumn::make('class.Faculty.full_name')
                ->label('Faculty'),
            
            // Grade Verification
            ExportColumn::make('is_grades_finalized')
                ->label('Grades Finalized')
                ->formatStateUsing(fn ($state): string => $state ? 'Yes' : 'No'),
            ExportColumn::make('is_grades_verified')
                ->label('Grades Verified')
                ->formatStateUsing(fn ($state): string => $state ? 'Yes' : 'No'),
            ExportColumn::make('verified_at')
                ->label('Verified Date')
                ->formatStateUsing(fn ($state): string => $state ? $state->format('Y-m-d H:i:s') : ''),
            ExportColumn::make('verification_notes')
                ->label('Verification Notes'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your class enrollment export has completed and ' . number_format($export->successful_rows) . ' ' . str('row')->plural($export->successful_rows) . ' exported.';

        if (($failedRowsCount = $export->getFailedRowsCount()) !== 0) {
            $body .= ' ' . number_format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed to export.';
        }

        return $body;
    }
}
