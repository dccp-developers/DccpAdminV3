<?php

declare(strict_types=1);

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class Room
 *
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Classes> $classes
 * @property-read int|null $classes_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Schedule> $schedules
 * @property-read int|null $schedules_count
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Room newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Room newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Room query()
 *
 * @mixin \Eloquent
 */
final class Room extends Model
{
    protected $table = 'rooms';

    protected $fillable = [
        'name',
        'class_code',
    ];

    public function classes()
    {
        return $this->hasMany(Classes::class);
    }

    public function schedules()
    {
        return $this->hasMany(Schedule::class, 'room_id', 'id');
    }

    public function getSchedulesAttribute()
    {
        return $this->schedules()->get();
    }
}
