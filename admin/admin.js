(function () {
    'use strict';

    document.querySelectorAll('.admin-flash').forEach(function (el) {
        if (el.closest('.admin-login-page')) {
            return;
        }
        el.classList.add('admin-flash--autodismiss');
        window.setTimeout(function () {
            el.classList.add('is-hidden');
        }, 6200);
    });

    var dirty = false;
    document.querySelectorAll('form.js-admin-form-unsaved').forEach(function (form) {
        form.addEventListener('input', function () {
            dirty = true;
        });
        form.addEventListener('change', function () {
            dirty = true;
        });
        form.addEventListener('submit', function () {
            dirty = false;
        });
    });

    window.addEventListener('beforeunload', function (e) {
        if (!dirty) {
            return;
        }
        e.preventDefault();
        e.returnValue = '';
    });

    document.addEventListener('click', function (e) {
        var a = e.target.closest('a[data-confirm], button[data-confirm]');
        if (!a) {
            return;
        }
        var msg = a.getAttribute('data-confirm') || '';
        if (msg && !window.confirm(msg)) {
            e.preventDefault();
        }
    });

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.js-copy-url');
        if (!btn) {
            return;
        }
        e.preventDefault();
        var rel = btn.getAttribute('data-url') || '';
        if (!rel) {
            return;
        }
        var full = rel.indexOf('http') === 0 ? rel : (window.location.origin.replace(/\/$/, '') + '/' + rel.replace(/^\//, ''));
        function ok() {
            var t = btn.textContent;
            btn.textContent = 'Скопировано';
            window.setTimeout(function () {
                btn.textContent = t;
            }, 1600);
        }
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(full).then(ok).catch(function () {
                window.prompt('Копирование:', full);
            });
        } else {
            window.prompt('Копирование:', full);
        }
    });

    document.addEventListener('click', function (e) {
        var pick = e.target.closest('.js-pick-cms-media');
        if (!pick) {
            return;
        }
        var id = pick.getAttribute('data-target-input');
        var url = pick.getAttribute('data-url') || '';
        if (!id || !url) {
            return;
        }
        var input = document.getElementById(id);
        if (input) {
            input.value = url;
            input.dispatchEvent(new Event('input', { bubbles: true }));
            input.dispatchEvent(new Event('change', { bubbles: true }));
        }
    });

    // Media modal preview
    (function () {
        var modal = document.getElementById('media-modal');
        if (!modal) return;
        var img = document.getElementById('media-modal-img');
        var title = document.getElementById('media-modal-title');
        var urlIn = document.getElementById('media-modal-url');
        var copyBtn = document.getElementById('media-modal-copy');
        var openLink = document.getElementById('media-modal-open');
        var downloadLink = document.getElementById('media-modal-download');
        var delId = document.getElementById('media-modal-del-id');
        var editId = document.getElementById('media-modal-edit-id');

        function openFrom(el) {
            var card = el.closest('[data-media-id]');
            if (!card) return;
            var id = card.getAttribute('data-media-id') || '';
            var url = card.getAttribute('data-media-url') || '';
            var name = card.getAttribute('data-media-name') || '';
            if (!url) return;
            if (img) img.src = url;
            if (title) title.textContent = name || url;
            if (urlIn) urlIn.value = url;
            if (copyBtn) copyBtn.setAttribute('data-url', url);
            if (openLink) openLink.href = url;
            if (downloadLink) downloadLink.href = url;
            if (delId) delId.value = id;
            if (editId) editId.value = id;
            modal.setAttribute('aria-hidden', 'false');
            document.body.style.overflow = 'hidden';
        }

        function close() {
            modal.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = '';
            if (img) img.src = '';
        }

        document.addEventListener('click', function (e) {
            var openBtn = e.target.closest('.js-media-open');
            if (openBtn) {
                e.preventDefault();
                openFrom(openBtn);
                return;
            }
            if (e.target.closest('.js-modal-close')) {
                e.preventDefault();
                close();
            }
        });

        document.addEventListener('keydown', function (e) {
            if (modal.getAttribute('aria-hidden') !== 'false') return;
            if (e.key === 'Escape') close();
        });
    })();

    // Media library: drag & drop multi-upload with progress
    (function () {
        var form = document.querySelector('form.js-media-upload-form');
        if (!form) return;
        var input = form.querySelector('input[type="file"][name="files[]"]');
        var dz = form.querySelector('.js-media-dropzone');
        var pickBtn = form.querySelector('.js-media-pick');
        var queue = form.querySelector('.js-media-queue');
        var status = form.querySelector('.js-media-upload-status');
        var prog = form.querySelector('.js-media-progress');
        var bar = prog ? prog.querySelector('.admin-progress__bar') : null;
        if (!input || !dz) return;

        function setQueueText(files) {
            if (!queue) return;
            if (!files || !files.length) {
                queue.textContent = '';
                return;
            }
            var total = 0;
            for (var i = 0; i < files.length; i++) total += files[i].size || 0;
            var mb = (total / (1024 * 1024)).toFixed(2);
            queue.textContent = files.length + ' файл(ов), ' + mb + ' MB';
        }

        function setStatus(text) {
            if (status) status.textContent = text || '';
        }

        function setProgress(p) {
            if (!prog || !bar) return;
            prog.style.display = 'block';
            bar.style.width = Math.max(0, Math.min(100, p)) + '%';
        }

        if (pickBtn) {
            pickBtn.addEventListener('click', function (e) {
                e.preventDefault();
                input.click();
            });
        }
        input.addEventListener('change', function () {
            setQueueText(input.files);
            setStatus('');
        });

        ['dragenter', 'dragover'].forEach(function (evt) {
            dz.addEventListener(evt, function (e) {
                e.preventDefault();
                e.stopPropagation();
                dz.classList.add('is-dragover');
            });
        });
        ['dragleave', 'drop'].forEach(function (evt) {
            dz.addEventListener(evt, function (e) {
                e.preventDefault();
                e.stopPropagation();
                dz.classList.remove('is-dragover');
            });
        });
        dz.addEventListener('drop', function (e) {
            var dt = e.dataTransfer;
            if (!dt || !dt.files || !dt.files.length) return;
            input.files = dt.files;
            setQueueText(input.files);
            setStatus('Готово к загрузке. Нажмите «Загрузить» или подождите…');
            // auto-upload via XHR for smoother UX
            try {
                var fd = new FormData();
                fd.append('action', 'upload_json');
                for (var i = 0; i < dt.files.length; i++) {
                    fd.append('files[]', dt.files[i], dt.files[i].name);
                }
                var xhr = new XMLHttpRequest();
                xhr.open('POST', form.getAttribute('action') || window.location.href, true);
                xhr.upload.onprogress = function (ev) {
                    if (!ev.lengthComputable) return;
                    setProgress((ev.loaded / ev.total) * 100);
                };
                xhr.onload = function () {
                    setProgress(100);
                    if (xhr.status >= 200 && xhr.status < 300) {
                        setStatus('Загружено. Обновляем…');
                        window.location.reload();
                    } else {
                        setStatus('Ошибка загрузки.');
                    }
                };
                xhr.onerror = function () {
                    setStatus('Ошибка загрузки.');
                };
                setStatus('Загрузка…');
                setProgress(0);
                xhr.send(fd);
            } catch (err) {
                // fallback: do nothing (user can submit form)
                setStatus('');
            }
        });
    })();

    var bulkMaster = document.querySelector('[data-check-all-for]');
    if (bulkMaster) {
        var formId = bulkMaster.getAttribute('data-check-all-for');
        var form = formId ? document.getElementById(formId) : null;
        if (form) {
            var boxes = form.querySelectorAll('input[type="checkbox"].js-bulk-id');
            bulkMaster.addEventListener('change', function () {
                var on = bulkMaster.checked;
                boxes.forEach(function (cb) {
                    cb.checked = on;
                });
            });
        }
    }
})();
