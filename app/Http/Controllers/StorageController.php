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
use Twilio\Rest\Client;


class StorageController extends Controller
{

        public function __construct()
        { 
            $this->path = public_path('audio-contents/');
            $this->apikey = config('cloudconvert.api_key');
            $this->bucket_name = env('GOOGLE_CLOUD_STORAGE_BUCKET', 'femmy2');
        }

        /**
         * Initializes the SpeechClient
         * @return object \SpeechClient
         */
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
        $response = QuestionResponse::where('storage_completed',0)->first();
        $audio = $response->response . '.mp3';
        $_filename = $response->recording_sid;
        $cloudconvert = new CloudConvert([

            'api_key' => $this->apikey
        ]);

        $filename = self::getFilename($_filename);

        try {

            $cloudconvert->file($audio)->to($this->path . $filename);

        } catch (ClientException $e) {
           
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ]);
        }

        $file_path = $this->path.$filename;
        $file = fopen($file_path, 'r');
        $bucket = $storage->bucket($this->bucket_name);
        $object = $bucket->upload($file, [
            'name' => $filename
        ]);
        $this->deleteFile($filename);
        $this->updateQuestion($response, $filename);
        $this->deleteRecordingFromTwilio($response->recording_sid);
        
        error_log('Uploaded');
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

    public function updateQuestion($response, $filename)
    {
        $response->response = $filename;
        $response->storage_completed = 1;
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
