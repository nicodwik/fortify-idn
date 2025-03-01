<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Login;

class SetLastLoginSession
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(Login $event): void
    {
        request()->session()->put([
            'last_login' => now()->toDateString(),
        ]);
    }
}
