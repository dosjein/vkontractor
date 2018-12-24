<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use Telegram;
use \GuzzleHttp\Client;
use \GuzzleHttp\Exception\BadResponseException;

use Config;
use Cache;
use Carbon\Carbon;
use DB;

use Exception;

class TelegramTest extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'telegram:message';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Telegramm message responses';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }


    public function prepareResponse($id , $message , $user_id){

        // if (!Cache::has($id.'_message')){
        //     //$this->error('Message not tagged');
        //     return false;
        // }

        $this->error('Processing '.$id.':'.$message);

        $chatBotUrl = Config::get('app.chatbot_url');
        $chatBotToken = Config::get('app.chatbot_token');
        if (!$chatBotUrl || !$chatBotToken){
            $this->error('ChatBot details missing');
            return false;
        }

        $return = false;

        $this->info(Cache::has($id.'_telegram_message_sent').' '.Cache::has('telegram_message_in_process'));

        if (!Cache::has($id.'_telegram_message_sent') && !Cache::has('telegram_message_in_process')){
            $expiresAt = Carbon::now()->addMinutes(60);

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
                                    'in' => 1
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

            if (getenv('DB_CONNECTION') && $message){
                try {
                    DB::connection()->getPdo();
                    if(DB::connection()->getDatabaseName()){
                        
                        DB::connection()->table('messages')->insert(
                            [
                                'message' => $message ,
                                'created_at' => Carbon::now(),
                                'user_id' => $user_id,
                                'in' => 1
                            ]
                        );
                    }
                } catch (\Exception $e) {
                    $this->error("Could not connect to the database.  Please check your configuration.");
                }
            }

            $this->line($message);

            $client = new Client();
            $options = array('query' => array(
                'IDENT' => Config::get('app.chatbot_token'),
                'IN' => $message ,
                'SPEAKER_IDENT' => $user_id
            ));

            $this->info(json_encode($options));


            try {

                $response = $client->get(Config::get('app.chatbot_url'), $options);
                $json = json_decode($response->getBody(true)->getContents() , true);

                if (!(json_last_error() == JSON_ERROR_NONE && is_array($json))) {
                    $this->error('Response Error');
                }else{
                    Cache::put($id.'_telegram_message_sent', $message, $expiresAt); 
                    Cache::put('telegram_message_in_process', $id, $expiresAt); 
                    Cache::put('telegram_message_last_timestamp', $json['edit_time'], $expiresAt); 
                }

            } catch (BadResponseException $ex) {
                $error =  array('error' => 1 , 'details' => 'problems : '.$ex->getResponse()->getBody()); 

                $this->error(json_encode($error));
            }

        }else if (Cache::get('telegram_message_in_process') == $id){

            $expiresAt = Carbon::now()->addMinutes(60);

            $client = new Client();
            $options = array('query' => array(
                'IDENT' => Config::get('app.chatbot_token') ,
                'SPEAKER_IDENT' => $user_id
            ));

            $this->info(json_encode($options));

            try {

                $response = $client->get(Config::get('app.chatbot_url'), $options);
                $json = json_decode($response->getBody(true)->getContents() , true);

                if (!(json_last_error() == JSON_ERROR_NONE && is_array($json))) {
                    $this->error('Response Error');
                }else if (isset($json['edit_time']) && $json['edit_time'] != Cache::get('telegram_message_last_timestamp')){
                    $this->info('send_forget');
                    Cache::forget('telegram_message_in_process'); 
                    Cache::put('telegram_message_last_timestamp', $json['edit_time'], $expiresAt); 
                }

                $this->info($return);

                $return = $json['message'];


            } catch (BadResponseException $ex) {
                $error =  array('error' => 1 , 'details' => 'problems : '.$ex->getResponse()->getBody()); 

                $this->error(json_encode($error));
            }
        }else{
            $this->error('skipped all parts '.Cache::get('telegram_message_in_process'));
            die(55);
        }

        return $return;
    }


    private function triggerResponse($value)
    {
        $responseMessage = $this->prepareResponse(
            $value["message"]["message_id"] , 
            preg_replace('/\s+/', ' ', trim($value["message"]["text"])),
            $value["message"]["from"]["id"]
        ); 

        return $responseMessage;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $response = Telegram::getMe();

        $botId = $response->getId();
        $firstName = $response->getFirstName();
        $username = $response->getUsername();

        $this->info($username);

        $lastResponseId = false;

        while (true) {
            $response = Telegram::getUpdates();

            $this->info(json_encode($response));

            foreach (array_reverse($response) as $key => $value) {

                $ignore = false;

                if (Cache::has($value["message"]["message_id"].'_telegram_message_sent')){
                    $ignore = true;
                }

                $responseMessage = '';

                while (trim($responseMessage) == '' || !$ignore ){
                    $responseMessage = $this->triggerResponse($value);
                    $this->info($responseMessage.'::: and sleep');

                    if (trim($responseMessage) != ''){
                        $this->info('Brake UP');
                        break;
                    }

                    sleep(10);
                }

                //dd($value);

                $response = Telegram::sendMessage([
                  'chat_id' => $value['message']['chat']['id'], 
                  'text' => $responseMessage
                ]);

                $this->info(json_encode($response));

                $lastResponseId = $responseMessage;

                $responseMessage = '';

            }
        }

    }
}
