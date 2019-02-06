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
        $this->handleSMWResult( $result );
      }
    }
  }

  protected function handleSMWResult( $result ) {
    $r = reset($result['query']['results']);

    // TODO: handle version anchor
    // $r['printouts']['Version anchor']

    // Redirect to the target page
    $this->getOutput()->redirect($r['fullurl']);
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
    $api->execute();
    $result = $api->getResult()->getResultData();
    return $result;
  }
}