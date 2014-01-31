<?php
/**
 * Created by JetBrains PhpStorm.
 * User: vadym
 * Date: 9/14/13
 * Time: 1:02 PM
 * To change this template use File | Settings | File Templates.
 */
namespace atk4\x_s3;
class Model_Type extends \SQL_Model {
    public $s3;
	public $table='x_s3_type';
	function init(){
		parent::init();
        $this->s3 = $this->add('atk4\x_s3/Controller_S3');

		$this->addField('name')
            ;
		$this->addField('mime_type')
            ;
		$this->addField('extension')
            ;
		// TODO: extension should be substituted when recording filename
	}
}
