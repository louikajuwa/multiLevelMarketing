<?php

namespace App\Providers;

use App\Repositories\Contracts\CustomerRepositoryInterface;
use App\Repositories\Contracts\PartnerLevelRepositoryInterface;
use App\Repositories\Contracts\PartnerRepositoryInterface;
use App\Repositories\Eloquent\CustomerRepository;
use App\Repositories\Eloquent\PartnerLevelRepository;
use App\Repositories\Eloquent\PartnerRepository;
use Illuminate\Support\ServiceProvider;

/**
 * Binds repository interfaces to their Eloquent implementations.
 * Each module adds its binding to $bindings as it is built.
 */
class RepositoryServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        PartnerLevelRepositoryInterface::class => PartnerLevelRepository::class,
        PartnerRepositoryInterface::class => PartnerRepository::class,
        CustomerRepositoryInterface::class => CustomerRepository::class,
    ];

    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        //
    }
}
