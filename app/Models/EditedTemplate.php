<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EditedTemplate extends Model
{
    public function template() {
        return $this->belongsTo(Template::class,'template_id');
    }

    public function user() {
        return $this->belongsTo(User::class,'user_id');
    }
}
