<?php

class ResourceLoaderPagesModuleHooks {
	static public function onResourceLoaderRegisterModules( $resourceLoader ) {
		global $wgRequest;
		/* @var $request WebRequest */
		if ( $wgRequest->getText( 'mode' ) !== 'articles' ) {
			return true;
		}

		$articles = $wgRequest->getText('articles');
		$articles = explode('|', $articles);
		if ( empty( $articles ) ) {
			return true;
		}

		$text = '';
		foreach($articles as $article) {
			$articleObj = new Article(Title::newFromText($article));
			$text .= $articleObj->getContent();
		}

		// prepare fake ResourceLoader module metadata
		$moduleName = md5( serialize( array( $articles ) ) . $text);
		$moduleFullName = 'liquipedia.module.articles.' . $moduleName;
		$moduleInfo = array(
			'class' => 'ResourceLoaderPagesModule',
		);

		// register new fake module
		$resourceLoader->register($moduleFullName, $moduleInfo);

		// reinitialize ResourceLoader context
		$wgRequest->setVal('modules', $moduleFullName);
		return true;
	}
	static public function onMagicWordwgVariableIDs( &$aCustomVariableIds ) {
		$aCustomVariableIds[] = 'skinname';
		return true;
	}
	static public function onParserGetVariableValueSwitch( &$parser, &$cache, &$magicWordId, &$ret, &$frame ) {
		if( $magicWordId == 'skinname' ) {
			$rl = new RequestContext;
			$ret = $rl->getUser()->getOption( 'skin' );
		}
		return true;
	}
}

?>