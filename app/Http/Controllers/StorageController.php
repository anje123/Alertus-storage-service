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
        $_filename = $response->Recording_Sid;
        $cloudconvert = new CloudConvert([

            'api_key' => $this->apikey
        ]);

        $filename = self::getFilename($_filename);

        try {

            $cloudconvert->file($audio)->to($this->path . $filename);

        } catch (\Throwable $e) {
           $this->updateStorageStatusIfFailed($response);
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ]);
        }

        $file_path = $this->path.$filename;
        $file = fopen($file_path, 'r');
        $bucket = $storage->bucket($this->bucket_name);
        
         // upload audio
        try {
            $object = $bucket->upload($file, [
                'name' => $filename
            ]);
        } catch (Exception $e) {
            $this->updateStorageStatusIfFailed($response);
        }
        $this->deleteFile($filename);
        $this->updateStorageWhenStored($response, $filename);
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

    public static function getFilename($name)
    {
        return $name.".flac";
    } 

    public function deleteFile($filename)
    {
        $file = $this->path.$filename;

        $filesystem = new Filesystem;

        if ($filesystem->exists($file)) {

            $filesystem->delete($file);
        }
        return;
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
        error_log($filename);
        $disk = Storage::disk('gcs');
        $url = $disk->url($filename);
        $response->Recording_Url = $url;
        $response->Storage_status = $this->processed;
        $response->save();
    }

    public function deleteRecordingFromTwilio($RecordingSid){
        // Find your Account Sid and Auth Token at twilio.com/console
        $sid    = getenv('ACCOUNT_SID');
        $token  = getenv('TWILIO_TOKEN');
        $twilio = new Client($sid, $token);
        $twilio->recordings($RecordingSid)
               ->delete();
        // delete local copy
      //  unlink($file_name) or die("Couldn't delete file");
    }
    
    public function test()
    {
        $disk = Storage::disk('gcs');
        $url = $disk->url('RE297a63cc9986c1335792faae7024b4bd.flac');
        error_log($url);
    }

}
