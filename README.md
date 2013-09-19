x_s3
====

Amazon S3 addon for atk4



## Usage

#### Basic classes

	class S3Form extends Form {
		function init() {
			parent::init();
			$this->setModel('S3');
		}
	}

	class Model_S3 extends Model_Table {
		public $table = 's3';
		function init() {
			parent::init();
			$this->add('x_s3\Field_Image');
		}
	}

#### In View


	$f = $this->add('S3Form');
	$this->add('CRUD')->setModel('x_s3/Model_Image');