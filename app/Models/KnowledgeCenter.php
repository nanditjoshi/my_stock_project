<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KnowledgeCenter extends Model
{
    protected $table = 'knowledge_center';

    const CREATED_AT = 'created_date';
    const UPDATED_AT = 'updated_date';

    protected $fillable = [
        'name',
        'type',
        'message1',
        'message2',
        'message3',
        'images',
        'PMS_id',
    ];
}
