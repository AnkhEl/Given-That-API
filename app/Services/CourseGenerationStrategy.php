<?php

namespace App\Services;

use App\Models\Skill;
use Illuminate\Support\Facades\Log;

class CourseGenerationStrategy
{
    /**
     * Determine the course generation strategy for given skills.
     *
     * @param array $sourceSkillIds
     * @param array $targetSkillIds
     * @return array
     */
    public function determine(array $sourceSkillIds, array $targetSkillIds): array
    {
        Log::info('Determining course generation strategy', [
            'source_skill_ids' => $sourceSkillIds,
            'target_skill_ids' => $targetSkillIds,
        ]);

        // Load skill details
        $sourceSkills = Skill::whereIn('id', $sourceSkillIds)->get();
        $targetSkills = Skill::whereIn('id', $targetSkillIds)->get();

        // Edge case: No target skills
        if ($targetSkills->isEmpty()) {
            Log::warning('No target skills found');
            return [
                'type' => 'error',
                'reason' => 'No target skills provided',
                'courses' => [],
            ];
        }

        // Case 1: Single target skill - simple course
        if ($targetSkills->count() === 1) {
            Log::info('Single target skill detected - single course strategy');
            return $this->singleCourseStrategy($sourceSkills, $targetSkills);
        }

        // Case 2: Multiple target skills - analyze domains
        $domains = $this->groupByDomain($targetSkills);

        Log::info('Domain analysis complete', [
            'domain_count' => count($domains),
            'domains' => array_keys($domains),
        ]);

        // Case 2a: All skills in ONE domain - single transformation
        if (count($domains) === 1) {
            Log::info('All skills in single domain - single transformation course');
            return $this->singleTransformationStrategy($sourceSkills, $targetSkills);
        }

        // Case 2b: Skills in 2-3 domains - grouped transformations
        if (count($domains) <= 3) {
            Log::info('Skills span 2-3 domains - grouped transformation courses');
            return $this->groupedTransformationStrategy($sourceSkills, $targetSkills, $domains);
        }

        // Case 2c: Too many disparate domains - needs review
        Log::warning('Skills span too many domains - flagging for review', [
            'domain_count' => count($domains),
        ]);
        return $this->needsReviewStrategy($sourceSkills, $targetSkills, $domains);
    }

    /**
     * Strategy: Single target skill
     */
    protected function singleCourseStrategy($sourceSkills, $targetSkills): array
    {
        $targetSkill = $targetSkills->first();

        return [
            'type' => 'single_course',
            'reason' => 'Single target skill',
            'courses' => [
                [
                    'source_skills' => $sourceSkills->pluck('name')->toArray(),
                    'target_skills' => [$targetSkill->name],
                    'source_skill_ids' => $sourceSkills->pluck('id')->toArray(),
                    'target_skill_ids' => [$targetSkill->id],
                    'title_hint' => "Transitioning to {$targetSkill->name}",
                ]
            ],
        ];
    }

    /**
     * Strategy: Multiple target skills, single domain
     */
    protected function singleTransformationStrategy($sourceSkills, $targetSkills): array
    {
        return [
            'type' => 'single_transformation',
            'reason' => 'All target skills belong to the same domain',
            'courses' => [
                [
                    'source_skills' => $sourceSkills->pluck('name')->toArray(),
                    'target_skills' => $targetSkills->pluck('name')->toArray(),
                    'source_skill_ids' => $sourceSkills->pluck('id')->toArray(),
                    'target_skill_ids' => $targetSkills->pluck('id')->toArray(),
                    'title_hint' => $this->generateTransformationTitle($sourceSkills, $targetSkills),
                ]
            ],
        ];
    }

    /**
     * Strategy: Multiple target skills, multiple domains (2-3)
     */
    protected function groupedTransformationStrategy($sourceSkills, $targetSkills, $domains): array
    {
        $courses = [];

        foreach ($domains as $domainName => $skillsInDomain) {
            $courses[] = [
                'source_skills' => $sourceSkills->pluck('name')->toArray(),
                'target_skills' => collect($skillsInDomain)->pluck('name')->toArray(),
                'source_skill_ids' => $sourceSkills->pluck('id')->toArray(),
                'target_skill_ids' => collect($skillsInDomain)->pluck('id')->toArray(),
                'domain' => $domainName,
                'title_hint' => $this->generateDomainTitle($domainName, $skillsInDomain),
            ];
        }

        return [
            'type' => 'grouped_transformations',
            'reason' => 'Target skills span ' . count($domains) . ' related domains',
            'courses' => $courses,
        ];
    }

    /**
     * Strategy: Too many disparate skills
     */
    protected function needsReviewStrategy($sourceSkills, $targetSkills, $domains): array
    {
        return [
            'type' => 'needs_review',
            'reason' => 'Target skills span ' . count($domains) . ' disparate domains - manual review recommended',
            'domains' => array_keys($domains),
            'courses' => [], // Don't auto-generate
            'fallback' => $this->groupedTransformationStrategy($sourceSkills, $targetSkills, $domains),
        ];
    }

    /**
     * Group skills by domain using simple, deterministic rules
     */
    protected function groupByDomain($skills): array
    {
        // Domain mapping: category → domain
        $domainMap = [
            // Backend development
            'backend' => ['programming', 'framework', 'backend'],

            // Data layer
            'database' => ['database'],

            // Frontend
            'frontend' => ['frontend'],

            // Infrastructure & Operations
            'devops' => ['devops', 'cloud', 'cicd'],

            // Mobile
            'mobile' => ['mobile'],

            // CMS & Platforms
            'cms' => ['cms'],

            // Testing
            'testing' => ['testing'],

            // Other/Uncategorized
            'other' => ['other'],
        ];

        $grouped = [];

        foreach ($skills as $skill) {
            $domain = $this->categorizeToDomain($skill->category, $domainMap);

            if (!isset($grouped[$domain])) {
                $grouped[$domain] = [];
            }

            $grouped[$domain][] = $skill;
        }

        return $grouped;
    }

    /**
     * Map a category to its domain
     */
    protected function categorizeToDomain(string $category, array $domainMap): string
    {
        foreach ($domainMap as $domain => $categories) {
            if (in_array($category, $categories)) {
                return $domain;
            }
        }

        return 'other';
    }

    /**
     * Generate a meaningful title for a transformation course
     */
    protected function generateTransformationTitle($sourceSkills, $targetSkills): string
    {
        // Get primary source and target
        $primarySource = $sourceSkills->first()?->name ?? 'Current Stack';
        $primaryTarget = $targetSkills->first()?->name ?? 'New Stack';

        // If multiple targets, generalize
        if ($targetSkills->count() > 1) {
            $category = $targetSkills->first()?->category;
            $categoryLabels = [
                'framework' => 'Framework',
                'backend' => 'Backend',
                'frontend' => 'Frontend',
                'database' => 'Database',
                'mobile' => 'Mobile Development',
            ];

            $label = $categoryLabels[$category] ?? 'Technology Stack';
            return "{$primarySource} to Modern {$label} Stack";
        }

        return "{$primarySource} to {$primaryTarget} Transition";
    }

    /**
     * Generate title for domain-specific course
     */
    protected function generateDomainTitle(string $domain, array $skills): string
    {
        $domainLabels = [
            'backend' => 'Backend Development',
            'database' => 'Database Layer',
            'frontend' => 'Frontend Development',
            'devops' => 'Infrastructure & DevOps',
            'mobile' => 'Mobile Development',
            'cms' => 'CMS & Platforms',
            'testing' => 'Testing & Quality',
        ];

        $label = $domainLabels[$domain] ?? ucfirst($domain);
        $primarySkill = $skills[0]->name ?? '';

        return "Modern {$label}" . ($primarySkill ? " with {$primarySkill}" : "");
    }
}
