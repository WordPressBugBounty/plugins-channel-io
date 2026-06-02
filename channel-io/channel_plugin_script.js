;
function ch_parseInt(data) {
  if (!data) { return undefined; }
  try {
    return parseInt(data);
  } catch (e) {
    return undefined;
  }
}

(function() {
  var w = window;
  if (w.ChannelIO) {
    return (window.console.error || window.console.log || function(){})('ChannelIO script included twice.');
  }
  var ch = function() {
    ch.c(arguments);
  };
  ch.q = [];
  ch.c = function(args) {
    ch.q.push(args);
  };
  w.ChannelIO = ch;

  function loadSdk() {
    if (w.ChannelIOInitialized) {
      return;
    }
    w.ChannelIOInitialized = true;
    var s = document.createElement('script');
    s.type = 'text/javascript';
    s.async = true;
    s.src = 'https://cdn.channel.io/plugin/ch-plugin-web.js';
    s.charset = 'UTF-8';
    var x = document.getElementsByTagName('script')[0];
    x.parentNode.insertBefore(s, x);
  }

  function buildBaseSettings() {
    return {
      "pluginKey": channel_io_options.channel_io_plugin_key,
      "hideChannelButtonOnBoot": channel_io_options.channel_io_hide_default_launcher === 'on',
      "customLauncherSelector": channel_io_options.channel_io_custom_launcher_selector,
      "mobileMessengerMode": channel_io_options.channel_io_mobile_messenger_mode === 'on' ? 'iframe' : undefined,
      "zIndex": ch_parseInt(channel_io_options.channel_io_z_index),
      "scriptProvider": "channel",
      "scriptPlatform": "wordpress",
      "scriptVersion": "2.0.0"
    };
  }

  function bootAnonymous() {
    ChannelIO('boot', buildBaseSettings());
  }

  function bootWithProfile(me) {
    var settings = buildBaseSettings();
    if (me && me.login && me.memberId) {
      settings.memberId = me.memberId;
      if (me.memberHash) {
        settings.memberHash = me.memberHash;
      }
      settings.profile = me.profile || {};
    }
    ChannelIO('boot', settings);
  }

  // 페이지 HTML 에 회원 정보를 박지 않고, AJAX profile endpoint 에서 받아 boot 한다.
  // 이렇게 하면 어떤 페이지 캐시 (LiteSpeed Cache, WP Rocket 등) 환경에서도
  // 한 회원의 정보가 다른 방문자에게 노출되는 누출이 발생하지 않는다.
  //
  // fetch / XHR 의 정상 응답 / 에러 외에 응답이 stalled 되어 promise 가 영영
  // settle 안 되는 케이스(slow 3G, 끊긴 네트워크, 서버 hang 등)도 막아야 한다.
  // settle 안 되면 start() 의 started 가드 때문에 재시도도 없이 ChannelIO('boot')
  // 자체가 호출되지 않아 위젯이 영원히 안 뜬다. settled 플래그 + 5초 타임아웃으로
  // 어떤 경로로든 정확히 한 번 익명 부트 fallback 이 보장되도록 한다.
  function fetchProfileAndBoot() {
    if (!channel_io_options || !channel_io_options.profile_url) {
      bootAnonymous();
      return;
    }

    var settled = false;
    var timeoutId = window.setTimeout(function() {
      if (settled) {
        return;
      }
      settled = true;
      bootAnonymous();
    }, 5000);

    function finish(callback) {
      if (settled) {
        return;
      }
      settled = true;
      window.clearTimeout(timeoutId);
      callback();
    }

    if (typeof window.fetch === 'function') {
      window.fetch(channel_io_options.profile_url, {
        credentials: 'same-origin'
      })
        .then(function(res) { return res.ok ? res.json() : null; })
        .then(function(me) {
          finish(function() { bootWithProfile(me); });
        })
        .catch(function() { finish(bootAnonymous); });
      return;
    }

    // fetch 미지원 브라우저용 fallback
    try {
      var xhr = new XMLHttpRequest();
      xhr.open('GET', channel_io_options.profile_url, true);
      xhr.withCredentials = true;
      xhr.onreadystatechange = function() {
        if (xhr.readyState !== 4) return;
        if (xhr.status >= 200 && xhr.status < 300) {
          try {
            var me = JSON.parse(xhr.responseText);
            finish(function() { bootWithProfile(me); });
          } catch (e) {
            finish(bootAnonymous);
          }
        } else {
          finish(bootAnonymous);
        }
      };
      xhr.onerror = function() { finish(bootAnonymous); };
      xhr.send();
    } catch (e) {
      finish(bootAnonymous);
    }
  }

  // start() 는 한 번만 실행되어야 한다. document.readyState 검사 + DOMContentLoaded
  // + load 세 경로 중 둘 이상이 동작할 수 있어 (예: load 가 DOMContentLoaded 뒤에
  // 한 번 더 발생) 가드 없이 두면 fetchProfileAndBoot() 가 두 번 호출되어 /profile
  // 호출과 ChannelIO('boot', ...) 가 중복 실행된다. loadSdk() 는 자체 가드(
  // ChannelIOInitialized) 가 있지만 boot 호출 자체는 막지 못하므로 여기서 차단.
  var started = false;
  function start() {
    if (started) {
      return;
    }
    started = true;
    loadSdk();
    fetchProfileAndBoot();
  }

  if (document.readyState === 'complete') {
    start();
  } else if (window.attachEvent) {
    window.attachEvent('onload', start);
  } else {
    window.addEventListener('DOMContentLoaded', start, false);
    window.addEventListener('load', start, false);
  }
})();
