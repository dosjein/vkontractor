<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Model\Persons;
use Config;

class CusterPersonGather extends Command
{
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


        $clusterPersons = Persons::where('typeof(vk)' , 'undefined')
                            ->where(Config::get('clusterpoint.details.persons_status') , '200');
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

            $vkData = array();

            if ($full_name){

                $vk_status = 200;

                
            }else{
                $vk_status = 404;               
            }

            $personModelObject = ClusterPersons::where('_id' , $person->_id)->first();

            if ($personModelObject->_id){

                $this->info($vk_status.':'.$person->email);
                
                $personModelObject->vk_status = $vk_status;
                $personModelObject->vk = $vkData;
                $personModelObject->save();
            }else{
                $this->error('not ok that not exists '.$person->_id);
            }
        }

    
    }
}
