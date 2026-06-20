<?php

namespace App\Contracts\Repositories;

interface RepositoryInterface
{
    public function all(array $filters = [], int $perPage = 15);
    public function find($id);
    public function create(array $data);
    public function update($id, array $data);
    public function delete($id);
    public function findWhere(array $conditions);
    public function paginate(int $perPage = 15);
    public function count(): int;
}