<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use App\Models\IcalEvent;
use LiveCart;
use MailHelper;
use ModelHelper;
use App\Models\Property;
use App\Models\PropertyRate;
use App\Models\BookingRequest;

class GauravCommonController extends Controller
{
  	function stateDateSelect(Request $request){
  		return ModelHelper::stateDateSelect($request->id);
  	}
  	
  	function cityDateSelect(Request $request){
  		return ModelHelper::cityDateSelect($request->id);
  	}
}