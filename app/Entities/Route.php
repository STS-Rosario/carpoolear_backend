<?php

namespace STS\Entities;

use Illuminate\Database\Eloquent\Model;

class Route extends Model
{
    protected $table = 'routes';

    protected $fillable = [
        'id',
        'from_id',
        'to_id',
    ];

    protected $hidden = [];

    public $timestamps = false;


    public function nodes () {
        return $this->belongsToMany('STS\Entities\NodeGeo', 'route_nodes', 'route_id', 'node_id');
    }

}
