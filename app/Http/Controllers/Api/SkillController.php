<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Skill\StoreSkillRequest;
use App\Http\Requests\Skill\UpdateSkillRequest;
use App\Http\Resources\SkillResource;
use App\Models\Skill;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SkillController extends Controller
{
    use ApiResponse;

    /**
     * Display a listing of skills.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Skill::query();

        // Search by name
        if ($request->has('search')) {
            $query->search($request->search);
        }

        // Filter by category
        if ($request->has('category')) {
            $query->byCategory($request->category);
        }

        // Filter by popularity
        if ($request->has('min_popularity')) {
            $query->popular((int) $request->min_popularity);
        }

        // Sort
        $sortBy = $request->get('sort_by', 'name');
        $sortOrder = $request->get('sort_order', 'asc');

        $allowedSorts = ['name', 'popularity_score', 'created_at'];
        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortOrder);
        }

        // Paginate or get all
        if ($request->boolean('paginate', true)) {
            $perPage = min($request->get('per_page', 20), 100);
            $skills = $query->paginate($perPage);

            return $this->paginatedResponse($skills, 'Skills retrieved successfully');
        }

        $skills = $query->get();
        return $this->successResponse(
            SkillResource::collection($skills),
            'Skills retrieved successfully'
        );
    }

    /**
     * Store a newly created skill.
     */
    public function store(StoreSkillRequest $request): JsonResponse
    {
        $skill = Skill::create($request->validated());

        return $this->createdResponse(
            new SkillResource($skill),
            'Skill created successfully'
        );
    }

    /**
     * Display the specified skill.
     */
    public function show(string $id): JsonResponse
    {
        $skill = Skill::findOrFail($id);

        return $this->successResponse(
            new SkillResource($skill),
            'Skill retrieved successfully'
        );
    }

    /**
     * Update the specified skill.
     */
    public function update(UpdateSkillRequest $request, string $id): JsonResponse
    {
        $skill = Skill::findOrFail($id);
        $skill->update($request->validated());

        return $this->successResponse(
            new SkillResource($skill->fresh()),
            'Skill updated successfully'
        );
    }

    /**
     * Remove the specified skill.
     */
    public function destroy(string $id): JsonResponse
    {
        $skill = Skill::findOrFail($id);

        // Check if skill is being used in courses
        $coursesCount = $skill->coursesAsSource()->count() + $skill->coursesAsTarget()->count();

        if ($coursesCount > 0) {
            return $this->errorResponse(
                "Cannot delete skill. It is used in {$coursesCount} course(s).",
                400
            );
        }

        $skill->delete();

        return $this->deletedResponse('Skill deleted successfully');
    }

    /**
     * Get skill categories.
     */
    public function categories(): JsonResponse
    {
        $categories = [
            'programming_language' => 'Programming Language',
            'framework' => 'Framework',
            'database' => 'Database',
            'devops' => 'DevOps',
            'cloud' => 'Cloud',
            'frontend' => 'Frontend',
            'backend' => 'Backend',
            'mobile' => 'Mobile',
            'cms' => 'CMS',
            'testing' => 'Testing',
            'other' => 'Other',
        ];

        return $this->successResponse($categories, 'Categories retrieved successfully');
    }

    /**
     * Get popular skills.
     */
    public function popular(Request $request): JsonResponse
    {
        $minScore = $request->get('min_score', 80);
        $limit = min($request->get('limit', 10), 50);

        $skills = Skill::popular($minScore)
            ->limit($limit)
            ->get();

        return $this->successResponse(
            SkillResource::collection($skills),
            'Popular skills retrieved successfully'
        );
    }

    /**
     * Increment skill popularity.
     */
    public function incrementPopularity(string $id): JsonResponse
    {
        $skill = Skill::findOrFail($id);
        $skill->incrementPopularity();

        return $this->successResponse(
            new SkillResource($skill->fresh()),
            'Skill popularity incremented'
        );
    }
}
