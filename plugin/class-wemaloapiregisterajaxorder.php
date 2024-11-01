<?php

require_once __DIR__ . '/../api/class-wemaloapiconnect.php';
require_once __DIR__ . '/../api/handler/class-wemalopluginorders.php';

/**
 * Registers ajax events and handles requests
 *
 * @author Patric Eid
 */
class WemaloAPIRegisterAjaxOrder {

	/**
	 * Registers ajax events
	 */
	public function register_events() {
		add_action( 'wp_ajax_wemalo_file_upload', array( $this, 'file_upload' ) );
		add_action( 'wp_ajax_wemalo_celeb_mail', array( $this, 'send_celeb_mail' ) );
		add_action( 'wp_ajax_wemalo_load_dispatchers', array( $this, 'load_dispatcher_profiles' ) );
	}

	/**
	 * Loads available dispatcher profiles from wemalo
	 *
	 * @return void
	 */
	public function load_dispatcher_profiles(): void {
		$data                           = $this->get_post_data();
		$response                       = [];
		$post_id                        = $data['post_id'];
		$response['selectedDispatcher'] = get_post_meta( $post_id, '_wemalo_dispatcher', true );
		if ( empty( $response['selectedDispatcher'] ) ) {
			$response['selectedDispatcher'] = 'auto';
		}
		$response['selectedDispatcherProduct'] = get_post_meta( $post_id, '_wemalo_dispatcher_product', true );
		$handler                               = new WemaloPluginOrders();
		$profiles                              = $handler->get_wemalo_api_connect()->get_available_dispatcher_profiles();
		if ( is_array( $profiles ) ) {
			$response['response'] = 'SUCCESS';
			$response['items']    = [];
			$subarray             = [];
			$subarray[]           = array(
				'value' => '',
				'text'  => 'auto',
			);
			$response['items'][]  = array(
				'value'    => 'auto',
				'text'     => 'auto',
				'subarray' => $subarray,
			);
			foreach ( $profiles as $dispatcher ) {
				$item     = array(
					'value' => $dispatcher->name,
					'text'  => $dispatcher->name,
				);
				$subarray = [];
				foreach ( $dispatcher->profiles as $p ) {
					if ( ! empty( $p->matchId ) ) {
						$subarray[] = array(
							'value' => $p->matchId,
							'text'  => $p->name,
						);
					}
				}
				if ( count( $subarray ) > 0 ) {
					$item['subarray']    = $subarray;
					$response['items'][] = $item;
				}
			}
		} else {
			$response['response'] = 'ERROR';
			$response['error']    = $profiles;
		}
		echo wp_json_encode( $response );
	}

	/**
	 * Sends a celebrity notification mail
	 */
	public function send_celeb_mail() {
		$data = $this->get_post_data();
		update_post_meta( $data['post_id'], '_wemalo_celeb_active', 'yes' );
		$response   = [];
		$celeb_mail = get_option( '_wemalo_order_celeb_email' );
		$msg        = $data['msg'];
		$message    = 'Promi-Versand für Auftrag ' . $data['post_id'] . "\r\nPromi-Text:\r\n\r\n";
		$message   .= $data['msg'];
		$message   .= "\r\n\r\nGesendet von: " . WEMALO_BASE;
		if ( wp_mail( $celeb_mail, 'Promi-Versand für Auftrag ' . $data['post_id'], $message ) ) {
			$response['response'] = 'SUCCESS';
			$order_obj            = wc_get_order( $data['post_id'] );
			$order_obj->add_order_note( 'Promi-Versand versendet an ' . $celeb_mail, 0, true );
			// update wemalo order.
			$connect = new WemaloAPIConnect();
			$connect->add_order_note( $data['post_id'], $data['msg'] );
		} else {
			global $ts_mail_errors;
			global $phpmailer;
			if ( ! isset( $ts_mail_errors ) ) {
				$ts_mail_errors = [];
			}
			if ( isset( $phpmailer ) ) {
				$ts_mail_errors[] = $phpmailer->ErrorInfo;
			}
			$response['error'] = 'E-Mail nicht versendet. ';
			if ( count( $ts_mail_errors ) > 0 ) {
				$response['error'] .= wp_json_encode( $ts_mail_errors );
			} else {
				$response['error'] .= 'Bitte überprüfen Sie Ihre E-Mail-Einstellungen und Server-Logs.';
			}
			$response['response'] = 'ERROR';
		}
		echo wp_json_encode( $response );
	}

	/**
	 * Pploads a file to connect
	 */
	public function file_upload() {
		$response = [];
		$data     = $this->get_post_data();
		if ( ! empty( $data['wemalo_attachment_type'] ) ) {
			$response = $this->prepare_file_upload(
				$data,
				'wemalo_attachment_file',
				'return' !== $data['wemalo_attachment_type'] ? '.pdf' : false
			);
			if ( 'SUCCESS' === $response['response'] ) {
				$handler   = new WemaloPluginOrders();
				$order_obj = wc_get_order( $data['post_id'] );
				if ( 'return' === $data['wemalo_attachment_type'] ) {
					$ret = $handler->get_wemalo_api_connect()->transmit_return_document(
						$data['post_id'],
						$response['name'],
						$response['base64'],
						$order_obj
					);
				} else {
					$ret = $handler->get_wemalo_api_connect()->transmit_order_document(
						$data['post_id'],
						$data['wemalo_attachment_type'],
						$response['name'],
						$response['base64'],
						$order_obj
					);
				}
				if ( ! empty( $ret ) ) {
					// obviously, file was not uploaded to wemalo correctly.
					$response['response'] = 'ERROR';
					$response['error']    = $ret;
				}
			}
		} else {
			$response['error'] = 'Attachment type was not selected.';
		}
		echo wp_json_encode( $response );
	}

	/**
	 * Gets post data from ajax call
	 *
	 * @return array
	 */
	private function get_post_data(): array {
		$posted_data = $_POST ?? array();
		$file_data   = $_FILES ?? array();
		return array_merge( $posted_data, $file_data );
	}

	/**
	 * Checks whether the given haystack ends witht he given needle
	 *
	 * @param string $haystack
	 * @param string $needle
	 * @return boolean
	 */
	private function ends_with( string $haystack, string $needle ): bool {
		$length = strlen( $needle );
		return 0 === $length || ( substr( $haystack, -$length ) === $needle );
	}

	/**
	 * Reads uploaded file as base 64
	 *
	 * @param array       $obj
	 * @param string      $input_name
	 * @param string|bool $check_file_extension
	 * @return array
	 */
	private function prepare_file_upload(
		array $obj,
		string $input_name = 'wemalo_attachment_file',
		string|bool $check_file_extension = '.pdf'
	): array {
		$data = [];
		$file = $obj[ $input_name ];
		if ( ! empty( $file ) ) {
			if ( empty( $file['error'] ) ) {
				if ( ! $check_file_extension || $this->ends_with( strtolower( $file['name'] ), strtolower( $check_file_extension ) ) ) {
					$file_name = pathinfo( basename( $file['name'] ), PATHINFO_FILENAME );
					$file_name = empty( $file_name ) ? $file['name'] : $file_name;

					$data['base64']   = base64_encode( fread( fopen( $file['tmp_name'], 'rb' ), filesize( $file['tmp_name'] ) ) );
					$data['filename'] = $file_name;
					$data['name']     = $file['name'];
					$data['tmpname']  = $file['tmp_name'];
					$data['response'] = 'SUCCESS';
					return $data;
				} else {
					$data['error'] = $file['name'] . " doesn't end with $check_file_extension!";
				}
			} else {
				$file_errors = array(
					0 => 'There is no error, the file uploaded with success.',
					1 => 'The uploaded file exceeds the upload_max_files in server settings.',
					2 => 'The uploaded file exceeds the MAX_FILE_SIZE from html form.',
					3 => 'The uploaded file uploaded only partially.',
					4 => 'No file was uploaded.',
					6 => 'Missing a temporary folder.',
					7 => 'Failed to write file to disk.',
					8 => 'A PHP extension stoped file to upload.',
				);
				if ( in_array( $file['error'], $file_errors, true ) ) {
					$data['error'] = $file_errors[ $file['error'] ];
				} else {
					$data['error'] = 'File not uploaded correctly. Please check your server settings.';
				}
			}
		} else {
			$data['error'] = 'No upload file available. Please check your server settings.';
		}
		$data['response'] = 'ERROR';
		return $data;
	}
}
