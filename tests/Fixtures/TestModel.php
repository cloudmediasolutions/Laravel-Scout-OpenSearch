<?php

namespace Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Searchable;

class TestModel extends Model
{
    use Searchable;

    protected $fillable = [
        'id',
    ];

    public function searchableAs()
    {
        return 'table';
    }
}