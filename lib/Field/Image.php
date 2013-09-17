<?php
/**
 * Created by JetBrains PhpStorm.
 * User: vadym
 * Date: 9/14/13
 * Time: 12:12 PM
 */
namespace x_s3;
class Field_Image extends Field_File {
    public $use_model = 'x_s3/Image';


    /* Adds a calculated field for displaying a thumbnail of this image */
    function addThumb($name=null,$thumb='thumb_url'){

//        if(!$name)$name=$this->getDereferenced().'_thumb';
//
//        $self=$this;
//        $this->owner->addExpression($name)->set(function($m)use($self,$thumb){
//            return $m->refSQL($self->short_name)->fieldQuery($thumb);
//        });
        return $this;
    }
}
