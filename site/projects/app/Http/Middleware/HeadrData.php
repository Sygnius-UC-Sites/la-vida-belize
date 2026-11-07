<?php

namespace App\Http\Middleware;

use Closure;

class HeadrData
{

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string|null  $guard
     * @return mixed
     */
    public function handle($request, Closure $next, $guard = null)
    {
        $response = $next($request);
        return $response->header('Cache-Control','nocache, no-store, max-age=0, must-revalidate')
            ->header('Pragma','no-cache')
            ->header('Expires','Fri, 01 Jan 1990 00:00:00 GMT')
            
        
            ->header('X-Frame-Options','deny') // Anti clickjacking
            ->header('X-XSS-Protection', '1; mode=block') // Anti cross site scripting (XSS)
            ->header('X-Content-Type-Options', 'nosniff') // Reduce exposure to drive-by dl attacks
         //  ->header('Content-Security-Policy', 'default-src \'self\'')
            // Don't cache stuff (we'll be updating the page frequently)
           ;
            
            
            
    }
}
