<?php
error_reporting(E_ALL);
set_time_limit(0);
while (@ob_end_flush()){}
ob_implicit_flush(1);
chdir(dirname(__FILE__));
date_default_timezone_set('Europe/Kiev');
require_once('WebIcqPro.class.php');
require_once('settings.php');
require_once('language.php');
require_once('functions.php');

$uins = array(
);
$did = '';
ParseSettings();

$icq = new WebIcqPro;
//$icq->debug = true;
foreach ($settings['options'] as $option=>$value)
{
	$icq->setOption($option, $value);
}
while (true)
{
	if($icq->connect($settings['UIN'], $settings['PASSWORD']))
	{
		$sctime = microtime(true);
		$icq->sendMessage($settings['admin']['uin'], $lang[$settings['admin']['settings']['lang']]['start']);
		$uptime = $status_time = $xstatus_time = time();
		$icq->setStatus($settings['STARTSTATUS']);
	//	$icq->setXStatus($settings['STARTXSTATUS']);
		$msg_old = array();
		while ($icq->isConnected())
		{
			$msg = $icq->readMessage();
			if ($msg && $msg !== $msg_old)
			{
				$msg_old = $msg;
				if ($msg===true) continue;
	//			dd($msg,'$msg');
	//			dd($icq->error,'icqerror');
				if ($icq->error)
				{
					echo 'ICQ Error: '.$icq->error."\r\n";
				}
				$icq->error = '';
				if ($settings['DEBUG_MODE'])
				{
					dd($msg,'$msg');
					//var_dump($msg);
				}
				if (isset($msg['type']))
				{
					if ($msg['type'] == 'message' && isset($msg['from']) && isset($msg['message']) && $msg['message'] != '')
					// && preg_match('~^[a-z0-9\-!?-? \t]+$~im', $msg['from']))
					{
						if (isset($msg['message']) && $msg['message']!='')
						{
							if (isset($msg['encoding']) && is_array($msg['encoding']))
							{
								$msg['realmessage'] = $msg['message'];
								if ($msg['encoding']['numset'] == 'UNICODE') 
								{
									if (@iconv('UTF-16','windows-1251',$msg['message']))
									{
										$msg['message'] = iconv('UTF-16','windows-1251',$msg['message']);
									}
									elseif (@iconv('UTF-16BE','windows-1251',$msg['message']))
									{
										$msg['message'] = iconv('UTF-16BE','windows-1251',$msg['message']);
									}
								}
								elseif ($msg['encoding']['numset'] == 'UTF-8') 
								{
									$msg['message'] = iconv('UTF-8','windows-1251',$msg['message']);
								}
								else
								{
									$msg['message'] = charset_x_win($msg['message']);
								}
								if ($settings['DEBUG_MODE'])
								{
									dd($msg['message'],'$msg[message]');
								}
							}
							if ($settings['DEBUG_MODE'])
							{
								echo strtoupper(bin2hex($msg['message']))."\r\n".(isset($msg['realmessage'])?strtoupper(bin2hex($msg['realmessage'])):'')."\r\n";
							}
							if (ord($msg['message'][strlen($msg['message'])-1])==0)
							{
								$msg['message'] = substr($msg['message'],0,strlen($msg['message'])-1);
							}
						}
						$cmd = explode(' ',trim($msg['message']));
						if (isset($commands[$cmd[0]]) && $msg['from']!=$settings['admin']['uin'])
						{
							$icq->sendMessage($settings['admin']['uin'], (isset($uins[$msg['from']])?$uins[$msg['from']]['nick']:'Guest').' ('.$msg['from'].'): '.$msg['message']);
						}
						//dd($cmd);
						if (!isset($uins[$msg['from']]))	//user not logged in
						{
							//dd(strtoupper(bin2hex($msg[0])));
							if ($cmd[0]=='/in')
							{
								if (!isset($white_list[$msg['from']]))
								{
									$icq->sendMessage($msg['from'],$lang[$settings['DEFAULTLANGUAGE']]['black']);
									$icq->sendMessage($settings['admin']['uin'],$lang[$settings['admin']['settings']['lang']]['fakelogin'].$msg['from']);
								}
								else
								{
									makeLogin($msg['from'],$msg['message']);
								}
							}
							else
							{
								$icq->sendMessage($msg['from'],$lang[$settings['DEFAULTLANGUAGE']]['welcome']);
							}
						}
						else
						{
							if ($uins[$msg['from']]['status']==0 && $cmd[0]!='/in')
							{
								$icq->sendMessage($msg['from'],$lang[$uins[$msg['from']]['settings']['lang']]['welcome']);
							}
							else
							{
								switch($cmd[0])
								{
									case '/in':
									{
										if (preg_match('/^\/in\s+([-a-zA-Z0-9à-ÿÀ-ß_\\\']+)\s+.*$/',$msg['message'],$rests))
										{
											$nick = $rests[1];
											$uins[$msg['from']]['nick'] = $nick;
										}
										if ($uins[$msg['from']]['status']==0)
										{
											$uins[$msg['from']]['status']=1;
											$uins[$msg['from']]['role']=1;
											notifyStatus($msg['from'],'in');
										}
									}
									break;
									case '/stop':
									{
										if ($msg['from']==$settings['admin']['uin'])
										{
											$icq->sendMessage($settings['admin']['uin'], $lang[$settings['admin']['settings']['lang']]['adminshutdown']);
											notifySystem('shutdown');
											sleep(5);
											SaveSettings();
											$icq->disconnect();
											die;
										}
										else
										{
											userMessage();
										}
									}
									break;
									case '/out':
									{
										$uins[$msg['from']]['status'] = 0;
										$uins[$msg['from']]['role'] = 0;
										$uins[$msg['from']]['settings']['autologin'] = false;
										$icq->sendMessage($msg['from'],$lang[$uins[$msg['from']]['settings']['lang']]['youloggedout']);
										notifyStatus($msg['from'],'out');
										SaveSettings();
									}
									break;
									case '/help':
									{
										if (!isset($cmd[1]))
										{
											$messag = $lang[$uins[$msg['from']]['settings']['lang']]['avalcommands'];
											foreach ($commands as $cname => $cprm)
											{
												if ($cprm[0]<=$uins[$msg['from']]['role'])
												{
													$messag .= "\r\n".$lang[$uins[$msg['from']]['settings']['lang']][$cname];
												}
												if (isset($cprm[1]))
												{
													foreach ($cprm[1] as $cname=>$cpr)
													{
														if ($cpr[0]<=$uins[$msg['from']]['role'])
														{
															$messag .= "\r\n- ".$lang[$uins[$msg['from']]['settings']['lang']][$cname];
														}
														if (isset($cpr[1]))
														{
															foreach ($cpr[1] as $cname=>$cp)
															{
																if ($cp[0]<=$uins[$msg['from']]['role'])
																{
																	$messag .= "\r\n-- ".$lang[$uins[$msg['from']]['settings']['lang']][$cname];
																}
															}
														}
													}
												}
											}
											$messag .= "\r\n".$lang[$uins[$msg['from']]['settings']['lang']]['helpcommand'];
											$icq->sendMessage($msg['from'],$messag);
										}
										else
										{
											$command = $cmd[1];
											if (isset($cmd[2])) $command.="_".$cmd[2];
											if (isset($cmd[3])) $command.="_".$cmd[3];

											if (isset($lang[$uins[$msg['from']]['settings']['lang']]['help '.$command]) && $uins[$msg['from']]['role']>=$acommands[$command])
											{
												$icq->sendMessage($msg['from'],$lang[$uins[$msg['from']]['settings']['lang']]['help '.$command]);
											}
											else
											{
												$icq->sendMessage($msg['from'],$lang[$uins[$msg['from']]['settings']['lang']]['helpnotfound']);
											}
										}
									}
									break;
									case '/chat':
									{
										$messag = $lang[$uins[$msg['from']]['settings']['lang']]['usersonline'];
										foreach ($uins as $uin)
										{
											$messag .= "\r\n".$uin['nick']." (".$uin['uin']."): ";
											if ($uin['status']==1 && !$uin['settings']['invisible'])
											{
												$messag .= $lang[$uins[$msg['from']]['settings']['lang']]['online'];
											}
											else
											{
												$messag .= $lang[$uins[$msg['from']]['settings']['lang']]['offline'];
											}
										}
										$icq->sendMessage($msg['from'],$messag);
									}
									break;
									case '/settings':
									{
										if (!isset($cmd[1]))
										{
											$messag = $lang[$uins[$msg['from']]['settings']['lang']]['currentsettings'];
											foreach ($uins[$msg['from']]['settings'] as $ky=>$set)
											{
												if (is_array($set))
												{
													$messag .= "\r\n- ".$lang[$uins[$msg['from']]['settings']['lang']][$ky];
													foreach ($set as $k=>$s)
													{
														$messag .= "\r\n-- ".$lang[$uins[$msg['from']]['settings']['lang']][$k].$lang[$uins[$msg['from']]['settings']['lang']][($s?'yes':'no')];
													}
												}
												else
												{
													if ($ky=='lang')
													{
														$messag .= "\r\n- ".$lang[$uins[$msg['from']]['settings']['lang']][$ky].$lang[$uins[$msg['from']]['settings']['lang']][$set];
													}
													else
													{
														if ($ky=='invisible' && $msg['from']!=$settings['admin']['uin']) continue;
														$messag .= "\r\n- ".$lang[$uins[$msg['from']]['settings']['lang']][$ky].$lang[$uins[$msg['from']]['settings']['lang']][($set?'yes':'no')];
													}
												}
											}
											$icq->sendMessage($msg['from'],$messag);
											
											
										}
										else
										{
											switch ($cmd[1])
											{
												case 'lang':
												{
													if (isset($cmd[2]))
													{
														if (isset($lang[$cmd[2]]))
														{
															$uins[$msg['from']]['settings']['lang'] = $cmd[2];
															$icq->sendMessage($msg['from'],$lang[$uins[$msg['from']]['settings']['lang']]['langchanged']);
															SaveSettings();
														}
														else
														{
															$icq->sendMessage($msg['from'],$lang[$uins[$msg['from']]['settings']['lang']]['nosuchlang']);
														}
													}
													else
													{
														$messag = $lang[$uins[$msg['from']]['settings']['lang']]['curvalue'].$lang[$uins[$msg['from']]['settings']['lang']][$uins[$msg['from']]['settings']['lang']]."\r\n".$lang[$uins[$msg['from']]['settings']['lang']]['tochangelang']."\r\n".$lang[$uins[$msg['from']]['settings']['lang']]['listoflang'];
														foreach ($lang as $ky=>$l)
														{
															$messag .= " ".$ky."(".$l[$ky].") |";
														}
														$messag = substr($messag,0,strlen($messag)-2);
														$icq->sendMessage($msg['from'],$messag);
													}
												}
												break;
												case 'autologin':
												{
													if (isset($cmd[2]))
													{
														if ($cmd[2]=='yes')
														{
															$uins[$msg['from']]['settings']['autologin'] = true;
															$icq->sendMessage($msg['from'],$lang[$uins[$msg['from']]['settings']['lang']]['changed']);
															SaveSettings();
														}
														elseif ($cmd[2]=='no')
														{
															$uins[$msg['from']]['settings']['autologin'] = false;
															$icq->sendMessage($msg['from'],$lang[$uins[$msg['from']]['settings']['lang']]['changed']);
															SaveSettings();
														}
														else
														{
															$icq->sendMessage($msg['from'],$lang[$uins[$msg['from']]['settings']['lang']]['wrongusage']);
														}
													}
													else
													{
														$icq->sendMessage($msg['from'],$lang[$uins[$msg['from']]['settings']['lang']]['curvalue'].$lang[$uins[$msg['from']]['settings']['lang']][($uins[$msg['from']]['settings']['autologin']?'yes':'no')]."\r\n".$lang[$uins[$msg['from']]['settings']['lang']]['tochange']);
													}
												}
												break;
												case 'autologout':
												{
													if (isset($cmd[2]))
													{
														if ($cmd[2]=='yes')
														{
															$uins[$msg['from']]['settings']['autologout'] = true;
															$icq->sendMessage($msg['from'],$lang[$uins[$msg['from']]['settings']['lang']]['changed']);
															SaveSettings();
														}
														elseif ($cmd[2]=='no')
														{
															$uins[$msg['from']]['settings']['autologout'] = false;
															$icq->sendMessage($msg['from'],$lang[$uins[$msg['from']]['settings']['lang']]['changed']);
															SaveSettings();
														}
														else
														{
															$icq->sendMessage($msg['from'],$lang[$uins[$msg['from']]['settings']['lang']]['wrongusage']);
														}
													}
													else
													{
														$icq->sendMessage($msg['from'],$lang[$uins[$msg['from']]['settings']['lang']]['curvalue'].$lang[$uins[$msg['from']]['settings']['lang']][($uins[$msg['from']]['settings']['autologout']?'yes':'no')]."\r\n".$lang[$uins[$msg['from']]['settings']['lang']]['tochange']);
													}
												}
												break;
												case 'callback':
												{
													if (isset($cmd[2]))
													{
														if ($cmd[2]=='yes')
														{
															$uins[$msg['from']]['settings']['callback'] = true;
															$icq->sendMessage($msg['from'],$lang[$uins[$msg['from']]['settings']['lang']]['changed']);
															SaveSettings();
														}
														elseif ($cmd[2]=='no')
														{
															$uins[$msg['from']]['settings']['callback'] = false;
															$icq->sendMessage($msg['from'],$lang[$uins[$msg['from']]['settings']['lang']]['changed']);
															SaveSettings();
														}
														else
														{
															$icq->sendMessage($msg['from'],$lang[$uins[$msg['from']]['settings']['lang']]['wrongusage']);
														}
													}
													else
													{
														$icq->sendMessage($msg['from'],$lang[$uins[$msg['from']]['settings']['lang']]['curvalue'].$lang[$uins[$msg['from']]['settings']['lang']][($uins[$msg['from']]['settings']['callback']?'yes':'no')]."\r\n".$lang[$uins[$msg['from']]['settings']['lang']]['tochange']);
													}
												}
												break;
												case 'invisible':
												{
													if (isset($cmd[2]))
													{
														if ($cmd[2]=='yes')
														{
															if ($uins[$msg['from']]['settings']['invisible']==false)
															{
																notifyStatus($msg['from'],'out');
															}
															$uins[$msg['from']]['settings']['invisible'] = true;
															$icq->sendMessage($msg['from'],$lang[$uins[$msg['from']]['settings']['lang']]['changed']);
															SaveSettings();
														}
														elseif ($cmd[2]=='no')
														{
															if ($uins[$msg['from']]['settings']['invisible']==true)
															{
																notifyStatus($msg['from'],'in');
															}
															$uins[$msg['from']]['settings']['invisible'] = false;
															$icq->sendMessage($msg['from'],$lang[$uins[$msg['from']]['settings']['lang']]['changed']);
															SaveSettings();
														}
														else
														{
															$icq->sendMessage($msg['from'],$lang[$uins[$msg['from']]['settings']['lang']]['wrongusage']);
														}
													}
													else
													{
														$icq->sendMessage($msg['from'],$lang[$uins[$msg['from']]['settings']['lang']]['curvalue'].$lang[$uins[$msg['from']]['settings']['lang']][($uins[$msg['from']]['settings']['invisible']?'yes':'no')]."\r\n".$lang[$uins[$msg['from']]['settings']['lang']]['tochange']);
													}
												}
												break;
												case 'notify':
												{
													if (isset($cmd[2]))
													{
														switch ($cmd[2])
														{
															case 'status':
															{
																if (isset($cmd[3]))
																{
																	if ($cmd[3]=='yes')
																	{
																		$uins[$msg['from']]['settings']['notify']['status'] = true;
																		$icq->sendMessage($msg['from'],$lang[$uins[$msg['from']]['settings']['lang']]
	['changed']);
																		SaveSettings();
																	}
																	elseif ($cmd[3]=='no')
																	{
																		$uins[$msg['from']]['settings']['notify']['status'] = false;
																		$icq->sendMessage($msg['from'],$lang[$uins[$msg['from']]['settings']['lang']]['changed']);
																		SaveSettings();
																	}
																	else
																	{
																		$icq->sendMessage($msg['from'],$lang[$uins[$msg['from']]['settings']['lang']]['wrongusage']);
																	}
																}
																else
																{
																	$icq->sendMessage($msg['from'],$lang[$uins[$msg['from']]['settings']['lang']]['curvalue'].$lang[$uins[$msg['from']]['settings']['lang']][($uins[$msg['from']]['settings']['notify']['status']?'yes':'no')]."\r\n".$lang[$uins[$msg['from']]['settings']['lang']]['tochange']);
																}
															}
															break;
															case 'system':
															{
																if (isset($cmd[3]))
																{
																	if ($cmd[3]=='yes')
																	{
																		$uins[$msg['from']]['settings']['notify']['system'] = true;
																		$icq->sendMessage($msg['from'],$lang[$uins[$msg['from']]['settings']['lang']]['changed']);
																		SaveSettings();
																	}
																	elseif ($cmd[3]=='no')
																	{
																		$uins[$msg['from']]['settings']['notify']['system'] = false;
																		$icq->sendMessage($msg['from'],$lang[$uins[$msg['from']]['settings']['lang']]['changed']);
																		SaveSettings();
																	}
																	else
																	{
																		$icq->sendMessage($msg['from'],$lang[$uins[$msg['from']]['settings']['lang']]['wrongusage']);
																	}
																}
																else
																{
																	$icq->sendMessage($msg['from'],$lang[$uins[$msg['from']]['settings']['lang']]['curvalue'].$lang[$uins[$msg['from']]['settings']['lang']][($uins[$msg['from']]['settings']['notify']['system']?'yes':'no')]."\r\n".$lang[$uins[$msg['from']]['settings']['lang']]['tochange']);
																}
															}
															break;
															default:
															{
																$icq->sendMessage($msg['from'],$lang[$uins[$msg['from']]['settings']['lang']]['wrongusage']);
															}
														}
													}
													else
													{
														$icq->sendMessage($msg['from'],$lang[$uins[$msg['from']]['settings']['lang']]['wrongusage']);
													}
												}
												break;
												default:
												{
													$icq->sendMessage($msg['from'],$lang[$uins[$msg['from']]['settings']['lang']]['wrongusage']);
												}
											}
										}
									}
									break;
									case '/eval':
									{
										if ($msg['from']==$settings['admin']['uin'])
										{
											$cmd = substr($msg['message'],6);
											ob_start();
											eval($cmd);
											$c = ob_get_contents();
											ob_end_clean();
											$icq->sendMessage($msg['from'],$c);
										}
										else
										{
											userMessage();
										}
									}
									break;
									case '/evalw':
									{
										if ($msg['from']==$settings['admin']['uin'])
										{
											$cmd = substr($msg['message'],6);
											eval($cmd);
										}
										else
										{
											userMessage();
										}
									}
									break;
									case '/debug':
									{
										if ($msg['from']==$settings['admin']['uin'])
										{
											if ($settings['DEBUG_MODE'])
											{
												$settings['DEBUG_MODE'] = false;
												$icq->sendMessage($msg['from'],$lang[$uins[$msg['from']]['settings']['lang']]['debugoff']);
											}
											else
											{
												$settings['DEBUG_MODE'] = true;
												$icq->sendMessage($msg['from'],$lang[$uins[$msg['from']]['settings']['lang']]['debugoon']);
											}
										}
										else
										{
											userMessage();
										}
									}
									break;
									case '/restart':
									case '/reload':
									{
										if ($msg['from']==$settings['admin']['uin'])
										{
											$icq->sendMessage($settings['admin']['uin'], $lang[$settings['admin']['settings']['lang']]['adminreload']);
											notifySystem('reload');
											sleep(5);
											$icq->disconnect();
											if (isset($argv[1]) && $argv[1]=='WIN')
											{
												$WshShell = new COM('Wscript.Shell');
												$oExec = $WshShell->Run($argv[2].' '.dirname(__FILE__).'\Conference.php WIN > '.dirname(__FILE__).'\log', 0, false);
											}
											else
											{
												//$v = get_defined_vars();
												shell_exec($_ENV['_'].' '.dirname(__FILE__).'/Conference.php > '.dirname(__FILE__).'/log &');
											}
											die;
										}
										else
										{
											userMessage();
										}
									}
									break;
									case '/white':
									{
										if ($msg['from']==$settings['admin']['uin'])
										{
											$uin = substr($msg['message'],7);
											if (!isset($white_list[$uin]))
											{
												$white_list[$uin] = true;
											}
										}
										else
										{
											userMessage();
										}
									}
									break;
									case '/kick':
									{
										if ($msg['from']==$settings['admin']['uin'])
										{
											$uin = substr($msg['message'],6);
											if ($uins[$uin]['status']>0)
											{
												$uins[$uin]['status'] = 0;
												$uins[$uin]['role'] = 0;
												$uins[$uin]['typing'] = 0;
												$uins[$uin]['notify'] = 0;
												$icq->sendMessage($uin,$lang[$uins[$uin]['settings']['lang']]['youloggedkick']);
												notifyStatus($uin,'kick');
											}
										}
										else
										{
											userMessage();
										}
									}
									break;
									case '/ban':
									{
										if ($msg['from']==$settings['admin']['uin'])
										{
											$uin = substr($msg['message'],5);
											if ($uins[$uin]['status']>0)
											{
												$uins[$uin]['status'] = 0;
												$uins[$uin]['role'] = 0;
												$uins[$uin]['typing'] = 0;
												$uins[$uin]['notify'] = 0;
												$icq->sendMessage($uin,$lang[$uins[$uin]['settings']['lang']]['youloggedban']);
											}
											notifyStatus($uin,'ban');
											unset($white_list[$uin]);
											unset($uins[$uin]);
										}
										else
										{
											userMessage();
										}
									}
									break;
									case '/mtn':	//toggle mtn packets send
									{
										if ($msg['from']==$settings['admin']['uin'])
										{
											if ($settings['MTN'])
											{
												foreach ($uins as $uin)
												{
													$icq->sendTypingNotification($uin,0);
												}
											}
											$settings['MTN'] = $settings['MTN']?false:true;
											$icq->sendMessage($msg['from'],'MTN '.($settings['MTN']?'enabled.':'disabled.'));
										}
										else
										{
											userMessage();
										}
									}
									break;
									case '/status':
									{
										$time = floor(microtime(true) - $sctime);
										$time = floor($time/3600).':'.floor(($time-floor($time/3600)*3600)/60).':'.floor($time - floor($time/3600)*3600 - floor(($time - floor($time/3600)*3600)/60)*60);
										$icq->sendMessage($msg['from'],$lang[$uins[$msg['from']]['settings']['lang']]['uptime'].$time."\r\n".$lang[$uins[$msg['from']]['settings']['lang']]['version']);
									}
									break;
									case '/stats':
									{
										$uo = 0;
										$messag = '';
										foreach ($uins as $uin)
										{
											if ($uin['status']==1)
											{
												$uo++;
											}
											if ($uin['msgt']==0)
											{
												$uin['msgt'] = 1;
												$uinavg = sprintf('%.2F',$uin['txtt']/$uin['msgt']);
												$uin['msgt'] = 0;
											}
											else
											{
												$uinavg = sprintf('%.2F',$uin['txtt']/$uin['msgt']);
											}
											$messag .= str_replace(array('nick','number','-','=','/','?','!'),array($uin['nick'],$uin['uin'],$uin['msgs'],$uin['msgt'],$uin['txts'],$uin['txtt'],$uinavg),$lang[$uins[$msg['from']]['settings']['lang']]['stats1']);
										}
										$messag = str_replace(array('!','@'),array(count($uins),$uo),$lang[$uins[$msg['from']]['settings']['lang']]['stats']).$messag;
										$icq->sendMessage($msg['from'],$messag);
									}
									break;
									default:
									{
										userMessage($msg['from'],$msg['message']);
									}
								}
							}
						}
					}
					elseif ($msg['type']=='shortinfo' && $did!='')	//shortinfo received. somebody tries to login
					{
						$uins[$did] = array(
							'uin'=>$did,
							'nick'=>$msg['nick'],
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
						notifyStatus($did,'in');
						$did = '';
					}
					elseif ($msg['type']=='useronline')
					{
						if (isset($msg['status']))	//logged in
						{
							if (isset($uins[$msg['uin']]) && $uins[$msg['uin']]['status']==0)
							{
								if ($uins[$msg['uin']]['settings']['autologin'])
								{
									$uins[$msg['uin']]['status'] = 1;
									$uins[$msg['uin']]['role'] = 1;
									notifyStatus($msg['uin'],'in');
								} 
							}
						}
						elseif (!isset($msg['status']) && isset($msg['old_status']))	//logged out
						{
							if (isset($uins[$msg['uin']]) && $uins[$msg['uin']]['status']==1)
							{
								if ($uins[$msg['uin']]['settings']['autologout'])
								{
									$uins[$msg['uin']]['status'] = 0;
									$uins[$msg['uin']]['role'] = 0;
									//$icq->sendMessage($msg['from'],$lang[$uins[$msg['from']]['settings']['lang']]['youloggedin']);
									notifyStatus($msg['uin'],'out');
								}
							}
							$uins[$msg['uin']]['typing'] = 0;
							$uins[$msg['uin']]['notify'] = 0;
						}
					}
					elseif ($msg['type']=='mtn')	// mini typing notification
					{
						if (isset($uins[$msg['from']]) && $uins[$msg['from']]['status']==1 && $uins[$msg['from']]['settings']['invisible']==false)
						{
							$uins[$msg['from']]['typing'] = $msg['notify'];
							foreach ($uins as $uin=>$data)
							{
								$oldn = $data['notify'];
								$uins[$uin]['notify'] = 0;
								foreach ($uins as $uin2=>$data2)
								{
									if ($uin2 != $uin)
									{
										if ($data2['typing'] > 0 && $data2['typing'] < 3 && $data2['typing'] > $data['notify'])
										{
											$uins[$uin]['notify'] = $data2['typing'];
										}
									}
								}
								if ($uins[$uin]['notify'] != $oldn)
								{
									if ($settings['MTN'])
									{
										$icq->sendTypingNotification($uin,$uins[$uin]['notify']);
									}
								}
							}
						}
					}
				}
				if ($settings['DEBUG_MODE'])
				{
					echo "----------\r\n";
				}
			}
			else 
			{
				if ($icq->error)
				{
					echo 'ICQ error: '.$icq->error."\r\n----------\r\n";
				}
				$icq->error = '';
			}
			$ectime = microtime(true);
			if (($ectime-$sctime)<0.5) usleep(ceil((0.5 - $ectime + $sctime)*1000000));
			if (date('H:i:s')=='23:59:59')
			{
				foreach ($uins as $k=>$uin)
				{
					$uins[$k]['msgs'] = 0;
					$uins[$k]['txts'] = 0;
				}
				saveSettings();
				if ($settings['HISTORY'])
				{
					$f = fopen('history.txt','a');
					fwrite($f,"----------------------------------------\r\n".date('j.m.Y')."----------------------------------------\r\n");
					fclose($f);
				}
			}
			if ($settings['REBOOT'] && date('H:i:s')==$settings['REBOOT'])	//server is going for reboot
			{
				saveSettings();
				notifySystem('shutdown');
				sleep(2);
				$icq->disconnect();
				die;
			}
		}
	}
	else
	{
		echo "Connection error: ".$icq->error."\r\n";
		saveSettings();
		sleep(60);
		$icq->disconnect();
		unset($icq);
		$icq = new WebIcqPro;
		//$icq->debug = true;
		foreach ($settings['options'] as $option=>$value)
		{
			$icq->setOption($option, $value);
		}
	}
}
?>
