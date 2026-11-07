<?php
namespace App\Helper;
use JeroenNoten\LaravelAdminLte\Menu\Filters\FilterInterface;
use Auth;

class MyMenuFilter implements FilterInterface
{
    public function transform($item)
    {
        if (isset($item['can_admin']) ) {
            if($item['can_admin']!=true){
            	return false;
            }
        }
        return $item;
    }
}