<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CertificateResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'certificate_number' => $this->certificate_number,
            'certificate_url' => $this->certificate_url,
            'verification_url' => $this->verification_url . $this->certificate_number,
            'issued_at' => $this->issued_at->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'is_expired' => $this->expires_at ? $this->expires_at->isPast() : false,

            // Metadata
            'metadata' => $this->metadata,

            // User (if loaded)
            'user' => $this->whenLoaded('user', function () {
                return [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                    'email' => $this->user->email,
                ];
            }),

            // Course (if loaded)
            'course' => $this->whenLoaded('course', function () {
                return [
                    'id' => $this->course->id,
                    'title' => $this->course->title,
                    'source_skill' => $this->course->sourceSkill->name ?? null,
                    'target_skill' => $this->course->targetSkill->name ?? null,
                ];
            }),

            // Enrollment (if loaded and authorized)
            'enrollment' => $this->when(
                $this->relationLoaded('enrollment') &&
                ($request->user()->hasAnyRole(['super_admin', 'curator', 'company_admin']) ||
                    $request->user()->id == $this->user_id),
                function () {
                    return [
                        'id' => $this->enrollment->id,
                        'completion_percentage' => $this->enrollment->completion_percentage,
                        'completed_at' => $this->enrollment->completed_at?->toIso8601String(),
                    ];
                }
            ),

            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
