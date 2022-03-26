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
	'SCHEDULE_POST_TITLE'			=> 'Темы, запланированные к публикации в этом форуме',
	'SCHEDULE_POST_TIME'			=> 'Время публикации',
	'SCHEDULE_POST_TIME_DESC'		=> 'Оставьте эти поля пустыми, чтобы опубликовать тему незамедлительно.',
	'SCHEDULE_POST_TIME_CLEAR'		=> 'Очистить',
	'SCHEDULE_POST_ADDED'			=> 'Тема запланирована к публикации в указанное вами время.',
	'SCHEDULE_POST_EDITED'			=> 'Тема отредактирована.',
	'SCHEDULE_POST_DELETED'			=> 'Тема удалена.',
	'SCHEDULE_POST_DELETE_CONFIRM'	=> 'Точно хотите удалить эту запланированную тему?',
	'SCHEDULE_POST_TIME_D_ERROR'	=> 'Некорректная дата публикации.',
	'SCHEDULE_POST_TIME_T_ERROR'	=> 'Некорректное время публикации.',
	'SCHEDULE_POST_TIME_ERROR'		=> 'Некорректное время публикации. Должен быть указан момент времени в будущем.',
	'SCHEDULE_POST_TIME_CLEARED'	=> 'Тема будет опубликована незамедлительно, поскольку вы очистили поле «Время публикации». Продолжить?',
	'SCHEDULE_POST_PUBLISH'			=> 'Опубликовать',
	'SCHEDULE_POST_PUBLISH_TOOLTIP'	=> 'Опубликовать сейчас',
	'SCHEDULE_POST_PUBLISH_CONFIRM'	=> 'Тема будет опубликована незамедлительно. Продолжить?',
	'SCHEDULE_POST_DATE_SHORT'		=> 'Создано %1$s',
	'SCHEDULE_POST_DATE_LONG'		=> 'Создано: %1$s; будет опубликовано: %2$s',
	'SCHEDULE_POST_TOPIC'			=> 'Запланированная тема',
	'SCHEDULE_POST_VIEW_TOPIC'		=> 'Просмотреть тему',
	'SCHEDULE_POST_REPLY_ALERT'		=> 'Эта тема ещё не опубликована. Ответ, подписка и другие действия станут доступны после публикации.',

	'ACL_F_SCHEDULE_CAT'			=> 'Запланированные темы',
	'ACL_F_SCHEDULE_POST'			=> 'Может создавать запланированные темы, задавая дату и время публикации',
	'ACL_F_SCHEDULE_VIEW'			=> 'Может видеть запланированные темы, которые ещё не опубликованы <br /><i>Это право касается чужих тем; свои собственные темы автор видит всегда.</i>',
]);
