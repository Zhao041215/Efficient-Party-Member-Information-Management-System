/**
 * 主题管理：浅色党政红 / 深红夜间
 * 生化学院党员信息管理系统
 */

(function() {
    'use strict';

    const STORAGE_KEY = 'theme';
    const THEMES = ['light', 'dark'];
    const DEFAULT_THEME = 'light';
    let manager = null;

    function normalizeTheme(theme) {
        return THEMES.includes(theme) ? theme : null;
    }

    function readLocalTheme() {
        try {
            return normalizeTheme(localStorage.getItem(STORAGE_KEY));
        } catch (error) {
            return null;
        }
    }

    function readCookieTheme() {
        const match = document.cookie.match(/(?:^|;\s*)theme=(dark|light)(?:;|$)/);
        return match ? normalizeTheme(match[1]) : null;
    }

    function readDomTheme() {
        return normalizeTheme(document.documentElement.getAttribute('data-theme')) ||
            (document.body ? normalizeTheme(document.body.getAttribute('data-theme')) : null);
    }

    function readStoredTheme() {
        return readLocalTheme() || readCookieTheme();
    }

    function resolveTheme(theme) {
        return normalizeTheme(theme) || readStoredTheme() || readDomTheme() || DEFAULT_THEME;
    }

    function persistTheme(theme) {
        const normalizedTheme = normalizeTheme(theme) || DEFAULT_THEME;

        try {
            localStorage.setItem(STORAGE_KEY, normalizedTheme);
        } catch (error) {
            // localStorage 不可用时仍使用 cookie 维持跨页一致。
        }

        document.cookie = `theme=${normalizedTheme}; path=/; max-age=31536000; SameSite=Lax`;
        return normalizedTheme;
    }

    function applyThemeToDom(theme) {
        const normalizedTheme = normalizeTheme(theme) || DEFAULT_THEME;
        const colorScheme = normalizedTheme === 'dark' ? 'dark' : 'light';

        document.documentElement.setAttribute('data-theme', normalizedTheme);
        document.documentElement.style.colorScheme = colorScheme;

        if (document.body) {
            document.body.setAttribute('data-theme', normalizedTheme);
            document.body.style.colorScheme = colorScheme;
        }

        window.__shxyTheme = normalizedTheme;
        return normalizedTheme;
    }

    const initialTheme = resolveTheme();
    applyThemeToDom(initialTheme);
    persistTheme(initialTheme);

    class ThemeManager {
        constructor() {
            this.currentTheme = resolveTheme();
            this.boundSync = this.syncFromStorage.bind(this);
            this.init();
        }

        init() {
            this.applyTheme(this.currentTheme, { persist: true });
            this.createToggleButton();
            this.setupEventListeners();
        }

        getTheme() {
            return resolveTheme(this.currentTheme);
        }

        getStoredTheme() {
            return readStoredTheme();
        }

        setStoredTheme(theme) {
            return persistTheme(theme);
        }

        applyTheme(theme, options = {}) {
            const normalizedTheme = applyThemeToDom(resolveTheme(theme));
            this.currentTheme = normalizedTheme;

            if (options.persist !== false) {
                this.setStoredTheme(normalizedTheme);
            }

            this.updateToggleButton();
            return normalizedTheme;
        }

        syncFromStorage() {
            return this.applyTheme(resolveTheme(), { persist: true });
        }

        toggleTheme() {
            const newTheme = this.getTheme() === 'light' ? 'dark' : 'light';
            this.applyTheme(newTheme, { persist: true });

            if (document.body) {
                document.body.style.transition = 'background-color 0.3s ease, color 0.3s ease';
                setTimeout(() => {
                    document.body.style.transition = '';
                }, 300);
            }
        }

        shouldHideToggleButton() {
            return Boolean(document.querySelector('.login-container'));
        }

        createToggleButton() {
            if (this.shouldHideToggleButton() || document.getElementById('themeToggle')) {
                this.updateToggleButton();
                return;
            }

            const button = document.createElement('button');
            button.className = 'theme-toggle';
            button.id = 'themeToggle';
            button.type = 'button';
            document.body.appendChild(button);
            this.updateToggleButton();
        }

        updateToggleButton() {
            const button = document.getElementById('themeToggle');
            if (!button) {
                return;
            }

            const theme = this.getTheme();
            const isLight = theme === 'light';
            const label = isLight ? '切换深红夜间模式' : '切换浅色党政红模式';

            button.innerHTML = isLight
                ? '<i class="fa-solid fa-moon"></i>'
                : '<i class="fa-solid fa-sun"></i>';
            button.setAttribute('aria-label', label);
            button.setAttribute('title', label);
            button.setAttribute('aria-pressed', String(!isLight));
        }

        setupEventListeners() {
            document.addEventListener('click', (event) => {
                if (event.target.closest('#themeToggle')) {
                    this.toggleTheme();
                }
            });

            window.addEventListener('pageshow', this.boundSync);

            window.addEventListener('storage', (event) => {
                if (event.key === STORAGE_KEY) {
                    this.syncFromStorage();
                }
            });

            if (window.matchMedia) {
                const media = window.matchMedia('(prefers-color-scheme: dark)');
                const onPreferenceChange = (event) => {
                    if (!this.getStoredTheme()) {
                        this.applyTheme(event.matches ? 'dark' : 'light', { persist: true });
                    }
                };

                if (typeof media.addEventListener === 'function') {
                    media.addEventListener('change', onPreferenceChange);
                } else if (typeof media.addListener === 'function') {
                    media.addListener(onPreferenceChange);
                }
            }
        }
    }

    window.SHXYTheme = {
        get() {
            return manager ? manager.getTheme() : resolveTheme();
        },
        apply(theme, options) {
            if (manager) {
                return manager.applyTheme(theme, options);
            }

            const normalizedTheme = applyThemeToDom(resolveTheme(theme));
            if (!options || options.persist !== false) {
                persistTheme(normalizedTheme);
            }
            return normalizedTheme;
        },
        persist(theme) {
            return persistTheme(resolveTheme(theme));
        },
        sync() {
            return manager ? manager.syncFromStorage() : window.SHXYTheme.apply(resolveTheme());
        },
        readStored() {
            return readStoredTheme();
        }
    };

    function bootThemeManager() {
        if (!manager) {
            manager = new ThemeManager();
        } else {
            manager.syncFromStorage();
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bootThemeManager, { once: true });
    } else {
        bootThemeManager();
    }
})();
