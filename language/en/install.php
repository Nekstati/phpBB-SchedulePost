<?php

if (!defined('IN_PHPBB'))
{
	exit;
}

if (empty($lang) || !is_array($lang))
{
	$lang = [];
}

$lang = array_merge($lang, [
	'SCHEDULE_POST_INSTALL_TITLE'		=> 'Schedule Post preconfiguration',

	'SCHEDULE_POST_INSTALL_Q0'			=> 'Who do you allow to publish topics on a schedule?',
	'SCHEDULE_POST_INSTALL_Q1'			=> 'Who do you allow to view scheduled topics which are not yet published?',

	'SCHEDULE_POST_INSTALL_A0'			=> 'Nobody. You will setup permissions later',
	'SCHEDULE_POST_INSTALL_A1'			=> 'Those who have <i>Full Access</i> role (that’s administrators usually)',
	'SCHEDULE_POST_INSTALL_A2'			=> 'Those who have <i>Full Access</i>, <i>Standard Access</i> or <i>Standard Access + Polls</i> role (usually these roles include the most of users)',

	'SCHEDULE_POST_INSTALL_NOTES'		=> '<p><b>Notes</b></p>
		<p> You can ignore this page, if you are familiar with phpBB permissions. </p>
		<p> “Can view scheduled topics” permission applies to other people’s topics. Authors always can see their own topics. </p>
		<p> If your board uses forum roles that are different from the listed above, these parameters will not affect actual user’s permissions. You will need to setup permissions later, on the “Permissions” tab of ACP. </p>
		<p> Details, instructions and answers to your questions here: <a href="https://www.phpbbguru.net/community/">https://www.phpbbguru.net/community/</a> </p>',
]);
