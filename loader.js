(function () {
    'use strict';

    const config = {
        fadeOutDelay: 300,
        fadeOutDuration: 500,
        minDisplayTime: 500,
    };

    function initLoader() {
        const loader = document.getElementById('page-loader');
        if (!loader) return;

        const startTime = performance.now();

        window.addEventListener('load', function () {
            const loadTime = performance.now() - startTime;
            const remainingTime = Math.max(0, config.minDisplayTime - loadTime);

            setTimeout(() => {
                hideLoader();
            }, config.fadeOutDelay + remainingTime);
        });
    }

    function showLoader(text = 'Chargement en cours...') {
        const loader = document.getElementById('page-loader');
        if (!loader) return;

        // Mettre à jour le texte si nécessaire
        const textElement = loader.querySelector('.loader-text');
        if (textElement) textElement.textContent = text;

        loader.style.display = 'flex';
        // Petit délai pour permettre au display:flex de prise en compte avant l'opacité
        requestAnimationFrame(() => {
            loader.classList.remove('fade-out');
        });
    }

    function hideLoader() {
        const loader = document.getElementById('page-loader');
        if (!loader) return;

        loader.classList.add('fade-out');

        setTimeout(() => {
            loader.style.display = 'none';
        }, config.fadeOutDuration);
    }

    function handleFormSubmit() {
        const loginForm = document.querySelector('.login-form');
        if (loginForm) {
            loginForm.addEventListener('submit', function (e) {
                const username = document.getElementById('username')?.value;
                const password = document.getElementById('password')?.value;

                if (username && password) {
                    showLoader('Connexion en cours...');
                }
            });
        }

        const filterForm = document.getElementById('filter-form');
        if (filterForm) {
            filterForm.addEventListener('submit', function (e) {
                const formation = document.getElementById('formation')?.value;
                const annee = document.getElementById('annee')?.value;

                if (formation && annee) {
                    // showLoader('Chargement des données...');

                    // setTimeout(() => {
                    //     hideLoader();
                    // }, 5000);
                }
            });
        }
    }

    function handleLinkClicks() {
        document.querySelectorAll('a[href]').forEach(link => {
            link.addEventListener('click', function (e) {
                const href = this.getAttribute('href');

                if (href &&
                    !href.startsWith('mailto:') &&
                    !href.startsWith('#') &&
                    !href.startsWith('javascript:') &&
                    this.target !== '_blank') {

                    showLoader('Chargement en cours...');
                }
            });
        });
    }

    function handleButtonClicks() {
        const syncButton = document.querySelector('.btn-sync-header');
        if (syncButton) {
            const originalOnClick = syncButton.onclick;
            syncButton.onclick = function (e) {
                // showLoader('Synchronisation en cours...'); // DISABLED: Full page loader

                // ENABLE: Inline Loader
                const inlineLoader = document.getElementById('sync-loader');
                if (inlineLoader) inlineLoader.style.display = 'block';

                // Masquer l'ancienne alerte s'il y en a une
                const oldAlert = document.querySelector('.admin-alert');
                if (oldAlert) oldAlert.style.display = 'none';

                if (originalOnClick) {
                    return originalOnClick.call(this, e);
                }
            };
        }

        const logoutButtons = document.querySelectorAll('.btn-logout');
        logoutButtons.forEach(button => {
            button.addEventListener('click', function (e) {
                showLoader('Déconnexion...');
            });
        });
    }

    function handleBrowserNavigation() {
        window.addEventListener('pageshow', function (event) {
            if (event.persisted) {
                hideLoader();
            }
        });

        window.addEventListener('pagehide', function () {
            showLoader('Chargement en cours...');
        });
    }

    window.MnemosyneLoader = {
        show: showLoader,
        hide: hideLoader,
        config: config
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            initLoader();
            handleFormSubmit();
            handleLinkClicks();
            handleButtonClicks();
            handleBrowserNavigation();
        });
    } else {
        initLoader();
        handleFormSubmit();
        handleLinkClicks();
        handleButtonClicks();
        handleBrowserNavigation();
    }

})();