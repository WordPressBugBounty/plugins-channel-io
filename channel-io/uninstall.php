<?php

if (!defined('WP_UNINSTALL_PLUGIN')) exit;

// 플러그인이 저장하는 옵션을 모두 지운다. memberHash secret(channel_io_secret_key)도 포함한다.
delete_option('channel_io_plugin_key');
delete_option('channel_io_secret_key');
delete_option('channel_io_hide_default_launcher');
delete_option('channel_io_custom_launcher_selector');
delete_option('channel_io_mobile_messenger_mode');
delete_option('channel_io_z_index');

?>
