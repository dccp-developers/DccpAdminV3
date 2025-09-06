<x-dynamic-component :component="$getEntryWrapperView()" :entry="$entry">
    @php
        $student = $getRecord();
        $studentSubjects = $student->subjects;
        $groupedSubjects = $studentSubjects->groupBy(['academic_year', 'semester']);
        $subjectEnrolled = $student->subjectEnrolled
            ->filter(function($subject) {
                return $subject->grade === null || 
                    ($subject->grade >= 1.00 && $subject->grade <= 4.00) ||
                    $subject->grade >= 75;
            })
            ->keyBy('subject_id');
    @endphp

    @foreach ($groupedSubjects as $academicYear => $semesters)
        <x-filament::section collapsible collapsed class="mt-2">
            <x-slot name="heading">
                Academic Year {{ $academicYear }}
            </x-slot>
            <x-filament-tables::container>   
            @foreach ($semesters as $semester => $subjects)
                <h3 class="text-lg font-medium mb-2">Semester {{ $semester }}</h3>
                <x-filament-tables::table class="w-full">
                    <x-slot name="header">
                        <x-filament-tables::header-cell class="px-4 py-2">
                            Code
                        </x-filament-tables::header-cell>
                        <x-filament-tables::header-cell class="px-4 py-2">
                            Title
                        </x-filament-tables::header-cell>
                        <x-filament-tables::header-cell class="px-4 py-2" alignment="right">
                            Units
                        </x-filament-tables::header-cell>
                        <x-filament-tables::header-cell class="px-4 py-2">
                            Status
                        </x-filament-tables::header-cell>
                        <x-filament-tables::header-cell class="px-4 py-2">
                            Grade
                        </x-filament-tables::header-cell>
                    </x-slot>

                    @foreach ($subjects as $index => $subject)
                        @php
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
                        @endphp

                        <x-filament-tables::row class="{{ $index % 2 === 0 ? 'bg-gray-50 dark:bg-gray-800' : 'bg-white dark:bg-gray-900' }}">
                            <x-filament-tables::cell class="px-4 py-2">
                                {{ $subject->code }}
                            </x-filament-tables::cell>
                            <x-filament-tables::cell class="px-4 py-2">
                                {{ $subject->title }}
                            </x-filament-tables::cell>
                            <x-filament-tables::cell class="px-4 py-2" alignment="right">
                                {{ $subject->units }}
                            </x-filament-tables::cell>
                            <x-filament-tables::cell class="px-4 py-2">
                                <x-filament::badge :color="$statusColor">
                                    {{ $status }}
                                </x-filament::badge>
                            </x-filament-tables::cell>
                            <x-filament-tables::cell class="px-4 py-2">
                                <x-filament::badge :color="$gradeColor">
                                    {{ $grade }}
                                </x-filament::badge>
                            </x-filament-tables::cell>
                        </x-filament-tables::row>
                    @endforeach
                </x-filament-tables::table>
            @endforeach
             </x-filament-tables::container>
        </x-filament::section>
    @endforeach
</x-dynamic-component>
