<?php

namespace App\Providers;

use App\Repositories\Contracts\BrandRepositoryInterface;
use App\Repositories\Contracts\CategoryRepositoryInterface;
use App\Repositories\Contracts\CommissionRepositoryInterface;
use App\Repositories\Contracts\CustomerRepositoryInterface;
use App\Repositories\Contracts\OrderRepositoryInterface;
use App\Repositories\Contracts\PartnerLevelRepositoryInterface;
use App\Repositories\Contracts\PartnerRepositoryInterface;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Repositories\Eloquent\BrandRepository;
use App\Repositories\Eloquent\CategoryRepository;
use App\Repositories\Eloquent\CommissionRepository;
use App\Repositories\Eloquent\CustomerRepository;
use App\Repositories\Eloquent\OrderRepository;
use App\Repositories\Eloquent\PartnerLevelRepository;
use App\Repositories\Eloquent\PartnerRepository;
use App\Repositories\Eloquent\ProductRepository;
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
        BrandRepositoryInterface::class => BrandRepository::class,
        CategoryRepositoryInterface::class => CategoryRepository::class,
        ProductRepositoryInterface::class => ProductRepository::class,
        OrderRepositoryInterface::class => OrderRepository::class,
        CommissionRepositoryInterface::class => CommissionRepository::class,
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
