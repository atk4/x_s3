<?php
/**
 * Created by JetBrains PhpStorm.
 * User: vadym
 * Date: 9/14/13
 * Time: 12:11 PM
 */
namespace atk4\x_s3;
class Field_File extends \Field_Reference {
    public $s3;
    public $use_model = 'atk4\x_s3/File';
    function init(){
        parent::init();
        $this->s3 = $this->add('atk4\x_s3/Controller_S3');

        $this->setModel($this->use_model,'url');
        $this->display(array('form'=>'atk4\x_s3\S3Upload'));
    }
    function displaytype($x){return $this;}
    function getModel(){
        if(!$this->model)$this->model=$this->add($this->model_name);
        return $this->model;
    }
}