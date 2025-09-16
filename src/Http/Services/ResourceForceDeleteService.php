<?php

namespace SchoolAid\Nadota\Http\Services;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use SchoolAid\Nadota\Contracts\ResourceForceDeleteInterface;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use SchoolAid\Nadota\Http\Traits\TracksActionEvents;

class ResourceForceDeleteService implements ResourceForceDeleteInterface
{
    use TracksActionEvents;

    public function handle(NadotaRequest $request, $id): JsonResponse
    {
        $request->prepareResource();
        $resource = $request->getResource();

        // Check if model uses soft deletes
        if (!$resource->getUseSoftDeletes()) {
            return response()->json([
                'message' => 'This resource does not support force delete',
            ], 400);
        }

        // Get the model including trashed records
        $query = $resource->getQuery($request);
        $model = $query->withTrashed()->findOrFail($id);

        // Check authorization
        $request->authorized('forceDelete', $model);

        try {
            DB::beginTransaction();

            // Call before force delete hook
            $resource->beforeForceDelete($model, $request);

            // Track the force delete action before deleting
            $this->trackCustomAction('forceDelete', $model, $request, [], [
                'original' => $model->getAttributes(),
                'changes' => ['permanently_deleted' => true]
            ]);

            // Perform custom force delete logic
            $deleted = $resource->performForceDelete($model, $request);

            if (!$deleted) {
                throw new \Exception('Force delete operation failed');
            }

            // Call after force delete hook
            $resource->afterForceDelete($model, $request);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to permanently delete resource',
                'error' => $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'message' => 'Resource permanently deleted',
        ], 200);
    }
}