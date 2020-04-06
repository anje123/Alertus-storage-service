# ALERTUS Storage System

## Guidelines

ALERTUS Storage System uses Google Cloud Storage Platform to store audio file in Google Cloud Storage bucket.


HOW TO SETUP:
* Creating and activating a gcloud service account will give you a JSON file for accessing Google Cloud check https://cloud.google.com/speech-to-text/docs/quickstart-gcloud for quickstart
* Google Cloud Storage bucket. Once you’ve met the requirement, you can quickly create a bucket check https://cloud.google.com/storage/docs/creating-buckets for more information
* clone this repo
* cp .env.example .env fill the Keys in the .env accordingly with the API keys generated above
* composer install
* php artisan migrate, to migrate table

## RESTful URLs
* Upload an audio to google cloud storage bucket:
    * POST /api/store
    field: recording_sid,recording_url
* To get all google storage url uploaded:
    * GET /api/store
* To get one audio uploaded by id:
    * GET /api/store/{id}

#### Please Dont Forget Check My Pull Request and Issues for my recent contributions to Ushahidi :heart: :pray:

