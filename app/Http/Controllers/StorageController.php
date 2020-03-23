<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;
use App\Http\Requests;
use Response;
use App\Http\Controllers\Controller;
use Cookie;
use App\QuestionResponse;
use Google\Cloud\Speech\SpeechClient;
use RobbieP\CloudConvertLaravel\CloudConvert;
use Google\Cloud\Storage\StorageClient;
use Google\Cloud\Core\ExponentialBackoff;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Client;
use App\Storage;


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

    function upload_object()
    {
        $storage = new StorageClient();
        $response = QuestionResponse::where('storage_status',$this->not_processed)->orWhere('storage_status',$this->failed)->first();
        
        // check if an object was found
        if(!$response){
            return;
        }
        
        $this->updateStorageStatusWhenStoring($response);

        if(!$response){
            return;
        }

        $audio = $response->response . '.mp3';
        $_filename = $response->recording_sid;
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
        $this->deleteRecordingFromTwilio($response->recording_sid);
        
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
        Storage::create([
            'Recording_Sid' => $response['response'],
            'Recording_Url' => $response['recording_sid']
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
        $response->storage_status = $this->processing;
        $response->save();
    }

    public function updateStorageStatusIfFailed($response)
    {
        $response->storage_status = $this->failed;
        $response->save();
    }
    public function updateStorageWhenStored($response, $filename)
    {
        $response->response = $filename;
        $response->storage_status = $this->processed;
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
    
}
