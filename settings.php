<?php
$settings = array(
	'UIN'=>'123456789',
	'PASSWORD'=>'password',
	'STARTXSTATUS'=>'studying',
	'STARTSTATUS'=>'STATUS_FREE4CHAT',
	'DEBUG_MODE'=>false,
	'DEFAULTLANGUAGE'=>'en',
	'MTN'=>false,
	'REBOOT'=>false,
	'HISTORY'=>true,
	'admin'=>array(
		'uin'=>'345206',
		'nick'=>'UZER',
		'settings'=>array(
			'lang'=>'ua',
		),
	),
	'options' => array(
		'UserAgent' => 'macicq',
//		'MessageCapabilities' => 'utf-8',
		'MessageType' => 'plain_text',
		'Encoding' => 'ASCII',
//		'ASCII' UNICODE', 'LATIN_1'
	),
);
// 'command' => array(permissions)
// permissions: 0 - everyone, 1: logged in, 2: admin only
$commands = array(
	'/in' => array(1),
	'/login' => array(2),
	'/help' => array(1),
	'/chat' => array(1),
	'/debug' => array(2),
	'/reload' => array(2),
	'/refresh' => array(2),
	'/settings' => array(1,array(
		'/settings lang' => array(1),
		'/settings autologin' => array(1),
		'/settings autologout' => array(1),
		'/settings invisible' => array(2),
		'/settings callback' => array(1),
		'/settings notify' => array(1,array(
			'/settings notify status' => array(1),
			'/settings notify system' => array(1),
		)),
	)),
	'/status' => array(1),
	'/stats' => array(1),
	'/out' => array(1),
);

$acommands = array(
	'/in' => 1,
	'/login' => 2,
	'/help' => 1,
	'/chat' => 1,
	'/debug' => 2,
	'/reload' => 2,
	'/refresh' => 2,
	'/settings' => 1,
	'/settings_lang' => 1,
	'/settings_autologin' => 1,
	'/settings_autologout' => 1,
	'/settings_invisible' => 2,
	'/settings_callback' => 1,
	'/settings_notify' => 1,
	'/settings_notify_status' => 1,
	'/settings_notify_system' => 1,
	'/status' => 1,
	'/stats' => 1,
	'/out' => 1,
);

$version = '1.6';

//uin description
//array
//uin - \d							user uin
//nick - \s							nickname
//status - 0:out, 1:in				status in conference
//role - \d							privileges level
//settings - array					settings for uin
//	lang -							preferred language
//	autologin - true|false			login when online
//	autologout - true|false			logout when offline
//	notifystatus - true|false		notify about users status cahnges
//	notifysystem - true|false		notify about system events
//	invisible - true|false			invisible mode. For admin only
?>