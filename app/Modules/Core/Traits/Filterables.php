<?php

namespace App\Modules\Core\Traits;

use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Fluent;

trait Filterables
{
    protected int $perPage = 25;

    private array $comparisonOperators = [
        '__gt_'  => '>',
        '__gte_' => '>=',
        '__lt_'  => '<',
        '__lte_' => '<=',
    ];

    /**
     *
     * Validates params parameters.
     *
     * @param array $params
     *
     * @return array
     */
    public function validateFiltering(array $params): array
    {
        try {
            $rules = [
                'per_page' => 'sometimes|numeric',
                'page' => 'sometimes|numeric',
                'no_paginate' => 'sometimes|boolean',
                'sort_by' => 'sometimes',
                'sort_order' => 'sometimes|in:asc,desc',
                'search' => 'sometimes|string',
                'filter' => 'sometimes|array',
                'filter.*.filter_by' => 'required|string',
                'filter.*.value' => 'required_with:filter.*.filter_by|string',
            ];
            $messages = [
                'per_page.numeric' => 'Per page count must be a number.',
                'page.numeric' => 'Page must be a number.',
                'sort_order.in' => "Order must be 'asc' or 'desc'.",
                'search.string' => 'Search query must be a string.',
                'filter_by.string' => 'Filter by must be a string.',
            ];
            $validator = Validator::make(
                data: $params,
                rules: $rules,
                messages: $messages
            );

            $data = $validator->validated();
        } catch (Exception $exception) {
            throw $exception;
        }

        return $data;
    }

    /**
     * Get filtered data.
     *
     * @param  object $rows
     * @param  array $params
     *
     * @return object
     */
    public function getFiltered(object $rows, array $params, array $with = []): object
    {
        try {
            $params = new Fluent($params);

            $this->loadRelationships($rows, $with);
            $this->loadSearch($rows, $params);
            $this->loadFiltered($rows, $params);
            $this->loadSorted($rows, $params);
            $this->loadCompared($rows, $params);

            $resources = $this->loadPaginated($rows, $params);
        } catch (Exception $exception) {
            throw $exception;
        }

        return $resources;
    }

    /**
     * Search by params params in current instance of eloquent.
     *
     * @param  object $rows
     * @param  object $params
     *
     * @return object
     */
    protected function loadSearch(object $rows, object $params): object
    {
        try {
            if ($params->search) {
                $searchable = method_exists($this->model, 'getSearchable')
                    ? $this->model::getSearchable()
                    : $this->model::SEARCHABLE;

                $rows = $rows->whereLike($searchable, "%{$params->search}%");
            }
        } catch (Exception $exception) {
            throw $exception;
        }

        return $rows;
    }

    /**
     * Filter by params params in current instance of eloquent.
     *
     * @param  object $rows
     * @param  object $params
     *
     * @return object
     */
    protected function loadFiltered(object $rows, object $params): object
    {
        try {
            if (
                $params->offsetExists('filter')
                && !empty($params->filter)
            ) {
                $searchable = method_exists($this->model, 'getSearchable')
                    ? $this->model::getSearchable()
                    : $this->model::SEARCHABLE;
                foreach ($params->filter as $filter) {
                    if (
                        in_array($filter['filter_by'], $searchable)
                        && Arr::has($filter, ['filter_by', 'value'])
                    ) {
                        $rows = $rows->whereLike($filter['filter_by'], $filter['value']);
                    }
                }
            }
        } catch (Exception $exception) {
            throw $exception;
        }

        return $rows;
    }

    /**
     * loadRelationships loads relationship.
     *
     * @param  mixed $rows
     * @param  mixed $with
     *
     * @return object
     */
    protected function loadRelationships(object $rows, array $with): object
    {
        try {
            if ($with != []) {
                $rows = $rows->with($with);
            }
        } catch (Exception $exception) {
            throw $exception;
        }

        return $rows;
    }

    /**
     * Sort by params params in current instance of eloquent
     *
     * @param  object $rows
     * @param  object $params
     *
     * @return object
     */
    protected function loadSorted(object $rows, object $params): object
    {
        try {
            $sortBy = $params->sort_by ?? 'created_at';
            $rows = $params->sort_order == 'asc'
                ? $rows->oldest($sortBy)
                : $rows->latest($sortBy);
        } catch (Exception $exception) {
            throw $exception;
        }

        return $rows;
    }

    /**
     * Compare params params in current instance of eloquent
     *
     * @param  object $rows
     * @param  object $params
     *
     * @return object
     */
    protected function loadCompared(object $rows, object $params): object
    {
        try {
            $searchable = method_exists($this->model, 'getSearchable')
                ? $this->model::getSearchable()
                : $this->model::SEARCHABLE;
            $parameters = array_keys($params->toArray());

            $comparisonParameters = array_filter($parameters, function ($parameter) {
                preg_match('/^__[a-zA-Z]+_/', $parameter, $match);
                return count($match) > 0;
            });

            foreach ($comparisonParameters as $comparisonParameter) {
                preg_match('/^__[a-zA-Z]+_/', $comparisonParameter, $comparison);
                $comparison = end($comparison);
                $parameter = str_replace($comparison, '', $comparisonParameter);
                $compareWith = $params->{$comparisonParameter};

                if (!in_array($parameter, $searchable)) {
                    continue;
                }

                $rows = $rows->where(
                    $parameter,
                    $this->comparisonOperators[$comparison],
                    $compareWith
                );
            }
        } catch (Exception $exception) {
            throw $exception;
        }

        return $rows;
    }

    /**
     * Paginate or get all data.
     *
     * @param  object $rows
     * @param  object $params
     *
     * @return object
     */
    protected function loadPaginated(object $rows, object $params): object
    {
        try {
            $perPage = (int) ($params->per_page ?? $this->perPage);
            $paginate = (bool) $params->no_paginate;
            $infinite = (bool) $params->infinite;
            $resources = null;
            if ($infinite) {
                $resources = $rows
                    ->cursorPaginate($perPage)
                    ->withQueryString();
            } elseif ($paginate) {
                $resources = $rows
                    ->paginate($perPage)
                    ->appends(request()->except('page'));
            } else {
                $resources = $rows->get();
            }
        } catch (Exception $exception) {
            throw $exception;
        }

        return $resources;
    }
}
