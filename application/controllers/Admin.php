<?php
defined('BASEPATH') OR exit('No direct script access allowed');
require ('vendor/autoload.php');
use \Firebase\JWT\JWT;

/**
 * Handles all admin-checkup app related endpoints and views.
 *
 * @property GoogleSheets_Model $GoogleSheets_Model
 * @property Verification_Model $Verification_Model
 * @property CI_Input $input
 * @property CI_Output $output

 */
class Admin extends ASPA_Controller
{
    //constant for cookie name used in authenticate() and checkCookie()
    const AUTH_COOKIE_NAME = "aspa_admin_authentication";

    /**
     * Marks the attendee as paid by highlighting their row.
     * It checks if either the email or upi is found in the spreadsheet.
     * If either is found, it highlights the specified row.
     */
    public function markAsPaid() {
        $this->load->model('GoogleSheets_Model');

        // ONE OF THEM IS REQUIRED, EITHER.
        // get the members email and upi
        $email = $this->input->get('email');
        $upi = $this->input->get('upi');

        // If email and UPI both don't exist, return 412 to signify query params are not correct
        if (!$email && !$upi) {
            $this->output->set_status_header(412, "Queries not specified")->_display("412: Precondition failed");
            return;
        }

        // Get the cell with priority on email, and then UPI - if both are not found, then $cell is null
        $cell = $email ? $this->GoogleSheets_Model->getCellCoordinate($email, 'B')
            : $this->GoogleSheets_Model->getCellCoordinate($upi, 'E');

        if (!$cell) {
            $this->output->set_status_header(404, "error")->_display("404: Attendee not found");
            return;
        }

        // Split up the cell column and row
        list(, $row) = $this->GoogleSheets_Model->convertCoordinateToArray($cell);

        // Check if the cell is coloured, if not highlight the cell with pink :)
        $cellColour = $this->GoogleSheets_Model->getCellColour($cell);
        if ($cellColour == '000000' || $cellColour == 'ffffff') {
            // Highlight this row since it is paid, placed inside this code block to prevent unnecessary calls
            $this->GoogleSheets_Model->highlightRow($row ,[0.968, 0.670, 0.886]);

            // Return HTTP status code 200, to signify that it has successfully marked attendee as paid
            $this->output->set_status_header(200)->_display("200: Successfully, marked attendee as paid");
        }
    }

    /**
     * Checks an input key against a key stored in a file. If it matches, store a cookie on the users browser.
     */
    public function authenticate() {

        // Gets a key from the URL from the form admin/authenticate?key=xyz
        $urlKey = $this->input->get('key');
        
        // Check if it matches a key we have stored in auth_props.json
        if ($urlKey != ADMIN_AUTH_PASSKEY) {
            echo("Key is incorrect");
            return false;
        }

        $payload = array(
            "key" => $urlKey,
            "iat" => microtime(),
        );

        $jwt = JWT::encode($payload, ADMIN_AUTH_JWTKEY);

        setcookie(self::AUTH_COOKIE_NAME, $jwt); 
        
        echo 'Cookie set';
        return true;
    }


    /**
     * Check if a user has a specific cookie, and if they do, allow them to do something
     */
    public function checkCookie() {

        if(!isset($_COOKIE[self::AUTH_COOKIE_NAME])) {
            echo 'Doesn\'t exist';
            return false;
        }

        $jwt = $_COOKIE[self::AUTH_COOKIE_NAME];

        try {
            $decoded = JWT::decode($jwt, ADMIN_AUTH_JWTKEY, array('HS256'));
        } catch (Exception $e) {
            echo 'Caught exception: ',  $e->getMessage(), "\n";
            return false;
        }

        if ($decoded->key == ADMIN_AUTH_PASSKEY) {
            echo 'Exists, and matches';
            return true;
        }
        else
        {
            echo 'Exists, but doesn\'t match';
            return false;
        }
    }

     /**
     * Checks the current payment status of the user with
     * their email or UPI through [GET].
     */
    public function paymentStatus() {
        $this->load->model('GoogleSheets_Model');

        // ONE OF THEM IS REQUIRED, EITHER.
        // get the members email and upi
        $email = $this->input->get('email');
        $upi = $this->input->get('upi');

        // If email and UPI both don't exist, return 412 to signify query params are not correct
        if (!$email && !$upi) {
            $this->output->set_status_header(412, "Queries not specified")
                    ->_display("412: Precondition failed");
            return;
        }

        // Get the cell with priority on email, and then UPI - if both are not found, then $cell is null
        $cell = $email ? $this->GoogleSheets_Model->getCellCoordinate($email, 'B')
            : $this->GoogleSheets_Model->getCellCoordinate($upi, 'E');

        if (!$cell) {
            $this->output->set_status_header(404, "error")
                    ->_display("404: Attendee not found");
            return;
        }

        $this->load->model("Verification_Model");

        $hasUserPaid = $this->Verification_Model->hasUserPaidEvent($email, $this->eventData["gsheet_name"]);

        //Get attendance cell value
        $attendanceRowValue = $cell[1];
        $attendanceCellId = 'G' . $attendanceRowValue;
        $attendance = $this->GoogleSheets_Model->getCellContents($attendanceCellId, $attendanceCellId)[0][0];

        /**
         * 200 – OK, paymentMade = true` if `green` and `attendance=false` from the registration sheet
         * (this means the attendee has paid)
         */
        if ($hasUserPaid && $attendance != 'P') {
            $this->output->set_status_header(200)
                    ->set_output(json_encode(array('paymentMade' => true)));
            return;
        }

        /**
         * 409 CONFLICT` if `green` and `attendance=true`, this means there is a duplicate email used
         */
        if ($hasUserPaid && $attendance == 'P') {
            $this->output->set_status_header(409)
                    ->_display("409: Duplicate email used");
        }

        /**
         * 200 - OK, paymentMade = false` if `uncoloured` and `attendance=false` from the registration sheet
         * (this means the user has not paid)
         */
        if (!$hasUserPaid) {
            $this->output->set_status_header(200)
                    ->set_output(json_encode(array('paymentMade' => false)));
        }

    }

}
