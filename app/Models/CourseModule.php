<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CourseModule extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'course_id',
        'parent_module_id',
        'title',
        'slug',
        'description',
        'order_index',
        'content_type',
        'content',
        'comparison_points',
        'code_examples',
        'resources',
        'estimated_minutes',
        'is_published',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'comparison_points' => 'array',
        'code_examples' => 'array',
        'resources' => 'array',
        'estimated_minutes' => 'integer',
        'order_index' => 'integer',
        'is_published' => 'boolean',
    ];

    /**
     * Get the course that owns this module.
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * Get the parent module (if this is a sub-module).
     */
    public function parentModule(): BelongsTo
    {
        return $this->belongsTo(CourseModule::class, 'parent_module_id');
    }

    /**
     * Get child modules (sub-modules).
     */
    public function subModules(): HasMany
    {
        return $this->hasMany(CourseModule::class, 'parent_module_id')
            ->orderBy('order_index');
    }

    /**
     * Get assessments for this module.
     */
    public function assessments(): HasMany
    {
        return $this->hasMany(Assessment::class, 'module_id');
    }

    /**
     * Get user progress records for this module.
     */
    public function userProgress(): HasMany
    {
        return $this->hasMany(UserProgress::class, 'module_id');
    }

    /**
     * Check if module is published.
     */
    public function isPublished(): bool
    {
        return $this->is_published;
    }

    /**
     * Publish the module.
     */
    public function publish(): void
    {
        $this->update(['is_published' => true]);
    }

    /**
     * Unpublish the module.
     */
    public function unpublish(): void
    {
        $this->update(['is_published' => false]);
    }

    /**
     * Get next module in the course.
     */
    public function getNextModuleAttribute(): ?CourseModule
    {
        return CourseModule::where('course_id', $this->course_id)
            ->where('order_index', '>', $this->order_index)
            ->orderBy('order_index')
            ->first();
    }

    /**
     * Get previous module in the course.
     */
    public function getPreviousModuleAttribute(): ?CourseModule
    {
        return CourseModule::where('course_id', $this->course_id)
            ->where('order_index', '<', $this->order_index)
            ->orderBy('order_index', 'desc')
            ->first();
    }

    /**
     * Scope to get published modules.
     */
    public function scopePublished($query)
    {
        return $query->where('is_published', true);
    }

    /**
     * Scope to get modules by course.
     */
    public function scopeByCourse($query, int $courseId)
    {
        return $query->where('course_id', $courseId);
    }
}
