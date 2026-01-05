<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Skill extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'slug',
        'category',
        'description',
        'icon_url',
        'popularity_score',
        'metadata',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'metadata' => 'array',
        'popularity_score' => 'integer',
    ];

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        // Auto-generate slug from name if not provided
        static::creating(function ($skill) {
            if (empty($skill->slug)) {
                $skill->slug = Str::slug($skill->name);
            }
        });
    }

    /**
     * Get courses where this skill is the source.
     */
    public function coursesAsSource()
    {
        return $this->hasMany(Course::class, 'source_skill_id');
    }

    /**
     * Get courses where this skill is the target.
     */
    public function coursesAsTarget()
    {
        return $this->hasMany(Course::class, 'target_skill_id');
    }

    /**
     * Scope to get skills by category.
     */
    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Scope to get popular skills.
     */
    public function scopePopular($query, int $minScore = 50)
    {
        return $query->where('popularity_score', '>=', $minScore)
            ->orderBy('popularity_score', 'desc');
    }

    /**
     * Scope to search skills by name.
     */
    public function scopeSearch($query, string $search)
    {
        return $query->where('name', 'ILIKE', "%{$search}%")
            ->orWhere('description', 'ILIKE', "%{$search}%");
    }

    /**
     * Increment popularity score.
     */
    public function incrementPopularity(int $amount = 1): void
    {
        $this->increment('popularity_score', $amount);
    }

    /**
     * Get related skills (from metadata).
     */
    public function getRelatedSkillsAttribute(): array
    {
        return $this->metadata['related_skills'] ?? [];
    }
}
