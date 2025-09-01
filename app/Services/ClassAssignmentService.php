<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Classes;
use Illuminate\Support\Collection;

final class ClassAssignmentService
{
    public function __construct(
        private readonly GeneralSettingsService $settingsService
    ) {}

    public function getUnassignedClassOptions(): array
    {
        return Classes::currentAcademicPeriod()
            ->whereNull('faculty_id')
            ->get()
            ->mapWithKeys(function ($class) {
                return [$class->id => $this->formatClassLabel($class)];
            })
            ->toArray();
    }

    public function formatClassLabel(Classes $class): string
    {
        $type = $class->isShs() ? 'SHS' : 'College';
        $info = $class->isShs() ? $class->formatted_track_strand : $class->formatted_course_codes;
        
        $label = "[{$class->subject_code}] {$class->subject_title} - {$class->section} ({$type})";
        
        if ($info && $info !== 'N/A') {
            $label .= " - {$info}";
        }

        return $label;
    }

    public function assignClassesToFaculty(array $classIds, string $facultyId): int
    {
        if (empty($classIds)) {
            return 0;
        }

        Classes::whereIn('id', $classIds)
            ->update(['faculty_id' => $facultyId]);

        return count($classIds);
    }

    public function distributeClassesAmongFaculty(array $classIds, Collection $facultyMembers): int
    {
        if (empty($classIds) || $facultyMembers->isEmpty()) {
            return 0;
        }

        $facultyCount = $facultyMembers->count();
        
        foreach ($classIds as $index => $classId) {
            $facultyIndex = $index % $facultyCount;
            $facultyId = (string) $facultyMembers[$facultyIndex]['id'];

            Classes::where('id', $classId)
                ->update(['faculty_id' => $facultyId]);
        }

        return count($classIds);
    }

    public function unassignClass(Classes $class): void
    {
        $class->update(['faculty_id' => null]);
    }

    public function unassignClasses(Collection $classes): int
    {
        $count = $classes->count();
        $classes->each(fn ($record) => $record->update(['faculty_id' => null]));
        
        return $count;
    }
}
