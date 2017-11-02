<?php
/**
* S3 File Handling Class
*
* @version 1.0
*/

require 's3.php';

class S3Helper {
	protected static $S3;

	public function __construct($s3Key, $s3Key2) {
		self::$S3 = new S3($s3Key, $s3Key2); 
	}

	/**
	* Generates a random string for filename
	*
	* @return string
	*
	*/
	public function getRandomName() {
		$chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
		return substr(str_shuffle($chars),0,15);
	}

	/**
	* Upload a file to an S3 Bucket
	*
	* @param string $bucket Bucket name
	* @return file 
	*
	*/
	public function uploadFile($bucket, $fileKey) {
		if(!empty($_FILES[$fileKey]['tmp_name'])) {
			$extension = pathinfo($_FILES[$fileKey]['name'], PATHINFO_EXTENSION);

			do {
				$path = self::getRandomName().'.'.$extension;
			} while(self::$s3->getObjectInfo($bucket,$path));

			$s3Upload = S3::putObject(
				self::$s3->inputFile($_FILES[$fileKey]['tmp_name'], false), 
				$bucket,
				$path, 
				S3::ACL_PUBLIC_READ,
				array(),
				array(),
				S3::STORAGE_CLASS_RRS
			);

			if($s3Upload) {
				$file['name'] = $_FILES[$fileKey]['name'];
				$file['path'] = $bucket.$path;
				$file['size'] = $_FILES[$fileKey]['size']; 
				return $file;
			}
		} 
		return 'EC10';
	}

	/**
	* Delete a file from an S3 Bucket
	*
	* @param string $bucket Bucket name
	* @param string $filePath File path inside the bucket
	* @return string
	*
	*/
	public function deleteFile($bucket,$filePath) {
		if(self::$s3->getObjectInfo($bucket,$filePath)) {
			$s3Delete = S3::deleteObject($bucket, $filePath);
			if($s3Delete) {
				return 'Deleted';
			} 
			return 'EC11';
		} 
		return 'EC4'; // todo Error object
	}

	/**
	* Copy file 
	*
	* @param string $bucket Source bucket name
	* @param string $filePath File path inside the bucket
	* @param string $destBucket Destination bucket name
	* @return string
	*/
	public function copyFile($bucket,$filePath,$destBucket) {
		$s3 = new S3(self::$S3_KEY,self::$S3_KEY_2);

		if($s3->getObjectInfo($bucket,$filePath)) {
			$s3Copy = S3::copyObject(
				$bucket,
				$filePath,
				$destBucket,
				$filePath,
				S3::ACL_PUBLIC_READ,
				array(),
				array(),
				S3::STORAGE_CLASS_RRS
			);

			if($s3Copy) {
				return 'Copied';
			}
			return 'EC12';
		}
		return 'Not Found';
	}

	/**
	* Move a file
	*
	* @param string $bucket Source bucket name
	* @param string $filePath File path inside the bucket
	* @param string $destBucket Destination bucket name
	* @return string
	*/
	public function moveFile($bucket,$filePath,$destBucket) {
		$s3 = new S3(self::$S3_KEY,self::$S3_KEY_2);
		if($s3->getObjectInfo($bucket,$filePath)) {
			if($s3Copy = self::copyFile($bucket,$filePath,$destBucket) != 'EC12') {
				if($s3Copy) { // delete primary copy
					if(self::deleteFile($bucket,$filePath) != 'EC11') {
						return 'Moved';
					} else { // error, delete copy
						if(self::deleteFile($destBucket,$filePath) != 'EC11') {
							return 'EC12';
						}
					}
				} 
				return 'EC12';
			}
		} 
		return 'EC4';
	}
}
?>