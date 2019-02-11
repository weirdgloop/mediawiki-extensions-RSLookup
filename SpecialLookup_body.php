<?php

class SpecialLookup extends SpecialPage {
	function __construct() {
		parent::__construct( 'Lookup', '', false );
	}

	function execute( $par ) {
		$request = $this->getRequest();
		$output = $this->getOutput();
		$this->setHeaders();

    $type = $request->getText( 'type', '' );
    $id = $request->getText( 'id', '' );
    $name = $request->getText( 'name', '' );

    if ( empty( $id ) ) {
      // No ID given
      $this->backupPlan( $name );
    }

    $prop = '';

    switch ( $type ) {
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
      $this->backupPlan( $name );
    } else {
      $result = $this->makeSMWQuery( $prop, $id );
      if ( !$result['query']['results'] ) {
        // No results from SMW, check if name provided is a wiki page
        $this->backupPlan( $name );
      } else {
        if ( count( $result ) > 1 ) {
          // More than one result, log this
          wfDebugLog( 'rslookup', "Query returned more than one result. [{$name}, {$type}, {$id}, prop={$prop}]" );
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
      }
    }

    // Encode the URL
    $url = str_replace(' ', '_', $url); // Replace spaces with _

    // Redirect to the target URL
    $this->getOutput()->redirect($url);
  }

  protected function backupPlan( $name ) {
    $output = $this->getOutput();
    if ( empty( $name ) ) {
      // Redirect to the main page if there's no name
      $mp = Title::newMainPage();
      $url = $mp->getFullURL();
      $output->redirect( $url );
    } else {
      $title = Title::newFromText( $name );
      if ( $title->exists() ) {
        // Page exists, let's redirect to it
        $url = $title->getFullURL();
        $output->redirect( $url );
      } else {
        // Page doesn't exist, let's redirect to the search page
        $search = Title::newFromText( 'Special:Search' );
        $url = $search->getFullURL( 'search=' . $name );
        $output->redirect( $url );
      }
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