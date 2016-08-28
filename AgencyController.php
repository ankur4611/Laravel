<?php 

namespace App\Http\Controllers;

use Dingo\Api\Http\Request;
use Dingo\Api\Routing\Helpers;
use Symfony;
use Validator;
use App\Models\Agencies;
use App\Http\Requests\AgenciesRequest;
use App\Models\AgencyMarkets;
use App\Models\AgencyServices;
use App\Models\Clients;
use App\Models\MemberAgencies;
use App\Models\Members;
use Illuminate\Support\Facades\Hash;

class AgencyController extends Controller
{
  
    /**
     * @param Request $request
     * @return validator
     */
    protected function validation($request)
    {
    
        return Validator::make($request->all(), [
            'name' => 'required|max:255',
            'id'=>'sometimes|numeric',
            'phone' => 'required|max:40',
            'website'=>'sometimes',
            'facebook'=>'sometimes|url',
            'twitter'=>'sometimes|url',
            'linkedin'=>'sometimes|url',
            'description'=>'sometimes',
            'clients_url'=>'sometimes|url',
            'portfolio_url'=>'sometimes|url',
            'cases_url'=>'sometimes|url',
            'client_from'=>'sometimes|numeric',
            'client_to'=>'sometimes|numeric',
            'project_budget_from'=>'sometimes|numeric',
            'project_budget_to'=>'sometimes|numeric',
            'monthly_budget_from'=>'sometimes|numeric',
            'monthly_budget_to'=>'sometimes|numeric',
            'markets'=>'sometimes|array',
            'services'=>'sometimes|array']);
      
    }

    /**
     * @param Request $request
     * @return validator
     */
    protected function addMemberAgencyValidation($request)
    {
      
        return Validator::make($request, [
            'agencies_id' => 'required|exists:agencies,id',
            'members_id'=>'required|exists:members,id']);
      
    }

    /**
     * @param Request $request
     * @return validator
     */
    protected function initialRegistrationValidation($request)
    {
      
        return Validator::make($request->all(), [
            'first_name' => 'required|max:255',
            'last_name'=>'required|max:255',
            'email' => 'required|email',
            'password'=>'required|max:255',
            'name'=>'required|max:40',
            'website'=>'required|regex:/^([\da-z\.-]+)\.([a-z\.]{2,6})([\/\w \.-]*)*\/?$/']);
      
    }

    /**
     * @param Int Agency ID
     * @return mixed
     */
    public function get($agencyId)
    {
     
      profileIDs($agencyId);
      return Agencies::with('members','assets','categories','markets','services','clients','locations.state.country')->where('id',$agencyId)->get();
      
      
      profileIDs($agencyId);
      $agency = Agencies::with('members','categories','markets','services','clients','locations.state.country')->where('id',$agencyId)->get()->toArray();
      $agency = $agency[0];
      
      /**
      echo '<pre>';
      print_r( $agency );
      echo '</pre>';
      die();
      /**/
      
      $agency['assets'] = array();
        
      if( $agency['rnked_portfolio'] )
      {
        
        $ch = curl_init();
        curl_setopt($ch,CURLOPT_URL,"http://api.rnked.com/Asset.select.json?fields=asset_name,asset_lookup,asset_type_id,type_id,type_name,type_prefix&asset_portfolio_id=".$agency['rnked_portfolio']);
        curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
        $output=curl_exec($ch);
        
        /**
        echo '<pre>';
        print_r( $agency );
        print_r( $output );
        echo '</pre>';
        die();
        /**/
        
        if($output && strlen($output)>0)
        {

          curl_close($ch);
          $output = json_decode( $output, true );

          if( count( $output['data'] ) )
          {

            $agency['assets'] = $output['data'];

          }
          
        }

      }
      
      return $agency;
      
    }
  
    public function allAgencies(){
      return Agencies::with('members','assets','categories','markets','services','clients','locations.state.country')->get();
    }
    
    /**
     * @param Request $request (List)
     * @param ID (Numeric)
     * @return mixed
     */
    public function update(Request $request,$agencyId)
    {
      
        $validator = $this->validation($request);
      
        if ($validator->fails()) 
        {
          
            return ['errors'=>$validator->errors()->all()];
          
        }
        else
        {
          
            $this->insertMarket($request,$agencyId);
            Agencies::where('id',$agencyId)->update($request->except(['id','assets','categories','services','markets','members','clients','locations']));
          
            profileIDs($agencyId);
            profileCompletion($agencyId);
          
            return ['agency_id'=>$agencyId];
          
        }

    }

    /**
     * @param Request $request (List)
     * @return mixed
     * Extract function adds an agency to the agencies table
     */
    private function addAgency(Request $request){
      
        return Agencies::create([
            'name'=>$request->input('name'),
            'website'=>$request->input('website')]);
      
    }

    /**
     * @param Request $request (List)
     * @return mixed
     * Extract function updates a member information by email as the key.
     */
    private function updateMember(Request $request)
    {
      
        Members::where('email',$request->input('email'))->update([
            'first_name'=>$request->input('first_name'),
            'last_name'=>$request->input('last_name')]);
      
    }

    /**
     * @param Request $request (List)
     * @return mixed
     */
    public function add(Request $request)
    {
      
        $request->merge(['password'=>gettingPassword($request->all())]);
        $validator=$this->initialRegistrationValidation($request);
      
        if ($validator->fails()) 
        {
          
            return ['errors'=>$validator->errors()->all()];
          
        }
        else
        {
          
            // if(explode("@",$request->input('email'))[1]==$request->input('website'))
            {
              
                // Checking Agency Website If exists Agency is updated
                $agency = Agencies::where('website',$request->input('website'))->first();
              
                if($agency)
                {
                  
                    Agencies::where('website',$request->input('website'))->update(['name'=>$request->input('name')]);  
                  
                }
                else
                {
                  
                    $agency = $this->addAgency($request);
                    
                    profileIDs($agency->id);
                    profileCompletion($agency->id);
                  
                }
              
                // Checking Agency Member If exists Member is updated
                $member = Members::where('email',$request->input('email'))->first();
              
                if($member)
                {
                  
                    $this->updateMember($request);
                  
                }
                else
                {
                  
                    $member=Members::firstOrCreate($request->except('name','website'));
                  
                }
              
                $this->addMemberAgency($member->id,$agency->id);
              
                // Update Progress Value
                profileCompletion($agency->id);
                profileIDs($agency->id);
              
                // Everything is fine return the value
                return [
                    'id'=>$agency->id,
                    'name'=>$request->input('name')];
              
            }
            // else
            {
              
                // return ['errors'=>["Couldn't register, please use company email address."]];
              
            }
          
        }
      
    }

    /**
     * @param Member ID (Numeric)
     * @param Agency ID (Numeric)
     * @return Error, Success
     */
    private function addMemberAgency($memberId,$agencyId)
    {
      
        $member_agency=[
            'agencies_id'=>$agencyId,
            'members_id'=>$memberId];
      
        $validator=$this->addMemberAgencyValidation($member_agency);
      
        if ($validator->fails()) 
        {
          
            return ['errors'=>$validator->errors()->all()];
          
        }
        else
        {
          
            MemberAgencies::firstOrCreate($member_agency);   
          
        }
      
    }

    /**
     * @param Request $request (List)
     * @param Agency Id $request (Numeric)
     * @return Success
     */
    public function insertMarket(Request $request,$agencyId)
    {
      
        AgencyMarkets::where('agencies_id',$agencyId)->delete();
      
        if($request['markets'])
        {
            foreach ($request['markets'] as  $key=>$value) 
            {
                if($value==true)
                {
                  
                    AgencyMarkets::firstOrCreate([
                        'agencies_id'=>$agencyId,
                        'markets_id'=>$key]);   
                  
                }
              
            }
          
        }
      
        return "Success";
    }

    

    /**
     * @param Request $request (Array)
     * @return validator
     */
    protected function validationClient($request)
    {
      
        return Validator::make($request, [
            'name' => 'required|max:255',
            'domain' => 'required|url',
            'work'=>'required|max:1000']);
      
    }
  
}