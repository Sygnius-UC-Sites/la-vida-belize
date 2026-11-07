<?php
namespace App\Exports;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use App\Models\Agent;
use App\Models\Career;
use App\Models\Category;
use App\Models\Cms;
use App\Models\ContactusRequest;
use App\Models\Course;
use App\Models\Faq;
use App\Models\Feedback;
use App\Models\OurTeam;
use App\Models\Testimonial;
use App\Models\EmailTemplete;
use App\Models\BookingRequest;
use Schema;

class CommonExport implements FromView{
  
	function __construct($model){
		$this->model=($model);
	}
    
    public function view(): View{
    	// Guard: unknown export model → empty sheet instead of a 500.
    	$data=[]; $fields=[];
    	if($this->model=="feedbacks"){
    		$data=(array)Feedback::orderBy("id","asc")->get()->toArray();
    		$s=new Feedback;
    		$fields=Schema::getColumnListing($s->getTable());
    	}
    	if($this->model=="testimonials"){
    		$data=(array)Testimonial::orderBy("id","asc")->get()->toArray();
    		$s=new Testimonial;
    		$fields=Schema::getColumnListing($s->getTable());
    	}
    	if($this->model=="our-teams"){
    		$data=(array)OurTeam::orderBy("id","asc")->get()->toArray();
    		$s=new OurTeam;
    		$fields=Schema::getColumnListing($s->getTable());
    	}
    	if($this->model=="careers"){
    		$data=(array)Career::orderBy("id","asc")->get()->toArray();
    		
    		$s=new Career;
    		$fields=Schema::getColumnListing($s->getTable());
    	}
    	if($this->model=="category"){
    		$data=(array)Category::orderBy("id","asc")->get()->toArray();
    		$s=new Category;
    		$fields=Schema::getColumnListing($s->getTable());
    	}
    	if($this->model=="cms"){
    		$data=(array)Cms::orderBy("id","asc")->get()->toArray();
    		$s=new Cms;
    		$fields=Schema::getColumnListing($s->getTable());
    	}
    	if($this->model=="contact-us-enquiries"){
    		$data=(array)ContactusRequest::orderBy("id","asc")->get(['name', 'mobile', 'email', 'message'])->toArray();
    		$s=new ContactusRequest;
    		//$fields=Schema::getColumnListing($s->getTable());
            $fields = ['name', 'mobile', 'email', 'message'];
    	}
    	if($this->model=="courses"){
    		$data=(array)Course::orderBy("id","asc")->get()->toArray();
    		$s=new Course;
    		$fields=Schema::getColumnListing($s->getTable());
    	}
    	if($this->model=="email-templetes"){
    		$data=(array)EmailTemplete::orderBy("id","asc")->get()->toArray();
    		$s=new EmailTemplete;
    		$fields=Schema::getColumnListing($s->getTable());
    	}
    	if($this->model=="faqs"){
    		$data=(array)Faq::orderBy("id","asc")->get()->toArray();
    		$s=new Faq;
    		$fields=Schema::getColumnListing($s->getTable());
    	}
    	if($this->model=="booking-enquiries"){
    		$data=(array)BookingRequest::orderBy("id","asc")->get()->toArray();
    		$s=new BookingRequest;
    		$fields=Schema::getColumnListing($s->getTable());
    		return view('admin.dashboard.booking-enquiries', ['data' => $data,'fields'=>$fields	]);
    	}
        return view('admin.dashboard.export', ['data' => $data,'fields'=>$fields]);
    }
}