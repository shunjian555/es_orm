<?php

declare(strict_types=1);

namespace EsOrm\Model;

/**
 * Adds soft delete capabilities to a model.
 *
 * A soft deleting model stores the deletion timestamp in the `deleted_at`
 * field instead of removing the document. Every query built for such a model
 * automatically excludes the trashed documents unless `withTrashed()` (include
 * them) or `onlyTrashed()` (only them) is used.
 *
 * Usage:
 *
 * ```php
 * class User extends Model
 * {
 *     use SoftDeletes;
 *
 *     protected array $casts = ['deleted_at' => 'datetime'];
 * }
 *
 * $user->delete();          // soft delete
 * $user->trashed();         // true
 * $user->restore();         // clears deleted_at
 * $user->forceDelete();     // actually removes the document
 * ```
 *
 * The trait deliberately does not declare `usesSoftDeletes()`: trait methods are
 * overridden by methods declared directly on the class, and that predicate lives
 * on {@see Model}. Instead the base model detects the trait with
 * `class_uses_recursive()` (see {@see Model::usesSoftDeletes()}).
 */
trait SoftDeletes
{
    /**
     * Whether the model is currently soft deleted.
     */
    public function trashed(): bool
    {
        return $this->getAttribute($this->getDeletedAtColumn()) !== null;
    }

    /**
     * Restore a soft deleted document by clearing its deletion timestamp.
     *
     * @return bool True when the document was restored, false when it was not
     *              trashed or when a `restoring` listener aborted the operation.
     */
    public function restore(): bool
    {
        if (!$this->trashed()) {
            return false;
        }

        if ($this->fireModelEvent('restoring') === false) {
            return false;
        }

        $this->setAttribute($this->getDeletedAtColumn(), null);

        if ($this->fireModelEvent('updating') === false) {
            return false;
        }

        $this->performUpdate();

        $this->fireModelEvent('updated');
        $this->syncOriginal();
        $this->fireModelEvent('restored');

        return true;
    }
}
