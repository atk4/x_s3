<?php
/**
 * Created by JetBrains PhpStorm.
 * User: vadym
 * Date: 9/13/13
 * Time: 8:51 PM
 * To change this template use File | Settings | File Templates.
 */
namespace x_s3;
class Model_Image extends Model_File {
    public $default_thumb_width=140;
    public $default_thumb_height=140;
    public $entity_file='File';
    public $thumbnail_source=null;
    function init() {
        parent::init(); //$this->debug();
        $this->i = $this->join('x_s3_image.original_file_id');
        $this->i->hasOne('x_s3/'.$this->entity_file,'thumb_file_id')
            ->caption('Thumbnail');

        $this->addExpression('thumb_url')->set(array($this,'getThumbURLExpr'));
    }
    /* Produces expression which calculates full URL of image */
    function getThumbURLExpr($m,$q){
        $this->t = $this->i->join('x_s3_file.id','thumb_file_id');
        $this->tv = $this->t->leftJoin('x_s3_volume');
        $tb = $this->tv->addField('thumb_bucket','bucket');
        $tf = $this->t->addField('thumb_filename','filename');
        return $q->concat( 'http://', $tb, ".s3.amazonaws.com/", $tf );
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
        $thumb=$this->ref($field,'link');
        if(!$thumb->loaded()){
            $thumb['x_s3_volume_id']    = $this['x_s3_volume_id'];
            $thumb['original_filename'] = 'thumb_'.$this['original_filename'];
            $thumb['x_s3_type_id']      = $this['x_s3_type_id'];
        }

        if(class_exists('\Imagick',false)){
            $image = new \Imagick($this->thumbnail_source);
            $this->imagickCrop($image,$x,$y);
            $this->hook("beforeThumbSave", array($thumb));

            $tempfile=tempnam(sys_get_temp_dir(), 'thumb_');
            file_put_contents($tempfile,$image->getImageBlob());
            $thumb['filename'] = baseName($tempfile);

            $thumb->volume = $this->add('x_s3/Model_Volume')->load($thumb['x_s3_volume_id']);

            $this->s3->uploadFile($thumb->volume['bucket'],$tempfile);
        }
//        elseif(function_exists('imagecreatefromjpeg')) {
//            list($width, $height, $type) = getimagesize($this->getPath());
//            ini_set("memory_limit","1000M");
//
//
//            $a=array(null,'gif','jpeg','png');
//            $type=@$a[$type];
//            if(!$type)throw $this->exception('This file type is not supported');
//
//            //saving the image into memory (for manipulation with GD Library)
//            $fx="imagecreatefrom".$type;
//            $myImage = $fx($this->getPath());
//
//            $thumbSize = $x;    // only supports rectangles
//            if($x!=$y && 0)throw $this->exception('Model_Image currently does not support non-rectangle thumbnails with GD extension')
//                ->addMoreInfo('x',$x)
//                ->addMoreInfo('y',$y);
//
//            // calculating the part of the image to use for thumbnail
//            if ($width > $height) {
//                $y = 0;
//                $x = ($width - $height) / 2;
//                $smallestSide = $height;
//            } else {
//                $x = 0;
//                $y = ($height - $width) / 2;
//                $smallestSide = $width;
//            }
//
//            // copying the part into thumbnail
//            $myThumb = imagecreatetruecolor($thumbSize, $thumbSize);
//            imagecopyresampled($myThumb, $myImage, 0, 0, $x, $y, $thumbSize, $thumbSize, $smallestSide, $smallestSide);
//
//            //final output
//            imagejpeg($myThumb, $thumb->getPath());
//            imageDestroy($myThumb);
//            imageDestroy($myImage);
//            $thumb["filesize"] = filesize($thumb->getPath());
//        }
        else {
            // No Imagemagick support. Ignore resize
            $thumb->import($this->thumbnail_source,'copy');
        }
        $thumb->save();  // update size and chmod
    }
    function afterImport(){ exit('afterImport');
        // Called after original is imported. You can do your resizes here

        $f=$this->getPath();

        $gd_info=getimagesize($f);
    }
    function setMaxResize(){ exit('setMaxResize');
    }
    function beforeDelete(){ exit('beforeDelete.0');
        parent::beforeDelete();
        $this->ref('thumb_file_id')->tryDelete();
    }

}