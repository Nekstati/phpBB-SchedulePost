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
	'SCHEDULE_POST_TITLE'			=> 'Topics scheduled to be published in this forum',
	'SCHEDULE_POST_TIME'			=> 'Publish time',
	'SCHEDULE_POST_TIME_DESC'		=> 'Leave these fields empty to publish the topic immediately.',
	'SCHEDULE_POST_TIME_CLEAR'		=> 'Clear',
	'SCHEDULE_POST_ADDED'			=> 'Topic successfully scheduled to be published at specified date and time.',
	'SCHEDULE_POST_EDITED'			=> 'Topic successfully edited.',
	'SCHEDULE_POST_DELETED'			=> 'Topic successfully deleted.',
	'SCHEDULE_POST_DELETE_CONFIRM'	=> 'Really want to delete this scheduled topic?',
	'SCHEDULE_POST_TIME_D_ERROR'	=> 'Incorrect date in “Publish time”.',
	'SCHEDULE_POST_TIME_T_ERROR'	=> 'Incorrect time in “Publish time”.',
	'SCHEDULE_POST_TIME_ERROR'		=> 'Incorrect time in “Publish time”. Time must be in the future.',
	'SCHEDULE_POST_TIME_CLEARED'	=> 'Topic will be published immediately because you have cleared “Publish time” field. Continue?',
	'SCHEDULE_POST_PUBLISH'			=> 'Publish',
	'SCHEDULE_POST_PUBLISH_TOOLTIP'	=> 'Publish now',
	'SCHEDULE_POST_PUBLISH_CONFIRM'	=> 'Topic will be published immediately. Continue?',
	'SCHEDULE_POST_DATE_SHORT'		=> 'Created at %1$s',
	'SCHEDULE_POST_DATE_LONG'		=> 'Created: %1$s; will be published: %2$s',
	'SCHEDULE_POST_TOPIC'			=> 'Scheduled topic',
	'SCHEDULE_POST_VIEW_TOPIC'		=> 'View topic',
	'SCHEDULE_POST_REPLY_ALERT'		=> 'This topic is not published yet. Replying, subscription and other actions will be available after it is published.',

	'ACL_F_SCHEDULE_CAT'			=> 'Scheduled topics',
	'ACL_F_SCHEDULE_POST'			=> 'Can publish topics on a schedule, specifying date and time',
	'ACL_F_SCHEDULE_VIEW'			=> 'Can view scheduled topics that are not yet published <br /><i>This permission applies to other people’s topics; authors always can see their own topics.</i>',
]);
