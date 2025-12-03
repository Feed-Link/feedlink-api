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

    protected bool $sharedLock = false;
    protected bool $lockForUpdate = false;

    public function __construct()
    {
        $this->tableName = $this->model->getTable();
        $this->modelName = class_basename($this->model);
    }

    /**
     * Apply pessimistic locking to the query if required.
     * 
     * @param object $rows
     * 
     * @return object
     */
    public function pessimisticLocking(object $rows): object
    {
        if (
            DB::transactionLevel() > 0
            && $this->lockForUpdate
            && ! $this->sharedLock
        ) {
            /**
             * @example
             * START TRANSACTION;
             *   SELECT * from list WHERE list = 1 FOR UPDATE;
             *   # Do something
             * COMMIT;
             */
            $rows->lockForUpdate();
        } elseif (
            DB::transactionLevel() > 0
            && $this->sharedLock
            && ! $this->lockForUpdate
        ) {
            /**
             * @example
             * START TRANSACTION;
             *   SELECT * from list WHERE list = 1 LOCK IN SHARE MODE;
             *   # Do something
             * COMMIT;
             */
            $rows->sharedLock();
        }
        return $rows;
    }

    /**
     * Store a new record.
     * 
     * @param array $data
     * 
     * @return object
     */
    public function store(array $data): object
    {
        $created = $this->model->create($data)->refresh();

        return $created;
    }

    /**
     * Bulk insert data.
     * 
     * @param array $data
     * 
     * @return bool
     */
    public function insert(array $data): bool
    {
        $insert = DB::table($this->tableName)->insert($data);
        return $insert;
    }

    /**
     * Fetch all records without filtering.
     * 
     * @param array $with
     * 
     * @return object
     */
    public function fetch(array $with = []): object
    {
        $rows = $this->model::query();

        if (!empty($with)) {
            $rows = $rows->with($with);
        }

        return $rows->get();
    }

    /**
     * Fetch all records with filtering, sorting, and pagination.
     * 
     * @param array $params
     * @param array $with
     * 
     * @return object
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
     * 
     * @param string|int $id
     * @param array $data
     * 
     * @return object
     */
    public function update(string|int $id, array $data): object
    {
        $rows = $this->model::whereId($id);
        $this->pessimisticLocking($rows);

        $updated = $rows->firstOrFail();
        $updated->update($data);

        return $updated;
    }

    /**
     * Delete a record by ID.
     * 
     * @param string $id
     * 
     * @return void
     */
    public function delete(string $id): void
    {
        $this->model::whereId($id)->delete();
    }
}
