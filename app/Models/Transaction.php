<?php

declare(strict_types=1);

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Transaction
 *
 * @property-read Collection<int, \App\Models\AdminTransaction> $adminTransactions
 * @property-read int|null $admin_transactions_count
 * @property-read array $academic_period
 * @property-read float $raw_total_amount
 * @property-read string $student_course
 * @property-read mixed $student_email
 * @property-read mixed $student_full_name
 * @property-read mixed $student_id
 * @property-read mixed $student_personal_contact
 * @property-read string|float $total_amount
 * @property-read string $transaction_type_string
 * @property-read Collection<int, \App\Models\Student> $student
 * @property-read int|null $student_count
 * @property-read Collection<int, \App\Models\StudentTransaction> $studentTransactions
 * @property-read int|null $student_transactions_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction dateRange($startDate = null, $endDate = null)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction forAcademicPeriod(string $schoolYear, int $semester)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction sort($field = 'created_at', $direction = 'desc')
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction status($status = null)
 * @mixin \Eloquent
 */
final class Transaction extends Model
{
    protected $table = 'transactions';

    protected $fillable = [
        'description',
        'status',
        'transaction_date',
        'transaction_number',
        'settlements',
        'invoicenumber',
        'signature',
    ];

    protected $casts = [
        'settlements' => 'array',
        'transaction_date' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function getTransactionTypeStringAttribute(): string
    {
        return ucwords(str_replace('_', ' ', $this->transaction_type));
    }

    public function getStudentFullNameAttribute()
    {
        $student = $this->student()->first();

        return $student->full_name ?? 'No Name Found';
    }

    public function getStudentCourseAttribute(): string
    {
        $student = $this->student()->first();

        return $student->course->code.' '.$student->academic_year;
    }

    //  get student email
    public function getStudentEmailAttribute()
    {
        $student = $this->student()->first();

        return $student->email;
    }

    //  get student personal contact
    public function getStudentPersonalContactAttribute()
    {
        $student = $this->student()->first();

        return $student->studentContactsInfo->personal_contact ?? '';
    }

    public function getStudentIdAttribute()
    {
        $student = $this->student()->first();

        return $student->id ?? 'No ID Found';
    }

    public function student()
    {
        return $this->belongsToMany(Student::class, 'student_transactions', 'transaction_id', 'student_id');
    }

    public function studentTransactions()
    {
        return $this->hasMany(StudentTransaction::class, 'transaction_id');
    }

    public function adminTransactions()
    {
        return $this->hasMany(AdminTransaction::class, 'transaction_id');
    }

    public function getTotalAmountAttribute(): float|string
    {
        $settlements = $this->settlements;

        if (is_string($settlements)) {
            $settlements = json_decode($settlements, true);
        }

        if (! is_array($settlements)) {
            return 0.00;
        }

        $total = array_reduce(array_values($settlements), fn ($carry, $value): float => $carry + (float) $value, 0.0);

        return number_format($total, 2);
    }

    /**
     * Scope a query to sort transactions by a specific field.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string  $field
     * @param  string  $direction
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeSort($query, $field = 'created_at', $direction = 'desc')
    {
        $allowedFields = [
            'invoicenumber',
            'description',
            'transaction_date',
            'created_at',
            'status',
            'transaction_number',
        ];

        $field = in_array($field, $allowedFields) ? $field : 'created_at';
        $direction = in_array(mb_strtolower($direction), ['asc', 'desc']) ? $direction : 'desc';

        return $query->orderBy($field, $direction);
    }

    /**
     * Scope a query to filter transactions by date range.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string|null  $startDate
     * @param  string|null  $endDate
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeDateRange($query, $startDate = null, $endDate = null)
    {
        if ($startDate) {
            $query->whereDate('created_at', '>=', $startDate);
        }

        if ($endDate) {
            $query->whereDate('created_at', '<=', $endDate);
        }

        return $query;
    }

    /**
     * Scope a query to filter transactions by status.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string  $status
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeStatus($query, $status = null)
    {
        if ($status) {
            return $query->where('status', $status);
        }

        return $query;
    }

    /**
     * Get the raw numeric value of the total amount.
     */
    public function getRawTotalAmountAttribute(): float
    {
        $settlements = $this->settlements;

        if (is_string($settlements)) {
            $settlements = json_decode($settlements, true);
        }

        if (! is_array($settlements)) {
            return 0.00;
        }

        return array_reduce(array_values($settlements), fn ($carry, $value): float => $carry + (float) $value, 0.0);
    }

    /**
     * Get the academic period this transaction belongs to
     * Returns an array with school_year and semester
     */
    public function getAcademicPeriodAttribute(): array
    {
        $transactionDate = $this->created_at;
        $year = $transactionDate->year;
        $month = $transactionDate->month;

        // Determine semester based on month
        if ($month >= 8 && $month <= 12) {
            // First semester (August to December)
            $semester = 1;
            $schoolYear = $year . '-' . ($year + 1);
        } else {
            // Second semester (January to July)
            $semester = 2;
            $schoolYear = ($year - 1) . '-' . $year;
        }

        return [
            'school_year' => $schoolYear,
            'semester' => $semester
        ];
    }

    /**
     * Scope to filter transactions by academic period
     */
    public function scopeForAcademicPeriod($query, string $schoolYear, int $semester)
    {
        // Parse school year (e.g., "2024-2025")
        $years = explode('-', $schoolYear);
        $startYear = (int) $years[0];
        $endYear = (int) $years[1];

        if ($semester == 1) {
            // First semester: August to December
            $startDate = "{$startYear}-08-01 00:00:00";
            $endDate = "{$startYear}-12-31 23:59:59";
        } else {
            // Second semester: January to July
            $startDate = "{$endYear}-01-01 00:00:00";
            $endDate = "{$endYear}-07-31 23:59:59";
        }

        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    protected static function boot(): void
    {
        parent::boot();

        self::creating(function ($transaction): void {
            $transaction->transaction_number = null; // Set to null initially
        });

        self::created(function ($transaction): void {
            $randomNumber = mt_rand(1000000000, 9999999999); // 10 digit random number
            $transaction->update(['transaction_number' => $randomNumber]);
        });

        self::deleting(function ($transaction): void {
            $transaction->studentTransactions()->delete();
            $transaction->adminTransactions()->delete();
        });
    }
}
