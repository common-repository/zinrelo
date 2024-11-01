<?php

namespace Firebase\JWT;

use ArrayAccess;
use InvalidArgumentException;
use LogicException;
use OutOfBoundsException;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use RuntimeException;
use UnexpectedValueException;
use function is_null;
use function is_string;
use function strlen;

/**
 * Class CachedKeySet
 *
 * @package Firebase\JWT
 */
class CachedKeySet implements ArrayAccess {


	/**
	 * JwksUrl
	 *
	 * @var string
	 */
	private $jwksUri;
	/**
	 * ClientInterface
	 *
	 * @var ClientInterface
	 */
	private $httpClient;

	/**
	 * RequestFactoryInterface
	 *
	 * @var RequestFactoryInterface
	 */
	private $httpFactory;

	/**
	 * CacheItemPoolInterface
	 *
	 * @var CacheItemPoolInterface
	 */
	private $cache;
	/**
	 * ExpiresAfter
	 *
	 * @var ?int
	 */
	private $expiresAfter;
	/**
	 * CacheItemInterface
	 *
	 * @var ?CacheItemInterface
	 */
	private $cacheItem;
	/**
	 * KeySet
	 *
	 * @var array<string, array<mixed>>
	 */
	private $keySet;
	/**
	 * CacheKey
	 *
	 * @var string
	 */
	private $cacheKey;
	/**
	 * CacheKeyPrefix
	 *
	 * @var string
	 */
	private $cacheKeyPrefix = 'jwks';
	/**
	 * MaxKeyLength
	 *
	 * @var int
	 */
	private $maxKeyLength = 64;
	/**
	 * RateLimit
	 *
	 * @var bool
	 */
	private $rateLimit;
	/**
	 * RateLimitCacheKey
	 *
	 * @var string
	 */
	private $rateLimitCacheKey;
	/**
	 * MaxCallsPerMinute
	 *
	 * @var int
	 */
	private $maxCallsPerMinute = 10;
	/**
	 * DefaultAlg
	 *
	 * @var string|null
	 */
	private $defaultAlg;

	/**
	 * CachedKeySet constructor.
	 *
	 * @param string                  $jwksUri
	 * @param ClientInterface         $httpClient
	 * @param RequestFactoryInterface $httpFactory
	 * @param CacheItemPoolInterface  $cache
	 * @param int|null                $expiresAfter
	 * @param bool                    $rateLimit
	 * @param string|null             $defaultAlg
	 */
	public function __construct(
		string $jwksUri,
		ClientInterface $httpClient,
		RequestFactoryInterface $httpFactory,
		CacheItemPoolInterface $cache,
		int $expiresAfter = null,
		bool $rateLimit = false,
		string $defaultAlg = null
	) {
		$this->jwksUri      = $jwksUri;
		$this->httpClient   = $httpClient;
		$this->httpFactory  = $httpFactory;
		$this->cache        = $cache;
		$this->expiresAfter = $expiresAfter;
		$this->rateLimit    = $rateLimit;
		$this->defaultAlg   = $defaultAlg;
		$this->setCacheKeys();
	}

	private function setCacheKeys(): void {
		if ( empty( $this->jwksUri ) ) {
			throw new RuntimeException( 'JWKS URI is empty' );
		}

		// ensure we do not have illegal characters
		$key = preg_replace( '|[^a-zA-Z0-9_\.!]|', '', $this->jwksUri );

		// add prefix
		$key = $this->cacheKeyPrefix . $key;

		// Hash keys if they exceed $maxKeyLength of 64
		if ( strlen( $key ) > $this->maxKeyLength ) {
			$key = substr( hash( 'sha256', $key ), 0, $this->maxKeyLength );
		}

		$this->cacheKey = $key;

		if ( $this->rateLimit ) {
			// add prefix
			$rateLimitKey = $this->cacheKeyPrefix . 'ratelimit' . $key;

			// Hash keys if they exceed $maxKeyLength of 64
			if ( strlen( $rateLimitKey ) > $this->maxKeyLength ) {
				$rateLimitKey = substr( hash( 'sha256', $rateLimitKey ), 0, $this->maxKeyLength );
			}

			$this->rateLimitCacheKey = $rateLimitKey;
		}
	}

	/**
	 * OffsetGet
	 *
	 * @param mixed $keyId
	 * @return Key
	 */
	public function offsetGet( $keyId ): Key {
		if ( ! $this->keyIdExists( $keyId ) ) {
			throw new OutOfBoundsException( 'Key ID not found' );
		}
		return JWK::parseKey( $this->keySet[ $keyId ], $this->defaultAlg );
	}

	private function keyIdExists( string $keyId ): bool {
		if ( null === $this->keySet ) {
			$item = $this->getCacheItem();
			// Try to load keys from cache
			if ( $item->isHit() ) {
				// item found! retrieve it
				$this->keySet = $item->get();
				// If the cached item is a string, the JWKS response was cached (previous behavior).
				// Parse this into expected format array<kid, jwk> instead.
				if ( is_string( $this->keySet ) ) {
					$this->keySet = $this->formatJwksForCache( $this->keySet );
				}
			}
		}

		if ( ! isset( $this->keySet[ $keyId ] ) ) {
			if ( $this->rateLimitExceeded() ) {
				return false;
			}
			$request      = $this->httpFactory->createRequest( 'GET', $this->jwksUri );
			$jwksResponse = $this->httpClient->sendRequest( $request );
			if ( $jwksResponse->getStatusCode() !== 200 ) {
				throw new UnexpectedValueException(
					sprintf(
						'HTTP Error: %d %s for URI "%s"',
						wp_kses($jwksResponse->getStatusCode()),
						wp_kses($jwksResponse->getReasonPhrase()),
						wp_kses($this->jwksUri)
					),
					wp_kses($jwksResponse->getStatusCode())
				);
			}
			$this->keySet = $this->formatJwksForCache( (string) $jwksResponse->getBody() );

			if ( ! isset( $this->keySet[ $keyId ] ) ) {
				return false;
			}

			$item = $this->getCacheItem();
			$item->set( $this->keySet );
			if ( $this->expiresAfter ) {
				$item->expiresAfter( $this->expiresAfter );
			}
			$this->cache->save( $item );
		}

		return true;
	}

	/**
	 * Get CacheItem
	 *
	 * @return CacheItemInterface
	 */
	private function getCacheItem(): CacheItemInterface {
		if ( is_null( $this->cacheItem ) ) {
			$this->cacheItem = $this->cache->getItem( $this->cacheKey );
		}

		return $this->cacheItem;
	}

	/**
	 * FormatJwksForCache
	 *
	 * @return array<mixed>
	 */
	private function formatJwksForCache( string $jwks ): array {
		$jwks = json_decode( $jwks, true );

		if ( ! isset( $jwks['keys'] ) ) {
			throw new UnexpectedValueException( '"keys" member must exist in the JWK Set' );
		}

		if ( empty( $jwks['keys'] ) ) {
			throw new InvalidArgumentException( 'JWK Set did not contain any keys' );
		}

		$keys = array();
		foreach ( $jwks['keys'] as $k => $v ) {
			$kid                   = isset( $v['kid'] ) ? $v['kid'] : $k;
			$keys[ (string) $kid ] = $v;
		}

		return $keys;
	}

	/**
	 * RateLimitExceeded
	 *
	 * @return bool
	 */
	private function rateLimitExceeded(): bool {
		if ( ! $this->rateLimit ) {
			return false;
		}

		$cacheItem = $this->cache->getItem( $this->rateLimitCacheKey );
		if ( ! $cacheItem->isHit() ) {
			$cacheItem->expiresAfter( 60 ); // # of calls are cached each minute
		}

		$callsPerMinute = (int) $cacheItem->get();
		if ( ++$callsPerMinute > $this->maxCallsPerMinute ) {
			return true;
		}
		$cacheItem->set( $callsPerMinute );
		$this->cache->save( $cacheItem );
		return false;
	}

	/**
	 * OffsetExists
	 *
	 * @param string $keyId
	 * @return bool
	 */
	public function offsetExists( $keyId ): bool {
		return $this->keyIdExists( $keyId );
	}

	/**
	 * OffsetSet
	 *
	 * @param string $offset
	 * @param Key    $value
	 */
	public function offsetSet( $offset, $value ): void {
		throw new LogicException( 'Method not implemented' );
	}

	/**
	 * OffsetUnset
	 *
	 * @param string $offset
	 */
	public function offsetUnset( $offset ): void {
		throw new LogicException( 'Method not implemented' );
	}
}
