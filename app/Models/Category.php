<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Category extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'display_order',
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // Relationships
    public function menus()
    {
        return $this->hasMany(Menu::class);
    }

    public function activeMenus()
    {
        return $this->hasMany(Menu::class)->where('is_available', true);
    }
}