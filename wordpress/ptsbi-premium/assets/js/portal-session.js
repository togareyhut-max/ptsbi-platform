/**
 * Segarkan nonce form portal (admin-ajax tidak di-cache seperti HTML halaman).
 */
(function () {
    var cfg = window.ptprmPortalSession;
    if (!cfg || !cfg.ajaxUrl || !cfg.action) {
        return;
    }

    var fieldToAction = {
        ptprm_admin_settings: 'ptprm_admin_settings',
        ptprm_admin_post: 'ptprm_admin_post',
        ptprm_admin_member_approve: 'ptprm_admin_member_approve',
        ptprm_admin_member_reject: 'ptprm_admin_member_reject',
        ptprm_members_import_csv: 'ptprm_members_import_csv',
        ptprm_admin_team_photos: 'ptprm_admin_team_photos',
        ptprm_board_save: 'ptprm_board_save',
        ptprm_bidang_content_save: 'ptprm_bidang_content_save',
        ptprm_bidang_post_save: 'ptprm_bidang_post_save',
        ptprm_member_profile: 'ptprm_member_profile',
        ptprm_portal_pdf_save: 'ptprm_portal_pdf_save',
        ptprm_popup_portal_save: 'ptprm_popup_portal_save',
        ptprm_purge_cache: 'ptprm_purge_cache',
        ptprm_portal_login: 'ptprm_portal_login',
        ptprm_member_register: 'ptprm_member_register'
    };

    function applyNonces(nonces) {
        if (!nonces || typeof nonces !== 'object') {
            return;
        }

        document.querySelectorAll('form').forEach(function (form) {
            var action = '';
            Object.keys(fieldToAction).some(function (field) {
                if (form.querySelector('input[name="' + field + '"]')) {
                    action = fieldToAction[field];
                    return true;
                }
                return false;
            });
            if (!action || !nonces[action]) {
                return;
            }
            var nonce = form.querySelector('input[name="_wpnonce"]');
            if (nonce) {
                nonce.value = nonces[action];
            }
            var purgeNonce = form.querySelector('input[name="ptprm_purge_cache_nonce"]');
            if (purgeNonce && nonces.ptprm_purge_cache) {
                purgeNonce.value = nonces.ptprm_purge_cache;
            }
        });
    }

    function refresh() {
        var body = new URLSearchParams();
        body.set('action', cfg.action);

        fetch(cfg.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
            body: body.toString()
        })
            .then(function (res) {
                return res.json();
            })
            .then(function (json) {
                if (json && json.success && json.data && json.data.nonces) {
                    applyNonces(json.data.nonces);
                }
            })
            .catch(function () {
                /* abaikan — fallback verifikasi server tetap ada */
            });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', refresh);
    } else {
        refresh();
    }

    window.setInterval(refresh, 10 * 60 * 1000);
})();
