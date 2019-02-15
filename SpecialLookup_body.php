<?php

class SpecialLookup extends SpecialPage {
  protected $lookupType;

  protected $lookupId;

  protected $lookupName;

  protected $lookupDry;

	function __construct() {
    parent::__construct( 'Lookup', '', false );
	}

	function execute( $par ) {
		$request = $this->getRequest();
		$output = $this->getOutput();
		$this->setHeaders();

    $this->lookupType = $request->getText( 'type', '' );
    $this->lookupId = $request->getText( 'id', '' );
    $this->lookupName = htmlspecialchars( $request->getText( 'name', '' ) );
    $this->lookupDry = $request->getBool( 'dry', false );

    if ( empty( $this->lookupId ) ) {
      // No ID given
      $this->backupPlan();
    }

    $prop = '';

    switch ( $this->lookupType ) {
      case 'item':
        $prop = 'Item ID';
        break;
      case 'npc':
        $prop = 'NPC ID';
        break;
      case 'object':
        $prop = 'Object ID';
        break;
    }

    if ( empty( $prop ) ) {
      // Not a recognised type
      $this->backupPlan();
    } else {
      $result = $this->makeSMWQuery( $prop, $this->lookupId );
      if ( !$result['query']['results'] ) {
        // No results from SMW, check if name provided is a wiki page
        wfDebugLog( 'rslookup', "No results found. [{$this->lookupName}, {$this->lookupType}, {$this->lookupId}, prop={$prop}]" );
        $this->backupPlan();
      } else {
        if ( count( $result['query']['results'] ) > 1 ) {
          // More than one result, log this
          wfDebugLog( 'rslookup', "Query returned more than one result. [{$this->lookupName}, {$this->lookupType}, {$this->lookupId}, prop={$prop}]" );
        }

        $this->handleSMWResult( $result );
      }
    }
  }

  protected function handleSMWResult( $result ) {
    $results = $result['query']['results'];

    $r = reset($results);
    $url = $r['fullurl'];

    if ( !empty( $r['printouts']['Version anchor'] ) ) {
      // Get everything before the # in the URL already
      $split = explode( '#', $url );
      $url = $split[0];

      if ( array_key_exists( 'fulltext', $r['printouts']['Version anchor'][0] ) ) {
        // First item in Version anchor array has fulltext key, append the value
        $url .= '#' . $r['printouts']['Version anchor'][0]['fulltext'];
      } else {
        // Something weird happened, just add whatever the first item is
        $url .= '#' . $r['printouts']['Version anchor'][0];
      }
    }

    // Encode the URL
    $url = str_replace(' ', '_', $url); // Replace spaces with _

    // Redirect to the target URL
    if ( $this->lookupDry ) {
      // If this is a dry run, output what the result would have been
      $this->showDryRunResult( "URL to redirect to: {$url}\n\nSMW query results:\n\n" . print_r($results, true));
    } else {
      $this->getOutput()->redirect($url);
    }
  }

  protected function showDryRunResult( $text ) {
    $this->getOutput()->addWikiText(
      "This page will display the '''expected output''' of your query."
    . "\n\n==Output==\n\n<pre>\nRequested type: {$this->lookupType}\n"
    . "Requested name: {$this->lookupName}\n"
    . "Requested ID: {$this->lookupId}\n\n{$text}\n</pre>");
    return true;
  }

  protected function backupPlan() {
    $output = $this->getOutput();
    $name = $this->lookupName;
    if ( empty( $name ) ) {
      // Redirect to the main page if there's no name
      $mp = Title::newMainPage();
      $url = $mp->getFullURL();
    } else {
      $title = Title::newFromText( $name );
      if ( ( $title !== null ) && $title->exists() ) {
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
      $output->redirect( $url );
    }
  }
  
  protected function makeSMWQuery( $prop, $id ) {
    $params = new DerivativeRequest(
      $this->getRequest(),
      array(
        'action' => 'ask',
        'query' => '[[' . $prop . '::' . $id . ']]|?Version anchor'
      )
    );
    $api = new ApiMain( $params );
    $api->setCacheMaxAge(600); // cache for 10 minutes
    $api->execute();
    $result = $api->getResult()->getResultData();
    return $result;
  }
}