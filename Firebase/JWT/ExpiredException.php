<?php
namespace Firebase\JWT;

class ExpiredException extends \UnexpectedValueException implements JWTExceptionWithPayloadInterface {
	/**
	 * Payload
	 *
	 * @var payload
	 */
	private $payload;

	/**
	 * Set Payload
	 *
	 * @param object $payload
	 */
	public function setPayload( $payload ): void {
		$this->payload = $payload;
	}

	/**
	 * Get Payload
	 *
	 * @return payload|object
	 */
	public function getPayload(): object {
		return $this->payload;
	}
}
