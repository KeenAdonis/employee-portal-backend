<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'employee_no',
        'status',
        'is_temp_password', // ✅ ADDED
    ];

    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'is_temp_password' => 'boolean', // ✅ ADDED
    ];

    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }
}