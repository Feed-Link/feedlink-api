<?php

namespace App\Modules\Core\Repositories;

use App\Modules\Core\Traits\Filterables;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

abstract class BaseRepository
{
    use Filterables;

    public Model $model;

    public ?string $modelName;

    public ?string $tableName;

    public function __construct()
    {
        $this->tableName = $this->model->getTable();
        $this->modelName = class_basename($this->model);
    }

    /**
     * Store a new record.
     */
    public function store(array $data): object
    {
        $created = $this->model->create($data)->refresh();

        return $created;
    }

    /**
     * Bulk insert data.
     */
    public function insert(array $data): bool
    {
        $insert = DB::table($this->tableName)->insert($data);

        return $insert;
    }

    /**
     * Fetch all records without filtering.
     */
    public function fetch(array $with = []): object
    {
        $rows = $this->model::query();

        if (! empty($with)) {
            $rows = $rows->with($with);
        }

        return $rows->get();
    }

    public function fetchBy(string $column, int|string $value, array $with = []): ?object
    {
        $rows = $this->model::query();

        if (! empty($with)) {
            $rows = $rows->with($with);
        }
        $rows = $rows->where($column, $value);
        $fetched = $rows->first();

        return $fetched;
    }

    /**
     * Fetch all records with filtering, sorting, and pagination.
     */
    public function fetchAll(array $params, array $with = []): object
    {
        $this->validateFiltering($params);
        $rows = $this->model::query();

        $fetched = $this->getFiltered($rows, $params, $with);

        return $fetched;
    }

    /**
     * Update a record by ID.
     */
    public function update(string|int $id, array $data): object
    {
        $rows = $this->model::whereId($id);
        $updated = $rows->firstOrFail();
        $updated->update($data);

        return $updated;
    }

    /**
     * Delete a record by ID.
     */
    public function delete(string $id): void
    {
        $this->model::whereId($id)->delete();
    }
}
