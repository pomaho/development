define(['jquery'], function ($) {
  var CustomWidget = function () {
    var self = this;
    var rootClass = 'sonic-expert-task-overdue-widget';

    function settings() {
      if (typeof self.get_settings === 'function') {
        return self.get_settings() || {};
      }

      return {};
    }

    function normalizeBaseUrl(value) {
      return String(value || 'https://develop.sonic.expert').replace(/\/+$/, '');
    }

    function iframeUrl() {
      var currentSettings = settings();
      var publicKey = String(currentSettings.public_key || '').trim();
      var baseUrl = normalizeBaseUrl(currentSettings.service_base_url);

      if (!publicKey) {
        return null;
      }

      return baseUrl + '/widgets/amo/' + encodeURIComponent(publicKey) + '/task-overdue-dashboard';
    }

    function escapeAttribute(value) {
      return String(value)
        .replace(/&/g, '&amp;')
        .replace(/"/g, '&quot;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');
    }

    function mountNode() {
      var existing = $('.' + rootClass);

      if (existing.length) {
        return existing.first();
      }

      var candidates = [
        '.dashboard-widgets__wrap',
        '.dashboard__widgets',
        '.widget-page__content',
        '#work-area',
        'body'
      ];

      for (var i = 0; i < candidates.length; i += 1) {
        var candidate = $(candidates[i]).first();

        if (candidate.length) {
          var node = $('<div/>', { class: rootClass });
          candidate.prepend(node);

          return node;
        }
      }

      return null;
    }

    function renderEmptyState(node) {
      node.html(
        '<div class="sonic-expert-task-overdue-card">' +
          '<div class="sonic-expert-task-overdue-title">Sonic Expert</div>' +
          '<div class="sonic-expert-task-overdue-message">Укажите ключ клиента public_key в настройках виджета.</div>' +
        '</div>'
      );
    }

    function renderIframe(node, url) {
      node.html(
        '<div class="sonic-expert-task-overdue-card">' +
          '<iframe class="sonic-expert-task-overdue-frame" src="' + escapeAttribute(url) + '" title="Sonic Expert task overdue dashboard"></iframe>' +
        '</div>'
      );
    }

    function renderWidget() {
      var node = mountNode();

      if (!node) {
        return true;
      }

      var url = iframeUrl();

      if (!url) {
        renderEmptyState(node);
        return true;
      }

      renderIframe(node, url);

      return true;
    }

    this.callbacks = {
      render: function () {
        return renderWidget();
      },

      init: function () {
        return true;
      },

      bind_actions: function () {
        return true;
      },

      settings: function () {
        return true;
      },

      onSave: function () {
        renderWidget();
        return true;
      },

      destroy: function () {
        $('.' + rootClass).remove();
        return true;
      }
    };

    return this;
  };

  return CustomWidget;
});
