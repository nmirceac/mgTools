<?php namespace MgTools;

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
        if(config('mailgun.router.includeRoutes')) {
            $router->prefix(config('mailgun.router.prefix'))
                ->namespace('MgTools\Http\Controllers')
                ->middleware(config('mailgun.router.guestMiddleware'))
                    ->group(__DIR__.'/Http/routes.php');

            $router->prefix(config('mailgun.router.prefix'))
                ->namespace('MgTools\Http\Controllers')
                ->middleware(['api'])
                ->group(__DIR__.'/Http/api.php');
        }

        $argv = $this->app->request->server->get('argv');
        if(isset($argv[1]) and $argv[1]=='vendor:publish') {
            $this->publishes([
                __DIR__.'/../config/mailgun.php' => config_path('mailgun.php'),
            ], 'config');
            $this->publishes([
                __DIR__.'/MgCampaign.stub.php' => app_path('MgCampaign.php'),
                __DIR__.'/MgList.stub.php' => app_path('MgList.php'),
                __DIR__.'/MgSubscriber.stub.php' => app_path('MgSubscriber.php'),
                __DIR__.'/MgMessage.stub.php' => app_path('MgMessage.php'),
                __DIR__.'/MgMessageEvent.stub.php' => app_path('MgMessageEvent.php'),
            ], 'model');

            $existing = glob(database_path('migrations/*_create_mg_*'));
            if(empty($existing)) {
                $this->publishes([
                    __DIR__.'/../database/migrations/create_mg_campaigns.stub.php' => database_path('migrations/'.date('Y_m_d_His', time()).'1_create_mg_campaigns.php'),
                    __DIR__.'/../database/migrations/create_mg_lists.stub.php' => database_path('migrations/'.date('Y_m_d_His', time()+1).'2_create_mg_lists.php'),
                    __DIR__.'/../database/migrations/create_mg_subscribers.stub.php' => database_path('migrations/'.date('Y_m_d_His', time()+1).'3_create_mg_subscribers.php'),
                    __DIR__.'/../database/migrations/create_mg_messages.stub.php' => database_path('migrations/'.date('Y_m_d_His', time()+1).'4_create_mg_messages.php'),
                    __DIR__.'/../database/migrations/create_mg_message_events.stub.php' => database_path('migrations/'.date('Y_m_d_His', time()+1).'5_create_mg_message_events.php'),
                    __DIR__.'/../database/migrations/create_mg_list_subscriber.stub.php' => database_path('migrations/'.date('Y_m_d_His', time()+1).'6_create_mg_list_subscriber.php'),
                    __DIR__.'/../database/migrations/create_mg_campaign_list.stub.php' => database_path('migrations/'.date('Y_m_d_His', time()+1).'7_create_mg_campaign_list.php'),
                ], 'migrations');
            }
        }
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->bind('mailgun.client', function() {
            return \Http\Adapter\Guzzle6\Client::createWithConfig([]);
        });

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
