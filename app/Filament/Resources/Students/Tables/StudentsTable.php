<?php

namespace App\Filament\Resources\Students\Tables;

use App\Models\Student;
use Filament\Tables\Table;
use App\Models\GeneralSetting;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Actions\ForceDeleteBulkAction;

class StudentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make("id")
                    ->label("ID")
                    ->badge()
                    ->searchable()
                    ->sortable(),
                TextColumn::make("full_name")
                    ->searchable(["first_name", "last_name"])
                    ->sortable(),
                TextColumn::make("course.code")->sortable(),
                TextColumn::make("gender")->toggleable(
                    isToggledHiddenByDefault: true,
                ),
                TextColumn::make("academic_year")->toggleable(
                    isToggledHiddenByDefault: true,
                ),
                TextColumn::make("email")->toggleable(
                    isToggledHiddenByDefault: true,
                ),
                IconColumn::make("clearances")
                    ->label("Clearance Status")
                    ->getStateUsing(
                        fn(
                            Student $record,
                        ): bool => $record->hasCurrentClearance(),
                    )
                    ->boolean()
                    ->trueIcon("heroicon-o-check-circle")
                    ->falseIcon("heroicon-o-x-circle")
                    ->trueColor("success")
                    ->falseColor("danger")
                    ->tooltip(function (Student $record): string {
                        $settings = GeneralSetting::first();
                        $status = $record->hasCurrentClearance()
                            ? "Cleared"
                            : "Not Cleared";

                        return "{$status} for {$settings->getSemester()} {$settings->getSchoolYearString()}";
                    }),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
