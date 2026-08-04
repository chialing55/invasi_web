<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Gate;
use App\Models\User;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::define('manage-users', fn (User $user) => $user->role === 'admin' && !$user->trashed());

        Blade::directive('navActive', function ($pattern) {
            return "<?php echo Route::is($pattern) ? 'bg-forest-mist text-forest font-bold' : ''; ?>";
        });
        $this->mapApiRoutes();

    }

    protected function mapApiRoutes()
    {
        Route::prefix('api')
            ->middleware('api')
            // ->namespace($this->namespace)
            ->group(base_path('routes/api.php'));
    }
}
