<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use \GuzzleHttp\Client;
use \GuzzleHttp\Exception\BadResponseException;

use getjump\Vk\Core;
use getjump\Vk\Wrapper\Friends;
use getjump\Vk\Exception\Error;
use getjump\Vk\Wrapper\User;
use Config;
use Cache;
use Carbon\Carbon;

use Exception;

class MessageResponse extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'vk:messages {keepalive?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Vkontakti message exchange';

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

        $vkKey = Config::get('app.vk_key');

        if (!$vkKey){
            $this->error('vk oauth key not defined');
            return 1;
        }


        $this->info('parsing VK for new Messages and prepare Response');

        $vk = Core::getInstance()->apiVersion('5.5')->setToken(getenv('VKTOKEN'));

        //MESSAGES IN
        foreach($vk->request('messages.get', ['count' => 3 , 'out' => 0])->batch(2) as $data){

            $userMap = [];
            $userCache = [];

            $user = new User($vk);

            $fetchData = function($id) use($user, &$userMap, &$userCache)
            {
                if(!isset($userMap[$id]))
                {
                    $userMap[$id] = sizeof($userCache);
                    $userCache[] = $user->get($id)->response->get();
                }
                return $userCache[$userMap[$id]];
            };

            //REQUEST WILL ISSUE JUST HERE! SINCE __get overrided
            $that = $this;
            $data->each(function($key, $value) use($fetchData , $that , $vk) {

                sleep(2);
                $this->line('sleep bit before '.$value->id.' process');

                $user = $fetchData($value->user_id);

                $message = array($user , $value);

                $expiresAt = Carbon::now()->addMinutes(60);

                if ($value->read_state == 0 && !Cache::has($value->id.'_message')){
                    $that->info('reporting new message '.preg_replace('/\s+/', ' ', trim($value->body)));
                    Cache::put($value->id.'_message', json_encode($value), $expiresAt); 
                //}else{
                //    $that->info('message already read ::'.preg_replace('/\s+/', ' ', trim($value->body)));
                }

                if (
                    Cache::get('last_message_text') != preg_replace('/\s+/', ' ', trim($value->body))
                    ){
                      Cache::put('last_message_text', preg_replace('/\s+/', ' ', trim($value->body)), $expiresAt); 
                    $responseMessage = $that->prepareResponse(
                        $value->id , 
                        preg_replace('/\s+/', ' ', trim($value->body))
                    );                  
                }else{
                    $this->error('spam attempt');
                    $responseMessage = false;
                }


                if ($responseMessage){
                    $this->info('Response sent !!!'.$responseMessage);

                    //  mark as read , so will not hand on error
                    try {
                        $response = $vk->request('messages.markAsRead', ['message_ids' => $value->id])->get();
                        $this->info(json_encode($response));                        
                    } catch (Exception $e) {
                        $this->error(json_encode($e));
                    }

                    try {
                        $response = $vk->request('messages.send', ['user_id' => $value->user_id , 'message' =>  preg_replace('/\s+/', ' ', trim($responseMessage))])->get();
                        $this->info(json_encode($response));                        
                    } catch (Exception $e) {
                        $this->error(json_encode($e));
                    }

                }
                return;
            });
        }

        if ($this->argument('keepalive')){
            sleep(1);
            $this->info('restart');
            $this->handle();
        }else{
            return;
        }

        /**



        if (Cache::has('reports_list')){
            $reportList = json_decode(Cache::get('reports_list') , true);
        }
        **/
    }

    public function prepareResponse($id , $message){

        if (!Cache::has($id.'_message')){
            //$this->error('Message not tagged');
            return false;
        }

        $this->error('Processing '.$id.':'.$message);

        $chatBotUrl = Config::get('app.chatbot_url');
        $chatBotToken = Config::get('app.chatbot_token');
        if (!$chatBotUrl || !$chatBotToken){
            $this->error('ChatBot details missing');
            return false;
        }

        $return = false;

        if (!Cache::has($id.'_message_sent') && !Cache::has('message_in_process')){
            $expiresAt = Carbon::now()->addMinutes(60);

            $client = new Client();
            $options = array('query' => array(
                'IDENT' => Config::get('app.chatbot_token'),
                'IN' => $message
            ));

            try {

                $response = $client->get(Config::get('app.chatbot_url'), $options);
                $json = json_decode($response->getBody(true)->getContents() , true);

                if (!(json_last_error() == JSON_ERROR_NONE && is_array($json))) {
                    $this->error('Response Error');
                }else{
                    Cache::put($id.'_message_sent', $message, $expiresAt); 
                    Cache::put('message_in_process', $id, $expiresAt); 
                    Cache::put('message_last_timestamp', $json['edit_time'], $expiresAt); 
                }

            } catch (BadResponseException $ex) {
                $error =  array('error' => 1 , 'details' => 'problems : '.$ex->getResponse()->getBody()); 

                $this->error(json_encode($error));
            }

        }else if (Cache::get('message_in_process') == $id){

            $expiresAt = Carbon::now()->addMinutes(60);

            $client = new Client();
            $options = array('query' => array(
                'IDENT' => Config::get('app.chatbot_token'),
            ));

            try {

                $response = $client->get(Config::get('app.chatbot_url'), $options);
                $json = json_decode($response->getBody(true)->getContents() , true);

                if (!(json_last_error() == JSON_ERROR_NONE && is_array($json))) {
                    $this->error('Response Error');
                }else if ($json['edit_time'] != Cache::get('message_last_timestamp')){
                    Cache::forget('message_in_process'); 
                    Cache::put('message_last_timestamp', $json['edit_time'], $expiresAt); 
                }

                $return = $json['message'];


            } catch (BadResponseException $ex) {
                $error =  array('error' => 1 , 'details' => 'problems : '.$ex->getResponse()->getBody()); 

                $this->error(json_encode($error));
            }
        }

        return $return;
    }
}
