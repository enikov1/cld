<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CollectionItem extends Model
{
    protected $table = 'collection_items';

    protected $fillable = [
        'collection_id',
        'series_id',
        'rank_order',
        'is_auto',
    ];

    protected $casts = [
        'rank_order' => 'integer',
        'is_auto' => 'boolean',
    ];

    public function collection()
    {
        return $this->belongsTo(Collection::class, 'collection_id');
    }

    public function series()
    {
        return $this->belongsTo(Series::class, 'series_id');
    }
}
