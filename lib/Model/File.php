<?php
/**
 * Created by JetBrains PhpStorm.
 * User: vadym
 * Date: 9/13/13
 * Time: 8:39 PM
 * To change this template use File | Settings | File Templates.
 */
namespace x_s3;
class Model_File extends \SQL_Model {
    public $s3 = null;
    public $volume = null;

    public $table = 'x_s3_file';

    public $entity_filestore_type = 'x_s3\Type';
    public $entity_filestore_volume = 'x_s3\Volume';

    public $magic_file = null; // path to magic database file used in finfo-open(), null = default
    public $import_mode = null;
    public $import_source = null;

    public $policy_add_new_type=false; // set this to true, will allow to upload all file types
    function init() {
        parent::init(); //$this->debug();
        $this->s3 = $this->add('x_s3/Controller_S3');

        $this->hasOne($this->entity_filestore_type,'x_s3_type_id',false)
            ->caption('File Type')
            ->mandatory(true)
        ;


        $this->hasOne($this->entity_filestore_volume,'x_s3_volume_id',false)
            ->caption('Volume')
            ->mandatory(true)
            ;
        $this->addField('original_filename')
            ->type('text')
            ->caption('Original Name')
        ;
//        $this->addField('bucket')
//            ->type('string')
//            ->caption('Bucket')
//        ;
        $this->addField('filename')
            ->type('string')
            ->caption('Internal Name')
        ;
//        $this->addField('url')
//            ->type('string')
//            ->caption('Internal Name')
//        ;
        $this->addField('filesize')
            ->type('int')
            ->defaultValue(0)
        ;
        $this->addField('is_deleted')
            ->type('boolean')
            ->defaultValue(false)
        ;

        $this->vol=$this->leftJoin('x_s3_volume');
        $this->vol->addField('bucket');

        $this->addExpression('url')->set(array($this,'getURLExpr'));

        $this->addHook('beforeSave',$this);
        $this->addHook('beforeDelete',$this);
    }
    /* Produces expression which calculates full URL of image */
    function getURLExpr($m,$q){
        return $q->concat(
            'http://',
            $m->getElement('bucket'),
            ".s3.amazonaws.com/",
            $m->getElement('filename')
        );
    }
    function beforeSave($m){
        if(!$this->loaded()){
            // New record, generate the name
            $this->set('x_s3_volume_id',$x=$this->getAvailableVolumeID());
            $this->set('filename',$this->generateFilename());
        }
        if($this->import_mode){
            $this->performImport();
        }
    }
    function getFileUrl() {
        return $this->s3->getFileUrl($this['bucket'],$this['filename']);
    }
    function getAvailableVolume() {
        if (is_object($this->volume)) return $this->volume;
        $volume = $this->add('x_s3/Model_Volume')
                ->addCondition('enabled',true)
                ->setOrder('id',true)
                ->tryLoadAny();

        if (!$volume->loaded() || $volume->isFull()) {
            $volume = $volume->createNew();
        }
        $this->volume = $volume;
        return $volume;
    }
    function getAvailableVolumeID(){
        return $this->getAvailableVolume()->get('id');
    }
    function getFiletypeID($mime_type = null, $add = false){
        if($mime_type == null) {
            if ($this->import_source) {
                if(!function_exists('finfo_open')) throw $this->exception('You have to enable php_fileinfo extension of PHP.');
                $finfo = finfo_open(FILEINFO_MIME_TYPE, $this->magic_file);
                if($finfo===false)
                    throw $this->exception("Can't find magic_file in finfo_open().")
                            ->addMoreInfo('Magic_file: ',isnull($this->magic_file)?'default':$this->magic_file);
                $mime_type = finfo_file($finfo, $this->import_source);
                finfo_close($finfo);
                $this['mime_type'] = $mime_type;
            } else if ($this['mime_type']) {
                $mime_type = $this['mime_type'];
            } else {
                throw $this->exception('Load file entry from filestore or import');
            }
        }
        $c=$this->ref("x_s3_type_id");
        $data = $c->getBy('mime_type',$mime_type);
        if(!$data['id']){
            if ($add){
                $c->update(array("mime_type" => $mime_type, "name" => $mime_type));
                $data = $c->get();
            } else {
                throw $this->exception(
                    sprintf(
                        $this->api->_('This file type is not allowed for upload (%s)'),
                        $mime_type
                    ),'Exception_ForUser')
                    ->addMoreInfo('type',$mime_type);
            }
        }
        return $data['id'];
    }
    function getFilename() {
        if (!$this['bucket'] || !$this['filename']) {
            return false;
        }
        return $this->s3->getFileUrl($this['bucket'],$this['filename']);
    }
    function generateFilename(){
        $this->hook("beforeGenerateFilename");
        if ($this['filename']){
            return $this['filename'];
        }
        return $this->convertName(baseName($this->import_source));
    }

    /** Remove special characters in filename, replace spaces with -, trim and set all characters to lowercase */
    function convertName($str){
        $clean = iconv('UTF-8', 'ASCII//TRANSLIT', $str);
        $clean = preg_replace("/[^a-zA-Z0-9.\/_|+ -]/", '', $clean);
        $clean = /*strtolower(*/trim($clean, '-')/*)*/;
        $clean = preg_replace("/[\/_|+ -]+/", '-', $clean);
        return $clean;
    }

    function import($source,$mode='upload'){
        /*
           Import file from different location.

           $mode can be
            - upload - for moving uploaded files. (additional validations apply)
            - move - original file will be removed
            - copy - original file will be kept
            - string - data is passed inside $source and is not an existant file
         */
        $this->import_source=$source;
        $this->import_mode=$mode;

        if($this->loaded() && $this->id){// -- if we have this, then we
            // can import right now

            // If file is already in database - put it into store
            $this->performImport();
            $this->save();
        }
        return $this;
    }

    function getPath(){
        $path = $this->s3->getFileUrl($this->ref("x_s3_volume_id")->get('bucket'),$this['filename']);
        return $path;
    }
    function getMimeType(){exit('getMimeType');
        return $this->ref('x_s3_type_id')
            ->get('mime_type');
    }
    function performImport(){
        /*
           After our filename is determined - performs the operation
         */
        switch($this->import_mode){
        case'upload':
        case'copy':
            if (!$this->volume) $this->getAvailableVolume();
            $this->s3->uploadFile($this->volume['bucket'],$this->import_source);
            $this['x_s3_volume_id'] = $this->volume->id;
            break;
        case'move':
            throw $this->exception('Import mode '.$this->import_mode.' is not supported yet','Exception_File_NotSupportedImportMode');
            break;
        case'string':
            throw $this->exception('Import mode '.$this->import_mode.' is not supported yet','Exception_File_NotSupportedImportMode');
            break;
        case'none': // file is already in place
            break;
        default:
            throw $this->exception('Import mode '.$this->import_mode.' is not supported yet','Exception_File_NotSupportedImportMode');
        }
        //$this->set('filesize',$f=filesize($destination));
        $this->set('deleted',false);
        $this->set('x_s3_type_id',$this->getFiletypeID(null,$this->policy_add_new_type));
        $this->import_source=null;
        $this->import_mode=null;
        return $this;
    }
    function beforeDelete(){
        $this->s3->deleteFile($this['bucket'],$this['filename']);
    }

}