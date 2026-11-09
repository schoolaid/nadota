<?php

namespace SchoolAid\Nadota\Tests\Fixtures\Http;

use Illuminate\Http\Resources\Json\JsonResource;

/** Custom show/edit response used by CustomResponseStudentResource. */
class StudentSummaryJsonResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'summary' => "{$this->name} <{$this->email}>",
            'context' => $request->get('action', 'show'),
        ];
    }
}
