# Custom Delete Behavior Examples

This document shows how to customize delete, force delete, and restore behaviors in your Resource classes.

## Available Methods

The Resource class now provides several methods you can override to customize deletion behavior:

### Core Methods
- `performDelete()` - Customize the actual delete operation
- `performForceDelete()` - Customize the force delete operation
- `performRestore()` - Customize the restore operation

### Hook Methods
- `beforeDelete()` / `afterDelete()` - Run logic before/after deletion
- `beforeForceDelete()` / `afterForceDelete()` - Run logic before/after force deletion
- `beforeRestore()` / `afterRestore()` - Run logic before/after restoration

## Examples

### 1. Cascade Delete Related Records

```php
namespace App\Nadota;

use SchoolAid\Nadota\Resource;
use Illuminate\Database\Eloquent\Model;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;

class PostResource extends Resource
{
    public $model = \App\Models\Post::class;

    public function performDelete(Model $model, NadotaRequest $request): bool
    {
        // Delete all comments before deleting the post
        $model->comments()->delete();

        // Delete all media files
        $model->media()->each(function ($media) {
            Storage::delete($media->path);
            $media->delete();
        });

        // Now delete the post
        return $model->delete();
    }
}
```

### 2. Archive Instead of Delete

```php
class OrderResource extends Resource
{
    public $model = \App\Models\Order::class;

    public function performDelete(Model $model, NadotaRequest $request): bool
    {
        // Instead of deleting, archive the order
        $model->status = 'archived';
        $model->archived_at = now();
        $model->archived_by = $request->user()->id;

        return $model->save();
    }

    public function performRestore(Model $model, NadotaRequest $request): bool
    {
        // Restore from archive
        $model->status = 'active';
        $model->archived_at = null;
        $model->archived_by = null;

        return $model->save();
    }
}
```

### 3. Validation Before Delete

```php
class InvoiceResource extends Resource
{
    public $model = \App\Models\Invoice::class;

    public function beforeDelete(Model $model, NadotaRequest $request): void
    {
        // Prevent deletion if invoice is paid
        if ($model->status === 'paid') {
            abort(403, 'Cannot delete paid invoices');
        }

        // Prevent deletion if invoice has transactions
        if ($model->transactions()->exists()) {
            abort(403, 'Cannot delete invoice with transactions');
        }
    }
}
```

### 4. Cleanup After Delete

```php
class UserResource extends Resource
{
    public $model = \App\Models\User::class;

    public function afterDelete(Model $model, NadotaRequest $request): void
    {
        // Send notification email
        Mail::to($model->email)->send(new AccountDeleted($model));

        // Clear cache
        Cache::forget("user.{$model->id}");
        Cache::forget("user.{$model->email}");

        // Log the deletion
        activity()
            ->causedBy($request->user())
            ->performedOn($model)
            ->log('User account deleted');
    }
}
```

### 5. Transfer Ownership Before Delete

```php
class TeamResource extends Resource
{
    public $model = \App\Models\Team::class;

    public function beforeDelete(Model $model, NadotaRequest $request): void
    {
        // Transfer all projects to the default team
        $defaultTeam = Team::where('is_default', true)->first();

        if ($defaultTeam && $model->projects()->exists()) {
            $model->projects()->update(['team_id' => $defaultTeam->id]);
        }
    }

    public function performDelete(Model $model, NadotaRequest $request): bool
    {
        // Remove all team members
        $model->members()->detach();

        // Delete the team
        return $model->delete();
    }
}
```

### 6. Conditional Force Delete

```php
class DocumentResource extends Resource
{
    public $model = \App\Models\Document::class;
    protected bool $softDelete = true;

    public function performForceDelete(Model $model, NadotaRequest $request): bool
    {
        // Only allow force delete if user is super admin
        if (!$request->user()->isSuperAdmin()) {
            abort(403, 'Only super admins can permanently delete documents');
        }

        // Delete file from storage
        if ($model->file_path) {
            Storage::delete($model->file_path);
        }

        // Delete all versions
        $model->versions()->forceDelete();

        // Force delete the document
        return $model->forceDelete();
    }
}
```

### 7. Queue Heavy Delete Operations

```php
class MediaLibraryResource extends Resource
{
    public $model = \App\Models\MediaLibrary::class;

    public function performDelete(Model $model, NadotaRequest $request): bool
    {
        // Mark for deletion
        $model->pending_deletion = true;
        $model->save();

        // Queue the actual deletion
        DeleteMediaJob::dispatch($model)->delay(now()->addMinutes(5));

        return true;
    }

    public function afterDelete(Model $model, NadotaRequest $request): void
    {
        // Notify user that deletion is queued
        $request->user()->notify(new MediaDeletionQueued($model));
    }
}
```

### 8. Restore with Validation

```php
class SubscriptionResource extends Resource
{
    public $model = \App\Models\Subscription::class;
    protected bool $softDelete = true;

    public function beforeRestore(Model $model, NadotaRequest $request): void
    {
        // Check if the plan still exists
        if (!$model->plan()->exists()) {
            abort(400, 'Cannot restore subscription: Plan no longer exists');
        }

        // Check if user has an active subscription
        if ($model->user->subscriptions()->active()->exists()) {
            abort(400, 'User already has an active subscription');
        }
    }

    public function performRestore(Model $model, NadotaRequest $request): bool
    {
        // Restore the subscription
        $restored = $model->restore();

        if ($restored) {
            // Reactivate in payment gateway
            PaymentGateway::reactivateSubscription($model->gateway_id);

            // Update status
            $model->status = 'active';
            $model->save();
        }

        return $restored;
    }

    public function afterRestore(Model $model, NadotaRequest $request): void
    {
        // Send reactivation email
        Mail::to($model->user)->send(new SubscriptionReactivated($model));
    }
}
```

## Best Practices

1. **Always call parent methods** when extending behavior:
   ```php
   public function performDelete(Model $model, NadotaRequest $request): bool
   {
       // Your custom logic here

       // Call parent delete
       return parent::performDelete($model, $request);
   }
   ```

2. **Use transactions** for complex operations:
   ```php
   public function performDelete(Model $model, NadotaRequest $request): bool
   {
       return DB::transaction(function () use ($model) {
           // Multiple operations
           $model->related()->delete();
           return $model->delete();
       });
   }
   ```

3. **Handle errors gracefully**:
   ```php
   public function beforeDelete(Model $model, NadotaRequest $request): void
   {
       try {
           // Validation logic
       } catch (\Exception $e) {
           abort(400, 'Cannot delete: ' . $e->getMessage());
       }
   }
   ```

4. **Log important operations**:
   ```php
   public function afterDelete(Model $model, NadotaRequest $request): void
   {
       \Log::info('Resource deleted', [
           'resource' => get_class($model),
           'id' => $model->id,
           'user' => $request->user()->id,
       ]);
   }
   ```

5. **Use queues for heavy operations** to avoid timeouts:
   ```php
   public function afterDelete(Model $model, NadotaRequest $request): void
   {
       CleanupJob::dispatch($model->id)->onQueue('cleanup');
   }
   ```