<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'status',
        'designation_id',
        'api_token',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'api_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }

    /**
     * Whether this account may log in (web now, Android app later).
     *
     * Super Admins always can. Employees can only if their designation is
     * flagged can_login (e.g. "Sales"); any other designation - or none - is a
     * record without access, even if a password is stored. Inactive accounts
     * never can.
     */
    public function canLogin(): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        if ($this->isSuperAdmin()) {
            return true;
        }

        return (bool) $this->designation?->can_login && ! $this->designation?->api_only;
    }

    public function canApiLogin(): bool
    {
        return $this->status === 'active'
            && ! $this->isSuperAdmin()
            && (bool) $this->designation?->can_login;
    }

    public function quotations()
    {
        return $this->hasMany(Quotation::class);
    }

    public function designation()
    {
        return $this->belongsTo(Designation::class);
    }

    public function advances()
    {
        return $this->hasMany(EmployeeAdvance::class, 'employee_id');
    }

    public function collectedPayments()
    {
        return $this->hasMany(Payment::class, 'employee_id');
    }
     public function attendances()
    {
        return $this->hasMany(EmployeeAttendance::class, 'employee_id');
    }
}
