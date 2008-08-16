<?php
# Not a valid entry point, skip unless MEDIAWIKI is defined
if (!defined('MEDIAWIKI')) {
	echo <<<EOT
To install my extension, put the following line in LocalSettings.php:
require_once( "$IP/extensions/AddResource/AddResource.php" );
EOT;
	exit( 1 );
}

$dir = dirname(__FILE__);

$wgAutoloadClasses['AddResource'] = dirname(__FILE__) . '/SpecialAddResource.php';
$wgExtensionMessagesFiles['AddResource'] = $dir . '/AddResource.i18n.php';
$wgSpecialPages[ 'AddResource' ] = 'AddResource';
$wgHooks['LanguageGetSpecialPageAliases'][] = 'efAddResourceLocalizedPageName';
$wgHooks['SkinTemplateContentActions'][] = 'efAddResourceDisplayTab';

$wgExtensionCredits['specialpage'][] = array(
	'name' => 'AddResource',
	'description' => 'This special page allows you to \'\'\'attach\'\'\' resources to a given page',
	'version' => '1.5-1.13.0',
	'author' => 'Mathias Ertl',
	'url' => 'http://pluto.htu.tuwien.ac.at/devel_wiki/AddResource',
);

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

function efAddResourceDisplayTab( $tabs ) {
	global $wgTitle, $wgAddResourceTab, $wgResourcesTabs;
	$addResourcesTitle = SpecialPage::getTitleFor( 'AddResource' );
	$curSpecialPage = $wgTitle->getPrefixedText();

	/* return if not on the right page or not enabled */
	if ( $curSpecialPage != $addResourcesTitle ||
			! $wgAddResourceTab || ! $wgResourcesTabs )
		return true;

	global $wgRequest, $wgUser;
	$resourcesTitle = SpecialPage::getTitleFor( 'Resources' );
	$reqTitle = Title::newFromText( $wgRequest->getVal('title') );
        $par = preg_replace('/' . $curSpecialPage . '\/?/', '', $reqTitle->getPrefixedText() );
	if ( $par == '' ) // if no /par was given
		return true;
	$parTitle = Title::newFromText( $par )->getSubjectPage();
	$parTalkTitle = $parTitle->getTalkPage();

	/* build tabs */
	$skin = $wgUser->getSkin();
	$nskey = $parTitle->getNamespaceKey();
	
	// subject page and talk page:
	$customTabs[$nskey] = $skin->tabAction(
		$parTitle, $nskey, false, '', true);
	$customTabs['talk'] = $skin->tabAction(
		$parTalkTitle, 'talk', false, '', true);
	
	/* get number of resources */
	$resourcesPage = new Resources();
	$resourcesCount = $resourcesPage->getResourceListCount( $parTitle );

	$customTabs['view-resources'] = array ( 'class' => $resourcesCount ? false : 'new',
		'text' => wfMsg('ResourcesTab'),
		'href' => $resourcesTitle->getLocalURL() . '/' .
			$parTitle->getPrefixedText()
	);

	$customTabs['add-resource'] = array ( 'class' => 'selected',
		'text' => wfMsg('addResourceTab'),
		'href' => $tabs['nstab-special']['href'] 
	);
		
	$tabs = $customTabs;
	return true;
}

?>
