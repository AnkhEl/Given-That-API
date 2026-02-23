<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Course\StoreCourseRequest;
use App\Http\Requests\Course\UpdateCourseRequest;
use App\Http\Resources\CourseResource;
use App\Jobs\GenerateModuleContent;
use App\Models\Course;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Models\CourseModule;

class CourseController extends Controller
{
    use ApiResponse;

    /**
     * Display a listing of courses.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Course::with(['sourceSkill', 'targetSkill', 'curator']);

        // Search
        if ($request->has('search')) {
            $query->search($request->search);
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by source skill
        if ($request->has('source_skill_id')) {
            $query->bySourceSkill($request->source_skill_id);
        }

        // Filter by target skill
        if ($request->has('target_skill_id')) {
            $query->byTargetSkill($request->target_skill_id);
        }

        // Filter by difficulty
        if ($request->has('difficulty_level')) {
            $query->where('difficulty_level', $request->difficulty_level);
        }

        // Filter published only (for trainees)
        if ($request->user()->isTrainee()) {
            $query->published();
        }

        // Sort
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');

        $allowedSorts = ['title', 'created_at', 'published_at', 'estimated_hours'];
        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortOrder);
        }

        // Paginate
        $perPage = min($request->get('per_page', 20), 100);
        $courses = $query->paginate($perPage);

        return $this->paginatedResponse($courses, 'Courses retrieved successfully');
    }

    /**
     * Store a newly created course.
     */
    public function store(StoreCourseRequest $request): JsonResponse
    {
        $course = Course::create($request->validated());

        return $this->createdResponse(
            new CourseResource($course->load(['sourceSkill', 'targetSkill'])),
            'Course created successfully'
        );
    }

    /**
     * Display the specified course.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $course = Course::with([
            'sourceSkill',
            'targetSkill',
            'curator',
            'modules' => function ($query) {
                $query->where('is_published', true)->orderBy('order_index');
            },
            'assessments'
        ])->findOrFail($id);

        // Check if user can view unpublished courses
        if (!$course->isPublished() && $request->user()->isTrainee()) {
            return $this->forbiddenResponse('This course is not yet published');
        }

        return $this->successResponse(
            new CourseResource($course),
            'Course retrieved successfully'
        );
    }

    /**
     * Update the specified course.
     */
    public function update(UpdateCourseRequest $request, string $id): JsonResponse
    {
        $course = Course::findOrFail($id);
        $course->update($request->validated());

        return $this->successResponse(
            new CourseResource($course->fresh()->load(['sourceSkill', 'targetSkill'])),
            'Course updated successfully'
        );
    }

    /**
     * Remove the specified course.
     */
    public function destroy(string $id): JsonResponse
    {
        $course = Course::findOrFail($id);

        // Check if course has active enrollments
        $activeEnrollments = $course->enrollments()->active()->count();

        if ($activeEnrollments > 0) {
            return $this->errorResponse(
                "Cannot delete course. It has {$activeEnrollments} active enrollment(s).",
                400
            );
        }

        $course->delete();

        return $this->deletedResponse('Course deleted successfully');
    }

    /**
     * Publish a course.
     */
    public function publish(string $id): JsonResponse
    {
        $course = Course::findOrFail($id);

        // Check if user has permission
        if (!request()->user()->hasAnyRole(['super_admin', 'curator'])) {
            return $this->forbiddenResponse('Only curators and admins can publish courses');
        }

        // Validate course has required content
        if ($course->modules()->count() === 0) {
            return $this->errorResponse('Cannot publish course without modules', 400);
        }

        $course->publish();

        return $this->successResponse(
            new CourseResource($course->fresh()),
            'Course published successfully'
        );
    }

    /**
     * Archive a course.
     */
    public function archive(string $id): JsonResponse
    {
        $course = Course::findOrFail($id);

        $course->archive();

        return $this->successResponse(
            new CourseResource($course->fresh()),
            'Course archived successfully'
        );
    }

    /**
     * Get courses by skill transition.
     */
    public function bySkillTransition(Request $request): JsonResponse
    {
        $request->validate([
            'source_skill_id' => 'required|exists:skills,id',
            'target_skill_id' => 'required|exists:skills,id',
        ]);

        $courses = Course::bySkillTransition(
            $request->source_skill_id,
            $request->target_skill_id
        )
            ->published()
            ->with(['sourceSkill', 'targetSkill'])
            ->get();

        return $this->successResponse(
            CourseResource::collection($courses),
            'Courses retrieved successfully'
        );
    }

    /**
     * Get course statistics.
     */
    public function statistics(string $id): JsonResponse
    {
        $course = Course::with(['enrollments', 'modules'])->findOrFail($id);

        $stats = [
            'total_enrollments' => $course->enrollments()->count(),
            'active_enrollments' => $course->enrollments()->active()->count(),
            'completed_enrollments' => $course->enrollments()->completed()->count(),
            'completion_rate' => $course->completion_rate,
            'average_completion_percentage' => $course->enrollments()->avg('completion_percentage'),
            'total_modules' => $course->modules()->count(),
            'published_modules' => $course->modules()->where('is_published', true)->count(),
        ];

        return $this->successResponse($stats, 'Course statistics retrieved successfully');
    }

    /**
     * 🆕 Generate content for a specific module.
     * POST /api/v1/courses/{course}/modules/{module}/generate-content
     */
    public function generateModuleContent(Course $course, CourseModule $module, Request $request): JsonResponse
    {
        // Authorization: Only curators and super admins can generate content
        if (!$request->user()->hasAnyRole(['curator', 'super_admin'])) {
            return $this->errorResponse('Unauthorized', 403);
        }

        // Verify module belongs to course
        if ($module->course_id !== $course->id) {
            return $this->errorResponse('Module does not belong to this course', 400);
        }

        // Check if content already exists
        if (!empty($module->content)) {
            return $this->errorResponse(
                'Module already has content. Delete existing content first to regenerate.',
                400
            );
        }

        // Dispatch job to generate content
        GenerateModuleContent::dispatch($module);

        return $this->successResponse(
            [
                'module_id' => $module->id,
                'module_title' => $module->title,
                'status' => 'generating',
            ],
            'Content generation started for module'
        );
    }

    /**
     * 🆕 Generate content for ALL modules in a course.
     * POST /api/v1/courses/{course}/generate-all-content
     */
    public function generateAllModuleContent(Course $course, Request $request): JsonResponse
    {
        // Authorization
        if (!$request->user()->hasAnyRole(['curator', 'super_admin'])) {
            return $this->errorResponse('Unauthorized', 403);
        }

        // Get modules without content — grouped to avoid leaking other courses
        $modulesWithoutContent = $course->modules()
            ->where(function ($query) {
                $query->whereNull('content')
                    ->orWhere('content', '');
            })
            ->get();

        if ($modulesWithoutContent->isEmpty()) {
            return $this->errorResponse('All modules already have content', 400);
        }

        // Dispatch generation jobs for each module
        $modulesWithoutContent->each(function ($module) {
            GenerateModuleContent::dispatch($module);
        });

        return $this->successResponse(
            [
                'course_id' => $course->id,
                'modules_queued' => $modulesWithoutContent->count(),
                'total_modules' => $course->modules()->count(),
            ],
            'Content generation started for ' . $modulesWithoutContent->count() . ' modules'
        );
    }

    /**
     * 🆕 Force regenerate content for a module (deletes existing).
     * POST /api/v1/courses/{course}/modules/{module}/regenerate-content
     */
    public function regenerateModuleContent(Course $course, CourseModule $module, Request $request): JsonResponse
    {
        // Authorization
        if (!$request->user()->hasAnyRole(['curator', 'super_admin'])) {
            return $this->errorResponse('Unauthorized', 403);
        }

        // Verify module belongs to course
        if ($module->course_id !== $course->id) {
            return $this->errorResponse('Module does not belong to this course', 400);
        }

        // Clear existing content
        $module->update(['content' => null]);

        // Dispatch job to generate content
        GenerateModuleContent::dispatch($module);

        return $this->successResponse(
            [
                'module_id' => $module->id,
                'module_title' => $module->title,
                'status' => 'regenerating',
            ],
            'Content regeneration started for module'
        );
    }

    /**
     * 🆕 Check module content generation status.
     * GET /api/v1/courses/{course}/modules/{module}/content-status
     */
    public function checkModuleContentStatus(Course $course, CourseModule $module): JsonResponse
    {
        // Verify module belongs to course
        if ($module->course_id !== $course->id) {
            return $this->errorResponse('Module does not belong to this course', 400);
        }

        $hasContent = !empty($module->content);
        $content = $hasContent ? $module->content : null;

        return $this->successResponse([
            'module_id' => $module->id,
            'module_title' => $module->title,
            'has_content' => $hasContent,
            'content_sections' => $hasContent ? array_keys($content ?? []) : [],
            'code_examples_count' => count($content['code_examples'] ?? []),
            'tutorial_steps' => count($content['tutorial'] ?? []),
            'is_ai_generated' => $hasContent && !isset($content['error']),
        ]);
    }

    /**
     * 🆕 Get course content generation overview.
     * GET /api/v1/courses/{course}/content-overview
     */
    public function getContentOverview(Course $course): JsonResponse
    {
        $modules = $course->modules;
        $totalModules = $modules->count();
        $modulesWithContent = $modules->filter(fn($m) => !empty($m->content))->count();

        return $this->successResponse([
            'course_id' => $course->id,
            'course_title' => $course->title,
            'total_modules' => $totalModules,
            'modules_with_content' => $modulesWithContent,
            'modules_without_content' => $totalModules - $modulesWithContent,
            'completion_percentage' => $totalModules > 0
                ? round(($modulesWithContent / $totalModules) * 100, 2)
                : 0,
            'modules' => $modules->map(fn($module) => [
                'id' => $module->id,
                'title' => $module->title,
                'order_index' => $module->order_index,
                'has_content' => !empty($module->content),
                'content_preview' => !empty($module->content)
                    ? substr($module->content['explanation'] ?? '', 0, 100) . '...'
                    : null,
            ]),
        ]);
    }
}
