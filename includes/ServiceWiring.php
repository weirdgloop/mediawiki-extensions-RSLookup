<?php

namespace MediaWiki\Extension\RSLookup;

use MediaWiki\MediaWikiServices;

return [
	'RSLookupService' => static function ( MediaWikiServices $services ): RSLookupService {
		return new RSLookupService(
			$services->getMainConfig(),
			$services->getParser()
		);
	}
];
