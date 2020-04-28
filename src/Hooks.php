<?php

namespace Liquipedia\ResourceLoaderArticles;

use ContentHandler;
use DatabaseUpdater;
use OutputPage;
use ResourceLoader;
use Revision;
use Skin;
use Title;

class Hooks {

	/**
	 * @param ResourceLoader $resourceLoader
	 * @return bool
	 */
	public static function onResourceLoaderRegisterModules( $resourceLoader ) {
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
		foreach ( $articles as $article ) {
			$title = Title::newFromText( $article );
			if ( !$title ) {
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
		$moduleName = md5( serialize( [ $articles ] ) . $text );
		$moduleFullName = 'liquipedia.module.articles.' . $moduleName;
		$moduleInfo = [
			'class' => 'Liquipedia\\ResourceLoaderArticles\\ResourceLoaderArticlesModule',
		];

		// register new fake module
		$resourceLoader->register( $moduleFullName, $moduleInfo );

		// reinitialize ResourceLoader context
		$wgRequest->setVal( 'modules', $moduleFullName );
		return true;
	}

	/**
	 * @param OutputPage &$out
	 * @param Skin &$skin
	 */
	public static function onBeforePageDisplay( OutputPage &$out, Skin &$skin ) {
		if ( $skin->getSkinName() !== 'apioutput' ) {
			$dbr = wfGetDB( DB_REPLICA );
			$config = $out->getConfig();
			$scriptPath = substr( $config->get( 'ScriptPath' ), 1 );
			$debugMode = ResourceLoader::inDebugMode();
			$wikiUrl = $config->get( 'ResourceLoaderArticlesWiki' );
			$scripts = [ 'UseStrict.js', 'Polyfill.js', 'Core.js' ];
			$styles = [ 'Variables.css' ];
			$addScript = false;
			$addStyle = false;
			$res = $dbr->select(
				'resourceloaderarticles',
				'*',
				[ '`rla_wiki` IN(\'' . $scriptPath . '\', \'all\')' ],
				__METHOD__,
				[ 'ORDER BY' => 'rla_type ASC, rla_page ASC, rla_wiki ASC' ]
			);
			foreach ( $res as $row ) {
				if ( $row->rla_type === 'script' ) {
					$scripts[] = $row->rla_page;
					$addScript = true;
				} elseif ( $row->rla_type === 'style' ) {
					$styles[] = $row->rla_page;
					$addStyle = true;
				}
			}
			$scripts[] = 'CoreEnd.js';

			if ( $addScript ) {
				$script = $wikiUrl
					. '?debug=' . ( $debugMode ? 'true' : 'false' )
					. '&articles=' . urlencode( implode( '|', $scripts ) )
					. '&only=scripts&mode=articles&cacheversion='
					. urlencode( $out->msg( 'resourceloaderarticles-cacheversion' )->text() )
					. '&*';
				$out->addInlineScript(
					ResourceLoader::makeLoaderConditionalScript( 'mw.loader.load(\'' . $script . '\');' )
				);
			}
			if ( $addStyle ) {
				$style = $wikiUrl
					. '?debug=' . ( $debugMode ? 'true' : 'false' )
					. '&articles=' . urlencode( implode( '|', $styles ) )
					. '&only=styles&mode=articles&cacheversion='
					. urlencode( $out->msg( 'resourceloaderarticles-cacheversion' )->text() )
					. '&*';
				$out->addStyle( $style );
			}
		}
	}

	/**
	 * Handle database updates
	 * @param DatabaseUpdater $updater
	 */
	public static function onLoadExtensionSchemaUpdates( DatabaseUpdater $updater ) {
		$db = $updater->getDB();

		if ( !$db->tableExists( 'resourceloaderarticles', __METHOD__ ) ) {
			$updater->output( "Creating resourceloaderarticles table resourceloaderarticles ...\n" );
			$db->sourceFile( __DIR__ . '/sql/resourceloaderarticles.sql' );
			$updater->output( "done.\n" );
		} else {
			$updater->output( "...resourceloaderarticles table already exists.\n" );
		}
	}

}
