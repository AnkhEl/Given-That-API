<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Company extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'slug',
        'contact_email',
        'contact_phone',
        'subscription_tier',
        'subscription_status',
        'max_learners',
        'max_courses_per_month',
        'subscription_starts_at',
        'subscription_ends_at',
        'logo_url',
        'settings',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'settings' => 'array',
        'subscription_starts_at' => 'datetime',
        'subscription_ends_at' => 'datetime',
        'max_learners' => 'integer',
        'max_courses_per_month' => 'integer',
    ];

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        // Auto-generate slug from name if not provided
        static::creating(function ($company) {
            if (empty($company->slug)) {
                $company->slug = Str::slug($company->name);
            }
        });
    }

    /**
     * Get all users belonging to this company.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Get company administrators.
     */
    public function administrators(): HasMany
    {
        return $this->users()->where('role', 'company_admin');
    }

    /**
     * Get company trainees.
     */
    public function trainees(): HasMany
    {
        return $this->users()->where('role', 'trainee');
    }

    /**
     * Check if company subscription is active.
     */
    public function isSubscriptionActive(): bool
    {
        return $this->subscription_status === 'active'
            || $this->subscription_status === 'trial';
    }

    /**
     * Check if company has reached max learners limit.
     */
    public function hasReachedLearnersLimit(): bool
    {
        return $this->users()->where('role', 'trainee')->count() >= $this->max_learners;
    }

    /**
     * Get remaining learner slots.
     */
    public function getRemainingLearnerSlots(): int
    {
        $currentLearners = $this->users()->where('role', 'trainee')->count();
        return max(0, $this->max_learners - $currentLearners);
    }

    /**
     * Scope to get only active subscriptions.
     */
    public function scopeActiveSubscription($query)
    {
        return $query->whereIn('subscription_status', ['active', 'trial']);
    }

    /**
     * Get all skill metric files for this company.
     */
    public function skillMetricFiles(): HasMany
    {
        return $this->hasMany(SkillMetricFile::class);
    }
}
