<?php

/**
 * MediaWiki Extension to add additional Resource Loader module
 */

namespace Liquipedia\Extension\ResourceLoaderArticles\ResourceLoader;

use CSSJanus;
use Less_Parser;
use MediaWiki\MainConfigNames;
use MediaWiki\MediaWikiServices;
use MediaWiki\ResourceLoader\Context;
use MediaWiki\ResourceLoader\ResourceLoader;
use MediaWiki\ResourceLoader\WikiModule;
use MemoizedCallable;
use Peast\Peast;
use Peast\Syntax\Exception as PeastSyntaxException;
use ScssPhp\ScssPhp\Compiler as SCSSCompiler;

class ResourceLoaderArticlesModule extends WikiModule {

	private const USERJSPARSE_CACHE_VERSION = 3;

	/**
	 * Get list of pages used by this module
	 *
	 * @param Context $context
	 * @return array List of pages
	 */
	protected function getPages( Context $context ) {
		$request = $context->getRequest();
		$articles = $request->getVal( 'articles' );
		$articles = explode( '|', $articles ?? '' );
		if ( empty( $articles ) ) {
			return;
		}
		$pages = [];
		foreach ( $articles as $article ) {
			if ( substr( $article, -3 ) === '.js' ) {
				$pages[ 'MediaWiki:Common.js/' . $article ] = [ 'type' => 'script' ];
			} elseif (
				substr( $article, -4 ) === '.css'
				|| substr( $article, -5 ) === '.less'
				|| substr( $article, -5 ) === '.scss'
			) {
				$pages[ 'MediaWiki:Common.css/' . $article ] = [ 'type' => 'style' ];
			}
		}
		return $pages;
	}

	/**
	 * Override of the same function in order to support ES6 files
	 * Duplicate of https://gerrit.wikimedia.org/g/mediawiki/core/+/6fd9245f4ce47a77dc76f70994952cd6da2d1db7/includes/ResourceLoader/Module.php#1083
	 * Can be removed whenever MW core ships a js version we're happy with
	 * @param string $fileName
	 * @param string $contents
	 * @return string
	 */
	protected function validateScriptFile( $fileName, $contents ) {
		if ( !$this->getConfig()->get( MainConfigNames::ResourceLoaderValidateJS ) ) {
			return $contents;
		}
		$cache = MediaWikiServices::getInstance()->getMainWANObjectCache();
		// Cache potentially slow parsing of JavaScript code during the
		// critical path. This happens lazily when responding to requests
		// for modules=site, modules=user, and Gadgets.
		$error = $cache->getWithSetCallback(
			$cache->makeKey(
				'resourceloader-userjsparse',
				self::USERJSPARSE_CACHE_VERSION,
				md5( $contents ),
				$fileName
			),
			$cache::TTL_WEEK,
			static function () use ( $contents, $fileName ) {
				try {
					Peast::ES2020( $contents )->parse();
				} catch ( PeastSyntaxException $e ) {
					return $e->getMessage() . " on line " . $e->getPosition()->getLine();
				}
				// Cache success as null
				return null;
			}
		);
		if ( $error ) {
			// Send the error to the browser console client-side.
			// By returning this as replacement for the actual script,
			// we ensure user-provided scripts are safe to include in a batch
			// request, without risk of a syntax error in this blob breaking
			// the response itself.
			return 'mw.log.error(' .
				json_encode(
					'Parse error: ' . $error . ' for file ' . $fileName
				) .
				');';
		}
		return $contents;
	}

	/**
	 * @param Context $context
	 * @return array
	 */
	public function getStyles( Context $context ) {
		$less = '';
		$scss = '';
		foreach ( $this->getPages( $context ) as $titleText => $options ) {
			if ( $options[ 'type' ] !== 'style' ) {
				continue;
			}
			$style = $this->getContent( $titleText, $context );
			if ( strval( $style ) === '' ) {
				continue;
			}

			if ( strpos( $style, '@import' ) !== false ) {
				$style = '/* using @import is forbidden */';
			}

			$style = ResourceLoader::makeComment( $titleText ) . $style;
			if ( substr( $titleText, -5 ) === '.scss' ) {
				$scss .= $style;
			} else {
				$less .= $style;
			}
		}
		/* start of less parser */
		try {
			$lessc = new Less_Parser;
			$lessc->parse( $less );
			$compiledLess = $lessc->getCss();
		} catch ( \Exception $e ) {
			$compiledLess = '/* invalid less: ' . $e->getMessage() . ' */';
		}
		/* end of less parser */

		/* start of scss parser */
		try {
			$compiler = new SCSSCompiler();
			$compiledScss = $compiler->compileString( $scss )->getCss();
		} catch ( \Exception $e ) {
			$compiledScss = '/* invalid scss: ' . $e->getMessage() . ' */';
		}
		/* end of scss parser */

		$css = $compiledLess . $compiledScss;

		if ( $this->getFlip( $context ) ) {
			$css = CSSJanus::transform( $css, true, false );
		}
		$css = MemoizedCallable::call(
			'CSSMin::remap',
			[ $css, false, $this->getConfig()->get( 'ScriptPath' ), true ]
		);

		return [ 'all' => [ $css ] ];
	}

	/**
	 * Get group name
	 *
	 * @return string
	 */
	public function getGroup() {
		return 'pages';
	}

}
