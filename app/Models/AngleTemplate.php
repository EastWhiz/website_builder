<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AngleTemplate extends Model
{
    protected $fillable = ['angle_id', 'template_id', 'user_id', 'name', 'main_html', 'main_css'];

    public function angle()
    {
        return $this->belongsTo(Angle::class, 'angle_id');
    }

    public function template()
    {
        return $this->belongsTo(Template::class, 'template_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
