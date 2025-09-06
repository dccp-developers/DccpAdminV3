@foreach ($groupedSubjects as $academicYear => $semesters)
    <div x-data="{ collapsed: true }" class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 mt-6">
        <div class="fi-section-header flex items-center gap-x-3 overflow-hidden px-6 py-4 cursor-pointer" @click="collapsed = !collapsed">
            <div class="fi-section-header-heading flex-1">
                <h3 class="text-base font-semibold leading-6 text-gray-950 dark:text-white">Academic Year {{ $academicYear }}</h3>
            </div>
            <div class="fi-section-header-actions flex items-center gap-x-3">
                <svg class="w-6 h-6 text-gray-400 transition-transform duration-200" x-bind:class="{ '-rotate-180': !collapsed }" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"></path></svg>
            </div>
        </div>
        <div x-show="!collapsed" x-cloak class="fi-section-content-ctn border-t border-gray-200 dark:border-white/10">
            <div class="fi-section-content p-6">
                @foreach ($semesters as $semester => $subjects)
                    <h4 class="text-md font-semibold leading-6 text-gray-950 dark:text-white mb-4">Semester {{ $semester }}</h4>
                    <table class="w-full text-sm text-left rtl:text-right text-gray-500 dark:text-gray-400 fi-table">
                        <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
                            <tr>
                                <th scope="col" class="px-4 py-2">Code</th>
                                <th scope="col" class="px-4 py-2">Title</th>
                                <th scope="col" class="px-4 py-2 text-right">Units</th>
                                <th scope="col" class="px-4 py-2">Status</th>
                                <th scope="col" class="px-4 py-2">Grade</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($subjects as $subject)
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
                                <tr class="bg-white border-b dark:bg-gray-800 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">
                                    <td class="px-4 py-2">{{ $subject->code }}</td>
                                    <td class="px-4 py-2">{{ $subject->title }}</td>
                                    <td class="px-4 py-2 text-right">{{ $subject->units }}</td>
                                    <td class="px-4 py-2">
                                        <x-filament::badge :color="$statusColor">
                                            {{ $status }}
                                        </x-filament::badge>
                                    </td>
                                    <td class="px-4 py-2">
                                        <x-filament::badge :color="$gradeColor">
                                            {{ $grade }}
                                        </x-filament::badge>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <br>
                @endforeach
            </div>
        </div>
    </div>
@endforeach
