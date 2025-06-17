<?php

namespace Liquipedia\Extension\ResourceLoaderArticles\Hooks;

use Liquipedia\Extension\ResourceLoaderArticles\ResourceLoader\ResourceLoaderArticlesModule;
use MediaWiki\Content\ContentHandlerFactory;
use MediaWiki\Hook\BeforePageDisplayHook;
use MediaWiki\Hook\MakeGlobalVariablesScriptHook;
use MediaWiki\ResourceLoader\Hook\ResourceLoaderRegisterModulesHook;
use MediaWiki\ResourceLoader\ResourceLoader;
use MediaWiki\Revision\Hook\ContentHandlerDefaultModelForHook;
use MediaWiki\Revision\RevisionLookup;
use MediaWiki\Revision\SlotRecord;
use OutputPage;
use Skin;
use Title;

class MainHookHandler implements
	BeforePageDisplayHook,
	ContentHandlerDefaultModelForHook,
	MakeGlobalVariablesScriptHook,
	ResourceLoaderRegisterModulesHook
{

	private ContentHandlerFactory $contentHandlerFactory;
	private RevisionLookup $revisionLookup;

	public function __construct(
		ContentHandlerFactory $contentHandlerFactory,
		RevisionLookup $revisionLookup
	) {
		$this->contentHandlerFactory = $contentHandlerFactory;
		$this->revisionLookup = $revisionLookup;
	}

	/**
	 * @param OutputPage $out
	 * @param Skin $skin
	 */
	public function onBeforePageDisplay( $out, $skin ): void {
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
				[ 'ORDER BY' => 'rla_type ASC, rla_priority DESC, rla_page ASC, rla_wiki ASC' ]
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
					ResourceLoader::makeInlineCodeWithModule(
						'mediawiki.base',
						'mw.loader.load(\'' . $script . '\');'
					)
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
	 * Set the CONTENT_MODEL_CSS content handler for less and scss files
	 *
	 * @param Title $title
	 * @param string &$model
	 * @return bool
	 */
	public function onContentHandlerDefaultModelFor( $title, &$model ) {
		if ( $title->getNamespace() === NS_MEDIAWIKI ) {
			if ( str_ends_with( $title->getText(), '.less' ) ) {
				$model = CONTENT_MODEL_CSS;
				return true;
			} elseif ( str_ends_with( $title->getText(), '.scss' ) ) {
				$model = CONTENT_MODEL_CSS;
				return true;
			}
		}
	}

	/**
	 * @param array &$vars
	 * @param OutputPage $out
	 */
	public function onMakeGlobalVariablesScript( &$vars, $out ): void {
		$vars += [
			'resourceloaderarticles-cacheversion' => wfMessage( 'resourceloaderarticles-cacheversion' )->text(),
		];
	}

	/**
	 * @param ResourceLoader $rl
	 * @return bool
	 */
	public function onResourceLoaderRegisterModules( ResourceLoader $rl ): void {
		global $wgRequest;
		/* @var $wgRequest WebRequest */
		if ( $wgRequest->getText( 'mode' ) !== 'articles' ) {
			return;
		}

		$articles = $wgRequest->getText( 'articles' );
		$articles = explode( '|', $articles );
		if ( empty( $articles ) ) {
			return;
		}

		$text = '';
		foreach ( $articles as $article ) {
			$title = Title::newFromText( $article );
			if ( !$title ) {
				continue;
			}

			$handler = $this->contentHandlerFactory->getContentHandler( $title->getContentModel() );
			if ( $handler->isSupportedFormat( CONTENT_FORMAT_CSS ) ) {
				$format = CONTENT_FORMAT_CSS;
			} elseif ( $handler->isSupportedFormat( CONTENT_FORMAT_JAVASCRIPT ) ) {
				$format = CONTENT_FORMAT_JAVASCRIPT;
			} else {
				continue;
			}
			$revision = $this->revisionLookup->getRevisionByTitle( $title, 0, RevisionLookup::READ_NORMAL );

			if ( !$revision ) {
				continue;
			}

			$content = $revision->getContent( SlotRecord::MAIN );

			$text .= $content->getNativeData() . "\n";
		}

		// prepare fake ResourceLoader module metadata
		$moduleName = md5( serialize( [ $articles ] ) . $text );
		$moduleFullName = 'liquipedia.module.articles.' . $moduleName;
		$moduleInfo = [
			'class' => ResourceLoaderArticlesModule::class,
		];

		// register new fake module
		$rl->register( $moduleFullName, $moduleInfo );

		// reinitialize ResourceLoader context
		$wgRequest->setVal( 'modules', $moduleFullName );
	}

}
