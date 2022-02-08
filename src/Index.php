<?php

$vendor_dir = '/opt/vendor';
require_once $vendor_dir . '/autoload.php';


class Index
{
    private $s3;

    public function __construct()
    {
        $this->s3 = new Aws\S3\S3Client([
            'version' => 'latest',
            'region' => 'eu-central-1'
        ]);
    }

    public function run($request)
    {
        $s3Data = $request['payload']['Records'][0]['s3'];
        $bucketName = $s3Data['bucket']['name'];
        $object = $s3Data['object']['key'];


        $file = $this->s3->getObject([
            'Bucket' => $bucketName,
            'Key' => $object,
        ]);
        echo "Downloading " . $object;
        $path  = "/tmp/{$object}";
        file_put_contents($path, $file['Body']);
        echo "\nImage downloaded to {$path}";

        echo "\nGenerating thumbnail";
        $thumb = $this->generateThumbnail($path, 30, 30);

        echo "\nUploading to S3";
        $this->uploadToS3($object, $thumb);

        echo "\nUpladed to S3";

        return $this->APIResponse(['status' => true]);
    }


    private function generateThumbnail($src, $width, $height, $dest = "/tmp")
    {

        /* read the source image */
        $sourceImage = imagecreatefromjpeg($src);
        $imageWidth = imagesx($sourceImage);
        $imageHeight = imagesy($sourceImage);

        /* create a new, "virtual" image */
        $virtualImage = imagecreatetruecolor($width, $height);

        /* copy source image at a resized size */
        imagecopyresampled($virtualImage, $sourceImage, 0, 0, 0, 0, $width, $height, $imageWidth, $imageHeight);

        /* create the physical thumbnail image to its destination */
        $filename = basename($src);
        $dest = $dest . '/thumb_' . $filename;
        imagejpeg($virtualImage, $dest);

        return $dest;
    }

    private function uploadToS3($fileName, $path)
    {

        $bucket = "profile-img-abc-123-smoqadam-thumbnail";

        $this->s3->putObject([
            'Bucket' => $bucket,
            'Key' => $fileName,
            'SourceFile' => $path,
        ]);
    }

    function APIResponse($body)
    {
        $headers = array(
            "Content-Type" => "application/json",
            "Access-Control-Allow-Origin" => "*",
            "Access-Control-Allow-Headers" => "Content-Type",
            "Access-Control-Allow-Methods" => "OPTIONS,POST"
        );
        return json_encode(array(
            "statusCode" => 200,
            "headers" => $headers,
            "body" => $body
        ));
    }
}
