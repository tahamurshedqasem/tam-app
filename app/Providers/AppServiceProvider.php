<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Contracts\Repositories\UserRepositoryInterface;
use App\Repositories\UserRepository;
use App\Contracts\Repositories\CustomerRepositoryInterface;
use App\Repositories\CustomerRepository;
use App\Contracts\Repositories\InstitutionRepositoryInterface;
use App\Repositories\InstitutionRepository;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Bind Repository Interfaces
        $this->app->bind(UserRepositoryInterface::class, UserRepository::class);
        $this->app->bind(CustomerRepositoryInterface::class, CustomerRepository::class);
        $this->app->bind(InstitutionRepositoryInterface::class, InstitutionRepository::class);
    }

    public function boot(): void
    {
        //
    }
}