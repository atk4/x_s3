<?php
/**
 * Created by JetBrains PhpStorm.
 * User: vadym
 * Date: 9/14/13
 * Time: 1:06 PM
 * To change this template use File | Settings | File Templates.
 */
namespace x_s3;
class Model_Volume extends \SQL_Model {
    public $table='x_s3_volume';
    public $s3;

    public $bucket_prefix = 'x_s3_';
    public $max_quantity_per_volume = 4000;
	function init(){
		parent::init();
        $this->s3 = $this->add('x_s3/Controller_S3');

		$this->addField('bucket')
			->caption('Bucket Name')
			;
		$this->addField('stored_files_cnt')
			->type('int')
			->defaultValue(0)
			->caption('Files')
			;
		$this->addField('enabled')
			->type('boolean')
            ->defaultValue(1)
			->caption('Writable')
			;
	}
	function getFileNumber(){//exit('getFileNumber');
        return count($this->s3->getS3()->getBucket($this['bucket']));

		/*
		   Returns sequnetal file number. Each time this is called - number is increased.

		   Note that this is only approximate number and will not be decreased upon file delete.
		   */
		//$this->api->db->query('lock tables '.$this->entity_code.' write');

//		$f=$this->get('stored_files_cnt');
//		$this->set('stored_files_cnt',$f+1);
//		$this->api->db->dsql()
//			->table($this->table)
//			->set('stored_files_cnt',$f+1)
//			->where('id',$this->get('id'))
//			->do_update();
//
//		$this->api->db->query('unlock tables '.$this->entity_code.'');
//
//		return $f;
	}
    function bucketExist($bucket=null) {
        if (is_null($bucket)) {
            if ($this->loaded()) {
                $bucket = $this['bucket'];
            } else throw $this->exception('Not clear what busket you want to check','Exception_Volume_WhichBucket');
        }
        return $this->s3->bucketExist($bucket,false);
    }
    function isFull($bucket=null) {
        if (is_null($bucket)) {
            if ($this->loaded()) {
                $bucket = $this['bucket'];
            } else throw $this->exception('Not clear what busket you want to check','Exception_Volume_WhichBucket');
        }
        if (!$this->bucketExist($bucket)) {
            $this->createNew($bucket);
        } else {
            $this->tryLoadBy('bucket',$bucket);
        }
        $file_list = $this->s3->getS3()->getBucket($this['bucket']);
        return (count($file_list) >= $this->max_quantity_per_volume);
    }
    function createNew($bucket=null,$throw_exception = true) {
        $create_new = false;
        if (is_null($bucket)) {
            $create_new = true;
            $bucket = uniqid($this->bucket_prefix);
        }
        if (!$this->s3->getS3()->putBucket($bucket)) {
            if ($throw_exception) {
                throw $this->exception('Cannot create bucket','Exception_Volume_CannotCreate');
            }
            return false;
        }
        if ($create_new) $this->unload();
        $this['bucket'] = $bucket;
        return $this->save();
    }
}
