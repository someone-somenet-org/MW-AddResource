<?php
# Not a valid entry point, skip unless MEDIAWIKI is defined
if (!defined('MEDIAWIKI')) {
	echo <<<EOT
To install the AddResource extension, you must put at least the following
code in LocalSettings.php:
require_once( "$IP/extensions/AddResource/AddResource.php" );

Note that several variables have to be set for this extension to become
usefull. For full documentation please see:
	http://pluto.htu.tuwien.ac.at/devel_wiki/AddResource
EOT;
	exit( 1 );
}

/* this is boilerplate (hook-registration etc.) */
$dir = dirname(__FILE__);
$wgAutoloadClasses['AddResource'] = dirname(__FILE__) . '/SpecialAddResource.php';
$wgExtensionMessagesFiles['AddResource'] = $dir . '/AddResource.i18n.php';
$wgSpecialPages[ 'AddResource' ] = 'AddResource';
$wgHooks['LanguageGetSpecialPageAliases'][] = 'efAddResourceLocalizedPageName';
$wgHooks['SkinTemplateContentActions'][] = 'efAddResourceDisplayTab';

$wgExtensionCredits['specialpage'][] = array(
	'name' => 'AddResource',
	'description' => 'This special page allows you to \'\'\'attach\'\'\' resources to a given page',
	'version' => '1.5.2-1.13.1',
	'author' => 'Mathias Ertl',
	'url' => 'http://pluto.htu.tuwien.ac.at/devel_wiki/AddResource',
);

/**
 * These functions adds the localized pagename of the "Add resource" special-
 * page.
 * @param array $specialPageArray the current array of special pages
 * @param unknown $code unknown.
 */
function efAddResourceLocalizedPageName( &$specialPageArray, $code) {
        wfLoadExtensionMessages('AddResource');
        $textMain = wfMsgForContent('addresource');
        $textUser = wfMsg('addresource');

        # Convert from title in text form to DBKey and put it into the alias array:
        $titleMain = Title::newFromText( $textMain );
        $titleUser = Title::newFromText( $textUser );
        $specialPageArray['AddResource'][] = $titleMain->getDBKey();
        $specialPageArray['AddResource'][] = $titleUser->getDBKey();

        return true;
}

/**
 * This function is responsible for adding the tabs for the subject, talk and
 * resources-page.
 * 
 * @param array tabs the original tabs
 * @return boolean always true, there is no error-condition.
 */
function efAddResourceDisplayTab( $tabs ) {
	/* some variables needed immediatly */
	global $wgTitle, $wgAddResourceTab, $wgResourcesTabs;
	$addResourcesTitle = SpecialPage::getTitleFor( 'AddResource' );
	$curSpecialPage = $wgTitle->getPrefixedText();

	/* return if not on the right page or not enabled */
	if ( $curSpecialPage != $addResourcesTitle ||
			! $wgAddResourceTab || ! $wgResourcesTabs )
		return true;

	/* get the requested page */
	global $wgRequest, $wgUser;
	$par = ereg_replace( $wgTitle->getPrefixedDBkey() . '/?', '',
		$wgRequest->getVal( 'title' ) );
	if ( $par == '' ) // if no /par was given
		return true;

	/* build the subject page and add the tab */
	$skin = $wgUser->getSkin();
	$parTitle = Title::newFromText( $par )->getSubjectPage();
	$nskey = $parTitle->getNamespaceKey();
	$customTabs[$nskey] = $skin->tabAction(
		$parTitle, $nskey, false, '', true);

	/* build the talk page and add the tab */
	$parTalkTitle = $parTitle->getTalkPage();
	$customTabs['talk'] = $skin->tabAction(
		$parTalkTitle, 'talk', false, '', true);

	// build subject-page tab:
	$resourcesTitle = SpecialPage::getTitleFor( 'Resources' );
	$resourcesPage = new Resources();
	$resourcesCount = $resourcesPage->getResourceListCount( $parTitle );

	$customTabs['view-resources'] = array ( 'class' => $resourcesCount ? false : 'new',
		'text' => wfMsg('ResourcesTab'),
		'href' => $resourcesTitle->getLocalURL() . '/' .
			$parTitle->getPrefixedDBkey()
	);

	$customTabs['add-resource'] = array ( 'class' => 'selected',
		'text' => wfMsg('addResourceTab'),
		'href' => $tabs['nstab-special']['href'] 
	);
		
	$tabs = $customTabs;
	return true;
}

?>
