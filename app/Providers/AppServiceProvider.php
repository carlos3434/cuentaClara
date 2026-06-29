<?php

namespace App\Providers;

use App\Services\Ai\AnthropicReceiptVision;
use App\Services\Ai\Contracts\ReceiptVision;
use App\Services\Ai\FakeReceiptVision;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ReceiptVision::class, function () {
            return match (config('cuentaclara.ai.driver')) {
                'anthropic' => new AnthropicReceiptVision(),
                default => new FakeReceiptVision(),
            };
        });
    }

    public function boot(): void
    {
        // Public, unauthenticated receipt upload — bound per IP.
        RateLimiter::for('uploads', fn (Request $request) => Limit::perMinute(
            config('cuentaclara.rate_limits.uploads')
        )->by($request->ip()));

        // Login — bound per email + IP to slow credential stuffing.
        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(
            config('cuentaclara.rate_limits.login')
        )->by(Str::lower((string) $request->input('email')).'|'.$request->ip()));
    }
}
