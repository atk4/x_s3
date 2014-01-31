<?php
/**
 * Created by JetBrains PhpStorm.
 * User: vadym
 * Date: 9/13/13
 * Time: 11:05 AM
 * To change this template use File | Settings | File Templates.
 */
namespace atk4\x_s3;
require_once __DIR__.'/../../vendor/s3/S3.php'; // https://github.com/tpyo/amazon-s3-php-class
class Controller_S3 extends \AbstractController {

    public $publickey;
    public $privatekey;

    private $s3;

    function init() {
        parent::init();
        $this->publickey = $this->api->getConfig('amazon/s3/Access_Key_ID');
        $this->privatekey = $this->api->getConfig('amazon/s3/Secret_Access_Key');
        $this->s3 = new \S3($this->publickey,$this->privatekey);

    }
    function getS3() {
        return $this->s3;
    }

    function uploadFile($bucket,$filename,$overwrite_file=false) {
        $s3 = $this->getS3();
        $this->bucketExist($bucket,true);
        if (!$overwrite_file) $this->fileExist($bucket,$filename,true);
        $this->getS3()->putObject($this->getS3()->inputFile($filename, false), $bucket, baseName($filename), $s3::ACL_PUBLIC_READ);
        return $this->getFileUrl($bucket,$filename);
    }

    function getFileInfo($bucket,$filename) {
        $info = $this->getS3()->getObjectInfo($bucket, baseName($filename));
        return $info;
    }

    function deleteFile($bucket,$filename) {
        $this->bucketExist($bucket,true);
        return $this->getS3()->deleteObject($bucket,$filename);
    }

    function getFileUrl($bucket,$filename) {
        return 'http://'.$bucket.'.s3.amazonaws.com/'.$filename;
    }





    private $checked_buckets = array();
    private function isBucketChecked($name) {
        if (array_key_exists($name,$this->checked_buckets)) {
            return true; // exists || not_exists
        }
        return false;
    }
    private function _bucketExist($name, $throw_exception) {
        $list = $this->getS3()->listBuckets();
        if (!in_array($name,$list)) {
            $this->checked_buckets[$name] = 'not_exists';
            if ($throw_exception) {
                throw $this->exception('Bucket with name "'.$name.'" doesn\'t exist');
            }
            return false;
        }
        $this->checked_buckets[$name] = 'exists';
        return true;
    }
    function bucketExist($name, $throw_exception=false) {
        if ($this->isBucketChecked($name)) {
            if ($this->checked_buckets[$name] == 'exists') {
                return true;
            } else if ($throw_exception) {
                throw $this->exception('Bucket with name "'.$name.'" doesn\'t exist');
            }
            return false;
        }
        return $this->_bucketExist($name, $throw_exception);
    }

    private $checked_files = array(); // array('bucket'=>array(....files....))
    private function isFileChecked($bucket,$file) {
        if (array_key_exists($bucket,$this->checked_files)) {
            if (in_array($file,$this->checked_files[$bucket])) {
                return true; // exists || not_exists
            }
        }
        return false;
    }
    private function _fileExist($bucket,$file,$throw_exception) {
        $list = $this->getS3()->getBucket($bucket);
        if (array_key_exists($file,$list)) {
            $this->checked_files[$bucket][$file] = 'exists';
            if ($throw_exception) {
                throw $this->exception('File with name "'.$file.'" already exist in bucket "'.$bucket.'"');
            }
            return true;
        }
        $this->checked_files[$bucket][$file] = 'not_exists';
        return false;
    }
    function fileExist($bucket,$file,$throw_exception=false) {
        $file = basename($file);
        if ($this->isFileChecked($bucket,$file)) {
            if ($this->checked_files[$bucket][$file] == 'exists') {
                if ($throw_exception) {
                    throw $this->exception('File with name "'.$file.'" already exist in bucket "'.$bucket.'"');
                }
                return true;
            }
            return false;
        }
        return $this->_fileExist($bucket,$file,$throw_exception);
    }







    /*
     * Ещё одна крайне полезная функция — доступ к файлам посредством BitTorrent.
     * Так будет меньше тратиться трафик на сервере, а у пользователей окажется выше скорость скачивания.
     * Нужно всего-то добавить в конец ссылки на файл ?torrent.
     */
    function makeTorrent() {

    }
}
