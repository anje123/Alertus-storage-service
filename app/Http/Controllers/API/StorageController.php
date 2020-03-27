<?php
namespace App\Http\Controllers\API;
use Illuminate\Http\Request;
use App\Http\Requests;
use Response;
use App\Http\Controllers\Controller;
use Cookie;
use App\Store;
use Google\Cloud\Storage\StorageClient;
use Illuminate\Filesystem\Filesystem;
use App\Http\Controllers\BaseController as BaseController;
use Illuminate\Support\Facades\Log;
use Storage;

class StorageController extends BaseController
{

    public function __construct()
    { 
        $this->path = public_path('audio-contents/');
        $this->apikey = config('cloudconvert.api_key');
        $this->bucket_name = env('GOOGLE_CLOUD_STORAGE_BUCKET', '');
        $this->processed = 'processed';
        $this->not_processed = 'not_processed';
    }

    public  function upload_object(Request $request)
    {
        $storage = new StorageClient();
        

        $audio = $request->recording_url;
        $filename = $request->recording_sid .'.mp3';

        try {
            $contents = file_get_contents($audio);
            Storage::disk('gcs')->put($filename, $contents);
            $this->updateStorageWhenStored($request, $filename);
        } catch (\Throwable $th) {
            Log::error($th);
        }
        
    }

  
    public function updateStorageWhenStored($request, $filename)
    {
        $disk = Storage::disk('gcs');
        $url = $disk->url($filename);
        Store::create([
            'Recording_Sid' => $request->recording_sid,
            'Recording_Url' => $url,
            'Storage_status' => $this->processed
        ]);
    }
    
    public function getStoredUrl()
    {
        $store = Store::all();
        return response()->json($store);
    }

    public function getStoredUrlById($id)
    {
        $store = Store::find($id);
        return response()->json($store);  
    }
}
