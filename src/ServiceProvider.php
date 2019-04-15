<?php

namespace FilippoToso\MigrationsGenerator;

use Illuminate\Foundation\Support\Providers\EventServiceProvider;

use FilippoToso\MigrationsGenerator\GenerateMigrations;

class ServiceProvider extends EventServiceProvider
{

    /**
     * Register any events for your application.
     *
     * @return void
     */
    public function boot()
    {

        parent::boot();

        $this->loadViewsFrom(dirname(__DIR__) . '/resources/views', 'migrations-generator');

        $this->publishes([
            dirname(__DIR__) . '/config/migrations-generator.php' => config_path('migrations-generator.php'),
        ], 'config');

        $this->publishes([
            dirname(__DIR__) . '/resources/views' => resource_path('views/vendor/migrations-generator'),
        ], 'views');

        if ($this->app->runningInConsole()) {
            $this->commands([
                GenerateMigrations::class
            ]);
        }

    }

    /**
     * Register bindings in the container.
     *
     * @return void
     */
    public function register()
    {

        $this->mergeConfigFrom(
            dirname(__DIR__) . '/config/default.php',
            'migrations-generator'
        );

    }

}
