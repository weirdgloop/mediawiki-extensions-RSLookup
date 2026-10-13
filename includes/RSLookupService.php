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
	private array $lookupTypes = [];

	private Parser $parser;

	public function __construct( Config $config, Parser $parser ) {
		$this->lookupTypes = $config->get( 'RSLookupBucketNames' );
		$this->parser = $parser;
	}

	/**
	 * Performs a semantic search using Bucket and returns the URL of the result.
	 * @param WebRequest $request
	 * @param UserIdentity $user
	 * @param string $type
	 * @param string $id
	 * @return string|null
	 */
	public function performLookup( $request, $user, $type, $id ) {
		$propertyOrBucketName = $this->lookupTypes[$type] ?? null;
		if ( !$propertyOrBucketName ) {
			return null;
		}

		return $this->performBucketLookup( $user, $propertyOrBucketName, $id );
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
