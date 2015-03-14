<?php
/**
 * Русский языковой пакет.
 * @package Flazy_Russian
 */

/** Языковые конструкции используемые  в post.php и edit.php */
$lang_post = array(

// Post validation stuff (many are similar to those in edit.php)
'No subject'			=>	'Тема должна имень название.',
'Too long subject'		=>	'Название темы не может быть длиннее 70 символов.',
'Too long description'	=>	'Описание не может быть длиннее 100 символов.',
'All caps subject'		=>	'Название темы не может состоять только из заглавных букв.',
'All caps description'	=>	'Описание не может состоять только из заглавных букв.',
'No message'			=>	'Вы должны ввести текст сообщения.',
'Too long message'		=>	'Длина вашего сообщения %s байт. Это превышает лимит форума в %s байт.',
'All caps message'		=>	'Сообщение не может содержать только заглавные буквы.',
'Max count options'		=>	'Колличество вариантов не может быть больше %s.',
'Min count options'		=>	'Колличество вариантов не может быть меньше 2.',
'Days limit'			=>	'Число дней опроса не может быть больше чем 90 и меньше чем 1.',
'Votes count'			=>	'Число голосов не может быть больше чем 1000 и меньше чем 10.',
'Input error'			=>	'Вы должны ввести в число дней для голосования или число голосов.',
'CSRF token mismatch'	=>	'Передача данных прервана из соображений безопасности. Скорее всего это произошло из-за того, что прошло слишком много времени с того момента, как вы зашли на страницу, до того момента, как нажали кнопку отправки данных. Нажмите кнопку "Добавить тему/ответ" снова, это должно решить проблему.',
'Invalid e-mail'		=>	'Неправильный адрес e-mail.',
'Banned e-mail'			=>	'Адрес электронной почты, введённый вами, заблокирован на этом форуме. Пожалуйста, выберите другой e-mail.',
'Blocked spamer'		=>	'Анализ данных показал что вы спамер, если вы считаете это ошибкой, пожалуйста, посетите <a href="http://www.stopforumspam.com">Stop Forum Spam.com</a>.',

// Сообщения
'Compose your reply'	=>	'Напишите и опубликуйте ваш новый ответ ',
'Compose your topic'	=>	'Напишите и опубликуйте вашу новую тему',
'Compose edited reply'	=>	'Отредактируйте и опубликуйте ваш ответ',
'Compose edited topic'	=>	'Отредактируйте и опубликуйте вашу тему',
'Guest post legend'		=>	'Обязательная инфомация для гостей',
'Post errors'			=>	'<strong>Внимание!</strong> Следующие ошибки необходимо исправить перед отправкой сообщения:',
'Guest name'			=>	'Ваше имя', // Для гостей (вместо имени пользователя)
'Guest e-mail'			=>	'Ваш e-mail',
'Post redirect'			=>	'Сообщение отправлено. Перенаправление…',
'Post reply'			=>	'Ответить',
'Post new topic'		=>	'Новая тема',
'Topic subject'			=>	'Тема',
'Topic description'		=>	'Описание',
'Write message'			=>	'Сообщение',
'Added'					=>	'Добавлено через ',
'Hide smilies'			=>	'Не показывать графические смайлики для этого сообщения.',
'Merge posts'			=>	'Объеденить с предыдущим сообщением если оно ваше.',
'Subscribe'				=>	'Подписаться и следить за ответами в этой теме.',
'Stay subscribed'		=>	'Оставаться подписанным на эту тему.',
'Topic review'			=>	'Обзор темы (новые сверху)',
'Flood'					=>	'Хотя бы %s секунд должно пройти перед тем, как вы сможете написать следующее сообщение. Пожалуйста, подождите немного и попытайтесь отправить сообщение еще раз.',
'Permalink post'		=>	'Постоянная ссылка на это сообщение',
'Post settings'			=>	'Настройки сообщения',
'Poll question'			=>	'Вопрос голосования',
'Poll question info'	=>	'Длина вопроса не может быть меньше чем 5 символов.',
'Voting answer'			=>	'Вариант ответа',
'Summary count'			=>	'Количество ответов',
'Count'					=>	'Количество',
'Button note'			=>	'Обновить опрос',
'Show poll'				=>	'Показывать результаты',
'Show poll info'		=>	'Участники могут видеть результаты опроса без голосования.',
'Allow revote'			=>	'Переголосование',
'Allow revote info'		=>	'Разрешить проголосовавшим участникам изменить свой голос.',
'Allow days'			=>	'Продолжительность опроса',
'Allow days info'		=>	'Продолжительность опроса (в днях), 0 — неограничено.',
'Maximum votes'			=>	'Количество голосов',
'Reset res'				=>	'Сбросить результаты',
'Reset res info'		=>	'Отметьте, если вы хотите сбросить результаты голосования.',
'Remove'				=>	'Удалить опрос',
'Remove info'			=>	'Отметьте, если вы хотите удалить голосование.',
'Empty question'		=>	'Вы должны ввести вопрос.',
'Maximum votes info'	=>	'Максимальное колличество голосов в опросе.',
'Preview reply'			=>	'Предварительный просмотр вашего ответа',
'Preview new topic'		=>	'Предварительный просмотр вашей темы',
'Preview reply'			=>	'Посмотреть ответ', // For preview button
'Preview topic'			=>	'Посмотреть тему', // For preview button
'Submit reply'			=>	'Добавить ответ', // For submit button
'Submit topic'			=>	'Добавить тему', // For submit button
'Post byline'			=>	'<span>Сообщение от </span>%s',
'Topic byline'			=>	'<span>Тема от </span>%s',
'Reply byline'			=>	'<span>Ответ от </span>%s',
'Post posted'			=>	'<span>Написано </span>%s',

// Редактирование сообщения
'Edit post legend'		=>	'Отредактируйте сообщение и нажмите "Добавить ответ"',
'Edit topic'			=>	'Редактирование темы',
'Edit reply'			=>	'Редактирование ответа',
'Silent edit'			=>	'Не показывать информацию о редактировании  («Отредактировано…» при просмотре темы с сообщениями).',
'Mail send'				=>	'Отправить уведомление о редактировании автору сообщения.',
'Preview edited topic'	=>	'Предварительный просмотр измененной темы',
'Preview edited reply'	=>	'Предварительный просмотр измененного ответа',
'Edit redirect'			=>	'Сообщение обновлено. Перенаправление…'

);