<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

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
        // 항상 https 프록시(eta-caddy) 뒤에서 서빙되므로 프로덕션에선 모든 생성 URL을
        // https 로 강제한다 (asset/url/route). 안 그러면 내부 평문 :80 때문에 http 로
        // 생성돼 mixed-content 로 차단된다.
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }
    }
}
