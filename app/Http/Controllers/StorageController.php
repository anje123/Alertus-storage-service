<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;
use App\Http\Requests;
use Response;
use App\Http\Controllers\Controller;
use Cookie;
use App\Store;
use Google\Cloud\Speech\SpeechClient;
use RobbieP\CloudConvertLaravel\CloudConvert;
use Google\Cloud\Storage\StorageClient;
use Google\Cloud\Core\ExponentialBackoff;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Client;
use Storage;

class StorageController extends Controller
{

    public function __construct()
    { 
        $this->path = public_path('audio-contents/');
        $this->apikey = config('cloudconvert.api_key');
        $this->bucket_name = env('GOOGLE_CLOUD_STORAGE_BUCKET', 'femmy2');
        $this->processed = 'processed';
        $this->processing = 'processing';
        $this->not_processed = 'not_processed';
        $this->failed = 'failed';

    }


    public function createInstance()
    {
        $project_id = env('PROJECT_ID');
        $speech = new SpeechClient([
            'projectId' => $project_id,
            'languageCode' => 'en-US',
        ]);
        
        
        return $speech;
    }

    public  function upload_object()
    {
        $storage = new StorageClient();
        $response = Store::where('Storage_status',$this->not_processed)->orWhere('Storage_status',$this->failed)->first();
        
        // check if an object was found
        if(!$response){
            return;
        }
        
        $this->updateStorageStatusWhenStoring($response);

        $audio = $response->Recording_Url . '.mp3';
        $filename = $response->Recording_Sid .'.mp3';

        try {
            $contents = file_get_contents($audio);
            Storage::disk('gcs')->put($filename, $contents);
            $this->updateStorageWhenStored($response, $filename);
        } catch (\Throwable $th) {
            $this->updateStorageStatusIfFailed($response);
            Log::error($th);
        }


      //  $this->deleteRecordingFromTwilio($response->recording_sid);
        
        Log::info('Uploaded');
    }

    public function insertData()
    {
       $last_object_id = 1;
       $client = new \GuzzleHttp\Client([
       'base_uri' => 'http://localhost:8001',
        'defaults' => [
            'exceptions' => false
        ]
       ]);
    
        $response = $client->request('GET', '/api/responses/'.$last_object_id);
        $data = (string)$response->getBody();
        $responses = json_decode($data, true);

        $last_object_id = end($responses)['id'];
        Log::info($responses);
        Log::info($last_object_id);

        foreach($responses as $response) {
            Store::create([
                'Recording_Sid' => $response['recording_sid'],
                'Recording_Url' => $response['response']
            ]);
        }

    }

    public function updateStorageStatusWhenStoring($response)
    {
        $response->Storage_status = $this->processing;
        $response->save();
    }

    public function updateStorageStatusIfFailed($response)
    {
        $response->Storage_status = $this->failed;
        $response->save();
    }
    public function updateStorageWhenStored($response, $filename)
    {
        $disk = Storage::disk('gcs');
        $url = $disk->url($filename);
        $response->Recording_Url = $url;
        $response->Storage_status = $this->processed;
        $response->save();
    }

    public function deleteRecordingFromTwilio(){
        $client = new \GuzzleHttp\Client([
            'base_uri' => 'http://localhost:8001',
             'defaults' => [
                 'exceptions' => false
             ]
            ]);
        $responses = Store::where('Storage_status',$this->processed)->where('Twilio_delection_status','false')->take(10)->get();
        Log::info($responses);
        $response = $client->request('POST', '/api/delete_recording_from_twilio', ['form_params' => json_decode($responses)]);

        if($response->getStatusCode() == 200){
            $responses->Twilio_delection_status = 'true';
            $responses->save();
        }else{
            Log::error('Unable to delete');
            return;
        }
    }
    

}
