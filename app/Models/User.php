<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'credits',
        'free_credits',
        'is_admin',
        'status',
    ];

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
            'is_admin' => 'boolean',
        ];
    }

    /**
     * Get the top-up requests for the user.
     */
    public function topUpRequests()
    {
        return $this->hasMany(TopUpRequest::class);
    }

    /**
     * Get the affiliate profile for the user.
     */
    public function affiliate()
    {
        return $this->hasOne(Affiliate::class);
    }

    /**
     * Get the landing pages for the user.
     */
    public function landingPages()
    {
        return $this->hasMany(LandingPage::class);
    }

    /**
     * Get the billing logs for the user.
     */
    public function billingLogs()
    {
        return $this->hasMany(BillingLog::class);
    }

    /**
     * Get the CPA locker unlocks for the user.
     */
    public function affiliateUnlocks()
    {
        return $this->hasMany(AffiliateUnlock::class);
    }

    /**
     * Trainings unlocked by the user.
     */
    public function trainingUnlocks()
    {
        return $this->belongsToMany(TrainingModule::class, 'user_training_unlocks', 'user_id', 'training_id')->withTimestamps();
    }
}
