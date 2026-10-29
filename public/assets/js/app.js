document.addEventListener('DOMContentLoaded', function () {
    // Fecha os toasts de sucesso/erro (mensagens flash) ao clicar no botão de fechar.
    document.querySelectorAll('.toast__close').forEach(function (button) {
        button.addEventListener('click', function () {
            button.closest('.toast').remove();
        });
    });

    // Confirmação de ações destrutivas via diálogo centralizado (substitui window.confirm).
    const confirmDialog = document.getElementById('confirm-dialog');
    const confirmMessage = confirmDialog.querySelector('.confirm-dialog__message');
    const confirmOkBtn = confirmDialog.querySelector('[data-confirm-ok]');
    const confirmCancelBtn = confirmDialog.querySelector('[data-confirm-cancel]');

    const askConfirm = function (message) {
        return new Promise(function (resolve) {
            confirmMessage.textContent = message;
            confirmDialog.showModal();

            const cleanup = function (result) {
                confirmDialog.close();
                confirmOkBtn.removeEventListener('click', onOk);
                confirmCancelBtn.removeEventListener('click', onCancel);
                confirmDialog.removeEventListener('cancel', onCancel);
                resolve(result);
            };
            const onOk = function () { cleanup(true); };
            const onCancel = function (event) {
                if (event) { event.preventDefault(); }
                cleanup(false);
            };

            confirmOkBtn.addEventListener('click', onOk);
            confirmCancelBtn.addEventListener('click', onCancel);
            confirmDialog.addEventListener('cancel', onCancel);
        });
    };

    // Fecha ao clicar fora do conteúdo (no backdrop).
    confirmDialog.addEventListener('click', function (event) {
        if (event.target === confirmDialog) {
            confirmDialog.dispatchEvent(new Event('cancel', { cancelable: true }));
        }
    });

    // Diálogo informativo (substitui window.alert para resultados de ações, ex.: teste de conexão).
    const infoDialog = document.getElementById('info-dialog');
    const infoIcon = infoDialog.querySelector('[data-info-icon]');
    const infoMessage = infoDialog.querySelector('[data-info-message]');
    const infoOkBtn = infoDialog.querySelector('[data-info-ok]');

    const ICON_SUCCESS = '<svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/></svg>';
    const ICON_ERROR = '<svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>';

    const ICON_WARNING = '<svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>';

    // ok: true (sucesso), false (erro) ou 'aviso' (resultado parcial).
    const showInfo = function (message, ok) {
        const tipo = ok === 'aviso' ? 'warning' : (ok ? 'success' : 'error');
        infoIcon.className = 'confirm-dialog__icon confirm-dialog__icon--' + tipo;
        infoIcon.innerHTML = { success: ICON_SUCCESS, warning: ICON_WARNING, error: ICON_ERROR }[tipo];
        infoMessage.textContent = message;
        infoDialog.showModal();
    };

    infoOkBtn.addEventListener('click', function () { infoDialog.close(); });
    infoDialog.addEventListener('cancel', function () { infoDialog.close(); });
    infoDialog.addEventListener('click', function (event) {
        if (event.target === infoDialog) {
            infoDialog.close();
        }
    });

    document.querySelectorAll('[data-confirm]').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (form.dataset.confirmed === 'true') {
                return;
            }

            event.preventDefault();
            askConfirm(form.dataset.confirm).then(function (confirmed) {
                if (confirmed) {
                    form.dataset.confirmed = 'true';
                    form.requestSubmit ? form.requestSubmit() : form.submit();
                }
            });
        });
    });

    // Revelar/ocultar senha de FTP via requisição ao backend (senha nunca fica no HTML).
    const ICON_EYE = '<svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8Z"/><circle cx="12" cy="12" r="3"/></svg>';
    const ICON_EYE_OFF = '<svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.94 10.94 0 0 1 12 20c-7 0-11-8-11-8a21.8 21.8 0 0 1 5.06-6.06M9.9 4.24A10.94 10.94 0 0 1 12 4c7 0 11 8 11 8a21.8 21.8 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>';

    const setRevealButtonState = function (button, revealed) {
        button.dataset.revealed = revealed ? 'true' : 'false';
        button.innerHTML = revealed ? ICON_EYE_OFF : ICON_EYE;
        const label = revealed ? 'Ocultar senha' : 'Mostrar senha';
        button.setAttribute('aria-label', label);
        button.setAttribute('title', label);
    };

    document.querySelectorAll('[data-reveal-url]').forEach(function (button) {
        button.addEventListener('click', function () {
            const wrapper = button.closest('.password-cell');
            const output = wrapper.querySelector('.password-mask');
            const token = document.querySelector('meta[name="csrf-token"]').content;

            if (button.dataset.revealed === 'true') {
                output.textContent = '••••••••';
                setRevealButtonState(button, false);
                return;
            }

            fetch(button.dataset.revealUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: '_token=' + encodeURIComponent(token),
            })
                .then(function (response) { return response.json(); })
                .then(function (data) {
                    if (data.senha) {
                        output.textContent = data.senha;
                        setRevealButtonState(button, true);
                    } else {
                        output.textContent = data.error || 'Erro ao carregar';
                    }
                })
                .catch(function () {
                    output.textContent = 'Erro ao carregar';
                });
        });
    });

    // Testar acesso FTP e exibir o resultado de forma amigável.
    document.querySelectorAll('[data-test-url]').forEach(function (button) {
        button.addEventListener('click', function () {
            const token = document.querySelector('meta[name="csrf-token"]').content;
            const original = button.textContent;

            button.disabled = true;
            button.textContent = 'Testando...';

            fetch(button.dataset.testUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: '_token=' + encodeURIComponent(token),
            })
                .then(function (response) { return response.json(); })
                .then(function (data) {
                    const message = data.message || (data.ok ? 'Conexão FTP realizada com sucesso!' : 'Não foi possível testar a conexão.');
                    showInfo(message, !!data.ok);
                })
                .catch(function () {
                    showInfo('Não foi possível testar a conexão. Tente novamente.', false);
                })
                .finally(function () {
                    button.disabled = false;
                    button.textContent = original;
                });
        });
    });

    // Verificar Google Analytics e Tag Manager no site do cliente.
    document.querySelectorAll('[data-tags-url]').forEach(function (button) {
        button.addEventListener('click', function () {
            const token = document.querySelector('meta[name="csrf-token"]').content;
            const original = button.textContent;

            button.disabled = true;
            button.textContent = 'Verificando...';

            fetch(button.dataset.tagsUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: '_token=' + encodeURIComponent(token),
            })
                .then(function (response) { return response.json(); })
                .then(function (data) {
                    const estado = { ok: true, parcial: 'aviso' }[data.estado] || false;
                    showInfo(data.mensagem || 'Não foi possível verificar o site.', estado);
                })
                .catch(function () {
                    showInfo('Não foi possível verificar o site. Tente novamente.', false);
                })
                .finally(function () {
                    button.disabled = false;
                    button.textContent = original;
                });
        });
    });

    // Copiar senha para a área de transferência.
    document.querySelectorAll('[data-copy-url]').forEach(function (button) {
        button.addEventListener('click', function () {
            const token = document.querySelector('meta[name="csrf-token"]').content;

            fetch(button.dataset.copyUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: '_token=' + encodeURIComponent(token),
            })
                .then(function (response) { return response.json(); })
                .then(function (data) {
                    const original = button.textContent;

                    if (data.senha && navigator.clipboard) {
                        navigator.clipboard.writeText(data.senha);
                        button.textContent = 'Copiado!';
                    } else {
                        window.alert(data.error || 'Não foi possível copiar a senha.');
                        return;
                    }

                    setTimeout(function () { button.textContent = original; }, 1500);
                });
        });
    });
});
