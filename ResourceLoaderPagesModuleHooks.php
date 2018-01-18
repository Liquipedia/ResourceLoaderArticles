<?php

class ResourceLoaderPagesModuleHooks {
	static public function onResourceLoaderRegisterModules( $resourceLoader ) {
		global $wgRequest;
		/* @var $request WebRequest */
		if ( $wgRequest->getText( 'mode' ) !== 'articles' ) {
			return true;
		}

		$articles = $wgRequest->getText( 'articles' );
		$articles = explode( '|', $articles );
		if ( empty( $articles ) ) {
			return true;
		}

		$text = '';
		foreach( $articles as $article ) {
			$title = Title::newFromText( $article );
			if( !$title ) {
				continue;
			}

			$handler = ContentHandler::getForTitle( $title );
			if ( $handler->isSupportedFormat( CONTENT_FORMAT_CSS ) ) {
				$format = CONTENT_FORMAT_CSS;
			} elseif ( $handler->isSupportedFormat( CONTENT_FORMAT_JAVASCRIPT ) ) {
				$format = CONTENT_FORMAT_JAVASCRIPT;
			} else {
				continue;
			}

			$revision = Revision::newFromTitle( $title, false, Revision::READ_NORMAL );
			if ( !$revision ) {
				continue;
			}

			$content = $revision->getContent( Revision::RAW );

			$text .= $content->getNativeData() . "\n";
		}

		// prepare fake ResourceLoader module metadata
		$moduleName = md5( serialize( array( $articles ) ) . $text );
		$moduleFullName = 'liquipedia.module.articles.' . $moduleName;
		$moduleInfo = array(
			'class' => 'ResourceLoaderPagesModule',
		);

		// register new fake module
		$resourceLoader->register( $moduleFullName, $moduleInfo );

		// reinitialize ResourceLoader context
		$wgRequest->setVal( 'modules', $moduleFullName );
		return true;
	}
}

?>