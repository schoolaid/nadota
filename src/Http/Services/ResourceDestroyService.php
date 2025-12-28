<?php

namespace SchoolAid\Nadota\Http\Services;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use SchoolAid\Nadota\Contracts\ResourceDestroyInterface;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use SchoolAid\Nadota\Http\Traits\TracksActionEvents;

class ResourceDestroyService implements ResourceDestroyInterface
{
    use TracksActionEvents;
    public function handle(NadotaRequest $request, $id): JsonResponse
    {
        $request->prepareResource();
        $resource = $request->getResource();

        $model = $resource->getQuery($request)->findOrFail($id);

        $request->authorized('delete', $model);

        try {
            DB::beginTransaction();

            // Call before delete hook
            $resource->beforeDelete($model, $request);

            // Track the delete action before deleting
            $this->trackDelete($model, $request);

            // Perform custom delete logic
            $deleted = $resource->performDelete($model, $request);

            if (!$deleted) {
                throw new \Exception('Delete operation failed');
            }

            // Call after delete hook
            $resource->afterDelete($model, $request);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();

            // Call rollback hook if available
            if (method_exists($resource, 'onDeleteFailed')) {
                $resource->onDeleteFailed($model, $request, $e);
            }

            return response()->json([
                'message' => 'Failed to delete resource',
                'error' => $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'message' => 'Resource deleted successfully',
        ], 200);
    }
}
