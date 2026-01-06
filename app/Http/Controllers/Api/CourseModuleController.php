<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CourseModule\StoreCourseModuleRequest;
use App\Http\Requests\CourseModule\UpdateCourseModuleRequest;
use App\Http\Resources\CourseModuleResource;
use App\Models\Course;
use App\Models\CourseModule;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CourseModuleController extends Controller
{
    use ApiResponse;

    /**
     * Display modules for a course.
     */
    public function index(string $courseId): JsonResponse
    {
        $course = Course::findOrFail($courseId);

        $modules = $course->modules()
            ->with('subModules')
            ->whereNull('parent_module_id')
            ->orderBy('order_index')
            ->get();

        return $this->successResponse(
            CourseModuleResource::collection($modules),
            'Course modules retrieved successfully'
        );
    }

    /**
     * Store a newly created module.
     */
    public function store(string $courseId, StoreCourseModuleRequest $request): JsonResponse
    {
        $course = Course::findOrFail($courseId);

        $module = $course->modules()->create($request->validated());

        return $this->createdResponse(
            new CourseModuleResource($module),
            'Module created successfully'
        );
    }

    /**
     * Display the specified module.
     */
    public function show(string $courseId, string $moduleId): JsonResponse
    {
        $module = CourseModule::where('course_id', $courseId)
            ->with(['subModules', 'assessments'])
            ->findOrFail($moduleId);

        return $this->successResponse(
            new CourseModuleResource($module),
            'Module retrieved successfully'
        );
    }

    /**
     * Update the specified module.
     */
    public function update(string $courseId, string $moduleId, UpdateCourseModuleRequest $request): JsonResponse
    {
        $module = CourseModule::where('course_id', $courseId)->findOrFail($moduleId);

        $module->update($request->validated());

        return $this->successResponse(
            new CourseModuleResource($module->fresh()),
            'Module updated successfully'
        );
    }

    /**
     * Remove the specified module.
     */
    public function destroy(string $courseId, string $moduleId): JsonResponse
    {
        $module = CourseModule::where('course_id', $courseId)->findOrFail($moduleId);

        // Check if module has user progress
        $hasProgress = $module->userProgress()->count() > 0;

        if ($hasProgress) {
            return $this->errorResponse(
                'Cannot delete module. Users have already started it.',
                400
            );
        }

        $module->delete();

        return $this->deletedResponse('Module deleted successfully');
    }

    /**
     * Publish a module.
     */
    public function publish(string $courseId, string $moduleId): JsonResponse
    {
        $module = CourseModule::where('course_id', $courseId)->findOrFail($moduleId);

        $module->publish();

        return $this->successResponse(
            new CourseModuleResource($module->fresh()),
            'Module published successfully'
        );
    }

    /**
     * Unpublish a module.
     */
    public function unpublish(string $courseId, string $moduleId): JsonResponse
    {
        $module = CourseModule::where('course_id', $courseId)->findOrFail($moduleId);

        $module->unpublish();

        return $this->successResponse(
            new CourseModuleResource($module->fresh()),
            'Module unpublished successfully'
        );
    }

    /**
     * Reorder modules.
     */
    public function reorder(string $courseId, Request $request): JsonResponse
    {
        $request->validate([
            'modules' => 'required|array',
            'modules.*.id' => 'required|exists:course_modules,id',
            'modules.*.order_index' => 'required|integer|min:1',
        ]);

        foreach ($request->modules as $moduleData) {
            CourseModule::where('id', $moduleData['id'])
                ->where('course_id', $courseId)
                ->update(['order_index' => $moduleData['order_index']]);
        }

        return $this->successResponse(null, 'Modules reordered successfully');
    }
}
