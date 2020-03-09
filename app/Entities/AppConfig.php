<?php 
namespace STS\Entities;

use Illuminate\Database\Eloquent\Model;

class AppConfig extends Model {
	protected $table = 'config';
	protected $fillable = ['key', 'value', 'is_laravel'];
    protected $hidden = [];
    
    protected $cast = [
        'is_laravel' => 'boolean'
    ];

    public function getValueAttribute($value)
    {
        return json_decode($value);
    }

}