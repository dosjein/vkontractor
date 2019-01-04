<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use \App\Traits\RequestTrait;

use getjump\Vk\Core;
use getjump\Vk\Wrapper\Friends;
use getjump\Vk\Exception\Error;
use getjump\Vk\Wrapper\User;
use Config;
use Cache;
use Carbon\Carbon;

use DB;

use Exception;

use App\Processor;
use App\Messages;

class VkGroupMessages extends Command
{

    use RequestTrait;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'vk:group_trigger';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send Messages to VK Groups';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }


    private function createComment($vk , $group , $post , $message){
        $response = $vk->request('wall.createComment', [
            'owner_id' => $group , 
            'post_id' => $post ,  
            'message' =>  preg_replace('/\s+/', ' ', trim($message))
        ])->getResponse();
        
        $this->info(json_encode($response)); 

        $response = $vk->request('groups.getById', [
            'group_ids' => [$group] , 
        ])->getResponse();   

        $this->info(json_encode($response));                     
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $vkKey = Config::get('app.vk_key');

        if (!$vkKey){
            $this->error('vk oauth key not defined');
            return 1;
        }


        $jsapi = Config::get('app.jsapi');

        if (!$jsapi){
            $this->error('undefined jsapi');
            return 1;
        }


        $this->info('parsing VK for new Group Messages and prepare Response');

        $vk = Core::getInstance()->apiVersion('5.5')->setToken(getenv('VKTOKEN'));

        $this->error('Loading all dialogs');

        $groupUpdates = $this->requestGET([] , $jsapi.'/api/v1/vk_groups_updates');

        foreach ($groupUpdates['data'] as $key => $groupData) {
            try {

                $this->info('Wall for '.$groupData['id'].' '.(isset($groupData['id'])? $groupData['id'] : 'NoName'));

                foreach($vk->request('wall.get', ['owner_id' => $groupData['id'] ])->batch(50) as $data){

                    sleep(2);
                    if ($data->count == 0){
                        continue;
                    }

                    foreach ($data->data as $key => $wallRecord) {
                        $this->createComment($vk , $groupData['id'] , $wallRecord->id , 'I Love you :)');
                        $this->info('https://vk.com/feed?w=wall-'.$groupData['id'].'_'.$wallRecord->id);
                        dd($wallRecord);
                    }


                    // $this->line($accountItem['message_sender_id'].' groups');
                    // $that = $this;
                    // $data->each(function($key, $value) use($accountItem , $that , $vk , $cerf , $jsapi , $accountItem) {

                    //     $groupNotes = Notes::where('session' , 'vkgroup')->where('name' , $value->id);

                    //     $detailsArray = json_decode(json_encode($value) , true);

                    //     $detailsArray['source_user'] = $accountItem['message_sender_id'];

                    //     if ($groupNotes->count() == 0){
                    //         if (isset($value->name)){
                    //             $this->info('save note for '.$value->name);
                    //         }else if (isset($value->id)){
                    //             $this->info('save note for '.$value->id);
                    //         }

                    //         //if we have a group , we save it
                    //         if (isset($value->id)){
                    //             $groupNote = new Notes;
                    //             $groupNote->name = $value->id;
                    //             $groupNote->session = 'vkgroup';
                    //             $groupNote->description = json_encode($detailsArray);
                    //             $groupNote->save();
                    //         }
                    //     }
                    // });
                }  
                         
            } catch (Error $e) {
                $this->error($e->getMessage());
            }
            # code... 
        }

        dd($groupUpdates);
    }
}
