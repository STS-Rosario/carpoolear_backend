<?php

namespace STS\Models;

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

    public function origin () {
        return $this->belongsTo('STS\Models\NodeGeo', 'from_id');
    }

    public function destiny () {
        return $this->belongsTo('STS\Models\NodeGeo', 'to_id');
    }

    public function nodes () {
        return $this->belongsToMany('STS\Models\NodeGeo', 'route_nodes', 'route_id', 'node_id');
    }

}
