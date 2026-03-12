<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class UserCollection extends ResourceCollection
{
    /**
     * The resource that this resource collects.
     *
     * @var string
     */
    public $collects = UserResource::class;

    /**
     * Transform the resource collection into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'data' => $this->collection,
            'meta' => $this->paginationMeta(),
            'links' => $this->paginationLinks(),
        ];
    }

    /**
     * Get pagination meta when the collection is paginated.
     *
     * @return array<string, mixed>
     */
    protected function paginationMeta(): array
    {
        if (!$this->resource instanceof \Illuminate\Pagination\AbstractPaginator) {
            return [];
        }

        return [
            'current_page' => $this->resource->currentPage(),
            'from' => $this->resource->firstItem(),
            'last_page' => $this->resource->lastPage(),
            'per_page' => $this->resource->perPage(),
            'to' => $this->resource->lastItem(),
            'total' => $this->resource->total(),
        ];
    }

    /**
     * Get pagination links when the collection is paginated.
     *
     * @return array<string, mixed>
     */
    protected function paginationLinks(): array
    {
        if (!$this->resource instanceof \Illuminate\Pagination\AbstractPaginator) {
            return [];
        }

        return [
            'first' => $this->resource->url(1),
            'last' => $this->resource->url($this->resource->lastPage()),
            'prev' => $this->resource->previousPageUrl(),
            'next' => $this->resource->nextPageUrl(),
        ];
    }
}
