<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class EnrollmentForm extends ASPA_Controller 
{

	function __construct() {
		parent::__construct();
		// $this->load->helper();
		// $this->load->model();
	}

	public function index()
	{
		$this->load->view('EnrollmentForm');
	}

	public function sheetsapi_test()
	{
        $this->load->model('Gsheet_Interface_Model');

        // Test out functions here

    }
    
    public function makeStripePayment() {
        
        // $this->load->('stripe');

    }

    public function LoadPaymentSucessful() {
        
        $data['session_id'] = $this->input->get('session_id');
        $this->load->view('redir.php',$data);
        
    }
}

/* End of file EnrollmentForm.php */
/* Location: ./application/controllers/EnrollmentForm.php */