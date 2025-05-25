<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AngleTemplate extends Model
{
    protected $fillable = ['uuid', 'angle_id', 'template_id', 'user_id', 'name', 'main_html', 'main_css'];

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

    public function contents()
    {
        return $this->hasMany(ExtraContent::class, 'angle_template_uuid', 'uuid');
    }
}
