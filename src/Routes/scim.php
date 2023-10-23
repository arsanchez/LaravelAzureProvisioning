<?php

use RobTrehy\LaravelAzureProvisioning\Controllers\ResourceController;
use RobTrehy\LaravelAzureProvisioning\Controllers\ResourceTypeController;
use RobTrehy\LaravelAzureProvisioning\Controllers\SchemaController;
use RobTrehy\LaravelAzureProvisioning\Controllers\ServiceProviderController;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;

Route::group([
        'prefix' => config('azureprovisioning.routePrefix'),
        'middleware' => array_merge([SubstituteBindings::class], config('azureprovisioning.routeMiddleware'))
    ], function () {
        Route::get('/ServiceProviderConfig', [ServiceProviderController::class, 'index']);
        Route::get('/Schemas', [SchemaController::class, 'index']);
        Route::get('/ResourceTypes', [ResourceTypeController::class, 'index']);

        Route::get('/{resourceType}', [ResourceController::class, 'index'])
            ->name('AzureProvisioning.Resources');

        Route::post('/{resourceType}', [ResourceController::class, 'create'])
            ->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class]);

        Route::get('/{resourceType}/{resourceObject}', [ResourceController::class, 'show'])
            ->name('AzureProvisioning.Resource');

        Route::patch('/{resourceType}/{resourceObject}', [ResourceController::class, 'update'])
            ->name('AzureProvisioning.Resource.Update')
            ->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class]);

        Route::put('/{resourceType}/{resourceObject}', [ResourceController::class, 'replace'])
            ->name('AzureProvisioning.Resource.Replace')
            ->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class]);

        Route::delete('/{resourceType}/{resourceObject}', [ResourceController::class, 'delete'])
            ->name('AzureProvisioning.Resource.Delete')
            ->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class]);




    // TODO: Implement properly
        Route::get('/Schemas/{id}', function ($id) {
            return $id;
        })->name('AzureProvisioning.Schemas');
        Route::get('/ResourceTypes/{id}', function ($id) {
            return $id;
        })->name('AzureProvisioning.ResourceType');
    });
