<?php

namespace App\Providers;

use App\Models\User;
use App\Notifications\WhatsApp\LogWhatsAppDriver;
use App\Notifications\WhatsApp\WhatsAppDriver;
use App\Services\Datajud\DatajudClient;
use App\Services\Djen\Contracts\ComunicacaoGateway;
use App\Services\Djen\DjenClient;
use App\Services\Djen\Gateways\DjenApiGateway;
use App\Services\Djen\Gateways\FakeComunicacaoGateway;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use InvalidArgumentException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(DjenClient::class, fn ($app): DjenClient => new DjenClient(
            $app->make(HttpFactory::class),
            $app->make(CacheRepository::class),
            $app['config']->get('djen'),
            $app->make('events'),
        ));

        $this->app->singleton(DatajudClient::class, fn ($app): DatajudClient => new DatajudClient(
            $app->make(HttpFactory::class),
            $app->make(CacheRepository::class),
            $app['config']->get('datajud'),
        ));

        $this->app->singleton(ComunicacaoGateway::class, fn ($app): ComunicacaoGateway => match ($app['config']->get('djen.gateway')) {
            'fake' => new FakeComunicacaoGateway,
            default => $app->make(DjenApiGateway::class),
        });

        $this->registerWhatsApp();
    }

    /**
     * Drivers de WhatsApp. Para plugar Evolution API, Z-API ou Meta Cloud API,
     * implemente App\Notifications\WhatsApp\WhatsAppDriver e adicione aqui.
     */
    protected function registerWhatsApp(): void
    {
        $this->app->bind(WhatsAppDriver::class, fn ($app): WhatsAppDriver => match ($driver = $app['config']->get('djen.whatsapp.driver')) {
            'log' => new LogWhatsAppDriver($app->make('log')),
            default => throw new InvalidArgumentException("Driver de WhatsApp não suportado: [{$driver}]."),
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureRateLimiting();

        // Área administrativa (/admin): só quem tem users.is_admin.
        Gate::define('admin', fn (User $user): bool => (bool) $user->is_admin);
    }

    /**
     * Limites de chamadas à Comunica API (CNJ).
     *
     * - "djen": usado pelos Jobs via middleware RateLimited('djen'), global.
     * - "djen-consulta": consulta pública, por IP (middleware throttle:djen-consulta).
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('djen', fn (): Limit => Limit::perMinute(
            (int) config('djen.rate_limit.per_minute'),
        ));

        RateLimiter::for('djen-consulta', fn (Request $request): Limit => Limit::perMinute(
            (int) config('djen.rate_limit.public_per_minute'),
        )->by($request->ip()));

        RateLimiter::for('datajud', fn (Request $request): Limit => Limit::perMinute(
            (int) config('datajud.rate_limit_por_minuto'),
        )->by($request->ip()));
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
