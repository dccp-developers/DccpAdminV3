@php
    $student = $getRecord();
    $courseId = $get('course_id');
    $studentId = $get('student_id');
    $year = $get('year');

    $subjects = \App\Models\Subject::select('id', 'code', 'title', 'semester', 'units')
        ->where('course_id', $courseId)
        ->where('academic_year', $year)
        ->get()
        ->groupBy('semester');

    $enrolledSubjects = \App\Models\SubjectEnrolled::where('student_id', $studentId)
        ->get()
        ->keyBy('subject_id');
@endphp

<div class="space-y-4">
    @foreach ($subjects as $semester => $semesterSubjects)
        <div class="mb-6">
            <h3 class="text-lg font-medium mb-2">Semester {{ $semester }}</h3>
            <div class="overflow-x-auto">
                <x-filament-tables::table>
                    <x-slot name="header">
                        <x-filament-tables::header-cell>
                            Code
                        </x-filament-tables::header-cell>
                        <x-filament-tables::header-cell>
                            Title
                        </x-filament-tables::header-cell>
                        <x-filament-tables::header-cell alignment="right">
                            Units
                        </x-filament-tables::header-cell>
                        <x-filament-tables::header-cell>
                            Status
                        </x-filament-tables::header-cell>
                        <x-filament-tables::header-cell>
                            Grade
                        </x-filament-tables::header-cell>
                    </x-slot>

                    @foreach ($semesterSubjects as $index => $subject)
                        @php
                            $enrolledSubject = $enrolledSubjects->get($subject->id);
                            $grade = $enrolledSubject?->grade ?? '-';
                            
                            if ($enrolledSubject) {
                                if ($grade !== null && $grade !== '-') {
                                    $status = 'Completed';
                                    $statusColor = 'success';
                                } else {
                                    $status = 'In Progress';
                                    $statusColor = 'warning';
                                }
                            } else {
                                $status = 'Not Completed';
                                $statusColor = 'danger';
                            }

                            $gradeColor = 'gray';
                            if (is_numeric($grade)) {
                                $gradeValue = (float) $grade;
                                if ($gradeValue >= 75) {
                                    $gradeColor = 'success';
                                } else {
                                    $gradeColor = 'danger';
                                }
                            }
                        @endphp

                        <x-filament-tables::row :striped="true">
                            <x-filament-tables::cell>
                                {{ $subject->code }}
                            </x-filament-tables::cell>
                            <x-filament-tables::cell>
                                {{ $subject->title }}
                            </x-filament-tables::cell>
                            <x-filament-tables::cell alignment="right">
                                {{ $subject->units }}
                            </x-filament-tables::cell>
                            <x-filament-tables::cell>
                                <x-filament::badge :color="$statusColor">
                                    {{ $status }}
                                </x-filament::badge>
                            </x-filament-tables::cell>
                            <x-filament-tables::cell>
                                <x-filament::badge :color="$gradeColor">
                                    {{ $grade }}
                                </x-filament::badge>
                            </x-filament-tables::cell>
                        </x-filament-tables::row>
                    @endforeach
                </x-filament-tables::table>
            </div>
        </div>
    @endforeach
</div> 