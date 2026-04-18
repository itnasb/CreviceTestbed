(function () {
  'use strict';

  /*
   * Intentionally obvious client-side permission object for lab training.
   * Students are expected to inspect this flow in DevTools.
   */
  var accessState = null;

  function byId(id) {
    return document.getElementById(id);
  }

  function openPopover(popover) {
    if (!popover) return;
    popover.classList.add('is-open');
    popover.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
  }

  function closePopover(popover) {
    if (!popover) return;
    popover.classList.remove('is-open');
    popover.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
  }

  function setBannerContent(html) {
    var banner = byId('bannerMessage');
    var wrap = byId('bannerMessageWrap');

    if (!banner || !wrap) return;

    /*
     * Intentionally unsafe sink for lab purposes.
     * Note: innerHTML does not reliably execute injected <script> tags.
     */
    banner.innerHTML = html;

    if (html && String(html).trim() !== '') {
      wrap.style.display = '';
    } else {
      wrap.style.display = 'none';
    }
  }

  function fetchJson(url, options) {
    return fetch(url, options).then(function (response) {
      return response.json().then(function (data) {
        if (!response.ok) {
          throw new Error(data && data.message ? data.message : 'Request failed.');
        }
        return data;
      });
    });
  }

  function loadBannerIntoPage() {
    fetchJson('?action=get_banner', {
      headers: {
        'X-Requested-With': 'XMLHttpRequest'
      }
    }).then(function (data) {
      setBannerContent(data.banner || '');
    }).catch(function () {
      setBannerContent('');
    });
  }

  function restoreBanner(statusTarget) {
    fetchJson('?action=restore_banner', {
      method: 'POST',
      headers: {
        'X-Requested-With': 'XMLHttpRequest'
      }
    }).then(function (data) {
      setBannerContent(data.banner || '');
      if (statusTarget) {
        statusTarget.textContent = data.message || 'Banner restored successfully.';
      }
    }).catch(function (error) {
      if (statusTarget) {
        statusTarget.textContent = error.message || 'Failed to restore banner.';
      }
    });
  }

  function loadCurrentBannerIntoEditor() {
    var editor = byId('bannerEditor');
    var status = byId('statusMessage');

    fetchJson('?action=get_banner', {
      headers: {
        'X-Requested-With': 'XMLHttpRequest'
      }
    }).then(function (data) {
      if (editor) {
        editor.value = data.banner || '';
      }
      if (status) {
        status.textContent = '';
      }
    }).catch(function (error) {
      if (editor) {
        editor.value = '';
      }
      if (status) {
        status.textContent = error.message || 'Failed to load current banner.';
      }
    });
  }

  /*
   * First check:
   * decides whether the wrench should be shown.
   */
  function renderWrench() {
    var mount = byId('wrenchMount');
    if (!mount || !accessState || !accessState.permissions) {
      return;
    }

    if (accessState.permissions.admin !== true) {
      mount.innerHTML = '';
      return;
    }

mount.innerHTML =
  '<button type="button" id="toolWrenchBtn" class="wrench-btn" aria-label="Open tools" title="Banner Tools">' +
    '<span class="icon">&#128295;</span>' +
    '<span class="label">Admin</span>' +
  '</button>';

    var wrenchBtn = byId('toolWrenchBtn');
    if (wrenchBtn) {
      wrenchBtn.addEventListener('click', openBannerPanel);
    }
  }


function checkAdminAccess() {
  return fetchJson('?action=permissions', {
    headers: {
      'X-Requested-With': 'XMLHttpRequest'
    }
  }).then(function (data) {
    accessState = data;
    window.accessState = accessState;

    return !!(
      data &&
      data.permissions &&
      data.permissions.admin === true
    );
  });
}

function openBannerPanel() {
  var panel = byId('toolsPopover');
  var status = byId('statusMessage');

  checkAdminAccess().then(function (isAdmin) {
    /*
   * Second check:
   * decides whether the banner tools panel can be opened.
   */
	if (!isAdmin) {
      if (status) {
        status.textContent = 'You do not have access to this page.';
      }
      return;
    }

    loadCurrentBannerIntoEditor();
    openPopover(panel);
  }).catch(function () {
    if (status) {
      status.textContent = 'Unable to verify access.';
    }
  });
}

  function bindStaticControls() {
    var restoreMain = byId('restoreBannerBtn');
    var restoreTools = byId('restoreBannerBtnTools');
    var saveBtn = byId('saveBannerBtn');
    var closeTop = byId('closeToolsPopoverTop');
    var closeBottom = byId('closeToolsPopoverBottom');
    var panel = byId('toolsPopover');
    var status = byId('statusMessage');
    var editor = byId('bannerEditor');

    if (restoreMain) {
      restoreMain.addEventListener('click', function () {
        restoreBanner(null);
      });
    }

    if (restoreTools) {
      restoreTools.addEventListener('click', function () {
        restoreBanner(status);
        loadCurrentBannerIntoEditor();
      });
    }

    if (saveBtn) {
      saveBtn.addEventListener('click', function () {
        var body = new URLSearchParams();
        body.append('action', 'save_banner');
        body.append('banner_html', editor ? editor.value : '');

        fetchJson('', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
            'X-Requested-With': 'XMLHttpRequest'
          },
          body: body.toString()
        }).then(function (data) {
          if (status) {
            status.textContent = data.message || 'Banner updated successfully. Check the main page.';
          }
        }).catch(function (error) {
          if (status) {
            status.textContent = error.message || 'Failed to update banner.';
          }
        });
      });
    }

    if (closeTop) {
      closeTop.addEventListener('click', function () {
        closePopover(panel);
      });
    }

    if (closeBottom) {
      closeBottom.addEventListener('click', function () {
        closePopover(panel);
      });
    }

    if (panel) {
      panel.addEventListener('click', function (event) {
        if (event.target === panel) {
          closePopover(panel);
        }
      });
    }

    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape' && panel && panel.classList.contains('is-open')) {
        closePopover(panel);
      }
    });
  }

  function requestAccessData() {
    return fetchJson('?action=permissions', {
      headers: {
        'X-Requested-With': 'XMLHttpRequest'
      }
    }).then(function (data) {
      accessState = data;
      window.accessState = accessState;
      renderWrench();
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    bindStaticControls();
    loadBannerIntoPage();
    requestAccessData().catch(function () {
      accessState = {
        roles: ['user'],
        permissions: {
          view: true,
          admin: false
        }
      };
      window.accessState = accessState;
      renderWrench();
    });
  });
})();