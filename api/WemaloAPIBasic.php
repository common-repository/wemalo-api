<?php

require_once __DIR__.'/WemaloAPIException.php';

/**
 * contains basic methods for working with rest api
 * @author Patric Eid
 *
 */
class WemaloAPIBasic {
	
	/**
	 * checks whether authkey was set in header and matches option
	 * @param WP_REST_Request $request
	 * @throws WemaloAPIException
	 */
	protected function checkAuth(WP_REST_Request $request) {
		$headers = $request->get_headers();
		if (array_key_exists("authkey", $headers)) {
			if ($headers['authkey'][0] != get_option('wemalo_plugin_auth_key')) {
				throw new WemaloAPIException("2: You don't have permissions accessing this site.", 500);
			}
		}
		else {
			throw new WemaloAPIException("2: You don't have permissions accessing this site.", 404);
		}
	}

    /**
     * generates json output
     * @param array|string|null $data
     * @param string $message
     * @param bool $success
     * @param int $code
     * @param string $token
     * @return WP_REST_Response
     */
	protected function generateOutput(array|string $data = null, string $message="", bool $success=true, int $code=200, string $token=""): WP_REST_Response
    {
		$result = array();
		if ($data != null) {
			$result['data'] = $data;
			if ($token != "") {
				$result['token'] = $token;
			}
			if (is_array($data) && array_key_exists("Error", $data)) {
				$success = false;
				if ($code == 200) {
					$code = 418;
				}
			}
		}
		if ($message != "") {
			$result['message'] = $message;
		}
		$result['success'] = $success;
		return new WP_REST_Response($result, $code);
	}
	
	/**
	 * handles error messages
	 * @param WemaloAPIException|Exception $e
	 * @return WP_REST_Response
	 */
	protected function handleError(WemaloAPIException|Exception $e): WP_REST_Response
    {
		$code = 500;
		if ($e instanceof WemaloAPIException) {
			$code = $e->getReturnCode();
		}
		return $this->generateOutput(null, $e->getMessage(), false, $code);
	}
}