<?php
/**
 * Позволяет участникам создавать новые учетные записи.
 *
 * @copyright Copyright (C) 2008 PunBB, partially based on code copyright (C) 2008 FluxBB.org
 * @modified Copyright (C) 2014-2018 Flazy
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package Flazy
 */
if (!defined('FORUM_ROOT'))
    define('FORUM_ROOT', './');
require FORUM_ROOT . 'include/common.php';

($hook = get_hook('rg_start')) ? eval($hook) : null;

// Check for use of incorrect URLs
if (!isset($_GET['agree']) && !isset($_GET['cancel']) && !isset($_POST['form_sent']))
    confirm_current_url(forum_link($forum_url['register']));

// If we are logged in, we shouldn't be here
if (!$forum_user['is_guest']) {
    header('Location: ' . forum_link($forum_url['index']));
    die;
}

// Load the profile.php language file
require FORUM_ROOT . 'lang/' . $forum_user['language'] . '/profile.php';

if (!$forum_config['o_regs_allow'])
    message($lang_profile['No new regs']);

$errors = array();

if (isset($_POST['form_sent'])) {
    ($hook = get_hook('rg_register_form_submitted')) ? eval($hook) : null;

    // Check that someone from this IP didn't register a user within the last hour (DoS prevention)
    $query = array(
        'SELECT' => '1',
        'FROM'   => 'users AS u',
        'WHERE'  => 'u.registration_ip=\'' . $forum_db->escape(get_remote_address()) . '\' AND u.registered>' . (time() - $forum_config['o_register_timeout'])
    );

    ($hook     = get_hook('rg_register_qr_check_register_flood')) ? eval($hook) : null;
    $result   = $forum_db->query_build($query) or error(__FILE__, __LINE__);
    if ($forum_db->num_rows($result))
        $errors[] = $lang_profile['Registration flood'];

    // Did everything go according to plan so far?
    if (empty($errors)) {
        // Простая спам защита. Этап второй
        if ($_POST['username'] != '' || $_POST['email1'] != '')
            message($lang_profile['No regs spam']);

        $username = forum_trim($_POST['req_' . input_name('username')]);
        $email1   = utf8_strtolower(forum_trim($_POST['req_' . input_name('email1')]));

        if ($forum_config['o_regs_verify']) {
            $email2    = utf8_strtolower(forum_trim($_POST['req_email2']));
            $password1 = random_key(8, true);
            $password2 = $password1;
        } else {
            $password1 = forum_trim($_POST['req_password1']);
            $password2 = forum_trim($_POST['req_password2']);
        }

        // Validate the username
        if (!defined('FORUM_FUNCTIONS_VALIDATE_USERNAME'))
            require FORUM_ROOT . 'include/functions/validate_username.php';

        $errors = array_merge($errors, validate_username($username));

        // ... and the password
        if (utf8_strlen($password1) < 4)
            $errors[] = $lang_profile['Pass too short'];
        else if ($password1 != $password2)
            $errors[] = $lang_profile['Pass not match'];

        // ... and the e-mail address
        if (!defined('FORUM_EMAIL_FUNCTIONS_LOADED'))
            require FORUM_ROOT . 'include/functions/email.php';

        if (!is_valid_email($email1))
            $errors[] = $lang_profile['Invalid e-mail'];
        else if ($forum_config['o_regs_verify'] && $email1 != $email2)
            $errors[] = $lang_profile['E-mail not match'];

        // Check if it's a banned e-mail address
        $banned_email = is_banned_email($email1);
        if ($banned_email && !$forum_config['p_allow_banned_email'])
            $errors[]     = $lang_profile['Banned e-mail'];

        $dupe_email = is_dupe_email($email1);
        if ($dupe_email && !$forum_config['p_allow_dupe_email'])
            $errors[]   = $lang_profile['Dupe e-mail'];

        $stop_spam = array(
            'email'    => $email1,
            'ip'       => get_remote_address(),
            'username' => $username
        );

        if (stop_spam($stop_spam))
            $errors[] = $lang_profile['Blocked spamer'];

        // Clean old unverified registrators - delete older than 72 hours
        $query = array(
            'DELETE' => 'users',
            'WHERE'  => 'group_id=' . FORUM_UNVERIFIED . ' AND activate_key IS NOT NULL AND registered < ' . (time() - 259200)
        );

        ($hook = get_hook('rg_register_qr_delete_unverified')) ? eval($hook) : null;
        $forum_db->query_build($query) or error(__FILE__, __LINE__);

        ($hook = get_hook('rg_register_end_validation')) ? eval($hook) : null;

        // Did everything go according to plan so far?
        if (empty($errors)) {
            // Make sure we got a valid language string
            if (isset($_POST['language'])) {
                $language = preg_replace('#[\.\\\/]#', '', $_POST['language']);
                if (!file_exists(FORUM_ROOT . 'lang/' . $language . '/common.php'))
                    message($lang_common['Bad request']);
            } else
                $language = $forum_config['o_default_lang'];

            //$initial_group_id = (!$forum_config['o_regs_verify']) ? $forum_config['o_default_user_group'] : FORUM_UNVERIFIED;
            $salt          = random_key(12);
            $password_hash = forum_hash($password1, $salt);

            // Insert the new user into the database. We do this now to get the last inserted id for later use.
            $user_info = array(
                'username'      => $username,
                'salt'          => $salt,
                'password_hash' => $password_hash,
                'email'         => $email1,
                'timezone'      => $_POST['timezone'],
                'dst'           => isset($_POST['dst']) ? '1' : '0',
                'language'      => $language,
                'banned_email'  => $banned_email,
                'dupe_list'     => $dupe_email
            );

            ($hook = get_hook('rg_register_pre_add_user')) ? eval($hook) : null;
            if (!defined('FORUM_FUNCTIONS_ADD_USER'))
                require FORUM_ROOT . 'include/functions/add_user.php';
            add_user($user_info, $new_uid);

            ($hook = get_hook('rg_register_pre_login_redirect')) ? eval($hook) : null;

            // Must the user verify the registration or do we log him/her in right now?
            if ($forum_config['o_regs_verify'])
                message(sprintf($lang_profile['Reg e-mail'], '<a href="mailto:' . forum_htmlencode($forum_config['o_admin_email']) . '">' . forum_htmlencode($forum_config['o_admin_email']) . '</a>'));
            else {
                // Remove this user's guest entry from the online list
                $query = array(
                    'DELETE' => 'online',
                    'WHERE'  => 'ident=\'' . $forum_db->escape(get_remote_address()) . '\''
                );

                ($hook = get_hook('rg_register_qr_delete_online_user')) ? eval($hook) : null;
                $forum_db->query_build($query) or error(__FILE__, __LINE__);
            }

            $expire = time() + $forum_config['o_timeout_visit'];

            forum_setcookie($cookie_name, base64_encode($new_uid . '|' . $password_hash . '|' . $expire . '|' . sha1($salt . $password_hash . forum_hash($expire, $salt))), $expire);

            redirect(forum_link($forum_url['index']), $lang_profile['Reg complete']);
        }
    }
} else if ($forum_config['o_rules'] && (!isset($_GET['agree']) || !isset($_GET['req_agreement']))) {
    // User pressed the cancel button
    if (isset($_GET['cancel']))
        redirect(forum_link($forum_url['index']), $lang_profile['Reg cancel redirect']);

    // User pressed agree but failed to tick checkbox
    if (isset($_GET['agree']) && !isset($_GET['req_agreement']))
        $errors[] = $lang_profile['Reg agree fail'];

    // Setup form
    $forum_page['group_count'] = $forum_page['item_count']  = $forum_page['fld_count']   = 0;

    // Setup breadcrumbs
    $forum_page['crumbs'] = array(
        array($forum_config['o_board_title'], forum_link($forum_url['index'])),
        array($lang_common['Register'], forum_link($forum_url['register'])),
        array($lang_common['Rules'], forum_link($forum_url['register']))
    );

    ($hook = get_hook('rg_rules_pre_header_load')) ? eval($hook) : null;

    define('FORUM_PAGE', 'rules');
    require FORUM_ROOT . 'header.php';

    // START SUBST - <forum_main>
    ob_start();

    ($hook = get_hook('rg_rules_output_start')) ? eval($hook) : null;

    $forum_page['set_count'] = $forum_page['fld_count'] = 0;
    ?>
    <div class="row">
        <div class="col s12 m8 l8 offset-l2">
            <?php
            // If there were any errors, show them
            if (!empty($errors)) {
                $forum_page['errors']   = array();
                foreach ($errors as $cur_error)
                    $forum_page['errors'][] = $cur_error;

                ($hook = get_hook('rg_pre_register_errors')) ? eval($hook) : null;
                ?>
                <div class="alert alert-danger">
                    <?php echo $lang_profile['Register errors'] ?></br>
                    <?php echo implode("\n\t\t\t\t", $forum_page['errors']) . "\n" ?>
                </div>
                <?php
            }
            ?>
            <div class="card">
                <div class="card-content">
                    <span class="card-title"><?php echo $lang_profile['Reg rules head'] ?></span>
                    <p><?php echo $forum_config['o_rules_message'] . "\n" ?></p>

                    <form class="frm-form" method="get" accept-charset="utf-8" action="<?php echo forum_link($forum_url['register']) ?>"> 
                        <?php ($hook = get_hook('rg_rules_pre_group')) ? eval($hook) : null; ?>
                        <div class="row group<?php echo ++$forum_page['group_count'] ?>">
                            <?php ($hook = get_hook('rg_rules_pre_agree_checkbox')) ? eval($hook) : null; ?>
                            <div class="row set<?php echo ++$forum_page['item_count'] ?>">
                                <div class="input-field col s12">
                                    <input type="checkbox" id="fld<?php echo ++$forum_page['fld_count'] ?>" name="req_agreement" value="1"/>
                                    <label for="fld<?php echo $forum_page['fld_count'] ?>"><?php echo $lang_profile['Agreement'] ?> <small><?php echo $lang_profile['Agreement label'] ?></small></label>
                                </div>
                            </div>
                            <?php ($hook = get_hook('rg_rules_pre_group_end')) ? eval($hook) : null; ?>
                        </div>
                        <?php ($hook = get_hook('rg_rules_group_end')) ? eval($hook) : null; ?>
                        <div class="card-action">
                            <div class="row">
                                <div class="input-field col s12 l2">
                                    <button type="submit" name="agree" class="btn btn-simple green btn-lg"><?php echo $lang_profile['Agree'] ?></button>
                                </div>
                                <div class="input-field col s12 l3">
                                    <button type="submit" name="cancel" class="btn btn-simple red btn-lg"><?php echo $lang_common['Cancel'] ?></button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php
    ($hook = get_hook('rg_rules_end')) ? eval($hook) : null;

    $tpl_temp = forum_trim(ob_get_contents());
    $tpl_main = str_replace('<forum_main>', $tpl_temp, $tpl_main);
    ob_end_clean();
    // END SUBST - <forum_main>

    require FORUM_ROOT . 'footer.php';
}

// Setup form
$forum_page['group_count'] = $forum_page['item_count']  = $forum_page['fld_count']   = 0;
$forum_page['form_action'] = forum_link($forum_url['register']) . '?action=register';

// Setup form information
$forum_page['frm_info']['intro'] = '<p>' . $lang_profile['Register intro'] . '</p>';
if ($forum_config['o_regs_verify'])
    $forum_page['frm_info']['email'] = '<p class="warn">' . $lang_profile['Reg e-mail info'] . '</p>';

// Setup breadcrumbs
$forum_page['crumbs'] = array(
    array($forum_config['o_board_title'], forum_link($forum_url['index'])),
    array(sprintf($lang_profile['Register at'], $forum_config['o_board_title']), forum_link($forum_url['register'])),
);

($hook = get_hook('rg_register_pre_header_load')) ? eval($hook) : null;

$forum_js->file(array('jquery', 'material', 'flazy', 'common', 'pstrength'));
$forum_js->code('$(function() {
$(\'.password\').pstrength();
});
$(document).ready(function() {
    $(\'select\').material_select();
  });
');

define('FORUM_PAGE', 'register');
require FORUM_ROOT . 'header.php';

// START SUBST - <forum_main>
ob_start();

($hook = get_hook('rg_register_output_start')) ? eval($hook) : null;
?>
<div class="row">
    <div class="alert alert-info">
        <p><?php echo implode("\n\t\t\t", $forum_page['frm_info']) . "\n" ?></p>
    </div>
    <div class="col s12 l6 offset-l3">

        <?php
// If there were any errors, show them
        if (!empty($errors)) {
            $forum_page['errors']   = array();
            foreach ($errors as $cur_error)
                $forum_page['errors'][] = '<li class="warn"><span>' . $cur_error . '</span></li>';

            ($hook = get_hook('rg_pre_register_errors')) ? eval($hook) : null;
            ?>
            <div class="alert alert-danger">
                <div class="container-fluid">
                    <div class="alert-icon">
                        <i class="material-icons">error_outline</i>
                    </div>
                    <b><?php echo $lang_profile['Register errors'] ?></b> <?php echo implode("\n\t\t\t\t", $forum_page['errors']) . "\n" ?>
                </div>
            </div>
            <?php
        }
        ?>
        <div class="card">
            <form class="frm-form" id="afocus" method="post" accept-charset="utf-8" action="<?php echo $forum_page['form_action'] ?>">
                <div class="hidden">
                    <input type="hidden" name="form_sent" value="1" />
                    <input type="hidden" name="csrf_token" value="<?php echo generate_form_token($forum_page['form_action']) ?>" />
                </div>
                <?php ($hook = get_hook('rg_register_pre_group')) ? eval($hook) : null; ?>
                <div class="card-content group<?php echo ++$forum_page['group_count'] ?>">
                    <?php ($hook = get_hook('rg_register_pre_username')) ? eval($hook) : null; ?>
                    <div class="row set<?php echo ++$forum_page['item_count'] ?>">
                        <div class="input-field col s12">
                            <i class="material-icons prefix">face</i>
                            <input type="text" class="hidden" name="username" value="" size="35" maxlength="25" />
                            <input type="text" placeholder="<?php echo $lang_profile['Username'] ?>" id="fld<?php echo ++$forum_page['fld_count'] ?>" name="req_<?php echo input_name('username') ?>" value="<?php echo(isset($_POST['req_' . input_name('username')]) ? forum_htmlencode($_POST['req_' . input_name('username')]) : '') ?>" class="validate" />
                            <label for="fld<?php echo $forum_page['fld_count'] ?>"><?php echo $lang_profile['Username'] ?> <small><?php echo $lang_profile['Username help'] ?></small></label>
                        </div>
                    </div>		  
                    <?php ($hook = get_hook('rg_register_pre_password')) ? eval($hook) : null; ?>
                    <?php if (!$forum_config['o_regs_verify']): ?>
                        <div class="row set<?php echo ++$forum_page['item_count'] ?>">
                            <div class="input-field col s12">
                                <i class="material-icons prefix">lock_outline</i>
                                <input type="password" placeholder="<?php echo $lang_profile['Password'] ?>" id="fld<?php echo ++$forum_page['fld_count'] ?>" name="req_password1" value="<?php echo(isset($_POST['req_' . input_name('username')]) ? forum_htmlencode($_POST['req_' . input_name('username')]) : '') ?>" class="validate" />
                                <label for="fld<?php echo ++$forum_page['fld_count'] ?>"><?php echo $lang_profile['Password'] ?></label>
                            </div>
                        </div>	
                        <?php ($hook = get_hook('rg_register_pre_confirm_password')) ? eval($hook) : null; ?>
                        <div class="row set<?php echo ++$forum_page['item_count'] ?>">
                            <div class="input-field col s12">
                                <i class="material-icons prefix">lock_outline</i>
                                <input type="password" placeholder="<?php echo $lang_profile['Confirm password'] ?>" id="fld<?php echo ++$forum_page['fld_count'] ?>" name="req_password2" value="<?php echo(isset($_POST['req_' . input_name('username')]) ? forum_htmlencode($_POST['req_' . input_name('username')]) : '') ?>" class="validate" />
                                <label for="fld<?php echo ++$forum_page['fld_count'] ?>"><?php echo $lang_profile['Confirm password'] ?></label>
                            </div>
                        </div>
                    <?php endif;
                    ($hook = get_hook('rg_register_pre_email')) ? eval($hook) : null;
                    ?>
                    <div class="row set<?php echo ++$forum_page['item_count'] ?>">
                        <div class="input-field col s12">
                            <i class="material-icons prefix">email</i>
                            <input type="email" class="hidden" name="email1" value="" size="35" maxlength="80" />
                            <input type="email" placeholder="<?php echo $lang_profile['E-mail'] ?>" id="fld<?php echo $forum_page['fld_count'] ?>" name="req_<?php echo input_name('email1') ?>" value="<?php echo(isset($_POST['req_' . input_name('email1')]) ? forum_htmlencode($_POST['req_' . input_name('email1')]) : '') ?>" class="validate" />
                            <label for="fld<?php echo $forum_page['fld_count'] ?>"><?php echo $lang_profile['E-mail'] ?> <small><?php echo ($forum_config['o_regs_verify']) ? $lang_profile['E-mail activation help'] : $lang_profile['E-mail help'] ?></small></label>
                        </div>
                    </div>
                    <?php ($hook = get_hook('rg_register_pre_email_confirm')) ? eval($hook) : null; ?>
<?php if ($forum_config['o_regs_verify']): ?>
                        <div class="row set<?php echo ++$forum_page['item_count'] ?>">
                            <div class="input-field col s12">
                                <i class="material-icons prefix">email</i>
                                <input type="email" placeholder="<?php echo $lang_profile['E-mail'] ?>" id="fld<?php echo $forum_page['fld_count'] ?>" name="req_email2" value="<?php echo(isset($_POST['req_email2']) ? forum_htmlencode($_POST['req_email2']) : '') ?>" class="validate" />
                                <label for="fld<?php echo $forum_page['fld_count'] ?>"><?php echo $lang_profile['E-mail'] ?> <small><?php echo ($forum_config['o_regs_verify']) ? $lang_profile['E-mail activation help'] : $lang_profile['E-mail help'] ?></small></label>
                            </div>
                        </div>
                        <?php
                    endif;

                    $languages = get_language_packs();

                    ($hook = get_hook('rg_register_pre_language')) ? eval($hook) : null;

                    // Only display the language selection box if there's more than one language available
                    if (count($languages) > 1) {
                        ?>
                        <div class="row set<?php echo ++$forum_page['item_count'] ?>">
                            <div class="input-field col s12">
                                <i class="material-icons prefix">language</i>
                                <select id="fld<?php echo $forum_page['fld_count'] ?>" name="language" class="icons">


                                    <?php
                                    $select_lang = isset($_POST['language']) ? $_POST['language'] : $forum_config['o_default_lang'];
                                    foreach ($languages as $temp) {
                                        if ($select_lang == $temp)
                                            echo "\t\t\t\t\t\t" . '<option value="' . $temp . '" data-icon="' . $base_url . '/resources/flags/' . strtolower($temp) . '.png" selected="selected" class="left">' . $temp . '</option>' . "\n";
                                        else
                                            echo "\t\t\t\t\t\t" . '<option value="' . $temp . '" data-icon="' . $base_url . '/resources/flags/' . strtolower($temp) . '.png" class="left">' . $temp . '</option>' . "\n";
                                    }
                                    ?>
                                </select>
                                <label for="fld<?php echo ++$forum_page['fld_count'] ?>"><?php echo $lang_profile['Language'] ?></label>
                            </div>
                        </div>
                        <?php
                    }

                    $select_timezone = isset($_POST['timezone']) ? $_POST['timezone'] : $forum_config['o_default_timezone'];
                    $select_dst      = isset($_POST['form_sent']) ? isset($_POST['dst']) : $forum_config['o_default_dst'];

                    ($hook = get_hook('rg_register_pre_timezone')) ? eval($hook) : null;
                    ?>
                    <div class="row set<?php echo ++$forum_page['item_count'] ?>">
                        <div class="input-field col s12">
                            <i class="material-icons prefix">timelapse</i>
                            <select id="fld<?php echo $forum_page['fld_count'] ?>" name="timezone">
                                <option value="-12"<?php if ($select_timezone == -12) echo ' selected="selected"' ?>><?php echo $lang_profile['UTC-12:00'] ?></option>
                                <option value="-11"<?php if ($select_timezone == -11) echo ' selected="selected"' ?>><?php echo $lang_profile['UTC-11:00'] ?></option>
                                <option value="-10"<?php if ($select_timezone == -10) echo ' selected="selected"' ?>><?php echo $lang_profile['UTC-10:00'] ?></option>
                                <option value="-9.5"<?php if ($select_timezone == -9.5) echo ' selected="selected"' ?>><?php echo $lang_profile['UTC-09:30'] ?></option>
                                <option value="-9"<?php if ($select_timezone == -9) echo ' selected="selected"' ?>><?php echo $lang_profile['UTC-09:00'] ?></option>
                                <option value="-8"<?php if ($select_timezone == -8) echo ' selected="selected"' ?>><?php echo $lang_profile['UTC-08:00'] ?></option>
                                <option value="-7"<?php if ($select_timezone == -7) echo ' selected="selected"' ?>><?php echo $lang_profile['UTC-07:00'] ?></option>
                                <option value="-6"<?php if ($select_timezone == -6) echo ' selected="selected"' ?>><?php echo $lang_profile['UTC-06:00'] ?></option>
                                <option value="-5"<?php if ($select_timezone == -5) echo ' selected="selected"' ?>><?php echo $lang_profile['UTC-05:00'] ?></option>
                                <option value="-4"<?php if ($select_timezone == -4) echo ' selected="selected"' ?>><?php echo $lang_profile['UTC-04:00'] ?></option>
                                <option value="-3.5"<?php if ($select_timezone == -3.5) echo ' selected="selected"' ?>><?php echo $lang_profile['UTC-03:30'] ?></option>
                                <option value="-3"<?php if ($select_timezone == -3) echo ' selected="selected"' ?>><?php echo $lang_profile['UTC-03:00'] ?></option>
                                <option value="-2"<?php if ($select_timezone == -2) echo ' selected="selected"' ?>><?php echo $lang_profile['UTC-02:00'] ?></option>
                                <option value="-1"<?php if ($select_timezone == -1) echo ' selected="selected"' ?>><?php echo $lang_profile['UTC-01:00'] ?></option>
                                <option value="0"<?php if ($select_timezone == 0) echo ' selected="selected"' ?>><?php echo $lang_profile['UTC'] ?></option>
                                <option value="1"<?php if ($select_timezone == 1) echo ' selected="selected"' ?>><?php echo $lang_profile['UTC+01:00'] ?></option>
                                <option value="2"<?php if ($select_timezone == 2) echo ' selected="selected"' ?>><?php echo $lang_profile['UTC+02:00'] ?></option>
                                <option value="3"<?php if ($select_timezone == 3) echo ' selected="selected"' ?>><?php echo $lang_profile['UTC+03:00'] ?></option>
                                <option value="3.5"<?php if ($select_timezone == 3.5) echo ' selected="selected"' ?>><?php echo $lang_profile['UTC+03:30'] ?></option>
                                <option value="4"<?php if ($select_timezone == 4) echo ' selected="selected"' ?>><?php echo $lang_profile['UTC+04:00'] ?></option>
                                <option value="4.5"<?php if ($select_timezone == 4.5) echo ' selected="selected"' ?>><?php echo $lang_profile['UTC+04:30'] ?></option>
                                <option value="5"<?php if ($select_timezone == 5) echo ' selected="selected"' ?>><?php echo $lang_profile['UTC+05:00'] ?></option>
                                <option value="5.5"<?php if ($select_timezone == 5.5) echo ' selected="selected"' ?>><?php echo $lang_profile['UTC+05:30'] ?></option>
                                <option value="5.75"<?php if ($select_timezone == 5.75) echo ' selected="selected"' ?>><?php echo $lang_profile['UTC+05:45'] ?></option>
                                <option value="6"<?php if ($select_timezone == 6) echo ' selected="selected"' ?>><?php echo $lang_profile['UTC+06:00'] ?></option>
                                <option value="6.5"<?php if ($select_timezone == 6.5) echo ' selected="selected"' ?>><?php echo $lang_profile['UTC+06:30'] ?></option>
                                <option value="7"<?php if ($select_timezone == 7) echo ' selected="selected"' ?>><?php echo $lang_profile['UTC+07:00'] ?></option>
                                <option value="8"<?php if ($select_timezone == 8) echo ' selected="selected"' ?>><?php echo $lang_profile['UTC+08:00'] ?></option>
                                <option value="8.75"<?php if ($select_timezone == 8.75) echo ' selected="selected"' ?>><?php echo $lang_profile['UTC+08:45'] ?></option>
                                <option value="9"<?php if ($select_timezone == 9) echo ' selected="selected"' ?>><?php echo $lang_profile['UTC+09:00'] ?></option>
                                <option value="9.5"<?php if ($select_timezone == 9.5) echo ' selected="selected"' ?>><?php echo $lang_profile['UTC+09:30'] ?></option>
                                <option value="10"<?php if ($select_timezone == 10) echo ' selected="selected"' ?>><?php echo $lang_profile['UTC+10:00'] ?></option>
                                <option value="10.5"<?php if ($select_timezone == 10.5) echo ' selected="selected"' ?>><?php echo $lang_profile['UTC+10:30'] ?></option>
                                <option value="11"<?php if ($select_timezone == 11) echo ' selected="selected"' ?>><?php echo $lang_profile['UTC+11:00'] ?></option>
                                <option value="11.5"<?php if ($select_timezone == 11.5) echo ' selected="selected"' ?>><?php echo $lang_profile['UTC+11:30'] ?></option>
                                <option value="12"<?php if ($select_timezone == 12) echo ' selected="selected"' ?>><?php echo $lang_profile['UTC+12:00'] ?></option>
                                <option value="12.75"<?php if ($select_timezone == 12.75) echo ' selected="selected"' ?>><?php echo $lang_profile['UTC+12:45'] ?></option>
                                <option value="13"<?php if ($select_timezone == 13) echo ' selected="selected"' ?>><?php echo $lang_profile['UTC+13:00'] ?></option>
                                <option value="14"<?php if ($select_timezone == 14) echo ' selected="selected"' ?>><?php echo $lang_profile['UTC+14:00'] ?></option>
                            </select>
                            <label for="fld<?php echo ++$forum_page['fld_count'] ?>"><?php echo $lang_profile['Timezone'] ?> <small><?php echo $lang_profile['Timezone help'] ?></small></label>
                        </div>
                    </div>
<?php ($hook = get_hook('rg_register_pre_dst_checkbox')) ? eval($hook) : null; ?>
                    <div class="row set<?php echo ++$forum_page['item_count'] ?>">
                        <div class="input-field col s12">
                            <input type="checkbox" id="fld<?php echo ++$forum_page['fld_count'] ?>" name="dst" <?php if ($select_dst) echo ' checked="checked"' ?>  />
                            <label for="fld<?php echo ++$forum_page['fld_count'] ?>"><?php echo $lang_profile['Adjust for DST'] ?> <small><?php echo $lang_profile['DST label'] ?></small></label>
                        </div>
                    </div>
                <?php ($hook = get_hook('rg_register_pre_group_end')) ? eval($hook) : null; ?>
                </div>
<?php ($hook = get_hook('rg_register_group_end')) ? eval($hook) : null; ?>
                <div class="card-action">
                    <div class="row">
                        <div class="input-field col s12 l6 offset-l3">
                            <button type="submit" name="register" class="btn btn-simple btn-primary btn-lg"><?php echo $lang_profile['Register'] ?></button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
<?php
($hook = get_hook('rg_end')) ? eval($hook) : null;

$tpl_temp = forum_trim(ob_get_contents());
$tpl_main = str_replace('<forum_main>', $tpl_temp, $tpl_main);
ob_end_clean();
// END SUBST - <forum_main>

require FORUM_ROOT . 'footer.php';
