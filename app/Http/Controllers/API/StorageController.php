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
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;


class StorageController extends BaseController
{

    public function __construct()
    { 
        $this->path = public_path('audio-contents/');
        $this->apikey = config('cloudconvert.api_key');
        $this->bucket_name = env('GOOGLE_CLOUD_STORAGE_BUCKET', 'femmy2');
        $this->processed = 'processed';
        $this->not_processed = 'not_processed';
    }

    public function test()
    {
        $connection = new AMQPStreamConnection('localhost', 5672, 'guest', 'guest');
        $channel = $connection->channel();
        $channel->queue_declare('storage_queue', false, true, false, false);
        $callback = function($msg) {
            //Convert the data to array
            $data = json_decode($msg->body, true);
            Log::info($data);
            foreach ($data as $sdata) {
                Store::create([
                    'Recording_Url' => $sdata['response'],
                    'Recording_Sid' => $sdata['recording_sid']
                ]);
            }

     
            echo "Finished Processing\n";
        };
        $channel->basic_consume('storage_queue', '', false, false, false, false, $callback);

        //Listen to requests
        while (count($channel->callbacks)) {
            $channel->wait();
        }

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
