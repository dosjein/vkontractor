<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Persons;

use getjump\Vk\Core;
use getjump\Vk\Wrapper\Friends;
use getjump\Vk\Exception\Error;
use Config;

use Carbon\Carbon;

class CusterPersonGather extends Command
{


    private $fields = array(
        'uid','first_name','last_name','deactivated','hidden','verified','blacklisted','sex','bdate','city','country','home_town','photo_50','photo_100','photo_200_orig','photo_200','photo_400_orig','photo_max','photo_max_orig','online','lists','domain','has_mobile','contacts','site','education','universities','schools','status','last_seen','followers_count','common_count','counters','occupation','nickname','relatives','relation','personal','connections','exports','wall_comments','activities','interests','music','movies','tv','books','games','about','quotes','can_post','can_see_all_posts','can_see_audio','can_write_private_message','timezone','screen_name'
    );

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'vk:cluster';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Gather person information for ClusterPoint';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->info('Request VK Data by ClusterPoint model');

        $vk = Core::getInstance()->apiVersion('5.5')->setToken(Config::get('app.vk_key'));

        $clusterPersons = Persons::where('typeof(vk)' , 'undefined')
                            ->where(Config::get('clusterpoint.details.persons_status') , '200')
                            ->limit(100);

        foreach ($clusterPersons->get() as $person) {

            $full_name = false;

            //check fullname
            if (isset($person->full_name)){
                $full_name = $person->full_name;
            }else if (isset($person->fullName)){
                $full_name = $person->fullName;
            }

            //check fullcontact
            if (!$full_name && $person->fullcontact && $person->fullcontact->status == '200'){
                if (isset($person->fullcontact->contactInfo) && is_object($person->fullcontact->contactInfo) && trim($person->fullcontact->contactInfo->fullName) != ''){

                    $full_name = trim($person->fullcontact->contactInfo->fullName);

                }
            }

            if (!$full_name){
                if ($person->email){
                    $emailParts = explode('@' , $person->email);

                    if (strpos($emailParts[0], '.') > -1){
                        $namePartArray = explode('.' , $emailParts[0]);

                        if (count($namePartArray) == 2){
                            //let's assume it is name and lastname
                            $full_name = implode(' ' , $namePartArray);
                        }

                        if (count($namePartArray) == 3){
                            //let's assume it is name and lastname
                            $full_name = implode(' ' , $namePartArray);
                        }
                    }

                    if (strpos($emailParts[0], '_') > -1){
                        $namePartArray = explode('_' , $emailParts[0]);

                        if (count($namePartArray) == 2){
                            //let's assume it is name and lastname
                            $full_name = implode(' ' , $namePartArray);
                        }
                    }
                }
            }

            $vkData = array('count' => -1 , 'data'  => array());

            if ($full_name){

                $vk_status = 200;

                //user search
                foreach($vk->request('users.search', ['q' => $full_name , 'out' => 1])->batch(200) as $data){

                    if ($data->count == 0){
                        $vkData['count'] = $data->count;
                        $vk_status = '404';
                        break;
                    }else{

                        $vkDetails = (object) array();
                        $vkDetails->items = array();
                        $vkDetails->errors = array();

                        $vkData['count'] = $data->count;

                        $this->info($full_name.' are '.$data->count);
                        foreach ($data->items as $item) {
                            try {
                                $vk->request('users.get', ['user_ids' => $item->id , 'fields' => implode(',', $this->fields)])->each(function($i, $v) use ($vkDetails){
                                    
                                    /**
                                        $fullData = array();

                                        foreach ($this->fields as $key => $field) {
                                            $fullData[$field] = $v->__get($field);
                                        }
                                    **/

                                    $vkDetails->items[] = $v;
                                });     
                            } catch (Error $e) {
                                $this->error(json_encode($e));
                                $vkDetails->errors[] = json_encode($e);
                            }
                            sleep(1);
                        }

                        $vkData['data'][] = $vkDetails;
                    }

                }
                
            }else{
                $vk_status = 404;               
            }

            $personModelObject = Persons::where('_id' , $person->_id)->first();

            if ($personModelObject->_id){

                $this->info($vk_status.':'.$person->email);
                
                $personModelObject->vk_status = $vk_status;
                $personModelObject->vk = $vkData;
                $personModelObject->save();
                sleep(1);
            }else{
                $this->error('not ok that not exists '.$person->_id.' ( '.$person->email.')');
                return 1;
            }
        }

    
    }
}
