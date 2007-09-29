<?php
# Not a valid entry point, skip unless MEDIAWIKI is defined
if (!defined('MEDIAWIKI')) {
	echo <<<EOT
To install my extension, put the following line in LocalSettings.php:
require_once( "$IP/extensions/AddResource/AddResource.php" );
EOT;
	exit( 1 );
}

$wgAutoloadClasses['AddResource'] = dirname(__FILE__) . '/SpecialAddResource.php';
$wgSpecialPages[ 'AddResource' ] = 'AddResource';
$wgHooks['LoadAllMessages'][] = 'AddResource::loadMessages';
$wgHooks['LangugeGetSpecialPageAliases'][] = 'AddResource_LocalizedPageName';

function AddResource_LocalizedPageName( &$specialPageArray, $code) {
	AddResource::loadMessages();
	$text = wfMsg('addresource');

	# Convert from title in text form to DBKey and put it into the alias array:
	$title = Title::newFromText( $text );
	$specialPageArray['AddResource'][] = $title->getDBKey();

	return true;
}

?>
