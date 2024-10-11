<?php
namespace STS\Models;

use Illuminate\Database\Eloquent\Model;

class AppConfig extends Model
{
    protected $table = 'config';
    protected $fillable = ['key', 'value', 'is_laravel'];
    protected $hidden = [];

    protected function casts(): array
    {
        return [
            'is_laravel' => 'boolean'
        ];
    }

    public function getValueAttribute($value)
    {
        return json_decode($value);
    }

}