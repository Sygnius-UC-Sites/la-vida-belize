<?php
namespace App\Http\Middleware;
use Closure;

class HTTPIndexRedirect
{
    public function handle($request, Closure $next, $guard = null)
    {
        $url=url()->full();
        $url=explode("index.php",$url);
        if(count($url)>1){
            return redirect()->to(env('APP_URL'));
        }else{
            
        }
   
        return $next($request);
    }

}

