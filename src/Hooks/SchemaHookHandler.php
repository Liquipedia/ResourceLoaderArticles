<?php

namespace Liquipedia\Extension\ResourceLoaderArticles\Hooks;

use DatabaseUpdater;
use MediaWiki\Installer\Hook\LoadExtensionSchemaUpdatesHook;

class SchemaHookHandler implements
	LoadExtensionSchemaUpdatesHook
{

	/**
	 * Handle database updates
	 * @param DatabaseUpdater $updater
	 */
	public function onLoadExtensionSchemaUpdates( $updater ) {
		$db = $updater->getDB();

		if ( !$db->tableExists( 'resourceloaderarticles', __METHOD__ ) ) {
			$updater->output( "Creating resourceloaderarticles table resourceloaderarticles ...\n" );
			$db->sourceFile( __DIR__ . '/../sql/resourceloaderarticles.sql' );
			$updater->output( "done.\n" );
		} elseif ( !$db->fieldExists( 'resourceloaderarticles', 'rla_priority', __METHOD__ ) ) {
			$updater->output( "Adding `rla_priority` field to resourceloaderarticles table resourceloaderarticles ...\n" );
			$db->sourceFile( __DIR__ . '/../sql/resourceloaderarticlesPriorityMigration.sql' );
			$updater->output( "done.\n" );
		} else {
			$updater->output( "...resourceloaderarticles table already exists.\n" );
		}
	}

}
