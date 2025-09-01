<?php

declare(strict_types=1);

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class StudentContact
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentContact newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentContact newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentContact query()
 * @mixin \Eloquent
 */
final class StudentContact extends Model
{
    public $timestamps = false;

    protected $table = 'student_contacts';

    protected $fillable = [
        'emergency_contact_name',
        'emergency_contact_phone',
        'emergency_contact_address',
        'facebook_contact',
        'personal_contact',
    ];
}
