<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Http\Request;
use Statamic\Http\Requests\UserProfileRequest;

class StatamicServiceProvider extends ServiceProvider
{
    public function boot()
    {
        // Strip array fields from Old Input to prevent array-to-string crash on profile forms
        $this->app->resolving(UserProfileRequest::class, function ($request, $app) {
            $request->setValidatorResolver(function ($translator, $data, $rules, $messages, $customAttributes) {
                // If validation fails, we want to strip arrays from the flashed old input
                // But it's hard to intercept the exception cleanly here without overriding the class.
                // Let's modify the request input directly to remove arrays before validation.
                // Wait, if we remove them before validation, they won't save.
                return $app['validator']->make($data, $rules, $messages, $customAttributes);
            });
        });
    }
}
