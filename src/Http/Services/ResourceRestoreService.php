<?php

namespace SchoolAid\Nadota\Http\Services;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use SchoolAid\Nadota\Contracts\ResourceRestoreInterface;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use SchoolAid\Nadota\Http\Traits\TracksActionEvents;

class ResourceRestoreService implements ResourceRestoreInterface
{
    use TracksActionEvents;

    public function handle(NadotaRequest $request, $id): JsonResponse
    {
        $request->prepareResource();
        $resource = $request->getResource();

        // Check if model uses soft deletes
        if (!$resource->getUseSoftDeletes()) {
            return response()->json([
                'message' => 'This resource does not support restore',
            ], 400);
        }

        // Get the soft deleted model
        $query = $resource->getQuery($request);
        $model = $query->onlyTrashed()->findOrFail($id);

        // Check authorization
        $request->authorized('restore', $model);

        try {
            DB::beginTransaction();

            // Call before restore hook
            $resource->beforeRestore($model, $request);

            // Perform custom restore logic
            $restored = $resource->performRestore($model, $request);

            if (!$restored) {
                throw new \Exception('Restore operation failed');
            }

            // Track the restore action
            $this->trackRestore($model, $request);

            // Refresh the model to get updated data
            $model->refresh();

            // Call after restore hook
            $resource->afterRestore($model, $request);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to restore resource',
                'error' => $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'message' => 'Resource restored successfully',
            'data' => $model,
        ], 200);
    }
}