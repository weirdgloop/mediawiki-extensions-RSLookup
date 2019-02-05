<?php

use SMW\ApplicationFactory;
use SMWQueryProcessor;
use SMWQuery;

class SpecialLookup extends SpecialPage {
	function __construct() {
		parent::__construct( 'Lookup' );
	}

	function execute( $par ) {
		$request = $this->getRequest();
		$output = $this->getOutput();
		$this->setHeaders();

    $type = $request->getText( 'type' );
    $id = $request->getText( 'id' );
    $name = $request->getText( 'name' );

    if ( empty( $name ) ) {
      // Redirect to the main page if there's no name
      $mp = Title::newMainPage();
      $url = $mp->getFullURL();
      $output->redirect( $url );
    }

    switch ( $type ) {
      case 'item':
        $query = $this->getSMWQuery( '[[Item ID::' . $id . ']]|?Item ID|?Version anchor' );
        $result = $this->getSMWQueryResult( $query );
        $output->addWikitext( $result );
      case 'npc':
      case 'object':
      default:
        // Try seeing if name exists as a page
        // if not, redir to Special:Search
    }
  }
  
  protected function getSMWQuery( $queryString, array $printouts, array $parameters = [] ) {
		SMWQueryProcessor::addThisPrintout( $printouts, $parameters );
		$query = SMWQueryProcessor::createQuery(
			$queryString,
			SMWQueryProcessor::getProcessedParams( $parameters, $printouts ),
			SMWQueryProcessor::SPECIAL_PAGE,
			'',
			$printouts
		);
		$query->setOption( SMWQuery::PROC_CONTEXT, 'API' );
		return $query;
  }

  protected function getSMWQueryResult ( SMWQuery $query ) {
    return ApplicationFactory::getInstance()->getStore()->getQueryResult( $query );
  }
}