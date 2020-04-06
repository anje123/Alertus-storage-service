<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;
use App\Http\Requests;
use Response;
use App\Http\Controllers\Controller;
use Cookie;
use App\Store;
use Google\Cloud\Speech\SpeechClient;
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
        
        if(!$response){return;}
        
        $this->updateStorageStatusWhenStoring($response);

        $audio = $response->Recording_Url;
        $filename = $response->Recording_Sid .'.mp3';

        try {
            $contents = file_get_contents($audio);
            Storage::disk('gcs')->put($filename, $contents);
            $this->updateStorageWhenStored($response, $filename);
        } catch (\Throwable $th) {
            $this->updateStorageStatusIfFailed($response);
            Log::error($th);
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
        Log::info('BUCKET UPLOADED URL    '.$url);
    }

}
