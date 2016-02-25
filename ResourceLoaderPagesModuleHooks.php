<?php

class ResourceLoaderPagesModuleHooks {
	static public function onResourceLoaderRegisterModules( $resourceLoader ) {
		global $wgRequest;
		wfProfileIn(__METHOD__);
		/* @var $request WebRequest */
		if ( $wgRequest->getText( 'mode' ) !== 'articles' ) {
			wfProfileOut(__METHOD__);
			return true;
		}

		$articles = $wgRequest->getText('articles');
		$articles = explode('|',$articles);
		if ( empty( $articles ) ) {
			wfProfileOut(__METHOD__);
			return true;
		}

		// prepare fake ResourceLoader module metadata
		$timestamp = time();
		$moduleName = md5( serialize( array( $articles ) . ($timestamp - ($timestamp % 86400)) ) );
		$moduleFullName = 'liquipedia.module.articles.' . $moduleName;
		$moduleInfo = array(
			'class' => 'ResourceLoaderPagesModule',
		);

		// register new fake module
		$resourceLoader->register($moduleFullName, $moduleInfo);

		// reinitialize ResourceLoader context
		$wgRequest->setVal('modules', $moduleFullName);
		$context = new ResourceLoaderContext( $resourceLoader, $wgRequest );
		wfProfileOut(__METHOD__);
		return true;
	}
}

?>