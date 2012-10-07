<?php
# Not a valid entry point, skip unless MEDIAWIKI is defined
if (!defined('MEDIAWIKI')) {
    echo <<<EOT
To install the AddResource extension, you must put at least the following
code in LocalSettings.php:
require_once( "$IP/extensions/AddResource/AddResource.php" );

Note that several variables have to be set for this extension to become
useful. For full documentation please see:
    https:///fs.fsinf.at/wiki/AddResource
EOT;
    exit( 1 );
}

/**
 * Define some useful constants.
 */
define("ADD_RESOURCE_ACTION_UPLOAD", 1);
define("ADD_RESOURCE_ACTION_SUBPAGE", 2);
define("ADD_RESOURCE_ACTION_LINK", 3);
define("ADD_RESOURCE_ACTION_NAME", 'Action');
define("ADD_RESOURCE_ACTION_FIELD", 'wpAction');
define("ADD_RESOURCE_REFERER_NAME", 'ForArticle');
define("ADD_RESOURCE_REFERER_FIELD", "wpForArticle");

/**
 * Some extension boilerplate
 */
$dir = dirname(__FILE__);
$wgExtensionMessagesFiles['AddResource'] = $dir . '/AddResource.i18n.php';
$wgSpecialPages[ 'AddResource' ] = 'AddResource';
$wgSpecialPageGroups[ 'AddResource' ] = 'other';

$wgExtensionCredits['specialpage'][] = array(
    'path' => __FILE__,
    'name' => 'AddResource',
    'description' => 'This special page allows you to \'\'\'attach\'\'\' resources to a given page',
    'version' => '2.0.1-1.16.0',
    'author' => 'Mathias Ertl',
    'url' => 'https://fs.fsinf.at/wiki/AddResource',
);

/**
 * Autoload classes
 */
$wgAutoloadClasses['AddResource'] = $dir . '/SpecialAddResource.php';
$wgAutoloadClasses['UploadFileForm'] = $dir . '/ResourceForms.php';
$wgAutoloadClasses['SubpageForm'] = $dir . '/ResourceForms.php';
$wgAutoloadClasses['ExternalRedirectForm'] = $dir . '/ResourceForms.php';
$wgAutoloadClasses['UploadResourceFromFile'] = $dir . '/ResourceUploadBackends.php';
$wgAutoloadClasses['UploadResourceFromStash'] = $dir . '/ResourceUploadBackends.php';

/**
 * Hook registration.
 */
$wgHooks['LanguageGetSpecialPageAliases'][] = 'efAddResourceLocalizedPageName';
$wgHooks['SkinTemplateContentActions'][] = 'efAddResourceDisplayTab';
$wgHooks['UploadCreateFromRequest'][] = 'wgAddResourceGetUploadRequestHandler';

/**
 * Default values for most options.
 *
 * TODO.
 */
#$wgCategoryTreeDefaultOptions      = array();

/**
 * These functions adds the localized pagename of the "Add resource" special-
 * page.
 *
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

/**
 * Sets the upload handler to our special class in case the POST data includes
 * the ADD_RESOURCE_REFERER_FIELD value
 */
function wgAddResourceGetUploadRequestHandler( $type, $className ) {
    global $wgRequest;
    if ( ! $wgRequest->getText(ADD_RESOURCE_REFERER_FIELD) ) {
        return true;
    }

    switch ( $type ) {
        case "File":
            $className = 'UploadResourceFromFile';
            break;
        case "Stash":
            $className = 'UploadResourceFromStash';
            break;
        default:
            break;
    }
    return true;
}

/**
 * Primitive function that returns HTML for a Banner with the given text.
 * color is either red or green, default is red.
 */
function getBanner( $text, $div_id = 'random banner', $color = 'red' ) {
    $s = '<div id="' . $div_id . '">';
    $s .= '<table align="center" border="0" cellpadding="5" cellspacing="2"';
    switch ($color) {
        case 'red':
            $s .= '    style="border: 1px solid #FFA4A4; background-color: #FFF3F3; border-left: 5px solid #FF6666">';
            break;
        case 'green':
            $s .= '    style="border: 1px solid #A4FFA4; background-color: #F3FFF3; border-left: 5px solid #66FF66">';
            break;
        case 'grey':
            $s .= '    style="border: 1px solid #BDBDBD; background-color: #E6E6E6; border-left: 5px solid #6E6E6E">';
    }

    $s .= '<tr><td style=font-size: 95%;>';
    $s .= $text;
    $s .= '</td></tr></table></div>';
    return $s;
}

?>
