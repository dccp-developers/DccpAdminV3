<?php

declare(strict_types=1);

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class StudentTuition
 *
 * @property-read \App\Models\StudentEnrollment|null $enrollment
 * @property-read string $formatted_discount
 * @property-read string $formatted_downpayment
 * @property-read string $formatted_overall_tuition
 * @property-read string $formatted_semester
 * @property-read string $formatted_total_balance
 * @property-read string $formatted_total_laboratory
 * @property-read string $formatted_total_lectures
 * @property-read string $formatted_total_miscelaneous_fees
 * @property-read string $formatted_total_tuition
 * @property-read int $payment_progress
 * @property-read string $payment_status
 * @property-read string $status_class
 * @property-read \App\Models\Student|null $student
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentTuition newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentTuition newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentTuition onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentTuition query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentTuition withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentTuition withoutTrashed()
 * @mixin \Eloquent
 */
final class StudentTuition extends Model
{
    use SoftDeletes;

    protected $table = 'student_tuition';

    protected $fillable = [
        'total_tuition',
        'total_balance',
        'total_lectures',
        'total_laboratory',
        'total_miscelaneous_fees',
        'status',
        'semester',
        'school_year',
        'academic_year',
        'student_id',
        'enrollment_id',
        'discount',
        'downpayment',
        'overall_tuition',
        'paid',
    ];

    protected $casts = [
        'total_tuition' => 'float',
        'total_balance' => 'float',
        'total_lectures' => 'float',
        'total_laboratory' => 'float',
        'total_miscelaneous_fees' => 'float',
        'semester' => 'integer',
        'academic_year' => 'integer',
        'student_id' => 'integer',
        'enrollment_id' => 'integer',
        'discount' => 'integer',
        'downpayment' => 'float',
        'overall_tuition' => 'float',
        'paid' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'date',
        'deleted_at' => 'datetime',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function studentTransactions()
    {
        return $this->student->transactions();
    }

    public function enrollment()
    {
        return $this->belongsTo(
            StudentEnrollment::class,
            'enrollment_id',
            'id'
        );
    }

    /**
     * Calculate the payment progress percentage
     *
     * @return int
     */
    public function getPaymentProgressAttribute(): int|float
    {
        if ($this->overall_tuition <= 0) {
            return 0;
        }

        $paid = $this->overall_tuition - $this->total_balance;

        return min(100, round(($paid / $this->overall_tuition) * 100));
    }

    /**
     * Get the formatted total balance
     */
    public function getFormattedTotalBalanceAttribute(): string
    {
        return '₱ '.number_format($this->total_balance, 2);
    }

    /**
     * Get the formatted overall tuition
     */
    public function getFormattedOverallTuitionAttribute(): string
    {
        return '₱ '.number_format($this->overall_tuition, 2);
    }

    /**
     * Get the formatted total tuition
     */
    public function getFormattedTotalTuitionAttribute(): string
    {
        return '₱ '.number_format($this->total_tuition, 2);
    }

    /**
     * Get the formatted semester
     */
    public function getFormattedSemesterAttribute(): string
    {
        return $this->semester.($this->semester === 1 ? 'st' : 'nd').' Semester';
    }

    /**
     * Get the payment status
     */
    public function getPaymentStatusAttribute(): string
    {
        return $this->total_balance <= 0 ? 'Fully Paid' : 'Not Fully Paid';
    }

    /**
     * Get the payment status class for UI
     */
    public function getStatusClassAttribute(): string
    {
        return $this->total_balance <= 0
            ? 'bg-green-100 text-green-800 dark:bg-green-200 dark:text-green-900'
            : 'bg-red-100 text-red-800 dark:bg-red-200 dark:text-red-900';
    }

    /**
     * Get the formatted total lectures
     */
    public function getFormattedTotalLecturesAttribute(): string
    {
        return '₱ '.number_format($this->total_lectures, 2);
    }

    /**
     * Get the formatted total laboratory
     */
    public function getFormattedTotalLaboratoryAttribute(): string
    {
        return '₱ '.number_format($this->total_laboratory, 2);
    }

    /**
     * Get the formatted total miscellaneous fees
     */
    public function getFormattedTotalMiscelaneousFeesAttribute(): string
    {
        return '₱ '.number_format($this->total_miscelaneous_fees, 2);
    }

    /**
     * Get the formatted downpayment
     */
    public function getFormattedDownpaymentAttribute(): string
    {
        return '₱ '.number_format($this->downpayment, 2);
    }

    /**
     * Get the formatted discount
     */
    public function getFormattedDiscountAttribute(): string
    {
        return $this->discount.'%';
    }
}
