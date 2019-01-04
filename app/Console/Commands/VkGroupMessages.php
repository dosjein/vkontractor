<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use \App\Traits\RequestTrait;

use \GuzzleHttp\Client;
use \GuzzleHttp\Exception\BadResponseException;

use getjump\Vk\Core;
use getjump\Vk\Wrapper\Friends;
use getjump\Vk\Exception\Error;
use getjump\Vk\Wrapper\User;
use Config;
use Cache;
use Carbon\Carbon;

use DB;

use Exception;

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

        // $response = $vk->request('groups.getById', [
        //     'group_ids' => [$group] , 
        // ])->getResponse();   

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

        $client = new Client();

        $this->error('Loading all dialogs');

        $groupUpdates = $this->requestGET([] , $jsapi.'/api/v1/vk_groups_updates');

        foreach ($groupUpdates['data'] as $key => $groupData) {
            try {

                $this->info('Wall for '.$groupData['id'].' '.(isset($groupData['id'])? $groupData['id'] : 'NoName'));

                if (isset($groupData['error'])){
                    $this->error($groupData['error']);
                    continue;
                }

                if (!isset($groupData['group_id'])){
                    $this->error('Missing Group ID');
                    continue;
                }

                foreach($vk->request('wall.get', ['owner_id' => $groupData['group_id']  , 'count' => 2])->batch(5) as $data){

                    sleep(2);
                    if ($data->count == 0){
                        continue;
                    }

                    $this->info('Total to porcess '.$data->count);

                    if (!$data->data){
                        continue;
                    }

                    foreach (array_slice($data->data, 0, 3) as $key => $wallRecord) {

                        try {

                            $response = $client->get('https://api.chucknorris.io/jokes/random', array());
                            $json = json_decode($response->getBody(true)->getContents() , true);

                            if (!(json_last_error() == JSON_ERROR_NONE && is_array($json))) {
                                $this->error('Response Error');
                                continue;
                            }else{
                                $message = $json['value'];
                            }

                        } catch (BadResponseException $ex) {
                            $error =  array('error' => 1 , 'details' => 'problems : '.$ex->getResponse()->getBody()); 

                            $this->error(json_encode($error));
                            continue;
                        }

                        $messageRequest = Messages::where('user_id' , 'https://vk.com/feed?w=wall-'.$groupData['id'].'_'.$wallRecord->id);

                        if ($messageRequest->count() == 0){
                            $this->createComment($vk , $groupData['group_id'] , $wallRecord->id , $message);
                            $this->info('https://vk.com/feed?w=wall-'.$groupData['id'].'_'.$wallRecord->id);

                            $messageObject = new Messages();
                            $messageObject->user_id = 'https://vk.com/feed?w=wall-'.$groupData['id'].'_'.$wallRecord->id;
                            $messageObject->in = 0;
                            $messageObject->message = $message;
                            $messageObject->save();
                        }else{
                            break;
                        }

                        $this->info('Wait for me');
                        sleep(40);
                    }



                }  
                         
            } catch (Error $e) {
                $this->error($e->getMessage());
            }
            # code... 
        }

        dd($groupUpdates);
    }
}
