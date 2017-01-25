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

class Trigger extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'vk:trigger {message?} {uid?} {rebel?}';

    /**
    * UID vk
    */
    public $uid;

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Trigger a Person in VK. UID needed , if none , will trigger last one. [message user]';

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

        $vk = Core::getInstance()->apiVersion('5.5')->setToken(getenv('VKTOKEN'));

        if ($this->argument('uid') && !$this->argument('rebel')){
            $this->uid = $this->argument('uid');
            $message = $this->argument('message');
            $this->info('Triggering '.$uid);
        }else{
            $this->info('Triggering last contacted');

            $that = $this;
            if ($this->argument('rebel')){
                try {
                    $client = new Client();
                    $options = array();

                    $response = $client->get('https://randomuser.me/api/', $options);
                    $json = json_decode($response->getBody(true)->getContents() , true);

                    if (!(json_last_error() == JSON_ERROR_NONE && is_array($json))) {
                        $this->error('Response Error');
                        return 1;
                    }else{
                        $name = $json['results'][0]['name']['first'];
                    }

                } catch (BadResponseException $ex) {
                    $error =  array('error' => 1 , 'details' => 'problems : '.$ex->getResponse()->getBody()); 
                    $this->error(json_encode($error));
                }

                $vk->request('users.search', ['count' => 1 , 'online' => 1 , 'q' => $name])->each(
                    function($key, $value) use($that){
                        $that->info($value->id.' will triggered ');
                        $that->uid = $value->id;
                    }
                ); 
            }else{
                $vk->request('messages.get', ['count' => 1 , 'out' => 0])->each(
                    function($key, $value) use($that){
                        $that->info($value->user_id.' will triggered ');
                        $that->uid = $value->user_id;
                    }
                );                
            }

            if ($this->argument('message') && !$this->argument('rebel')){
                $message = $this->argument('message');
            }else{
                //get Chuck Norris quote here for a test ... AnyWay 
                if (getenv('REBEL_MSG')){
                    $message = getenv('REBEL_MSG');
                }else{
                    $client = new Client();
                    $options = array();

                    try {

                        $response = $client->get('https://api.chucknorris.io/jokes/random', $options);
                        $json = json_decode($response->getBody(true)->getContents() , true);

                        if (!(json_last_error() == JSON_ERROR_NONE && is_array($json))) {
                            $this->error('Response Error');
                            return 1;
                        }else{
                            $message = $json['value'];
                        }

                    } catch (BadResponseException $ex) {
                        $error =  array('error' => 1 , 'details' => 'problems : '.$ex->getResponse()->getBody()); 

                        $this->error(json_encode($error));
                        return 1;
                    }
                }
            }

            
        }


        try {
            $response = $vk->request('messages.send', ['user_id' => $this->uid , 'message' =>  preg_replace('/\s+/', ' ', trim($message))])->get();
            $this->info(json_encode($response));                        
        } catch (Exception $e) {
            $this->error(json_encode($e));
        }



    }
}
