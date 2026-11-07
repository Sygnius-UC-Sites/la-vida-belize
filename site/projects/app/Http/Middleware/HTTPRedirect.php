<?php
namespace App\Http\Middleware;
use Closure;

class HTTPRedirect
{
    public function handle($request, Closure $next, $guard = null)
    {
        if(!$request->secure()){
            return redirect()->to(env('APP_URL').($request->getRequestUri()));
        }
        $url=url()->full();
        $url=explode("https://www",$url);
        if(count($url)>1){
            
        }else{
            return redirect()->to(env('APP_URL').($request->getRequestUri()));
        }
        
        
        return $next($request);
    }

}

