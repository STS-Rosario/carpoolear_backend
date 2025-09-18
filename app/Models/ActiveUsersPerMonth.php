<?php

namespace STS\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class ActiveUsersPerMonth extends Model
{
    protected $table = 'active_users_per_month';

    protected $fillable = [
        'year',
        'month',
        'saved_at',
        'value'
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'month' => 'integer',
            'saved_at' => 'datetime',
            'value' => 'integer'
        ];
    }

    /**
     * Get the formatted month name
     */
    public function getMonthNameAttribute(): string
    {
        return Carbon::createFromDate($this->year, $this->month, 1)->format('F');
    }

    /**
     * Get the year-month as a string (e.g., "2024-01")
     */
    public function getYearMonthAttribute(): string
    {
        return sprintf('%04d-%02d', $this->year, $this->month);
    }

    /**
     * Scope to get records for a specific year
     */
    public function scopeForYear($query, int $year)
    {
        return $query->where('year', $year);
    }

    /**
     * Scope to get records for a specific month
     */
    public function scopeForMonth($query, int $month)
    {
        return $query->where('month', $month);
    }

    /**
     * Scope to get records for a specific year and month
     */
    public function scopeForYearMonth($query, int $year, int $month)
    {
        return $query->where('year', $year)->where('month', $month);
    }
}
