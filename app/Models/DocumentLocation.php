<?php

declare(strict_types=1);

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class DocumentLocation
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DocumentLocation newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DocumentLocation newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DocumentLocation query()
 * @mixin \Eloquent
 */
final class DocumentLocation extends Model
{
    public $timestamps = false;

    protected $table = 'document_locations';

    protected $fillable = [
        'birth_certificate',
        'form_138',
        'form_137',
        'good_moral_cert',
        'transfer_credentials',
        'transcript_records',
        'picture_1x1',
    ];
}
