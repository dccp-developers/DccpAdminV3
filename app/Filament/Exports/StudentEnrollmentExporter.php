<?php

declare(strict_types=1);

namespace App\Filament\Exports;

use App\Models\StudentEnrollment;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

final class StudentEnrollmentExporter extends Exporter
{
    protected static ?string $model = StudentEnrollment::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('student_id')
                ->label('Student ID'),
            ExportColumn::make('student.full_name')
                ->label('Student Name'),
            ExportColumn::make('student.course.code')
                ->label('Course'),
            ExportColumn::make('status')
                ->label('Status'),
            ExportColumn::make('school_year')
                ->label('School Year'),
            ExportColumn::make('student.academic_year')
                ->label('Academic Year Level'),
            ExportColumn::make('semester')
                ->label('Semester'),
            ExportColumn::make('created_at')
                ->label('Enrolled Date'),
            ExportColumn::make('studentTuition.discount')
                ->label('Discount'),
            ExportColumn::make('studentTuition.total_lectures')
                ->label('Total Lecture Fee'),
            ExportColumn::make('studentTuition.total_laboratory')
                ->label('Total Laboratory Fee'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your student enrollment export has completed and '.number_format($export->successful_rows).' '.str('row')->plural($export->successful_rows).' exported.';

        if (($failedRowsCount = $export->getFailedRowsCount()) !== 0) {
            $body .= ' '.number_format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to export.';
        }

        return $body;
    }
}
