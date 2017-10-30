<?php

/**
 * Hooks for PeriodicRelatedChanges
 *
 * Copyright (C) 2016  NicheWork, LLC
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @author Mark A. Hershberger <mah@nichework.com>
 */

namespace MediaWiki\Extension\PeriodicRelatedChanges;

use Category;
use Content;
use DatabaseUpdater;
use GlobalVarConfig;
use Revision;
use Status;
use User;
use WikiPage;

class Hook {
	/**
	 * Schema update handler
	 *
	 * @param DatabaseUpdater $db to for upddates
	 */
	public static function onLoadExtensionSchemaUpdates(
		DatabaseUpdater $db
	) {
		$db->addExtensionTable(
			'periodic_related_change',
			__DIR__ . '/../sql/periodic_related_change.sql'
		);
	}

	/**
	 * Bundling handler
	 *
	 * @param Event $event to bundle
	 * @param string &$bundleString to use
	 */
	public static function onEchoGetBundleRules(
		EchoEvent $event, &$bundleString
	) {
		switch ( $event->getType() ) {
		case 'periodic-related-changes':
			$bundleString = 'periodic-related-changes';
		break;
		}
	}

	/**
	 * Define the PeriodicRelatedChanges notifications
	 *
	 * @param array &$notifications assoc array of notification types
	 * @param array &$notificationCategories assoc array describing
	 *        categories
	 * @param array &$icons assoc array of icons we define
	 */
	public static function onBeforeCreateEchoEvent(
		array &$notifications, array &$notificationCategories, array &$icons
	) {
		$icons['periodic-related-changes']['path']
			= 'PeriodicRelatedChanges/assets/periodic.svg';

		$notifications['periodic-related-changes'] = [
			'bundle' => [
				'web' => true,
				'email' => true,
				'expandable' => true,
			],
			'category' => 'periodic-related-changes',
			'group' => 'neutral',
			'user-locators' => [ 'PeriodicRelatredChanges\\Hook::userLocater' ],
			'user-filters' => [ 'PeriodicRelatredChanges\\Hook::userFilter' ],
			'presentation-model'
			=> 'PeriodicRelatredChanges\\EchoEventPresentationModel',
		];

		$notificationCategories['periodic-related-changes'] = [
			'priority' => 2
		];
	}

	/**
	 * Locate users to notify for our events
	 *
	 * @param EchoEvent $event we are handling
	 * @return array of users
	 */
	public static function userLocater( EchoEvent $event ) {
		return RelatedChangeWatcher::getRelatedChangeWatchers(
			$event->getTitle()
		);
	}

	/**
	 * Filter out these users
	 *
	 * @param EchoEvent $event we are handling
	 * @return array of users
	 */
	public static function userFilter( EchoEvent $event ) {
		return [ $event->getAgent() ];
	}

	/**
	 * Register a config thingy
	 *
	 * @return GlobalVarConfig
	 */
	public static function makeConfig() {
		return new GlobalVarConfig( "PeriodicRelatedChanges" );
	}

	/**
	 * When a page is modified.
	 * Occurs after the save page request has been processed
	 *
	 * @param Wikipage $article WikiPage modified
	 * @param User $user User performing the modification
	 * @param Content $content New content
	 * @param string $summary Edit summary/comment
	 * @param bool $isMinor Whether or not the edit was marked as minor
	 * @param null $isWatch (No longer used)
	 * @param null $section (No longer used)
	 * @param int &$flags Flags passed to WikiPage::doEditContent()
	 * @param Revision $revision saved content. This parameter may be null
	 * @param Status $status about to be returned by doEditContent()
	 * @param bool|int $baseRevId the rev ID (or false) this edit was based on
	 * @param int $undidRevId the rev id (or 0) this edit undid
	 *
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/PageContentSaveComplete
	 * @see https://doc.wikimedia.org/mediawiki-core/master/php/classWikiPage.html#a1a69c99a33a08b923d5482254223cad5
	 * for definition of flags
	 */
	public static function onPageContentSaveComplete(
		WikiPage $article, User $user, Content $content, $summary, $isMinor,
		$isWatch, $section, &$flags, Revision $revision, Status $status,
		$baseRevId, $undidRevId = 0
	) {
		if (
			RelatedChangeWatcher::hasRelatedChangeWatchers(
				$article->getTitle()
			)
		) {
			global $wgContLang;
			EchoEvent::create( [
				'type' => 'periodic-related-changes',
				'title' => $title,
				'extra' => [
					'revid' => $revision->getId(),
					'source' => $source,
					'excerpt' => EchoDiscussionParser::getEditExcerpt(
						$revision, $wgContLang
					),
				],
				'agent' => $user,
			] );
		}
	}
}
