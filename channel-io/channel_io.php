<?php
/*
Plugin Name: Channel.io
Version: 0.19
Description: Channel is a conversational CRM solution that helps online businesses to capture potential customers before they leave the websites.
*/
if (!defined('ABSPATH')) {
  exit;
}

define( 'CHANNEL_IO_VERSION', '0.19' );

// Add font to font icon to set menu icon

add_action('wp_enqueue_scripts','channel_io_register_style');

function channel_io_replace_admin_menu_icons_css() {
?>
  <style>
    #adminmenu #toplevel_page_channel_io div.wp-menu-image::before {
      font-family: 'channelicons';
      content: '\0041';
    }
  </style>
<?php
}

add_action('admin_head', 'channel_io_replace_admin_menu_icons_css');

// add menu

add_action('admin_menu', 'channel_io_plugin_create_menu');
add_action('admin_init', 'channel_io_register_plugin_settings');
add_action('wp_enqueue_scripts','channel_io_plugin_init');
add_action('wp_ajax_channel_io_profile', 'channel_io_ajax_profile');
add_action('wp_ajax_nopriv_channel_io_profile', 'channel_io_ajax_profile');

function channel_io_plugin_create_menu() {
  add_menu_page('Channel.io Settings', 'Channel.io', 'administrator', 'channel_io', 'channel_io_plugin_settings_page', '', 80);
}

function channel_io_plugin_settings_page() {
?>
<div class="wrap">
<h1>Channel.io Settings</h1>

<form method="post" action="options.php">
  <?php settings_fields('channel-plugin-settings-group'); ?>
  <?php do_settings_sections('channel-plugin-settings-group'); ?>
  <p>How to get plugin key: <a target="_blank" href="https://developers.channel.io/docs/what-is-a-channel-plugin">See here</a></p>
  <table class="form-table">
    <tr valign="top">
      <th scope="row">Plugin key</th>
      <td>
        <input
          type="text"
          name="channel_io_plugin_key"
          placeholder="Enter here"
          style="min-width: 350px;"
          value="<?php echo sanitize_text_field(get_option('channel_io_plugin_key')); ?>" />
      </td>
    </tr>

    <tr valign="top">
      <th scope="row">Secret key (optional)</th>
      <td>
        <?php // 저장된 secret 은 화면에 다시 출력하지 않는다. 빈칸으로 저장하면 기존 값을 유지하고, 삭제는 체크박스로 받는다. ?>
        <?php if (channel_io_has_secret_key_constant()) { ?>
        <input
          type="password"
          placeholder="Set in wp-config.php"
          style="min-width: 350px;"
          disabled />
        <p class="description">Using CHANNEL_IO_SECRET_KEY defined in wp-config.php.</p>
        <?php } else { ?>
        <?php $channel_io_has_saved_secret_key = '' !== channel_io_get_secret_key(); ?>
        <input
          type="password"
          name="channel_io_secret_key"
          placeholder="<?php echo esc_attr($channel_io_has_saved_secret_key ? 'Saved (leave blank to keep)' : 'Enter here'); ?>"
          style="min-width: 350px;"
          autocomplete="new-password"
          value="" />
        <?php if ($channel_io_has_saved_secret_key) { ?>
        <p>
          <label>
            <input type="checkbox" name="channel_io_secret_key_delete" value="1" />
            Delete saved secret key
          </label>
        </p>
        <?php } ?>
        <?php } ?>
      </td>
    </tr>

    <tr valign="top">
      <th scope="row">Hide channel button</th>
      <td>
        <input
          type="checkbox"
          name="channel_io_hide_default_launcher"
          <?php checked(get_option('channel_io_hide_default_launcher'), 'on' ); ?>
        />
      </td>
    </tr>

    <tr valign="top">
      <th scope="row">Mobile iframe mode</th>
      <td>
        <input
          type="checkbox"
          name="channel_io_mobile_messenger_mode"
          <?php checked(get_option('channel_io_mobile_messenger_mode'), 'on' ); ?>
        />
      </td>
    </tr>

    <tr valign="top">
      <th scope="row">z-Index</th>
      <td>
        <input
          type="text"
          name="channel_io_z_index"
          placeholder="z-index"
          style="min-width: 350px;"
          value="<?php echo sanitize_text_field(get_option('channel_io_z_index')); ?>" />
      </td>
    </tr>

    <tr valign="top">
      <th scope="row">Custom launcher selector (deprecated)</th>
      <td>
        <input
          type="text"
          name="channel_io_custom_launcher_selector"
          placeholder="Enter here"
          style="min-width: 350px;"
          value="<?php echo sanitize_text_field(get_option('channel_io_custom_launcher_selector')); ?>" />
      </td>
    </tr>
  </table>
<?php submit_button(); ?>
<?php
  $selector = sanitize_text_field(get_option('channel_io_custom_launcher_selector'));

  echo sprintf('
    <script>
      var chSelector = \'%s\';
      if (!chSelector) {
        document.getElementsByName(\'channel_io_custom_launcher_selector\')[0].setAttribute(\'disabled\', \'\')
      }
    </script>'
  , $selector);
?>
</form>
</div>
<?php }

function channel_io_register_plugin_settings() {
  register_setting('channel-plugin-settings-group', 'channel_io_plugin_key');
  register_setting('channel-plugin-settings-group', 'channel_io_secret_key');
  register_setting('channel-plugin-settings-group', 'channel_io_hide_default_launcher');
  register_setting('channel-plugin-settings-group', 'channel_io_custom_launcher_selector');
  register_setting('channel-plugin-settings-group', 'channel_io_mobile_messenger_mode');
  register_setting('channel-plugin-settings-group', 'channel_io_z_index');
}

// memberHash secret 은 wp-config.php 의 CHANNEL_IO_SECRET_KEY 상수를 우선 쓰고, 없으면
// wp_options(channel_io_secret_key) 값을 쓴다. 상수가 문자열이 아니거나 sanitize_text_field
// 후 비어 있으면(getenv() 실패로 false 등) 정의되지 않은 것으로 본다.
function channel_io_has_secret_key_constant() {
  return defined('CHANNEL_IO_SECRET_KEY')
    && is_string(CHANNEL_IO_SECRET_KEY)
    && '' !== sanitize_text_field(CHANNEL_IO_SECRET_KEY);
}

function channel_io_get_secret_key() {
  if (channel_io_has_secret_key_constant()) {
    return sanitize_text_field(CHANNEL_IO_SECRET_KEY);
  }
  return sanitize_text_field(get_option('channel_io_secret_key'));
}

// 설정 저장 시 secret 처리. 삭제 체크박스가 켜져 있으면 옵션을 지운다. 빈 값이면 기존 값을
// 유지한다. 빈칸으로 저장한 경우와, 상수를 써서 입력란이 비활성화돼 필드가 제출되지 않은
// 경우(options.php 가 null 로 update_option 을 부른다)가 해당한다. 두 경우 모두 기존 값을
// 돌려줘 update_option 이 행을 새로 쓰지 않게 한다. options.php 가 권한과 nonce 를 확인한 뒤
// update_option 을 부르므로 $_POST 를 그대로 읽는다.
add_filter('pre_update_option_channel_io_secret_key', 'channel_io_filter_secret_key_update', 10, 2);

function channel_io_filter_secret_key_update($value, $old_value) {
  if (!empty($_POST['channel_io_secret_key_delete'])) {
    delete_option('channel_io_secret_key');
    return $old_value;
  }
  if ('' === sanitize_text_field((string) $value)) {
    return $old_value;
  }
  return $value;
}

// 상수를 쓰면 DB 에 남은 secret 을 지운다.
add_action('admin_init', 'channel_io_delete_secret_key_option_if_constant');

function channel_io_delete_secret_key_option_if_constant() {
  if (channel_io_has_secret_key_constant() && false !== get_option('channel_io_secret_key')) {
    delete_option('channel_io_secret_key');
  }
}

function channel_io_register_style() {
  wp_register_style('channel_io_dashicons', plugins_url( '/css/channelicons.css', __FILE__));
  wp_enqueue_style('channel_io_dashicons');
}

function channel_io_plugin_init() {
  wp_register_script('channel-io-plugin-js', plugins_url( '/channel_plugin_script.js', __FILE__ ), array(), CHANNEL_IO_VERSION, true);

  // 회원 정보(memberId, email, mobileNumber 등)는 절대 페이지 HTML 에 inline 으로
  // 박지 않는다. 전역 페이지 캐시(LiteSpeed Cache, WP Rocket 등) 에 회원 정보가
  // 함께 저장되어 다른 방문자에게 노출되는 사고가 보고되었기 때문 (v0.17 이하).
  // 회원 정보는 wp_enqueue_scripts 시점이 아닌 클라이언트 fetch 로 받아온다
  // (channel_plugin_script.js 가 admin-ajax.php?action=channel_io_profile 호출).
  //
  // admin-ajax.php 를 쓰는 이유: REST API (/wp-json/...) 는 cookie 기반 인증 시
  // X-WP-Nonce 헤더가 필요한데, 사용자별 nonce 를 페이지 HTML 에 박으면 캐시
  // 누출이 같은 형태로 재현된다. nonce 를 빼면 rest_cookie_check_errors() 가
  // 로그인 사용자를 wp_set_current_user(0) 으로 처리해 익명으로 응답해버리는
  // 부작용이 있다. admin-ajax 는 이 강제 nonce 검증이 없어 워드프레스 로그인
  // 쿠키만으로 is_user_logged_in() 이 정상 동작한다.
  $channel_io_options = array(
    'channel_io_plugin_key' => sanitize_text_field(get_option('channel_io_plugin_key')),
    'channel_io_hide_default_launcher' => get_option('channel_io_hide_default_launcher'),
    'channel_io_custom_launcher_selector' => sanitize_text_field(get_option('channel_io_custom_launcher_selector')),
    'channel_io_mobile_messenger_mode' => get_option('channel_io_mobile_messenger_mode'),
    'channel_io_z_index' => sanitize_text_field(get_option('channel_io_z_index')),
    'profile_url' => esc_url_raw(add_query_arg('action', 'channel_io_profile', admin_url('admin-ajax.php'))),
  );

  wp_localize_script('channel-io-plugin-js', 'channel_io_options', $channel_io_options);
  wp_enqueue_script('channel-io-plugin-js');
}

function channel_io_ajax_profile() {
  // admin-ajax.php 응답은 어떤 layer 에서도 캐시되지 않도록 강제. WP Super Cache,
  // W3 Total Cache, LiteSpeed Cache 등은 모두 nocache_headers() / DONOTCACHEPAGE 를
  // 존중한다.
  nocache_headers();
  if (!defined('DONOTCACHEPAGE')) {
    define('DONOTCACHEPAGE', true);
  }

  if (!is_user_logged_in()) {
    wp_send_json(array('login' => false));
  }

  $current_user = wp_get_current_user();
  $secret_key = channel_io_get_secret_key();
  $member_hash = !empty($secret_key)
    ? hash_hmac('sha256', $current_user->ID, pack('H*', $secret_key))
    : null;
  // get_user_meta() 는 메타가 없을 때 빈 문자열 ''을 반환한다. 빈 문자열을 그대로
  // SDK 에 전달하면 채널톡 서버에서 user.profile.mobileNumber 를 빈 값으로 덮어쓸
  // 위험이 있어, 값이 없으면 null 로 응답해 SDK 가 해당 필드를 무시하도록 한다.
  $mobile_number = get_user_meta($current_user->ID, 'billing_phone', true);

  wp_send_json(array(
    'login' => true,
    'memberId' => $current_user->ID,
    'memberHash' => $member_hash,
    'profile' => array(
      'name' => $current_user->display_name,
      'email' => $current_user->user_email,
      'mobileNumber' => !empty($mobile_number) ? $mobile_number : null,
    ),
  ));
}

// Hook activation

add_action('activated_plugin', 'channel_io_activation_redirect');

function channel_io_activation_redirect( $plugin ) {
  if ($plugin == plugin_basename( __FILE__ )) {
    exit(wp_redirect(admin_url('admin.php?page=channel_io')));
  }
}
?>
