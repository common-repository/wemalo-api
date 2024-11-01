<?php

use JetBrains\PhpStorm\NoReturn;

require_once __DIR__ . '/../api/WemaloAPIConnect.php';
require_once __DIR__ . '/../api/handler/WemaloPluginOrders.php';

/**
 * registers ajax events and handles requests
 * @author Patric Eid
 *
 */
class WemaloAPIRegisterAjaxOrder {
	
	/**
	 * registers ajax events
	 */
	public function registerEvents() {
		add_action('wp_ajax_wemalo_file_upload', array($this, 'fileUpload'));
		add_action('wp_ajax_wemalo_celeb_mail', array($this, 'sendCelebMail'));
		add_action('wp_ajax_wemalo_load_dispatchers', array($this, 'loadDispatcherProfile'));
	}
	
	/**
	 * loads available dispatcher profiles from wemalo
	 */
	#[NoReturn] public function loadDispatcherProfile() {
		$data = $this->getPostData();
		$response = array();
		$postId = $data['post_id'];
		$response['selectedDispatcher'] = get_post_meta($postId, '_wemalo_dispatcher', true);
		if ($response['selectedDispatcher'] == "") {
			$response['selectedDispatcher'] = "auto";
		}
		$response['selectedDispatcherProduct'] = get_post_meta($postId, '_wemalo_dispatcher_product', true);
		$handler = new WemaloPluginOrders();
		$profiles = $handler->getConnect()->getAvailableDispatcherProfiles();
		if (is_array($profiles)) {
			$response['response'] = "SUCCESS";
			$response['items'] = array();
			$subarray = array();
			$subarray[] = array("value" => "", "text" => "auto");
			$response['items'][] = array("value" => "auto", "text" => "auto", "subarray" => $subarray);
			foreach ($profiles as $dispatcher) {
				$item = array("value" => $dispatcher->name, "text" => $dispatcher->name);
				$subarray = array();
				foreach ($dispatcher->profiles as $p) {
					if ($p->matchId != "") {
						$subarray[] = array("value" => $p->matchId, "text" => $p->name);
					}
				}
				if (count($subarray) > 0) {
					$item['subarray'] = $subarray;
					$response['items'][] = $item;
				}
			}
		}
		else {
			$response['response'] = "ERROR";
			$response['error'] = $profiles;
		}
		echo json_encode($response);
		die();
	}
	
	/**
	 * sends a celebrity notification mail
	 */
	#[NoReturn] public function sendCelebMail() {
		$data = $this->getPostData();
		update_post_meta($data['post_id'], '_wemalo_celeb_active', 'yes');
		$response = array();
		$celebMail = get_option("_wemalo_order_celeb_email");
		$msg = $data['msg'];
		$message = "Promi-Versand für Auftrag ".$data['post_id']."\r\nPromi-Text:\r\n\r\n";
		$message .= $data['msg'];
		$message .= "\r\n\r\nGesendet von: ".WEMALO_BASE;
		if (wp_mail($celebMail, 'Promi-Versand für Auftrag '.$data['post_id'], $message)) {
			$response['response'] = "SUCCESS";
			$orderObj = wc_get_order($data['post_id']);
			$orderObj->add_order_note("Promi-Versand versendet an ".$celebMail, 0, true);
			//update wemalo order
			$connect = new WemaloAPIConnect();
			$connect->addOrderNote($data['post_id'], $data['msg']);
		}
		else {
			global $ts_mail_errors;
			global $phpmailer;
			if (!isset($ts_mail_errors)) {
				$ts_mail_errors = array();
			}
			if (isset($phpmailer)) {
				$ts_mail_errors[] = $phpmailer->ErrorInfo;
			}
			$response['error'] = 'E-Mail nicht versendet. ';
			if (count($ts_mail_errors) > 0) {
				$response['error'] .= var_export($ts_mail_errors, true);
			}
			else {
				$response['error'] .= "Bitte überprüfen Sie Ihre E-Mail-Einstellungen und Server-Logs.";
			}
			$response['response'] = "ERROR";
		}
		echo json_encode($response);
		die();
	}
	
	/**
	 * uploads a file to connect
	 */
	#[NoReturn] public function fileUpload() {
		$response = array();
		$data = $this->getPostData();
		if ($data['wemalo_attachment_type'] != "") {
			$response = $this->prepareFileUpload($data, 'wemalo_attachment_file',
				$data['wemalo_attachment_type'] != "return" ? ".pdf" : false);
			if ($response['response'] == "SUCCESS") {
				$handler = new WemaloPluginOrders();
				$orderObj = wc_get_order($data['post_id']);
				$ret = "";
				if ($data['wemalo_attachment_type'] == "return") {
					$ret = $handler->getConnect()->transmitReturnsDocument(
						$data['post_id'], 
						$response['name'], $response['base64'], $orderObj);
				}
				else {
					$ret = $handler->getConnect()->transmitOrderDocument($data['post_id'],
						$data['wemalo_attachment_type'],
						$response['name'],
						$response['base64'],
						$orderObj);
				}
				if ($ret != "") {
					//obviously, file was not uploaded to wemalo correctly
					$response['response'] = "ERROR";
					$response['error'] = $ret;
				}
			}
		}
		else {
			$response['error'] = "Attachment type was not selected.";
		}
		echo json_encode($response);
		die();
	}
	
	/**
	 * gets post data from ajax call
	 * @return array
	 */
	private function getPostData(): array
    {
		$posted_data = $_POST ?? array();
		$file_data = $_FILES ?? array();
		return array_merge($posted_data, $file_data);
	}
	
	/**
	 * checks whether the given haystack ends witht he given needle
	 * @param string $haystack
	 * @param string $needle
	 * @return boolean
	 */
	private function endsWith(string $haystack, string $needle): bool
    {
		$length = strlen($needle);
		if ($length == 0) {
			return true;
		}
		return (substr($haystack, -$length) === $needle);
	}
	
	/**
	 * reads uploaded file as base 64
	 * @param array $obj
	 * @param string $inputName
	 * @param string $checkFileExtension
	 * @return array
	 */
	private function prepareFileUpload(array  $obj, string $inputName = 'wemalo_attachment_file',
                                       string $checkFileExtension = '.pdf'): array
    {
		$data = array();
		$file = $obj[$inputName];
		if ($file != null) {
			if ($file['error'] == 0) {
				if (!$checkFileExtension || $this->endsWith(strtolower($file["name"]), strtolower($checkFileExtension))) {
					$fileName = pathinfo(basename($file["name"]), PATHINFO_FILENAME);
					if ($fileName == "") {
						$fileName = $file["name"];
					}
					$data['base64'] = base64_encode(fread(fopen($file["tmp_name"], "rb"), filesize($file["tmp_name"])));
					$data['filename'] = $fileName;
					$data['name'] = $file["name"];
					$data['tmpname'] = $file['tmp_name'];
					$data['response'] = "SUCCESS";
					return $data;
				}
				else {
					$data['error'] = $file["name"]." doesn't end with $checkFileExtension!";
				}
			}
			else {
				$fileErrors = array(
						0 => "There is no error, the file uploaded with success.",
						1 => "The uploaded file exceeds the upload_max_files in server settings.",
						2 => "The uploaded file exceeds the MAX_FILE_SIZE from html form.",
						3 => "The uploaded file uploaded only partially.",
						4 => "No file was uploaded.",
						6 => "Missing a temporary folder.",
						7 => "Failed to write file to disk.",
						8 => "A PHP extension stoped file to upload.");
				if (in_array($file['error'], $fileErrors)) {
					$data['error'] = $fileErrors[$file['error']];
				}
				else {
					$data['error'] = 'File not uploaded correctly. Please check your server settings.';
				}
			}
		}
		else {
			$data['error'] = 'No upload file available. Please check your server settings.';
		}
		$data['response'] = "ERROR";
		return $data;
	}
}