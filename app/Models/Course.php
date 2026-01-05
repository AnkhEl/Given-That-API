<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Course extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'skill_metric_file_id',
        'source_skill_id',
        'target_skill_id',
        'title',
        'slug',
        'description',
        'learning_objectives',
        'prerequisites',
        'difficulty_level',
        'estimated_hours',
        'status',
        'ai_generated_outline',
        'curated_by',
        'published_at',
        'thumbnail_url',
        'tags',
        'metadata',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'ai_generated_outline' => 'array',
        'tags' => 'array',
        'metadata' => 'array',
        'published_at' => 'datetime',
        'estimated_hours' => 'integer',
    ];

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        // Auto-generate slug from title if not provided
        static::creating(function ($course) {
            if (empty($course->slug)) {
                $course->slug = Str::slug($course->title);
            }
        });
    }

    /**
     * Get the skill metric file that generated this course.
     */
    public function skillMetricFile(): BelongsTo
    {
        return $this->belongsTo(SkillMetricFile::class);
    }

    /**
     * Get the source skill (what learners already know).
     */
    public function sourceSkill(): BelongsTo
    {
        return $this->belongsTo(Skill::class, 'source_skill_id');
    }

    /**
     * Get the target skill (what learners want to learn).
     */
    public function targetSkill(): BelongsTo
    {
        return $this->belongsTo(Skill::class, 'target_skill_id');
    }

    /**
     * Get the curator who worked on this course.
     */
    public function curator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'curated_by');
    }

    /**
     * Get all modules for this course.
     */
    public function modules(): HasMany
    {
        return $this->hasMany(CourseModule::class)->orderBy('order_index');
    }

    /**
     * Get only published modules.
     */
    public function publishedModules(): HasMany
    {
        return $this->modules()->where('is_published', true);
    }

    /**
     * Get all assessments for this course.
     */
    public function assessments(): HasMany
    {
        return $this->hasMany(Assessment::class);
    }

    /**
     * Get all enrollments for this course.
     */
    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    /**
     * Check if course is published.
     */
    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    /**
     * Check if course is AI-generated (needs curation).
     */
    public function isAIGenerated(): bool
    {
        return $this->status === 'ai_generated';
    }

    /**
     * Check if course is in curation.
     */
    public function isInCuration(): bool
    {
        return $this->status === 'in_curation';
    }

    /**
     * Publish the course.
     */
    public function publish(): void
    {
        $this->update([
            'status' => 'published',
            'published_at' => now(),
        ]);
    }

    /**
     * Archive the course.
     */
    public function archive(): void
    {
        $this->update(['status' => 'archived']);
    }

    /**
     * Get total module count.
     */
    public function getModuleCountAttribute(): int
    {
        return $this->modules()->count();
    }

    /**
     * Get total enrolled users count.
     */
    public function getEnrolledCountAttribute(): int
    {
        return $this->enrollments()->count();
    }

    /**
     * Scope to get published courses.
     */
    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }

    /**
     * Scope to get courses by source skill.
     */
    public function scopeBySourceSkill($query, int $skillId)
    {
        return $query->where('source_skill_id', $skillId);
    }

    /**
     * Scope to get courses by target skill.
     */
    public function scopeByTargetSkill($query, int $skillId)
    {
        return $query->where('target_skill_id', $skillId);
    }

    /**
     * Scope to get courses by skill transition.
     */
    public function scopeBySkillTransition($query, int $sourceSkillId, int $targetSkillId)
    {
        return $query->where('source_skill_id', $sourceSkillId)
            ->where('target_skill_id', $targetSkillId);
    }

    /**
     * Scope to search courses.
     */
    public function scopeSearch($query, string $search)
    {
        return $query->where('title', 'ILIKE', "%{$search}%")
            ->orWhere('description', 'ILIKE', "%{$search}%");
    }
}
