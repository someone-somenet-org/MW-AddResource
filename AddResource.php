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
    exit(1);
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
$wgExtensionMessagesFiles['AddResource'] = __DIR__ . '/AddResource.i18n.php';
$wgSpecialPages[ 'AddResource' ] = 'AddResource';
$wgSpecialPageGroups[ 'AddResource' ] = 'other';

$wgExtensionCredits['specialpage'][] = array(
    'path' => __FILE__,
    'name' => 'AddResource',
    'description' => 'This special page allows you to \'\'\'attach\'\'\' resources to a given page',
    'version' => '2.1.0',
    'author' => 'Mathias Ertl',
    'url' => 'https://fs.fsinf.at/wiki/AddResource',
);

/**
 * Autoload classes
 */
$wgAutoloadClasses['AddResource'] = __DIR__ . '/SpecialAddResource.php';
$wgAutoloadClasses['UploadFileForm'] = __DIR__ . '/ResourceForms.php';
$wgAutoloadClasses['SubpageForm'] = __DIR__ . '/ResourceForms.php';
$wgAutoloadClasses['ExternalRedirectForm'] = __DIR__ . '/ResourceForms.php';
$wgAutoloadClasses['UploadResourceFromFile'] = __DIR__ . '/ResourceUploadBackends.php';
$wgAutoloadClasses['UploadResourceFromStash'] = __DIR__ . '/ResourceUploadBackends.php';

/**
 * Hook registration.
 */
$wgHooks['UploadCreateFromRequest'][] = 'wgAddResourceGetUploadRequestHandler';
$wgHooks['SkinTemplateNavigation::SpecialPage'][] = 'efAddResourceSpecialPage';

/**
 * Default values for most options.
 *
 * TODO.
 */
#$wgCategoryTreeDefaultOptions      = array();

function getResourcesUrl($title) {
    $resources = SpecialPage::getTitleFor('Resources');
    return $resources->getLocalURL() .'/'. $title->getPrefixedDBkey();
}

function efAddResourceSpecialPage($template, $links) {
    global $wgTitle, $wgRequest, $wgUser, $wgAddResourceTab;

    // return if we are not on the right special page
    if (!$wgTitle->isSpecial('AddResource')) {
        return true;
    }

    // parse subpage-part. We cannot use $wgTitle->getSubpage() because the
    // special namespaces doesn't have real subpages
    $prefixedText = $wgTitle->getPrefixedText();
    if (strpos($prefixedText, '/') === FALSE) {
        return true; // no page given
    }
    $parts = explode( '/', $prefixedText);
    $pageName = $parts[count( $parts ) - 1];

    $title = Title::newFromText($pageName)->getSubjectPage();
    $talkTitle = $title->getTalkPage();

    // Get AddResource URL:
    $resourceCount = getResourceCount($title);
    $resourcesUrl = getResourcesUrl($title);
    $resourcesText = getResourceTabText($resourceCount);
    $resourcesClass = $resourceCount > 0 ? 'is_resources' : 'new is_resources';

    $head = array (
        $title->getNamespaceKey('') => array(
            'class' => $title->exists() ? null : 'new',
            'text' => $title->getText(),
            'href' => $title->getLocalUrl(),
        ),
        'resources' => array(
            'class' => $resourcesClass,
            'text' => $resourcesText,
            'href' => $resourcesUrl,
        ),
    );
    $tail = array (
        $title->getNamespaceKey('') . '_talk' => array(
            'class' => $talkTitle->exists() ? null : 'new',
            'text' => wfMsg('Talk'),
            'href' => $talkTitle->getLocalUrl(),
        )
    );
    $resourceCount = getResourceCount($title);

    $links['namespaces'] = array_merge($head, $links['namespaces'], $tail);
    $links['namespaces']['special']['text'] = '+';

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
