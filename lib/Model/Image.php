<?php
/**
 * Created by JetBrains PhpStorm.
 * User: vadym
 * Date: 9/13/13
 * Time: 8:51 PM
 * To change this template use File | Settings | File Templates.
 */
namespace atk4\x_s3;
class Model_Image extends Model_File {
    public $default_thumb_width=140;
    public $default_thumb_height=140;
    public $entity_file ='atk4\x_s3\File';
    public $entity_file_model ='atk4\x_s3\Model_File';
    public $thumbnail_source=null;
    function init() {
        parent::init(); //$this->debug();
        $this->i = $this->join('x_s3_image.original_file_id');
        $this->i->hasOne($this->entity_file,'thumb_file_id')
            ->caption('Thumbnail');

        $this->addExpression('thumb_url')->set(array($this,'getThumbURLExpr'));
    }
    /* Produces expression which calculates full URL of image */
    function getThumbURLExpr($m,$q){
        $t = $this->add($this->entity_file_model);
        $t->addCondition('id',$this->i->fieldExpr('thumb_file_id'));
        return $q->concat( 'http://', $t->fieldQuery('bucket'), ".s3.amazonaws.com/", $t->fieldQuery('filename') );

//        $this->t = $this->i->join('x_s3_file.id','thumb_file_id');
//        $this->tv = $this->t->leftJoin('x_s3_volume');
//        $tb = $this->tv->addField('thumb_bucket','bucket');
//        $tf = $this->t->addField('thumb_filename','filename');
//        return $q->concat( 'http://', $tb, ".s3.amazonaws.com/", $tf );
    }
    function toStringSQL($source_field, $dest_fieldname){ exit('toStringSQL');
        return $source_field.' '.$dest_fieldname;
    }
    function performImport(){
        $this->thumbnail_source = $this->import_source;
        parent::performImport();
        $this->createThumbnails();
        $this->thumbnail_source = null;
        return $this;
    }
    function createThumbnails(){
        if($this->id)$this->load($this->id);// temporary
        $this->createThumbnail('thumb_file_id',$this->default_thumb_width,$this->default_thumb_height);
    }
    function imagickCrop($i,$width,$height){
        $geo = $i->getImageGeometry();

        if($geo['width']<$width && $geo['height']<$height)return; // don't crop, image is too small

        // crop the image
        if(($geo['width']/$width) < ($geo['height']/$height)) {
            $i->cropImage($geo['width'], floor($height*$geo['width']/$width), 0, (($geo['height']-($height*$geo['width']/$width))/2));
        } else {
            $i->cropImage(ceil($width*$geo['height']/$height), $geo['height'], (($geo['width']-($width*$geo['height']/$height))/2), 0);
        }
        // thumbnail the image
        $i->ThumbnailImage($width,$height,true);
    }
    function createThumbnail($field,$x,$y){
        // Create entry for thumbnail.
        $thumb = $this->ref($field,'link');
        if(!$thumb->loaded()){
            $thumb['x_s3_volume_id']    = $this['x_s3_volume_id'];
            $thumb['original_filename'] = 'thumb_'.$this['original_filename'];
            $thumb['x_s3_type_id']      = $this['x_s3_type_id'];
        }

        if (class_exists('\Imagick',false)){
            $image = new \Imagick($this->thumbnail_source);
            $this->imagickCrop($image,$x,$y);
            $this->hook("beforeThumbSave", array($thumb));

            $tempfile = tempnam(sys_get_temp_dir(), 'thumb_');
            file_put_contents($tempfile,$image->getImageBlob());
            $thumb['filename'] = baseName($tempfile);

            $thumb->volume = $this->add('atk4\x_s3/Model_Volume')->load($thumb['x_s3_volume_id']);
            $this->s3->uploadFile($thumb->volume['bucket'],$tempfile);
        } else if (function_exists('imagecreatefromjpeg')){
            list($width, $height, $type) = getimagesize($this->thumbnail_source);
            ini_set("memory_limit","1000M");

            $a=array(null,'gif','jpeg','png');
            $type=@$a[$type];
            if(!$type)throw $this->exception('This file type is not supported');

            //saving the image into memory (for manipulation with GD Library)
            $fx="imagecreatefrom".$type;
            $myImage = $fx($this->thumbnail_source);

            $geo = $this->getGeo($x,$y,$width, $height);

            $myThumb = imagecreatetruecolor($geo['width'], $geo['height']);
            imagecopyresampled($myThumb, $myImage, 0, 0, 0, 0, $geo['width'], $geo['height'],$width, $height);

            //final output
            $tempfile = tempnam(sys_get_temp_dir(), 'thumb_');
            $thumb['filename'] = baseName($tempfile);
            imagejpeg($myThumb, $tempfile);

            $thumb->volume = $this->add('atk4\x_s3/Model_Volume')->load($thumb['x_s3_volume_id']);
            $this->s3->uploadFile($thumb->volume['bucket'],$tempfile);

            imageDestroy($myThumb);
            imageDestroy($myImage);
            //$thumb["filesize"] = filesize($thumb->getPath());
        } else {
            // No Imagemagick support. Ignore resize
            $thumb->import($this->thumbnail_source,'copy');
        }
        $thumb->save();  // update size and chmod
    }
    function getGeo($width,$height,$orig_width, $orig_height){
        $new_geo=array('width'=>$width,'height'=>$height);

        $geo = array(
            'height'=> $orig_height,
            'width' => $orig_width,
        );

        if($geo['width']<$width && $geo['height']<$height)return $new_geo; // image is too small

        if(($geo['width']/$width) > ($geo['height']/$height)) {
            $new_geo=array(
                'width'=>$width,
                'height'=>ceil($geo['height']*$width/$geo['width'])
            );
        } else {
            $new_geo=array(
                'width'=>ceil($geo['width']*$height/$geo['height']),
                'height'=>$height
            );
        }
        return $new_geo;
    }
    function afterImport(){ exit('afterImport');
        // Called after original is imported. You can do your resizes here

        $f=$this->getPath();

        $gd_info=getimagesize($f);
    }
    function setMaxResize(){ exit('setMaxResize');
    }
    function beforeDelete(){ //exit('beforeDelete.0');
        parent::beforeDelete();
        $this->ref('thumb_file_id')->tryDelete();
    }

}