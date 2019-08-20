<?php

namespace App\Repositories\Eloquent;

use Illuminate\Support\Arr;
use Webmozart\Assert\Assert;
use Illuminate\Support\Collection;
use App\Repositories\Repository;
use Illuminate\Database\Query\Expression;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use App\Contracts\Repository\RepositoryInterface;
use App\Exceptions\Model\DataValidationException;
use App\Exceptions\Repository\RecordNotFoundException;
use App\Contracts\Repository\Attributes\SearchableInterface;

abstract class EloquentRepository extends Repository implements RepositoryInterface
{
    /**
     * Return an instance of the eloquent model bound to this
     * repository instance.
     *
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function getModel()
    {
        return $this->model;
    }

    /**
     * Return an instance of the builder to use for this repository.
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function getBuilder()
    {
        return $this->getModel()->newQuery();
    }

    /**
     * Create a new record in the database and return the associated model.
     *
     * @param array $fields
     * @param bool  $validate
     * @param bool  $force
     * @return \Illuminate\Database\Eloquent\Model|bool
     *
     * @throws \App\Exceptions\Model\DataValidationException
     */
    public function create(array $fields, bool $validate = true, bool $force = false)
    {
        $instance = $this->getBuilder()->newModelInstance();
        ($force) ? $instance->forceFill($fields) : $instance->fill($fields);

        if (! $validate) {
            $saved = $instance->skipValidation()->save();
        } else {
            if (! $saved = $instance->save()) {
                throw new DataValidationException($instance->getValidator());
            }
        }

        return ($this->withFresh) ? $instance->fresh() : $saved;
    }

    /**
     * Find a model that has the specific ID passed.
     *
     * @param int $id
     * @return \Illuminate\Database\Eloquent\Model
     *
     * @throws \App\Exceptions\Repository\RecordNotFoundException
     */
    public function find(int $id)
    {
        try {
            return $this->getBuilder()->findOrFail($id, $this->getColumns());
        } catch (ModelNotFoundException $exception) {
            throw new RecordNotFoundException;
        }
    }

    /**
     * Find a model matching an array of where clauses.
     *
     * @param array $fields
     * @return \Illuminate\Support\Collection
     */
    public function findWhere(array $fields): Collection
    {
        return $this->getBuilder()->where($fields)->get($this->getColumns());
    }

    /**
     * Find and return the first matching instance for the given fields.
     *
     * @param array $fields
     * @return \Illuminate\Database\Eloquent\Model
     *
     * @throws \App\Exceptions\Repository\RecordNotFoundException
     */
    public function findFirstWhere(array $fields)
    {
        try {
            return $this->getBuilder()->where($fields)->firstOrFail($this->getColumns());
        } catch (ModelNotFoundException $exception) {
            throw new RecordNotFoundException;
        }
    }

    /**
     * Return a count of records matching the passed arguments.
     *
     * @param array $fields
     * @return int
     */
    public function findCountWhere(array $fields): int
    {
        return $this->getBuilder()->where($fields)->count($this->getColumns());
    }

    /**
     * Delete a given record from the database.
     *
     * @param int  $id
     * @param bool $destroy
     * @return int
     */
    public function delete(int $id, bool $destroy = false): int
    {
        return $this->deleteWhere(['id' => $id], $destroy);
    }

    /**
     * Delete records matching the given attributes.
     *
     * @param array $attributes
     * @param bool  $force
     * @return int
     */
    public function deleteWhere(array $attributes, bool $force = false): int
    {
        $instance = $this->getBuilder()->where($attributes);

        return ($force) ? $instance->forceDelete() : $instance->delete();
    }

    /**
     * Update a given ID with the passed array of fields.
     *
     * @param int   $id
     * @param array $fields
     * @param bool  $validate
     * @param bool  $force
     * @return \Illuminate\Database\Eloquent\Model|bool
     *
     * @throws \App\Exceptions\Model\DataValidationException
     * @throws \App\Exceptions\Repository\RecordNotFoundException
     */
    public function update($id, array $fields, bool $validate = true, bool $force = false)
    {
        try {
            $instance = $this->getBuilder()->where('id', $id)->firstOrFail();
        } catch (ModelNotFoundException $exception) {
            throw new RecordNotFoundException;
        }

        ($force) ? $instance->forceFill($fields) : $instance->fill($fields);

        if (! $validate) {
            $saved = $instance->skipValidation()->save();
        } else {
            if (! $saved = $instance->save()) {
                throw new DataValidationException($instance->getValidator());
            }
        }

        return ($this->withFresh) ? $instance->fresh() : $saved;
    }

    /**
     * Perform a mass update where matching records are updated using whereIn.
     * This does not perform any model data validation.
     *
     * @param string $column
     * @param array  $values
     * @param array  $fields
     * @return int
     */
    public function updateWhereIn(string $column, array $values, array $fields): int
    {
        Assert::notEmpty($column, 'First argument passed to updateWhereIn must be a non-empty string.');

        return $this->getBuilder()->whereIn($column, $values)->update($fields);
    }

    /**
     * Update a record if it exists in the database, otherwise create it.
     *
     * @param array $where
     * @param array $fields
     * @param bool  $validate
     * @param bool  $force
     * @return \Illuminate\Database\Eloquent\Model
     *
     * @throws \App\Exceptions\Model\DataValidationException
     * @throws \App\Exceptions\Repository\RecordNotFoundException
     */
    public function updateOrCreate(array $where, array $fields, bool $validate = true, bool $force = false)
    {
        foreach ($where as $item) {
            Assert::true(is_scalar($item) || is_null($item), 'First argument passed to updateOrCreate should be an array of scalar or null values, received an array value of %s.');
        }

        try {
            $instance = $this->setColumns('id')->findFirstWhere($where);
        } catch (RecordNotFoundException $exception) {
            return $this->create(array_merge($where, $fields), $validate, $force);
        }

        return $this->update($instance->id, $fields, $validate, $force);
    }

    /**
     * Return all records associated with the given model.
     *
     * @return \Illuminate\Support\Collection
     */
    public function all(): Collection
    {
        $instance = $this->getBuilder();
        if (is_subclass_of(get_called_class(), SearchableInterface::class) && $this->hasSearchTerm()) {
            $instance = $instance->search($this->getSearchTerm());
        }

        return $instance->get($this->getColumns());
    }

    /**
     * Return a paginated result set using a search term if set on the repository.
     *
     * @param int $perPage
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function paginated(int $perPage): LengthAwarePaginator
    {
        $instance = $this->getBuilder();
        if (is_subclass_of(get_called_class(), SearchableInterface::class) && $this->hasSearchTerm()) {
            $instance = $instance->search($this->getSearchTerm());
        }

        return $instance->paginate($perPage, $this->getColumns());
    }

    /**
     * Insert a single or multiple records into the database at once skipping
     * validation and mass assignment checking.
     *
     * @param array $data
     * @return bool
     */
    public function insert(array $data): bool
    {
        return $this->getBuilder()->insert($data);
    }

    /**
     * Insert multiple records into the database and ignore duplicates.
     *
     * @param array $values
     * @return bool
     */
    public function insertIgnore(array $values): bool
    {
        if (empty($values)) {
            return true;
        }

        foreach ($values as $key => $value) {
            ksort($value);
            $values[$key] = $value;
        }

        $bindings = array_values(array_filter(Arr::flatten($values, 1), function ($binding) {
            return ! $binding instanceof Expression;
        }));

        $grammar = $this->getBuilder()->toBase()->getGrammar();
        $table = $grammar->wrapTable($this->getModel()->getTable());
        $columns = $grammar->columnize(array_keys(reset($values)));

        $parameters = collect($values)->map(function ($record) use ($grammar) {
            return sprintf('(%s)', $grammar->parameterize($record));
        })->implode(', ');

        $statement = "insert ignore into $table ($columns) values $parameters";

        return $this->getBuilder()->getConnection()->statement($statement, $bindings);
    }

    /**
     * Get the amount of entries in the database.
     *
     * @return int
     */
    public function count(): int
    {
        return $this->getBuilder()->count();
    }
}
