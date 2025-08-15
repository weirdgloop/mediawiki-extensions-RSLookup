<?php

namespace MediaWiki\Extension\RSLookup;

use MediaWiki\MediaWikiServices;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Title\Title;

class SpecialLookup extends SpecialPage {
	protected string $lookupType;

	protected string $lookupId;

	protected string $lookupName;

	protected bool $lookupDry;

	private RSLookupService $lookupService;

	public function __construct() {
		parent::__construct( 'Lookup', '', false );
		$this->lookupService = MediaWikiServices::getInstance()->getService( 'RSLookupService' );
	}

	/**
	 * @param null|string $subPage
	 * @return void
	 */
	public function execute( $subPage ) {
		$request = $this->getRequest();
		$this->setHeaders();

		$this->lookupType = strtolower( $request->getText( 'type', '' ) );
		$this->lookupId = $request->getText( 'id', '' );
		$this->lookupName = htmlspecialchars( $request->getText( 'name', '' ) );
		$this->lookupDry = $request->getBool( 'dry', false );

		if ( empty( $this->lookupId ) ) {
			// No ID given
			$this->backupPlan();
			return;
		}

		$url = $this->lookupService->performLookup(
			$this->getRequest(), $this->getUser(), $this->lookupType, $this->lookupId );
		if ( !$url ) {
			// No results from SMW, check if name provided is a wiki page
			wfDebugLog( 'rslookup',
				"No valid URL to redirect to. [$this->lookupName, $this->lookupType, $this->lookupId]" );
			$this->backupPlan();
		} else {
			// Redirect to the target URL
			if ( $this->lookupDry ) {
				// If this is a dry run, output what the result would have been
				$this->showDryRunResult( "URL to redirect to: $url" );
			} else {
				$this->getOutput()->redirect( $url );
			}
		}
	}

	protected function showDryRunResult( $text ) {
		$this->getOutput()->addWikiTextAsInterface(
			"This page will display the '''expected output''' of your query."
			. "\n\n==Output==\n\n<pre>\nRequested type: $this->lookupType\n"
			. "Requested name: $this->lookupName\n"
			. "Requested ID: $this->lookupId\n\n$text\n</pre>" );
		return true;
	}

	/**
	 * If we did not successfully get a result from our semantic data search, either redirect directly to the page if
	 * it exists by name, or redirect to the search page if it doesn't.
	 *
	 * If no name was provided, redirect to the main page of the wiki.
	 * @return void
	 */
	protected function backupPlan() {
		$name = $this->lookupName;
		if ( empty( $name ) ) {
			// Redirect to the main page if there's no name
			$mp = Title::newMainPage();
			$url = $mp->getFullURL();
		} else {
			$title = Title::newFromText( $name );
			if ( $title !== null && $title->exists() ) {
				// Page exists, let's redirect to it
				$url = $title->getFullURL();
			} else {
				// Page doesn't exist, let's redirect to the search page
				$search = Title::newFromText( 'Special:Search' );
				$url = $search->getFullURL( 'search=' . $name );
			}
		}
		if ( $this->lookupDry ) {
			// If this is a dry run, output what the result would have been
			$this->showDryRunResult( "URL to redirect to: {$url}" );
		} else {
			$this->getOutput()->redirect( $url );
		}
	}
}
