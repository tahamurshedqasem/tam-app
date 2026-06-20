<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Contracts\Repositories\UserRepositoryInterface;
use App\Contracts\Repositories\CustomerRepositoryInterface;
use App\Contracts\Repositories\InstitutionRepositoryInterface;
use App\Repositories\UserRepository;
use App\Repositories\CustomerRepository;
use App\Repositories\InstitutionRepository;

class RepositoryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Bind User Repository Interface to its implementation
        $this->app->bind(
            UserRepositoryInterface::class,
            UserRepository::class
        );

        // Bind Customer Repository Interface to its implementation
        $this->app->bind(
            CustomerRepositoryInterface::class,
            CustomerRepository::class
        );

        // Bind Institution Repository Interface to its implementation
        $this->app->bind(
            InstitutionRepositoryInterface::class,
            InstitutionRepository::class
        );
    }

    public function boot(): void
    {
        //
    }
}