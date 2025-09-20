<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'is_active',
        'line_user_id',
        'line_display_name',
        'line_picture_url',
        'line_connected_at',
    ];

    /**
     * Role constants
     */
    const ROLE_ADMIN = 'admin';
    const ROLE_MANAGER = 'manager';
    const ROLE_USER = 'user';

    /**
     * Available roles
     */
    public static function getRoles(): array
    {
        return [
            self::ROLE_ADMIN => 'Administrator',
            self::ROLE_MANAGER => 'Manager',
            self::ROLE_USER => 'User',
        ];
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'line_connected_at' => 'datetime',
        ];
    }

    /**
     * Check if user has connected their LINE account
     */
    public function hasLineAccount(): bool
    {
        return !empty($this->line_user_id);
    }

    /**
     * Get LINE display name or fallback to user name
     */
    public function getLineDisplayNameAttribute($value): string
    {
        return $value ?: $this->name;
    }

    /**
     * Check if user is admin
     */
    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    /**
     * Check if user is manager
     */
    public function isManager(): bool
    {
        return $this->role === self::ROLE_MANAGER;
    }

    /**
     * Check if user can manage other users
     */
    public function canManageUsers(): bool
    {
        return in_array($this->role, [self::ROLE_ADMIN, self::ROLE_MANAGER]);
    }

    /**
     * Get role display name
     */
    public function getRoleDisplayName(): string
    {
        return self::getRoles()[$this->role] ?? 'Unknown';
    }
}
