<?php

function ParseSettings()
{
	global $uins,$white_list;
	$f = fopen(dirname(__FILE__).'/settings.dat','r');
	$t = fgets($f);
	$t = unserialize($t);
	if ($t)
	{
		$uins = $t[0];
		$white_list = $t[1];
	}
	foreach ($uins as $ky=>$uin)
	{
		$uins[$ky]['status'] = 0;
		$uins[$ky]['role'] = 0;
		$uins[$ky]['typing'] = 0;
		$uins[$ky]['notify'] = 0;
		$uins[$ky]['msgs'] = 0;
		$uins[$ky]['txts'] = 0;
	}
	fclose($f);
}

function SaveSettings()
{
	global $uins,$white_list;
	$f = fopen(dirname(__FILE__).'/settings.dat','w');
	fwrite($f,serialize(array($uins,$white_list))."\r\n");
	fclose($f);
}

function makeLogin($ui,$command)
{
	global $did,$icq,$lang,$settings,$uins;
	$command = str_replace(chr(10),'',$command);
	$command = str_replace(chr(13),'',$command);
	if (strlen($command)>4)	// '/in nickname'
	{
		$command = explode(' ',$command);
		$nick = $command[1];
		$uins[$ui] = array(
			'uin'=>$ui,
			'nick'=>$nick,
			'status'=>1,
			'role'=>1,
			'typing' => 0,
			'notify' => 0,
			'msgs' => 0,
			'msgt' => 0,
			'txts' => 0,
			'txtt' => 0,
			'settings'=>array(
				'lang'=>$settings['DEFAULTLANGUAGE'],
				'autologin'=>false,
				'autologout'=>false,
				'callback'=>false,
				'notify'=>array(
					'status'=>false,
					'system'=>true,
				),
				'invisible'=>false,
			),
		);
		notifyStatus($ui,'in');
	}
	else
	{
		$did = $icq->getShortInfo($ui);
		$did = $ui;
	}
}

function notifyStatus($nuin,$event)
{
	global $icq,$uins,$lang,$settings;
	if ($event=='in')
	{
		$messag = $lang[$uins[$nuin]['settings']['lang']]['youloggedin'];
		$messag .= "\r\n".$lang[$uins[$nuin]['settings']['lang']]['currentsettings'];
		foreach ($uins[$nuin]['settings'] as $ky=>$set)
		{
			if (is_array($set))
			{
				$messag .= "\r\n- ".$lang[$uins[$nuin]['settings']['lang']][$ky];
				foreach ($set as $k=>$s)
				{
					$messag .= "\r\n-- ".$lang[$uins[$nuin]['settings']['lang']][$k].$lang[$uins[$nuin]['settings']['lang']][($s?'yes':'no')];
				}
			}
			else
			{
				if ($ky=='lang')
				{
					$messag .= "\r\n- ".$lang[$uins[$nuin]['settings']['lang']][$ky].$lang[$uins[$nuin]['settings']['lang']][$set];
				}
				else
				{
					if ($ky=='invisible' && $nuin!=$settings['admin']['uin']) continue;
					$messag .= "\r\n- ".$lang[$uins[$nuin]['settings']['lang']][$ky].$lang[$uins[$nuin]['settings']['lang']][($set?'yes':'no')];
				}
			}
		}
		$icq->sendMessage($nuin,$messag);
	}
	foreach ($uins as $uin)
	{
		if ($uin['uin'] != $nuin && $uin['status']==1 && $uin['settings']['notify']['status'])
		{
			if ($event!='kick' && $event!='ban')
			{
				$icq->sendMessage($uin['uin'], $uins[$nuin]['nick']." (".$nuin.") ".$lang[$uin['settings']['lang']]['smblogged'.$event]);
			}
			else
			{
				$icq->sendMessage($uin['uin'], $lang[$uin['settings']['lang']]['smblogged'.$event].$uins[$nuin]['nick']." (".$nuin.") ");
			}
		}
	}
}

function notifySystem($event)
{
	global $uins,$settings,$icq,$lang;
	foreach ($uins as $uin)
	{
		if ($uin['uin'] != $settings['admin']['uin'] &&  $uin['status']==1 && $uin['settings']['notify']['system'])
		{
			$icq->sendMessage($uin['uin'], $lang[$uin['settings']['lang']][$event]);
		}
	}
}

function userMessage()
{
	global $uins,$icq,$msg,$settings;
	if (!$uins[$msg['from']]['settings']['invisible'])
	{
		foreach ($uins as $uin)
		{
			if ($uin['status']==1 && ($uin['uin']!=$msg['from'] || $uins[$msg['from']]['settings']['callback']))
			{
				$icq->sendMessage($uin['uin'],$uins[$msg['from']]['nick'].': '.$msg['message']);
				//$icq->sendMessage($uin['uin'],iconv('cp1251','UTF-16',$uins[$msg['from']]['nick'].': '.$msg['message']));
			}
		}
		$uins[$msg['from']]['msgs']++;
		$uins[$msg['from']]['msgt']++;
		$uins[$msg['from']]['txts'] += strlen($msg['message']);
		$uins[$msg['from']]['txtt'] += strlen($msg['message']);
		saveSettings();
		if ($settings['HISTORY'])
		{
			$f = fopen('history.txt','a');
			fwrite($f,'('.date('j.m.Y H:i:s').') '.$uins[$msg['from']]['nick'].': '.$msg['message']."\r\n");
			fclose($f);
		}
	}
}

?>
