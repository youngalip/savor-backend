<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SettingHistory extends Model
{
    use HasFactory;

    protected $table = 'settings_history';
    public $timestamps = false;

    protected $fillable = [
        'setting_id', 'key', 'old_value', 'new_value', 'changed_by', 'changed_at'
    ];

    protected $casts = [
        'changed_at' => 'datetime',
    ];

    public function setting()
    {
        return $this->belongsTo(Setting::class);
    }
}