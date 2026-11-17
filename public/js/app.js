// Avant chaque envoi, récupère un jeton CSRF à jour : un formulaire resté ouvert
// longtemps ne tombe plus sur « Page Expired ». Bloque aussi le double envoi.
(function () {
    var refreshUrl = document.currentScript.dataset.csrfUrl;

    document.addEventListener('submit', function (event) {
        var form = event.target;

        // getAttribute : form.method désignerait un champ nommé « method ».
        if (event.defaultPrevented || (form.getAttribute('method') || '').toLowerCase() !== 'post') {
            return;
        }

        event.preventDefault();
        if (form.dataset.sending) {
            return;
        }
        form.dataset.sending = '1';

        fetch(refreshUrl, { credentials: 'same-origin', cache: 'no-store', headers: { Accept: 'application/json' } })
            .then(function (response) { return response.ok ? response.json() : null; })
            .then(function (data) {
                var input = form.querySelector('input[name="_token"]');
                if (data && data.token && input) {
                    input.value = data.token;
                }
            })
            .catch(function () {})
            .then(function () { form.submit(); });
    });

    // Retour arrière du navigateur : la page restaurée doit pouvoir renvoyer ses formulaires.
    window.addEventListener('pageshow', function () {
        document.querySelectorAll('form[data-sending]').forEach(function (form) {
            delete form.dataset.sending;
        });
    });

    // Sur téléphone, chaque ligne de tableau s'affiche en fiche : la cellule reprend le titre de sa colonne.
    document.querySelectorAll('.table-wrap table').forEach(function (table) {
        var heads = Array.prototype.map.call(table.querySelectorAll('tr:first-child th'), function (th) {
            return th.textContent.trim();
        });
        if (!heads.length) {
            return;
        }

        // Les titres de colonnes sont masqués sur téléphone : le tri passe par une liste.
        var sortLinks = table.querySelectorAll('.sort-link');
        if (sortLinks.length && window.tableText) {
            var text = window.tableText;
            var select = document.createElement('select');
            select.setAttribute('aria-label', text.label);
            select.add(new Option(text.usual, sortLinks[0].dataset.reset));
            sortLinks.forEach(function (link) {
                var kind = link.dataset.kind;
                var order = kind === 'text' ? ['asc', 'desc'] : ['desc', 'asc'];
                order.forEach(function (direction) {
                    var wording = kind === 'text' ? (direction === 'asc' ? 'A → Z' : 'Z → A') : text[kind][direction];
                    var option = new Option(link.textContent.trim() + ' : ' + wording, link.dataset[direction]);
                    option.selected = link.dataset.current === direction;
                    select.add(option);
                });
            });
            select.addEventListener('change', function () { window.location.href = select.value; });

            var bar = document.createElement('label');
            bar.className = 'sort-bar phone-only';
            bar.append(text.sortBy, select);
            table.closest('.table-wrap').before(bar);
        }

        table.classList.add('stacked');
        table.querySelectorAll('tr').forEach(function (row) {
            var titled = false;
            Array.prototype.forEach.call(row.cells, function (cell, index) {
                if (cell.tagName !== 'TD') {
                    return;
                }
                var isCheck = cell.children.length === 1 && cell.firstElementChild.matches('input[type=checkbox]');
                var isEmpty = !isCheck && cell.textContent.trim() === '' && !cell.querySelector('input, button, a, img');
                cell.dataset.label = heads[index] || '';
                cell.classList.toggle('cell-check', isCheck);
                cell.classList.toggle('cell-empty', isEmpty);
                if (!isCheck && !isEmpty && !titled) {
                    cell.classList.add('cell-title');
                    titled = true;
                }
                var value = document.createElement('span');
                value.className = 'cell-value';
                while (cell.firstChild) {
                    value.appendChild(cell.firstChild);
                }
                cell.appendChild(value);
            });
        });
    });

    // WhatsApp ou les SMS s'ouvrent à part : au retour, l'utilisateur voit la facture suivante.
    // Dans l'application installée, le message s'ouvre depuis une page intermédiaire, dans la même fenêtre.
    var standaloneApp = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;

    document.querySelectorAll('form[data-message-send]').forEach(function (form) {
        if (standaloneApp) {
            form.removeAttribute('target');
            var via = document.createElement('input');
            via.type = 'hidden';
            via.name = 'via';
            via.value = 'app';
            form.appendChild(via);
        }

        form.addEventListener('submit', function () {
            if (form.dataset.pdfUrl) {
                var download = document.createElement('a');
                download.href = form.dataset.pdfUrl;
                download.download = '';
                document.body.appendChild(download);
                download.click();
                download.remove();
            }
            // Laisse au PDF le temps de partir avant de recharger la liste.
            if (!standaloneApp) {
                setTimeout(function () { window.location.reload(); }, 2500);
            }
        });
    });

    // Sur téléphone, le bouton PDF ouvre le partage du fichier (WhatsApp, e-mail…) ; ailleurs, il le télécharge.
    document.querySelectorAll('a[data-pdf-share]').forEach(function (link) {
        link.addEventListener('click', function (event) {
            var probe = window.File ? new File(['x'], 'facture.pdf', { type: 'application/pdf' }) : null;
            if (!window.matchMedia('(pointer: coarse)').matches || !navigator.canShare || !probe || !navigator.canShare({ files: [probe] })) {
                return;
            }
            event.preventDefault();
            fetch(link.href, { credentials: 'same-origin' }).then(function (response) {
                var name = (response.headers.get('Content-Disposition') || '').match(/filename="([^"]+)"/);
                return response.blob().then(function (blob) {
                    return navigator.share({ files: [new File([blob], name ? name[1] : 'facture.pdf', { type: 'application/pdf' })] });
                });
            }).catch(function (error) {
                if (error.name !== 'AbortError') {
                    window.location.href = link.href;
                }
            });
        });
    });

    // Un clic ailleurs referme le bouton d'information.
    document.addEventListener('click', function (event) {
        document.querySelectorAll('.info-button[open]').forEach(function (info) {
            if (!info.contains(event.target)) {
                info.removeAttribute('open');
            }
        });
    });

    // Menu en tiroir sur téléphone.
    var opener = document.querySelector('[data-nav-open]');
    if (opener) {
        var closer = document.querySelector('.sidebar-close');

        function setMenu(open) {
            document.body.classList.toggle('nav-open', open);
            opener.setAttribute('aria-expanded', String(open));
            // Le tiroir fermé est masqué : il faut le laisser s'afficher avant d'y placer le focus.
            setTimeout(function () { (open ? closer : opener).focus(); }, open ? 50 : 0);
        }

        opener.addEventListener('click', function () { setMenu(true); });
        document.querySelectorAll('[data-nav-close]').forEach(function (element) {
            element.addEventListener('click', function () { setMenu(false); });
        });
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && document.body.classList.contains('nav-open')) {
                setMenu(false);
            }
        });
    }
    // Application installable : le service worker (page hors connexion) et le bouton « Installer ».
    if ('serviceWorker' in navigator && window.isSecureContext) {
        window.addEventListener('load', function () {
            navigator.serviceWorker.register('/sw.js').catch(function () {});
        });
    }

    var installButtons = document.querySelectorAll('[data-install-app]');
    // Accord arrivé avant ce script : il a été gardé dans la tête de la page.
    var installPrompt = window.earlyInstallPrompt || null;
    var installed = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
    var isIphone = /iphone|ipad|ipod/i.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
    var labels = window.installLabels || {};
    var waiting = null;

    // Le bouton est visible sur toutes les tailles d'écran, sauf dans l'application déjà ouverte depuis son icône.
    function setButtons(text, disabled) {
        installButtons.forEach(function (button) {
            button.hidden = installed;
            button.disabled = !!disabled;
            button.title = text;
            button.setAttribute('aria-label', text);
            button.querySelectorAll('[data-install-label]').forEach(function (label) { label.textContent = text; });
        });
    }

    setButtons(labels.install, false);

    // « Installée ✓ » s'affiche un instant, puis le bouton redevient « Installer » : si l'application est
    // désinstallée plus tard, il reste utilisable sans recharger la page.
    var installedTimer = null;
    function showInstalled() {
        setButtons(labels.installed, true);
        clearTimeout(installedTimer);
        installedTimer = setTimeout(function () { setButtons(labels.install, false); }, 6000);
    }

    function install() {
        installPrompt.prompt();
        installPrompt.userChoice.then(function (choice) {
            installPrompt = null;
            if (choice.outcome === 'accepted') {
                showInstalled();
            } else {
                setButtons(labels.install, false);
            }
        });
    }

    window.addEventListener('beforeinstallprompt', function (event) {
        event.preventDefault();
        installPrompt = event;
        setButtons(labels.install, false);
        // Un clic attendait l'accord du navigateur : l'installation part tout de suite.
        if (waiting) {
            clearTimeout(waiting);
            waiting = null;
            install();
        }
    });

    installButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            if (installPrompt) {
                install();

                return;
            }
            if (isIphone) {
                document.querySelectorAll('[data-install-ios]').forEach(function (help) { help.hidden = false; });

                return;
            }
            // Le navigateur n'a pas encore donné son accord : on l'attend quelques secondes.
            setButtons(labels.preparing, true);
            waiting = setTimeout(function () {
                waiting = null;
                setButtons(labels.retry, false);
                setTimeout(function () {
                    if (!installPrompt && !installButtons[0].disabled) {
                        setButtons(labels.install, false);
                    }
                }, 4000);
            }, 10000);
        });
    });

    window.addEventListener('appinstalled', function () {
        installPrompt = null;
        showInstalled();
    });

    if (isIphone && !installed) {
        document.querySelectorAll('[data-install-ios]').forEach(function (help) { help.hidden = false; });
    }
})();
