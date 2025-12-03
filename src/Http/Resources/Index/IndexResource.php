<?php

namespace SchoolAid\Nadota\Http\Resources\Index;

use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;

class IndexResource extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request): array
    {
        $isMobile = $this->isMobile($request);
        
        $response = [
            'data' => $this->collection,
        ];

        // Add pagination meta if the resource is paginated
        if ($this->resource instanceof LengthAwarePaginator) {
            $response['meta'] = $this->getPaginationMeta($this->resource, $isMobile);
            
            // Add pagination links (only for non-mobile or if explicitly requested)
            if (!$isMobile || $request->boolean('include_links', false)) {
                $response['links'] = $this->getPaginationLinks($this->resource);
            }
        }

        return $response;
    }

    /**
     * Get pagination meta information
     *
     * @param LengthAwarePaginator $paginator
     * @param bool $isMobile
     * @return array
     */
    protected function getPaginationMeta(LengthAwarePaginator $paginator, bool $isMobile): array
    {
        $meta = [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'from' => $paginator->firstItem(),
            'to' => $paginator->lastItem(),
        ];

        // For mobile, add simplified pagination info
        if ($isMobile) {
            $meta['has_more'] = $paginator->hasMorePages();
            $meta['has_previous'] = $paginator->currentPage() > 1;
        }

        return $meta;
    }

    /**
     * Get pagination links
     *
     * @param LengthAwarePaginator $paginator
     * @return array
     */
    protected function getPaginationLinks(LengthAwarePaginator $paginator): array
    {
        return [
            'first' => $paginator->url(1),
            'last' => $paginator->url($paginator->lastPage()),
            'prev' => $paginator->previousPageUrl(),
            'next' => $paginator->nextPageUrl(),
        ];
    }

    /**
     * Detect if the request is from a mobile device
     *
     * @param \Illuminate\Http\Request $request
     * @return bool
     */
    protected function isMobile($request): bool
    {
        // Check if explicitly set via query parameter
        if ($request->has('mobile')) {
            return $request->boolean('mobile');
        }

        // Check User-Agent header
        $userAgent = $request->header('User-Agent', '');
        
        if (empty($userAgent)) {
            return false;
        }

        // Common mobile user agent patterns
        $mobilePatterns = [
            'Mobile',
            'Android',
            'iPhone',
            'iPad',
            'iPod',
            'BlackBerry',
            'Windows Phone',
            'Opera Mini',
        ];

        foreach ($mobilePatterns as $pattern) {
            if (stripos($userAgent, $pattern) !== false) {
                return true;
            }
        }

        return false;
    }
}
