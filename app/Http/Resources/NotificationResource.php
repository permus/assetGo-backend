<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'companyId' => (string) $this->company_id,
            'userId' => (string) $this->user_id,
            'type' => $this->type,
            'action' => $this->action,
            'title' => $this->title,
            'message' => $this->message,
            'data' => $this->data ?? [],
            'read' => (bool) $this->read,
            'readAt' => $this->read_at?->toISOString(),
            'createdBy' => $this->created_by ? (string) $this->created_by : null,
            'createdAt' => $this->created_at->toISOString(),
            'timeAgo' => $this->created_at->diffForHumans(),
        ];
    }
}
