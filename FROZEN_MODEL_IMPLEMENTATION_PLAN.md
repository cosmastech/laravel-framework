# Frozen Model Implementation Plan

## Overview

This document outlines the implementation plan for adding a "frozen" Model concept to Laravel's Eloquent ORM. A frozen model prevents mutations and lazy relationship loading, throwing a `FrozenModelException` when such operations are attempted.

## Feature Requirements

### Core Behavior
- `$model->freeze()` - Freezes the model instance
- `$model->unfreeze()` - Unfreezes the model instance
- `$model->isFrozen()` - Returns boolean indicating frozen state
- When frozen, the following operations should throw `FrozenModelException`:
  - Setting attributes (directly or via `fill()`, `setAttribute()`, etc.)
  - Saving/updating the model (`save()`, `update()`, etc.)
  - Deleting the model (`delete()`, `forceDelete()`)
  - Loading relationships that weren't already eager-loaded
  - Pivot table operations (`attach()`, `detach()`, `sync()`, etc.)
  - `refresh()` - reloading model data from DB
  - `increment()`, `decrement()`, `touch()`

### Collection Support
- `$collection->freeze()` - Freezes all models in collection
- `$collection->unfreeze()` - Unfreezes all models in collection

### Query Builder Integration
- `User::query()->frozen()->get()` - Returns frozen models

### Child Relationships
- When a model is frozen, loaded child relationships should also be frozen by default

### Serialization
- Frozen state should survive serialization (queue jobs, etc.)

### Cloning/Replication
- `clone $frozenModel` - Clone should NOT be frozen
- `$frozenModel->replicate()` - Replica should remain frozen

---

## Implementation Approach

### Phase 1: Core Infrastructure

#### 1.1 Create the Exception
**File:** `src/Illuminate/Database/Eloquent/FrozenModelException.php`

This is done.
```php
<?php

namespace Illuminate\Database\Eloquent;

use RuntimeException;

class FrozenModelException extends RuntimeException
{
    public Model $model;
    public string $operation;

    public function __construct(Model $model, string $operation)
    {
        $this->model = $model;
        $this->operation = $operation;

        parent::__construct(
            "Cannot {$operation} on frozen model [".get_class($model)."]."
        );
    }
}
```

#### 1.2 Create the Concern/Trait
**File:** `src/Illuminate/Database/Eloquent/Concerns/IsFrozen.php`

This is done.
```php
<?php

namespace Illuminate\Database\Eloquent\Concerns;

use Illuminate\Database\Eloquent\FrozenModelException;

trait IsFrozen
{
    /**
     * Indicates if the model is frozen.
     *
     * @var bool
     */
    protected bool $frozen = false;

    /**
     * Freeze the model, preventing mutations and lazy loading.
     *
     * @return $this
     */
    public function freeze(): static
    {
        $this->frozen = true;

        // Freeze loaded relationships
        $this->freezeRelations();

        return $this;
    }

    /**
     * Unfreeze the model, allowing mutations and lazy loading.
     *
     * @return $this
     */
    public function unfreeze(): static
    {
        $this->frozen = false;

        // Optionally unfreeze relations too
        $this->unfreezeRelations();

        return $this;
    }

    /**
     * Determine if the model is frozen.
     *
     * @return bool
     */
    public function isFrozen(): bool
    {
        return $this->frozen;
    }

    /**
     * Throw if model is frozen.
     *
     * @param string $operation
     * @return void
     * @throws FrozenModelException
     */
    protected function throwIfFrozen(string $operation): void
    {
        if ($this->frozen) {
            throw new FrozenModelException($this, $operation);
        }
    }

    /**
     * Freeze all loaded relations.
     *
     * @return void
     */
    protected function freezeRelations(): void
    {
        foreach ($this->relations as $relation) {
            if ($relation instanceof self) {
                $relation->freeze();
            } elseif ($relation instanceof \Illuminate\Database\Eloquent\Collection) {
                $relation->freeze();
            }
        }
    }

    /**
     * Unfreeze all loaded relations.
     *
     * @return void
     */
    protected function unfreezeRelations(): void
    {
        foreach ($this->relations as $relation) {
            if ($relation instanceof self) {
                $relation->unfreeze();
            } elseif ($relation instanceof \Illuminate\Database\Eloquent\Collection) {
                $relation->unfreeze();
            }
        }
    }
}
```

#### 1.3 Generic Type Approach (like PendingRequest)

For type-safety and IDE support, we could consider a generic approach:

```php
/**
 * @template TFrozen of bool
 */
class Model
{
    /** @var TFrozen */
    protected bool $frozen = false;

    /**
     * @return static<true>
     */
    public function freeze(): static { ... }

    /**
     * @return static<false>
     */
    public function unfreeze(): static { ... }
}
```

This allows static analysis tools to track frozen state at compile time.

---

### Phase 2: Model Integration

#### 2.1 Methods to Guard (with `throwIfFrozen()`)

**Model.php:**
- `fill()` - "fill attributes"
- `forceFill()` - "force fill attributes"
- `save()` - "save"
- `saveQuietly()` - "save"
- `saveOrFail()` - "save"
- `update()` - "update"
- `updateOrFail()` - "update"
- `updateQuietly()` - "update"
- `delete()` - "delete"
- `deleteQuietly()` - "delete"
- `deleteOrFail()` - "delete"
- `forceDelete()` - "force delete"
- `push()` - "push"
- `pushQuietly()` - "push"
- `refresh()` - "refresh"
- `increment()` - "increment"
- `decrement()` - "decrement"
- `incrementQuietly()` - "increment"
- `decrementQuietly()` - "decrement"
- `__set()` - "set attribute"
- `offsetSet()` - "set attribute"
- `offsetUnset()` - "unset attribute"

**HasAttributes.php:**
- `setAttribute()` - "set attribute"
- `setRawAttributes()` - "set attributes"
- `fillJsonAttribute()` - "fill JSON attribute"
- `getRelationValue()` - "load relationship" (only if not already loaded)

**HasRelationships.php:**
- `setRelation()` - "set relation" (debatable - might want to allow for hydration)
- `setRelations()` - "set relations"

**HasTimestamps.php:**
- `touch()` - "touch"
- `touchQuietly()` - "touch"
- `updateTimestamps()` - "update timestamps"
- `setCreatedAt()` - "set timestamp"
- `setUpdatedAt()` - "set timestamp"

**Relationship Loading (Model.php):**
- `load()` - "load relationship"
- `loadMissing()` - "load relationship"
- `loadMorph()` - "load relationship"
- `loadAggregate()` - "load aggregate"
- `loadCount()` - "load count"
- `loadMax()` - "load max"
- `loadMin()` - "load min"
- `loadSum()` - "load sum"
- `loadAvg()` - "load avg"
- `loadExists()` - "load exists"
- `loadMorphAggregate()` - "load aggregate"
- `loadMorphCount()` - "load count"
- `loadMorphMax()` - "load max"
- `loadMorphMin()` - "load min"
- `loadMorphSum()` - "load sum"
- `loadMorphAvg()` - "load avg"

---

### Phase 3: Relationship Classes

#### 3.1 BelongsTo.php
- `associate()` - "associate relationship"
- `dissociate()` - "dissociate relationship"

#### 3.2 MorphTo.php
- `associate()` - "associate relationship"
- `dissociate()` - "dissociate relationship"

#### 3.3 HasOneOrMany.php
- `save()` - "save related model"
- `saveQuietly()` - "save related model"
- `saveMany()` - "save related models"
- `saveManyQuietly()` - "save related models"
- `create()` - "create related model"
- `createQuietly()` - "create related model"
- `createMany()` - "create related models"
- `createManyQuietly()` - "create related models"
- `forceCreate()` - "create related model"
- `forceCreateQuietly()` - "create related model"
- `forceCreateMany()` - "create related models"
- `forceCreateManyQuietly()` - "create related models"
- `updateOrCreate()` - "update or create related model"
- `upsert()` - "upsert related models"

#### 3.4 InteractsWithPivotTable.php
- `toggle()` - "toggle pivot"
- `sync()` - "sync pivot"
- `syncWithoutDetaching()` - "sync pivot"
- `syncWithPivotValues()` - "sync pivot"
- `attach()` - "attach pivot"
- `detach()` - "detach pivot"
- `updateExistingPivot()` - "update pivot"

#### 3.5 AsPivot.php
- `delete()` - "delete pivot"

---

### Phase 4: Collection Support

**File:** `src/Illuminate/Database/Eloquent/Collection.php`

```php
/**
 * Freeze all models in the collection.
 *
 * @return $this
 */
public function freeze(): static
{
    return $this->each->freeze();
}

/**
 * Unfreeze all models in the collection.
 *
 * @return $this
 */
public function unfreeze(): static
{
    return $this->each->unfreeze();
}
```

Also guard collection load methods:
- `load()` - Check if any model in collection is frozen
- `loadMissing()` - Check if any model in collection is frozen
- `loadAggregate()` - Check if any model in collection is frozen
- etc.

---

### Phase 5: Query Builder Integration

**File:** `src/Illuminate/Database/Eloquent/Builder.php`

```php
/**
 * Indicates that retrieved models should be frozen.
 *
 * @var bool
 */
protected bool $shouldFreeze = false;

/**
 * Set the query to return frozen models.
 *
 * @return $this
 */
public function frozen(): static
{
    $this->shouldFreeze = true;

    return $this;
}

// In hydrate/get methods, check $this->shouldFreeze and call ->freeze() on models
```

---

### Phase 6: Serialization Support

For frozen state to survive serialization:

```php
// In Model.php
public function __serialize(): array
{
    return [
        // ... existing serialization
        'frozen' => $this->frozen,
    ];
}

public function __unserialize(array $data): void
{
    // ... existing unserialization
    $this->frozen = $data['frozen'] ?? false;
}
```

---

### Phase 7: Clone/Replicate Behavior

```php
// In Model.php
public function __clone()
{
    // Clone should NOT be frozen
    $this->frozen = false;
}

public function replicate(?array $except = null)
{
    // Existing code...
    $instance = ...;

    // Replica maintains frozen state
    if ($this->frozen) {
        $instance->freeze();
    }

    return $instance;
}
```

---

## Files to Modify

1. **New Files:**
   - `src/Illuminate/Database/Eloquent/FrozenModelException.php`
   - `src/Illuminate/Database/Eloquent/Concerns/IsFrozen.php`

2. **Modified Files:**
   - `src/Illuminate/Database/Eloquent/Model.php`
   - `src/Illuminate/Database/Eloquent/Builder.php`
   - `src/Illuminate/Database/Eloquent/Collection.php`
   - `src/Illuminate/Database/Eloquent/Concerns/HasAttributes.php`
   - `src/Illuminate/Database/Eloquent/Concerns/HasRelationships.php`
   - `src/Illuminate/Database/Eloquent/Concerns/HasTimestamps.php`
   - `src/Illuminate/Database/Eloquent/Relations/BelongsTo.php`
   - `src/Illuminate/Database/Eloquent/Relations/MorphTo.php`
   - `src/Illuminate/Database/Eloquent/Relations/HasOneOrMany.php`
   - `src/Illuminate/Database/Eloquent/Relations/Concerns/InteractsWithPivotTable.php`
   - `src/Illuminate/Database/Eloquent/Relations/Concerns/AsPivot.php`

---

## Methods Marked for Review

All methods with `// Luke -- look here` comment have been identified as requiring freeze guards:

### Model.php
- `fill()`, `forceFill()`
- `load()`, `loadMorph()`, `loadMissing()`, `loadAggregate()`, `loadCount()`, `loadMax()`, `loadMin()`, `loadSum()`, `loadAvg()`, `loadExists()`
- `loadMorphAggregate()`, `loadMorphCount()`, `loadMorphMax()`, `loadMorphMin()`, `loadMorphSum()`, `loadMorphAvg()`
- `increment()`, `decrement()`, `incrementQuietly()`, `decrementQuietly()`
- `update()`, `updateOrFail()`, `updateQuietly()`
- `push()`, `pushQuietly()`
- `saveQuietly()`, `save()`
- `delete()`, `deleteQuietly()`, `deleteOrFail()`, `forceDelete()`
- `refresh()`
- `__set()`, `offsetSet()`, `offsetUnset()`

### HasAttributes.php
- `getRelationValue()`, `setAttribute()`, `fillJsonAttribute()`, `setRawAttributes()`

### HasRelationships.php
- `setRelation()`, `setRelations()`

### HasTimestamps.php
- `touch()`, `touchQuietly()`, `updateTimestamps()`, `setCreatedAt()`, `setUpdatedAt()`

### Collection.php
- `load()`, `loadAggregate()`, `loadCount()`, `loadMax()`, `loadMin()`, `loadSum()`, `loadAvg()`, `loadExists()`, `loadMissing()`, `loadMorph()`, `loadMorphCount()`

### InteractsWithPivotTable.php
- `toggle()`, `syncWithoutDetaching()`, `sync()`, `syncWithPivotValues()`, `updateExistingPivot()`, `attach()`, `detach()`

### BelongsTo.php
- `associate()`, `dissociate()`

### HasOneOrMany.php
- `updateOrCreate()`, `upsert()`, `save()`, `saveQuietly()`, `saveMany()`, `saveManyQuietly()`, `create()`, `createQuietly()`, `forceCreate()`, `forceCreateQuietly()`, `createMany()`, `createManyQuietly()`, `forceCreateMany()`, `forceCreateManyQuietly()`

### AsPivot.php
- `delete()`

### MorphTo.php
- `associate()`, `dissociate()`

---

## Open Questions & Decisions

1. **`setRelation()` / `setRelations()`** - Should these be blocked? They're used internally for hydration. Possible solution: allow if setting during initial hydration, block otherwise.

2. **Relationship method calls on frozen parent** - When calling `$frozenModel->posts()->create([...])`, should this be blocked? The parent model isn't being mutated, but it implies intent to mutate. Decision: Block it for consistency.

3. **Performance** - The `throwIfFrozen()` check adds minimal overhead (boolean check). Can be inlined by JIT.

4. **Nested freeze propagation** - When freezing a model with deep nested relations (A -> B -> C), should all levels be frozen? Current decision: Yes.

---

## Testing Strategy

1. **Unit Tests:**
   - Test `freeze()`, `unfreeze()`, `isFrozen()` methods
   - Test exception thrown for each guarded operation
   - Test serialization/unserialization preserves frozen state
   - Test clone behavior (unfrozen)
   - Test replicate behavior (stays frozen)

2. **Integration Tests:**
   - Test collection freeze/unfreeze
   - Test `frozen()` query builder method
   - Test nested relationship freezing
   - Test pivot operations on frozen models

---

## Usage Examples

```php
// Basic usage
$user = User::find(1)->freeze();
$user->name = 'New Name'; // Throws FrozenModelException

// Collection usage
$users = User::all()->freeze();
$users->first()->save(); // Throws FrozenModelException

// Query builder
$users = User::query()->frozen()->get();
$users->first()->delete(); // Throws FrozenModelException

// Relationship loading blocked
$user = User::find(1)->freeze();
$user->posts; // Throws FrozenModelException (not eager loaded)

// Eager loaded relationships work
$user = User::with('posts')->find(1)->freeze();
$user->posts; // Works - was eager loaded
$user->posts->first()->title = 'New'; // Throws - child is frozen too

// Unfreezing
$user->unfreeze();
$user->name = 'Updated'; // Works now
```
