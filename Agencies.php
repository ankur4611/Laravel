<?php namespace App\Models;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Tymon\JWTAuth\Contracts\JWTSubject;

class Agencies extends Model
{
	protected $guarded =[];  
	protected $hidden = ['pivot','created_at','updated_at','deleted_at'];  
	protected $table='agencies';

	//Get all members of the agency
	public function members()
    {
        return $this->belongsToMany('App\Models\Members', 'members_agencies')->select(['members.id','members.first_name','members.last_name','members.confirm']);
    }

    //Get all markets of the agency
    public function markets()
    {
        return $this->belongsToMany('App\Models\Markets', 'agencies_markets')->select(['markets.id']);
    }

    //Get all categories of the agency
    public function categories()
    {
        return $this->hasMany('App\Models\AgencyCategories');
    }

    //Get all services of the agency
    public function services()
    {
        return $this->belongsToMany('App\Models\Services')->select(['services.id','services.type','services.name','services.services_id'])->withPivot('type', 'budget_from','budget_to','id');
    }
	
		//Get all locations of the agency
		public function locations()
    {
        return $this->hasMany('App\Models\Locations');
    }
		
		//Get all clients of the agency
		public function clients()
    {
        return $this->hasMany('App\Models\Clients');
    }
	
		//Get all clients of the agency
		public function assets()
    {
        return $this->hasMany('App\Models\Assets');
    }
	
		
}
