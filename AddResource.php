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
$wgHooks['LoadAllMessages'][] = 'AddResource::loadMessages';

switch ( $wgLanguageCode ) {
	case 'en':
		$wgSpecialPages[ 'AddResource' ] = 'AddResource';
		break;
	case 'de':
		$wgSpecialPages[ 'Materialien hinzufüge' ] = 'AddResource';
		break;
	default:
		$wgSpecialPages[ 'AddResource' ] = 'AddResource';
		break;
}

?>
