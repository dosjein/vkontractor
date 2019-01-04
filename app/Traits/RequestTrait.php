<?php
namespace App\Traits;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

use InvalidArgumentException;
use GuzzleHttp\Exception\ConnectException;
use \GuzzleHttp\Client;
use \GuzzleHttp\Exception\BadResponseException;

use Config;

trait RequestTrait {

	private $error = false;

    public function requestGET($params , $url , $code = false)
    {

        $this->client = new Client();

        $requestQuery = $params;

        $options = array(
            'query' => $requestQuery
        );

        try {
            $response = $this->client->get($url, $options);

            if ($code){
                $codeDetails = $response->getStatusCode ();
                if ($codeDetails == 200){
                    return true;
                }
            }else{
                $responseArray = json_decode(
                    $response->getBody(true)->getContents() , 
                    true
                );
                return $responseArray; 
            }
   
        } catch (BadResponseException $ex) {
        	$this->error = $ex->getResponse()->getBody();
        } catch (ConnectException $ex) {
            
        }
    }

    public function requestPOST($params , $url, $code = false , $json = true)
    {

        $this->client = new Client();

        $requestQuery = $params;

        $options = array(
            'form_params' => $requestQuery
        );

        try {
            $response = $this->client->post($url, $options);

            if ($code){
                $codeDetails = $response->getStatusCode ();
                if ($codeDetails == 200){
                    return true;
                }
            }else{
                if ($json){
                    $responseArray = json_decode(
                        $response->getBody(true)->getContents() , 
                        true
                    ); 
                }else{
                    return $response->getBody(true)->getContents();
                }

                return $responseArray; 
            }

			return $responseArray;    
        } catch (BadResponseException $ex) {
        	$this->error = $ex->getResponse()->getBody();

            return $this->error;
        }
    }

}