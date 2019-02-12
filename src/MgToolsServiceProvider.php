<?php

namespace MgTools;

use Illuminate\Support\ServiceProvider;

class MgToolsServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot(\Illuminate\Routing\Router $router)
    {
//        if(config('colortools.router.includeRoutes')) {
//            $router->prefix(config('colortools.router.prefix'))
//                ->namespace('ColorTools\Http\Controllers')
//                ->middleware(config('colortools.router.guestMiddleware'))
//                    ->group(__DIR__.'/Http/routes.php');
//        }

        $argv = $this->app->request->server->get('argv');
        if(isset($argv[1]) and $argv[1]=='vendor:publish') {
            $this->publishes([
                __DIR__.'/../config/mailgun.php' => config_path('mailgun.php'),
            ], 'config');
//            $this->publishes([
//                __DIR__.'/ImageStore.stub.php' => app_path('ImageStore.php'),
//            ], 'model');

//            $existing = glob(database_path('migrations/*_create_images_table.php'));
//            if(empty($existing)) {
//                $this->publishes([
//                    __DIR__.'/../database/migrations/create_images_table.stub.php' => database_path('migrations/'.date('Y_m_d_His', time()).'_create_images_table.php'),
//                    __DIR__.'/../database/migrations/create_image_associations_pivot.stub.php' => database_path('migrations/'.date('Y_m_d_His', time()+1).'_create_image_associations_pivot.php'),
//                ], 'migrations');
//            } else {
//                echo 'Skipping';
//            }
        }
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {

//        $this->mergeConfigFrom(__DIR__.'/../config/mailgun.php', 'mailgun');
//
//
//        $this->app->bind('command.colortools:stats', Commands\StatsCommand::class);
//        $this->app->bind('command.colortools:config', Commands\ConfigCommand::class);
//        $this->app->bind('command.colortools:setup', Commands\SetupCommand::class);
//        $this->app->bind('command.colortools:clean', Commands\CleanCommand::class);
//
//        $this->commands([
//            'command.colortools:stats',
//            'command.colortools:config',
//            'command.colortools:setup',
//            'command.colortools:clean',
//        ]);

    }

}
