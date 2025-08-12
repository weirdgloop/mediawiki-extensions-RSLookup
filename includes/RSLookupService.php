<?php

namespace MediaWiki\Extension\RSLookup;

use Exception;
use MediaWiki\Api\ApiMain;
use MediaWiki\Config\Config;
use MediaWiki\Extension\Scribunto\Scribunto;
use MediaWiki\Parser\Parser;
use MediaWiki\Parser\ParserOptions;
use MediaWiki\Request\DerivativeRequest;
use MediaWiki\Request\WebRequest;
use MediaWiki\Title\Title;
use MediaWiki\User\UserIdentity;

class RSLookupService {
	public const BACKEND_SMW = 'smw';

	private array $lookupTypes = [];
	private string $backend = '';

	private Parser $parser;

	public function __construct( Config $config, Parser $parser ) {
		$this->backend = $config->get( 'RSLookupBackend' );

		if ( $this->backend === self::BACKEND_SMW ) {
			$this->lookupTypes = $config->get( 'RSLookupSMWProps' );
		} else {
			$this->lookupTypes = $config->get( 'RSLookupBucketNames' );
		}

		$this->parser = $parser;
	}

	/**
	 * Performs a semantic search using the configured backend and returns the URL of the result.
	 * @param WebRequest $request
	 * @param UserIdentity $user
	 * @param string $type
	 * @param string $id
	 * @return string|null
	 */
	public function performLookup( $request, $user, $type, $id ) {
		$propertyOrBucketName = $this->lookupTypes[$type];
		if ( !$propertyOrBucketName ) {
			return null;
		}

		if ( $this->backend === self::BACKEND_SMW ) {
			return $this->performSMWLookup( $request, $propertyOrBucketName, $id );
		} else {
			return $this->performBucketLookup( $user, $propertyOrBucketName, $id );
		}
	}

	/**
	 * Performs a semantic search using Semantic MediaWiki and returns the URL of the result.
	 * @param WebRequest $request
	 * @param string $prop
	 * @param string $id
	 * @return string|null
	 */
	private function performSMWLookup( $request, $prop, $id ) {
		// Technically we're not supposed to make derivative requests in newer MW, but SMW's internal classes are
		// frankly a nightmare to understand, so I'm not even going to try using them.
		$params = new DerivativeRequest(
			$request,
			[
				'action' => 'ask',
				'query' => '[[' . $prop . '::' . $id . ']]|?Version anchor'
			]
		);
		$api = new ApiMain( $params );
		// Cache for 10 minutes
		$api->setCacheMaxAge( 600 );
		$api->execute();

		$results = $api->getResult()->getResultData();
		if ( !$results ) {
			return null;
		}

		$data = $results['query']['results'];

		if ( empty( $data ) ) {
			return null;
		}

		if ( count( $data ) > 1 ) {
			// More than one result, log this
			wfDebugLog( 'rslookup',
				"SMW query returned more than one result. [$prop, $id]" );
		}

		$r = reset( $data );
		$url = $r['fullurl'];

		if ( !empty( $r['printouts']['Version anchor'] ) ) {
			// Get everything before the # in the URL already
			$split = explode( '#', $url );
			$url = $split[0];
			$anchor = $r['printouts']['Version anchor'][0];

			if ( is_array( $anchor ) && array_key_exists( 'fulltext', $anchor ) ) {
				// First item in Version anchor array has fulltext key, append the value
				$url .= '#' . $anchor['fulltext'];
			} else {
				// Something weird happened, just add whatever the first item is
				$url .= '#' . $anchor;
			}
		}

		return str_replace( ' ', '_', $url );
	}

	/**
	 * Performs a semantic search using Bucket and returns the URL of the result.
	 * @param UserIdentity $user
	 * @param string $bucketName
	 * @param string $id
	 * @return string|null
	 */
	private function performBucketLookup( $user, $bucketName, $id ) {
		// Bucket queries are performed using Scribunto, so we can just call Scribunto directly instead of an API call.
		$options = new ParserOptions( $user );
		$title = Title::makeTitle( NS_SPECIAL, 'Lookup' );
		$this->parser->startExternalParse( $title, $options, Parser::OT_HTML );
		$engine = Scribunto::getParserEngine( $this->parser );

		$query = "= mw.text.jsonEncode(bucket('$bucketName').select('page_name_sub').where('id', $id).limit(1).run())";

		try {
			$result = $engine->runConsole( [
				'title' => $title,
				'content' => '',
				'prevQuestions' => [],
				'question' => $query
			] );
		} catch ( Exception $e ) {
			return null;
		}

		if ( !$result ) {
			return null;
		}

		$bucketResult = json_decode( $result['return'] );
		if ( empty( $bucketResult ) ) {
			return null;
		}

		$page = $bucketResult[0]->page_name_sub;
		return Title::newFromText( $page )->getFullUrlForRedirect();
	}
}
