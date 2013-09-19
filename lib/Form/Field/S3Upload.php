<?php
/**
 * Created by JetBrains PhpStorm.
 * User: vadym
 * Date: 9/19/13
 * Time: 3:30 PM
 * To change this template use File | Settings | File Templates.
 */
namespace x_s3;
class Form_Field_S3Upload extends \Form_Field_Upload {
    function getVolumeIDFieldName() {
        return 'x_s3_volume_id';
    }
    function getTypeIDFieldName() {
        return 'x_s3_type_id';
    }
}