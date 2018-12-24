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

use DB;

use Exception;

use App\Processor;
use App\Messages;

class MessageSmartResponse extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'vk:messages {ignore_news?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Message processing based on deeper checking';

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

        $this->error('Loading all dialogs');

        if (!$this->argument('ignore_news')){

            if (Processor::count() == 0){
                $this->info('First load operations');
                foreach($vk->request('messages.getConversations', ['count' => 200 ])->batch(200) as $data){
                    foreach ($data->items as $key => $dialogItem) {
                        $processor = new Processor();

                        $processor->user_id = $dialogItem->last_message->from_id;
                        $processor->status = $dialogItem->last_message->out;

                        if (!$dialogItem->conversation->can_write->allowed){
                            $processor->status = 5;
                        }

                        $processor->message_id = $dialogItem->last_message->id;

                        $processor->message = $dialogItem->last_message->text;
                        $processor->save();
                    }
                }
            }

            $this->info('incomming message process');

            foreach($vk->request('messages.getConversations', ['count' => 200 ])->batch(200) as $data){
                $this->info('wait for 2 sec');
                sleep(2);
                foreach ($data->items as $key => $dialogItem) {
                    $processorRequest = Processor::where('user_id' , $dialogItem->last_message->from_id);
                    if ($processorRequest->count() == 0){
                        $processor = new Processor();

                        $processor->user_id = $dialogItem->last_message->from_id;
                        $processor->status = $dialogItem->last_message->out;

                        if (!$dialogItem->conversation->can_write->allowed){
                            $processor->status = 5;
                        }

                        $processor->message = $dialogItem->last_message->text;
                        $processor->message_id = $dialogItem->last_message->id;
                        $processor->save();
                    }else{
                        $processor = $processorRequest->first();

                        //if banned
                        if (!$dialogItem->conversation->can_write->allowed){
                            $processor->status = 5;
                            $processor->save();
                            //message procedure (must extract as external method)
                            if (Messages::where('message' , 'BAN')->where('user_id' , $dialogItem->last_message->from_id)->count() == 0){
                                $message = new Messages();
                                $message->in = 1;
                                $message->message = 'BAN';
                                $message->user_id = $dialogItem->last_message->from_id;
                                $message->save();
                            }
                        }

                        //if has response
                        if ($processor->status == 1 && $dialogItem->last_message->out == 0){

                            //save incomming message
                            $message = new Messages();
                            $message->in = 1;
                            $message->message = $dialogItem->last_message->text;
                            $message->user_id = $dialogItem->last_message->from_id;
                            $message->save();

                            //save processor status change
                            $processor->status = 0;
                            $processor->message = $dialogItem->last_message->text;
                            $processor->message_id = $dialogItem->last_message->id;
                            $processor->save();

                        }else if ($dialogItem->last_message->out == 0 && $dialogItem->last_message->text != $processor->message){
                            //new incomming text
                            $message = new Messages();
                            $message->in = 1;
                            $message->message = $dialogItem->last_message->text;
                            $message->user_id = $dialogItem->last_message->from_id;
                            $message->save();

                            //change if messge is not in process
                            if ($processor->status != 3){
                                $processor->status = 0;
                                $processor->message = $dialogItem->last_message->text;
                                $processor->message_id = $dialogItem->last_message->id;
                            }else{
                                $processor->message_id = $dialogItem->last_message->id;
                            }
                            $processor->save();
                        }
                    }
                }
            }

        }

        $this->info('response prepare process');
        //if QUEUE is empty - put some new meat in
        if (Processor::where('status' , 3)->count() == 0){
            if (Processor::where('status' , 0)->count() == 0){
                $this->error('no incomming messages');
                return;
            }

            $processingText = Processor::where('status' , 0)->first();
            $processingText->status = 3;
            //$processingText->save();

            $message = $processingText->message;
            $user_id = $processingText->user_id;

            //translate incomming message
            //translate message if possible
            if (getenv('DEFAULT_TRANSLATE_API') && $message){

                if (getenv('DB_CONNECTION') && $message){
                    try {
                        DB::connection()->getPdo();
                        if(DB::connection()->getDatabaseName()){
                            
                            DB::connection()->table('messages')->insert(
                                [
                                    'message' => 'original:'.$message ,
                                    'created_at' => Carbon::now(),
                                    'user_id' => $user_id,
                                    'in' => 101
                                ]
                            );
                        }
                    } catch (\Exception $e) {
                        $this->error("Could not connect to the database.  Please check your configuration.");
                    }
                }
                try {

                    $options = array(
                        'query' => array(
                            'language_from' => 'ru' ,
                            'language_to' => 'en',
                            'translate_text' => $message
                        )
                    );

                    $this->info(getenv('DEFAULT_TRANSLATE_API')."/api/v1/translate");

                    $client = new Client();

                    $response = $client->get(getenv('DEFAULT_TRANSLATE_API')."/api/v1/translate", $options);
                    $data = json_decode($response->getBody(true)->getContents() , true);

                    if ($data['status'] == 1){
                        $message = $data['translated_text'];
                    }

            

                } catch (BadResponseException $ex) {
                    $return =  array('error' => 1 , 'details' => 'problems : '.$ex->getResponse()->getBody());
                    $this->error('Dosje Slack register issue: '.json_encode($return));
                }
            }

            // send message to ChatBot

            $client = new Client();
            $options = array('query' => array(
                'IDENT' => Config::get('app.chatbot_token'),
                'IN' => $message ,
                'SPEAKER_IDENT' => $user_id
            ));

            try {

                $this->info('Chatbot approach '.Config::get('app.chatbot_url'));

                $response = $client->get(Config::get('app.chatbot_url'), $options);
                $json = json_decode($response->getBody(true)->getContents() , true);

                if (!(json_last_error() == JSON_ERROR_NONE && is_array($json))) {
                    $this->error('Response Error');
                }else{
                    //save edit time 
                    $processingText->reponse = $json['edit_time'];
                    $processingText->save();
                }

            } catch (BadResponseException $ex) {
                $error =  array('error' => 1 , 'details' => 'problems : '.$ex->getResponse()->getBody()); 
                $this->error(json_encode($error));
            }



        }else{
            //paralel processing in Future
            $this->error('Paralel process in not yet implemented');
        }


        $this->info('Check Response processor');
        if (Processor::where('status' , 3)->count() > 0){
            foreach (Processor::where('status' , 3)->get() as $key => $process) {
                $this->info($process->id.' lookup');

                //quickFix
                if (trim($process->message) == ''){
                    $process->message = 'Иид хнайу';
                    $this->error($process->message);
                    $process->status = 0;
                    $process->save();
                }


                $waitTime = Carbon::now()->diffInMinutes($process->updated_at);

                $this->info('Waiting time '.$waitTime.' ('.$process->updated_at.')');

                if ($waitTime > 5){
                    $this->error('reCap');
                    $process->status = 0;
                    $process->save();
                }

                $client = new Client();
                $options = array('query' => array(
                    'IDENT' => Config::get('app.chatbot_token') ,
                    'SPEAKER_IDENT' => $process->user_id
                ));

                try {

                    $response = $client->get(Config::get('app.chatbot_url'), $options);
                    $json = json_decode($response->getBody(true)->getContents() , true);

                    if (!(json_last_error() == JSON_ERROR_NONE && is_array($json))) {
                        $this->error('Response Error');
                        //TYPO ALERT !!!!
                    }else if ($json['edit_time'] != $process->reponse){
                        $this->line($json['message'].' got');
                        //process send out to VK
                        $this->sendVkMessage($json['message'] , $process);
                        $process->status = 1;
                        $process->delete();
                    }else{
                        $this->line($json['edit_time']);
                    }

                    $return = $json['message'];


                } catch (BadResponseException $ex) {
                    $error =  array('error' => 1 , 'details' => 'problems : '.$ex->getResponse()->getBody()); 

                    $this->error(json_encode($error));
                }
            }
        }
    }

    private function sendVkMessage($responseMessage , $processor){
        //translate message if possible
        if (getenv('DEFAULT_TRANSLATE_API') && $responseMessage){

            if (getenv('DB_CONNECTION') && $responseMessage){
                try {
                    DB::connection()->getPdo();
                    if(DB::connection()->getDatabaseName()){
                        
                        DB::connection()->table('messages')->insert(
                            [
                                'message' => 'original:'.$responseMessage ,
                                'created_at' => Carbon::now(),
                                'in' => 103,                             
                                'user_id' => $processor->user_id
                            ]
                        );
                    }
                } catch (\Exception $e) {
                    $this->error("Could not connect to the database.  Please check your configuration.");
                }
            }

            try {

                $options = array(
                    'query' => array(
                        'language_from' => 'en' ,
                        'language_to' => 'ru',
                        'translate_text' => $responseMessage
                    )
                );

                $this->info(getenv('DEFAULT_TRANSLATE_API')."/api/v1/translate");

                $client = new Client();

                $response = $client->get(getenv('DEFAULT_TRANSLATE_API')."/api/v1/translate", $options);
                $data = json_decode($response->getBody(true)->getContents() , true);

                if ($data['status'] == 1){
                    $responseMessage = $data['translated_text'];
                }

        

            } catch (BadResponseException $ex) {
                $return =  array('error' => 1 , 'details' => 'problems : '.$ex->getResponse()->getBody());
                $this->error('Dosje Slack register issue: '.json_encode($return));
            }
        }

        if (getenv('DB_CONNECTION') && $responseMessage){
            try {
                DB::connection()->getPdo();
                if(DB::connection()->getDatabaseName()){
                    
                    DB::connection()->table('messages')->insert(
                        [
                            'message' => $responseMessage ,
                            'created_at' => Carbon::now(),
                            'in' => 104 , 
                            'user_id' => $processor->user_id
                        ]
                    );
                }
            } catch (\Exception $e) {
                $this->error("Could not connect to the database.  Please check your configuration.");
            }
        }


        if ($responseMessage){

            $vkKey = Config::get('app.vk_key');

            if (!$vkKey){
                $this->error('vk oauth key not defined');
                return 1;
            }

            $vk = Core::getInstance()->apiVersion('5.5')->setToken(getenv('VKTOKEN'));
            $this->info('Response sent !!!'.$responseMessage);

            //  mark as read , so will not hand on error
            try {
                $response = $vk->request('messages.markAsRead', ['message_ids' => [$processor->message_id]])->get();
                $this->info(json_encode($response));                        
            } catch (Exception $e) {
                $this->error($e->getMessage());
            }

            $vk = Core::getInstance()->apiVersion('5.92')->setToken(getenv('VKTOKEN'));

            try {

                $response = $vk->request('messages.send', ['user_id' => $processor->user_id , 'message' =>  preg_replace('/\s+/', ' ', trim($responseMessage)) , 'random_id' => rand(5,5000)])->get();
                $this->info(json_encode($response));                        
            } catch (Exception $e) {
                $this->error($e->getMessage());
            }

        }
    }
}
