<?php

namespace Firebase\JWT;

use ArrayAccess;
use DateTime;
use DomainException;
use Exception;
use InvalidArgumentException;
use OpenSSLAsymmetricKey;
use OpenSSLCertificate;
use stdClass;
use UnexpectedValueException;
use function array_merge;
use function base64_decode;
use function base64_encode;
use function chr;
use function count;
use function gmdate;
use function explode;
use function function_exists;
use function hash_equals;
use function hash_hmac;
use function implode;
use function in_array;
use function is_array;
use function is_null;
use function is_string;
use function json_decode;
use function json_last_error;
use function ltrim;
use function mb_strlen;
use function min;
use function openssl_error_string;
use function openssl_sign;
use function openssl_verify;
use function ord;
use function str_pad;
use function str_repeat;
use function str_replace;
use function str_split;
use function strlen;
use function strtr;
use function substr;
use function time;
use const JSON_UNESCAPED_SLASHES;

/**
 * Class JWT
 *
 * @package Firebase\JWT
 */
class JWT {


	private const ASN1_INTEGER    = 0x02;
	private const ASN1_SEQUENCE   = 0x10;
	private const ASN1_BIT_STRING = 0x03;

	/**
	 * When checking nbf, iat or expiration times,
	 * we want to provide some extra leeway time to
	 * account for clock skew.
	 *
	 * @var int
	 */
	public static $leeway = 0;

	/**
	 * Allow the current timestamp to be specified.
	 * Useful for fixing a value within unit testing.
	 * Will default to PHP time() value if null.
	 *
	 * @var ?int
	 */
	public static $timestamp = null;

	/**
	 * Supported_algs
	 *
	 * @var array<string, string[]>
	 */
	public static $supported_algs = array(
		'ES384'  => array( 'openssl', 'SHA384' ),
		'ES256'  => array( 'openssl', 'SHA256' ),
		'ES256K' => array( 'openssl', 'SHA256' ),
		'HS256'  => array( 'hash_hmac', 'SHA256' ),
		'HS384'  => array( 'hash_hmac', 'SHA384' ),
		'HS512'  => array( 'hash_hmac', 'SHA512' ),
		'RS256'  => array( 'openssl', 'SHA256' ),
		'RS384'  => array( 'openssl', 'SHA384' ),
		'RS512'  => array( 'openssl', 'SHA512' ),
		'EdDSA'  => array( 'sodium_crypto', 'EdDSA' ),
	);

	/**
	 * Decodes a JWT string into a PHP object.
	 *
	 * @param string                                        $jwt The JWT
	 * @param Key|ArrayAccess<string,Key>|array<string,Key> $keyOrKeyArray The Key or associative array of key IDs
	 *                                                                      (kid) to Key objects.
	 *                                                                      If the algorithm used is asymmetric, this is
	 *                                                                      the public key.
	 *                                                                      Each Key object contains an algorithm and
	 *                                                                      matching key.
	 *                                                                      Supported algorithms are 'ES384','ES256',
	 *                                                                      'HS256', 'HS384', 'HS512', 'RS256', 'RS384'
	 *                                                                      and 'RS512'.
	 * @param stdClass                                      $headers Optional. Populates stdClass with headers.
	 *
	 * @return stdClass The JWT's payload as a PHP object
	 *
	 * @throws InvalidArgumentException     Provided key/key-array was empty or malformed
	 * @throws DomainException              Provided JWT is malformed
	 * @throws UnexpectedValueException     Provided JWT was invalid
	 * @throws SignatureInvalidException    Provided JWT was invalid because the signature verification failed
	 * @throws BeforeValidException         Provided JWT is trying to be used before it's eligible as defined by 'nbf'
	 * @throws BeforeValidException         Provided JWT is trying to be used before it's been created as defined by 'iat'
	 * @throws ExpiredException             Provided JWT has since expired, as defined by the 'exp' claim
	 *
	 * @uses jsonDecode
	 * @uses urlsafeB64Decode
	 */
	public static function decode(
		string $jwt,
		$keyOrKeyArray,
		stdClass &$headers = null
	): stdClass {
		// Validate JWT
		$timestamp = is_null( static::$timestamp ) ? time() : static::$timestamp;

		if ( empty( $keyOrKeyArray ) ) {
			throw new InvalidArgumentException( 'Key may not be empty' );
		}
		$tks = explode( '.', $jwt );
		if ( count( $tks ) !== 3 ) {
			throw new UnexpectedValueException( 'Wrong number of segments' );
		}
		list($headb64, $bodyb64, $cryptob64) = $tks;
		$headerRaw                           = static::urlsafeB64Decode( $headb64 );
		$header                              = static::jsonDecode( $headerRaw );
		if ( null === ( $header ) ) {
			throw new UnexpectedValueException( 'Invalid header encoding' );
		}
		if ( null !== $headers ) {
			$headers = $header;
		}
		$payloadRaw = static::urlsafeB64Decode( $bodyb64 );
		$payload    = static::jsonDecode( $payloadRaw );
		if ( null === ( $payload ) ) {
			throw new UnexpectedValueException( 'Invalid claims encoding' );
		}
		if ( is_array( $payload ) ) {
			// prevent PHP Fatal Error in edge-cases when payload is empty array
			$payload = (object) $payload;
		}
		if ( ! $payload instanceof stdClass ) {
			throw new UnexpectedValueException( 'Payload must be a JSON object' );
		}
		$sig = static::urlsafeB64Decode( $cryptob64 );
		if ( empty( $header->alg ) ) {
			throw new UnexpectedValueException( 'Empty algorithm' );
		}
		if ( empty( static::$supported_algs[ $header->alg ] ) ) {
			throw new UnexpectedValueException( 'Algorithm not supported' );
		}

		$key = self::getKey( $keyOrKeyArray, property_exists( $header, 'kid' ) ? $header->kid : null );

		// Check the algorithm
		if ( ! self::constantTimeEquals( $key->getAlgorithm(), $header->alg ) ) {
			// See issue #351
			throw new UnexpectedValueException( 'Incorrect key for this algorithm' );
		}
		if ( in_array( $header->alg, array( 'ES256', 'ES256K', 'ES384' ), true ) ) {
			// OpenSSL expects an ASN.1 DER sequence for ES256/ES256K/ES384 signatures
			$sig = self::signatureToDER( $sig );
		}
		if ( ! self::verify( "{$headb64}.{$bodyb64}", $sig, $key->getKeyMaterial(), $header->alg ) ) {
			throw new SignatureInvalidException( 'Signature verification failed' );
		}

		// Check the nbf if it is defined. This is the time that the
		// token can actually be used. If it's not yet that time, abort.
		if ( isset( $payload->nbf ) && floor( $payload->nbf ) > ( $timestamp + static::$leeway ) ) {
			$ex = new BeforeValidException(
				'Cannot handle token with nbf prior to ' . gmdate( DateTime::ISO8601, (int) $payload->nbf )
			);
			$ex->setPayload( $payload );
			throw $ex;
		}

		// Check that this token has been created before 'now'. This prevents
		// using tokens that have been created for later use (and haven't
		// correctly used the nbf claim).
		if ( ! isset( $payload->nbf ) && isset( $payload->iat ) && floor( $payload->iat ) > ( $timestamp + static::$leeway ) ) {
			$ex = new BeforeValidException(
				'Cannot handle token with iat prior to ' . gmdate( DateTime::ISO8601, (int) $payload->iat )
			);
			$ex->setPayload( $payload );
			throw $ex;
		}

		// Check if this token has expired.
		if ( isset( $payload->exp ) && ( $timestamp - static::$leeway ) >= $payload->exp ) {
			$ex = new ExpiredException( 'Expired token' );
			$ex->setPayload( $payload );
			throw $ex;
		}

		return $payload;
	}

	/**
	 * Decode a string with URL-safe Base64.
	 *
	 * @param string $input A Base64 encoded string
	 *
	 * @return string A decoded string
	 *
	 * @throws InvalidArgumentException invalid base64 characters
	 */
	public static function urlsafeB64Decode( string $input ): string {
		return base64_decode( self::convertBase64UrlToBase64( $input ) );
	}

	/**
	 * Convert a string in the base64url (URL-safe Base64) encoding to standard base64.
	 *
	 * @param string $input A Base64 encoded string with URL-safe characters (-_ and no padding)
	 *
	 * @return string A Base64 encoded string with standard characters (+/) and padding (=), when
	 * needed.
	 *
	 * @see https://www.rfc-editor.org/rfc/rfc4648
	 */
	public static function convertBase64UrlToBase64( string $input ): string {
		$remainder = strlen( $input ) % 4;
		if ( $remainder ) {
			$padlen = 4 - $remainder;
			$input .= str_repeat( '=', $padlen );
		}
		return strtr( $input, '-_', '+/' );
	}

	/**
	 * Decode a JSON string into a PHP object.
	 *
	 * @param string $input JSON string
	 *
	 * @return mixed The decoded JSON string
	 *
	 * @throws DomainException Provided string was invalid JSON
	 */
	public static function jsonDecode( string $input ) {
		$obj   = json_decode( $input, false, 512, JSON_BIGINT_AS_STRING );
		$errno = json_last_error();

		if ( $errno ) {
			self::handleJsonError( $errno );
		} elseif ( null === $obj && 'null' !== $input ) {
			throw new DomainException( 'Null result with non-null input' );
		}
		return $obj;
	}

	/**
	 * Helper method to create a JSON error.
	 *
	 * @param int $errno An error number from json_last_error()
	 *
	 * @return void
	 * @throws DomainException
	 */
	private static function handleJsonError( int $errno ): void {
		$messages = array(
			JSON_ERROR_DEPTH          => 'Maximum stack depth exceeded',
			JSON_ERROR_STATE_MISMATCH => 'Invalid or malformed JSON',
			JSON_ERROR_CTRL_CHAR      => 'Unexpected control character found',
			JSON_ERROR_SYNTAX         => 'Syntax error, malformed JSON',
			JSON_ERROR_UTF8           => 'Malformed UTF-8 characters', // PHP >= 5.3.3
		);
		throw new DomainException(
			isset( $messages[ $errno ] )
				? wp_kses($messages[ $errno ])
				: 'Unknown JSON error: ' . wp_kses($errno)
		);
	}

	/**
	 * Determine if an algorithm has been provided for each Key
	 *
	 * @param Key|ArrayAccess<string,Key>|array<string,Key> $keyOrKeyArray
	 * @param string|null                                   $kid
	 *
	 * @return Key
	 * @throws UnexpectedValueException
	 */
	private static function getKey(
		$keyOrKeyArray,
		?string $kid
	): Key {
		if ( $keyOrKeyArray instanceof Key ) {
			return $keyOrKeyArray;
		}

		if ( empty( $kid ) && '0' !== empty( $kid ) && $kid ) {
			throw new UnexpectedValueException( '"kid" empty, unable to lookup correct key' );
		}

		if ( $keyOrKeyArray instanceof CachedKeySet ) {
			// Skip "isset" check, as this will automatically refresh if not set
			return $keyOrKeyArray[ $kid ];
		}

		if ( ! isset( $keyOrKeyArray[ $kid ] ) ) {
			throw new UnexpectedValueException( '"kid" invalid, unable to lookup correct key' );
		}

		return $keyOrKeyArray[ $kid ];
	}

	/**
	 * Constant Time Equals
	 *
	 * @param string $left The string of known length to compare against
	 * @param string $right The user-supplied string
	 * @return bool
	 */
	public static function constantTimeEquals( string $left, string $right ): bool {
		if ( function_exists( 'hash_equals' ) ) {
			return hash_equals( $left, $right );
		}
		$len = min( self::safeStrlen( $left ), self::safeStrlen( $right ) );

		$status = 0;
		for ( $i = 0; $i < $len; $i++ ) {
			$status |= ( ord( $left[ $i ] ) ^ ord( $right[ $i ] ) );
		}
		$status |= ( self::safeStrlen( $left ) ^ self::safeStrlen( $right ) );

		return ( 0 === $status );
	}

	/**
	 * Get the number of bytes in cryptographic strings.
	 *
	 * @param string $str
	 *
	 * @return int
	 */
	private static function safeStrlen( string $str ): int {
		if ( function_exists( 'mb_strlen' ) ) {
			return mb_strlen( $str, '8bit' );
		}
		return strlen( $str );
	}

	/**
	 * Convert an ECDSA signature to an ASN.1 DER sequence
	 *
	 * @param string $sig The ECDSA signature to convert
	 * @return  string The encoded DER object
	 */
	private static function signatureToDER( string $sig ): string {
		// Separate the signature into r-value and s-value
		$length      = max( 1, (int) ( strlen( $sig ) / 2 ) );
		list($r, $s) = str_split( $sig, $length );

		// Trim leading zeros
		$r = ltrim( $r, "\x00" );
		$s = ltrim( $s, "\x00" );

		// Convert r-value and s-value from unsigned big-endian integers to
		// signed two's complement
		if ( ord( $r[0] ) > 0x7f ) {
			$r = "\x00" . $r;
		}
		if ( ord( $s[0] ) > 0x7f ) {
			$s = "\x00" . $s;
		}

		return self::encodeDER(
			self::ASN1_SEQUENCE,
			self::encodeDER( self::ASN1_INTEGER, $r ) .
			self::encodeDER( self::ASN1_INTEGER, $s )
		);
	}

	/**
	 * Encodes a value into a DER object.
	 *
	 * @param int    $type DER tag
	 * @param string $value the value to encode
	 *
	 * @return  string  the encoded object
	 */
	private static function encodeDER( int $type, string $value ): string {
		$tag_header = 0;
		if ( self::ASN1_SEQUENCE === $type ) {
			$tag_header |= 0x20;
		}

		// Type
		$der = chr( $tag_header | $type );

		// Length
		$der .= chr( strlen( $value ) );

		return $der . $value;
	}

	/**
	 * Verify a signature with the message, key and method. Not all methods
	 * are symmetric, so we must have a separate verify and sign method.
	 *
	 * @param string                                                  $msg The original message (header and body)
	 * @param string                                                  $signature The original signature
	 * @param string|resource|OpenSSLAsymmetricKey|OpenSSLCertificate $keyMaterial For Ed*, ES*, HS*, a string key works. for RS*, must be an instance of OpenSSLAsymmetricKey
	 * @param string                                                  $alg The algorithm
	 *
	 * @return bool
	 *
	 * @throws DomainException Invalid Algorithm, bad key, or OpenSSL failure
	 */
	private static function verify(
		string $msg,
		string $signature,
		$keyMaterial,
		string $alg
	): bool {
		if ( empty( static::$supported_algs[ $alg ] ) ) {
			throw new DomainException( 'Algorithm not supported' );
		}

		list($function, $algorithm) = static::$supported_algs[ $alg ];
		switch ( $function ) {
			case 'openssl':
				$success = openssl_verify( $msg, $signature, $keyMaterial, $algorithm ); // @phpstan-ignore-line
				if ( 1 === $success ) {
					return true;
				}
				if ( 0 === $success ) {
					return false;
				}
				// returns 1 on success, 0 on failure, -1 on error.
				throw new DomainException(
					'OpenSSL error: ' . wp_kses(openssl_error_string())
				);
			case 'sodium_crypto':
				if ( ! function_exists( 'sodium_crypto_sign_verify_detached' ) ) {
					throw new DomainException( 'libsodium is not available' );
				}
				if ( ! is_string( $keyMaterial ) ) {
					throw new InvalidArgumentException( 'key must be a string when using EdDSA' );
				}
				try {
					// The last non-empty line is used as the key.
					$lines = array_filter( explode( "\n", $keyMaterial ) );
					$key   = base64_decode( (string) end( $lines ) );
					if ( strlen( $key ) === 0 ) {
						throw new DomainException( 'Key cannot be empty string' );
					}
					if ( strlen( $signature ) === 0 ) {
						throw new DomainException( 'Signature cannot be empty string' );
					}
					return sodium_crypto_sign_verify_detached( $signature, $msg, $key );
				} catch ( Exception $e ) {
					throw new DomainException( wp_kses($e->getMessage()), 0, wp_kses($e) );
				}
			case 'hash_hmac':
			default:
				if ( ! is_string( $keyMaterial ) ) {
					throw new InvalidArgumentException( 'key must be a string when using hmac' );
				}
				$hash = hash_hmac( $algorithm, $msg, $keyMaterial, true );
				return self::constantTimeEquals( $hash, $signature );
		}
	}

	/**
	 * Converts and signs a PHP array into a JWT string.
	 *
	 * @param array<mixed>                                            $payload PHP array
	 * @param string|resource|OpenSSLAsymmetricKey|OpenSSLCertificate $key The secret key.
	 * @param string                                                  $alg Supported algorithms are 'ES384','ES256', 'ES256K', 'HS256',
	 *                                                                                                                          'HS384', 'HS512', 'RS256', 'RS384', and 'RS512'
	 * @param string                                                  $keyId
	 * @param array<string, string>                                   $head An array with header elements to attach
	 *
	 * @return string A signed JWT
	 *
	 * @uses jsonEncode
	 * @uses urlsafeB64Encode
	 */
	public static function encode(
		array $payload,
		$key,
		string $alg,
		string $keyId = null,
		array $head = null
	): string {
		$header = array( 'typ' => 'JWT' );
		if ( isset( $head ) && is_array( $head ) ) {
			$header = array_merge( $header, $head );
		}
		$header['alg'] = $alg;
		if ( null !== $keyId ) {
			$header['kid'] = $keyId;
		}
		$segments      = array();
		$segments[]    = static::urlsafeB64Encode( (string) static::jsonEncode( $header ) );
		$segments[]    = static::urlsafeB64Encode( (string) static::jsonEncode( $payload ) );
		$signing_input = implode( '.', $segments );

		$signature  = static::sign( $signing_input, $key, $alg );
		$segments[] = static::urlsafeB64Encode( $signature );

		return implode( '.', $segments );
	}

	/**
	 * Encode a string with URL-safe Base64.
	 *
	 * @param string $input The string you want encoded
	 *
	 * @return string The base64 encode of what you passed in
	 */
	public static function urlsafeB64Encode( string $input ): string {
		return str_replace( '=', '', strtr( base64_encode( $input ), '+/', '-_' ) );
	}

	/**
	 * Encode a PHP array into a JSON string.
	 *
	 * @param array<mixed> $input A PHP array
	 *
	 * @return string JSON representation of the PHP array
	 *
	 * @throws DomainException Provided object could not be encoded to valid JSON
	 */
	public static function jsonEncode( array $input ): string {
		if ( PHP_VERSION_ID >= 50400 ) {
			$json = wp_json_encode( $input, JSON_UNESCAPED_SLASHES );
		} else {
			// PHP 5.3 only
			$json = wp_json_encode( $input );
		}
		$errno = json_last_error();
		if ( $errno ) {
			self::handleJsonError( $errno );
		} elseif ( 'null' === $json ) {
			throw new DomainException( 'Null result with non-null input' );
		}
		if ( false === $json ) {
			throw new DomainException( 'Provided object could not be encoded to valid JSON' );
		}
		return $json;
	}

	/**
	 * Sign a string with a given key and algorithm.
	 *
	 * @param string                                                  $msg The message to sign
	 * @param string|resource|OpenSSLAsymmetricKey|OpenSSLCertificate $key The secret key.
	 * @param string                                                  $alg Supported algorithms are 'EdDSA', 'ES384', 'ES256', 'ES256K', 'HS256',
	 *                                                                                                                      'HS384', 'HS512', 'RS256', 'RS384', and 'RS512'
	 *
	 * @return string An encrypted message
	 *
	 * @throws DomainException Unsupported algorithm or bad key was specified
	 */
	public static function sign(
		string $msg,
		$key,
		string $alg
	): string {
		if ( empty( static::$supported_algs[ $alg ] ) ) {
			throw new DomainException( 'Algorithm not supported' );
		}
		list($function, $algorithm) = static::$supported_algs[ $alg ];
		switch ( $function ) {
			case 'hash_hmac':
				if ( ! is_string( $key ) ) {
					throw new InvalidArgumentException( 'key must be a string when using hmac' );
				}
				return hash_hmac( $algorithm, $msg, $key, true );
			case 'openssl':
				$signature = '';
				$success   = openssl_sign( $msg, $signature, $key, $algorithm ); // @phpstan-ignore-line
				if ( ! $success ) {
					throw new DomainException( 'OpenSSL unable to sign data' );
				}
				if ( 'ES256' === $alg || 'ES256K' === $alg ) {
					$signature = self::signatureFromDER( $signature, 256 );
				} elseif ( 'ES384' === $alg ) {
					$signature = self::signatureFromDER( $signature, 384 );
				}
				return $signature;
			case 'sodium_crypto':
				if ( ! function_exists( 'sodium_crypto_sign_detached' ) ) {
					throw new DomainException( 'libsodium is not available' );
				}
				if ( ! is_string( $key ) ) {
					throw new InvalidArgumentException( 'key must be a string when using EdDSA' );
				}
				try {
					// The last non-empty line is used as the key.
					$lines = array_filter( explode( "\n", $key ) );
					$key   = base64_decode( (string) end( $lines ) );
					if ( strlen( $key ) === 0 ) {
						throw new DomainException( 'Key cannot be empty string' );
					}
					return sodium_crypto_sign_detached( $msg, $key );
				} catch ( Exception $e ) {
					throw new DomainException( wp_kses($e->getMessage()), 0, wp_kses($e) );
				}
		}

		throw new DomainException( 'Algorithm not supported' );
	}

	/**
	 * Encodes signature from a DER object.
	 *
	 * @param string $der binary signature in DER format
	 * @param int    $keySize the number of bits in the key
	 *
	 * @return  string  the signature
	 */
	private static function signatureFromDER( string $der, int $keySize ): string {
		// OpenSSL returns the ECDSA signatures as a binary ASN.1 DER SEQUENCE
		list($offset, $_) = self::readDER( $der );
		list($offset, $r) = self::readDER( $der, $offset );
		list($offset, $s) = self::readDER( $der, $offset );

		// Convert r-value and s-value from signed two's compliment to unsigned
		// big-endian integers
		$r = ltrim( $r, "\x00" );
		$s = ltrim( $s, "\x00" );

		// Pad out r and s so that they are $keySize bits long
		$r = str_pad( $r, $keySize / 8, "\x00", STR_PAD_LEFT );
		$s = str_pad( $s, $keySize / 8, "\x00", STR_PAD_LEFT );

		return $r . $s;
	}

	/**
	 * Reads binary DER-encoded data and decodes into a single object
	 *
	 * @param string $der the binary data in DER format
	 * @param int    $offset the offset of the data stream containing the object
	 *       to decode
	 *
	 * @return array{int, string|null} the new offset and the decoded object
	 */
	private static function readDER( string $der, int $offset = 0 ): array {
		$pos         = $offset;
		$size        = strlen( $der );
		$constructed = ( ord( $der[ $pos ] ) >> 5 ) & 0x01;
		$type        = ord( $der[ $pos++ ] ) & 0x1f;

		// Length
		$len = ord( $der[ $pos++ ] );
		if ( $len & 0x80 ) {
			$n   = $len & 0x1f;
			$len = 0;
			while ( $n-- && $pos < $size ) {
				$len = ( $len << 8 ) | ord( $der[ $pos++ ] );
			}
		}

		// Value
		if ( self::ASN1_BIT_STRING === $type ) {
			++$pos; // Skip the first contents octet (padding indicator)
			$data = substr( $der, $pos, $len - 1 );
			$pos += $len - 1;
		} elseif ( ! $constructed ) {
			$data = substr( $der, $pos, $len );
			$pos += $len;
		} else {
			$data = null;
		}

		return array( $pos, $data );
	}
}