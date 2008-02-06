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

$wgExtensionCredits['specialpage'][] = array(
	'name' => 'AddResource',
	'description' => 'This special page allows you to \'\'\'attach\'\'\' resources to a given page',
	'version' => '1.2-1.11.0',
	'author' => 'Mathias Ertl',
	'url' => 'http://pluto.htu.tuwien.ac.at/devel_wiki/index.php/AddResource',
);

function AddResource_LocalizedPageName( &$specialPageArray, $code) {
	AddResource::loadMessages();
	$text = wfMsg('addresource');

	# Convert from title in text form to DBKey and put it into the alias array:
	$title = Title::newFromText( $text );
	$specialPageArray['AddResource'][] = $title->getDBKey();

	return true;
}

?>
