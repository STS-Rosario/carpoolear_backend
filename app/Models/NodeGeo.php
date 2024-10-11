<?php

namespace STS\Models;

use Illuminate\Database\Eloquent\Model;

class NodeGeo extends Model
{
    protected $table = 'nodes_geo';

    public $timestamps = false;

    protected $fillable = [
        'id',
        'name',
        'lat',
        'lng',
        'type',
        'state',
        'country'
    ];

    protected $hidden = [];

}
