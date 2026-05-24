document.querySelectorAll('[data-confirm-delete]').forEach((link) => {
    link.addEventListener('click', (event) => {
        const message = link.getAttribute('data-confirm-delete') || 'Continue?';
        if (!window.confirm(message)) {
            event.preventDefault();
        }
    });
});

document.querySelectorAll('[data-restock-message]').forEach((button) => {
    button.addEventListener('click', () => {
        window.alert(button.getAttribute('data-restock-message') || 'Stock needs to be restocked.');
    });
});

document.querySelectorAll('[data-category-input]').forEach((input) => {
    const form = input.closest('form');
    const select = form ? form.querySelector('[data-category-select]') : null;
    if (!select) {
        return;
    }

    const syncCategoryState = () => {
        const hasTypedCategory = input.value.trim() !== '';
        select.classList.toggle('category-select-muted', hasTypedCategory);
        select.setAttribute('aria-description', hasTypedCategory
            ? 'Typed category will be used when the form is submitted.'
            : 'Selected category will be used when the form is submitted.');
    };

    input.addEventListener('input', syncCategoryState);
    syncCategoryState();
});

const revealItems = document.querySelectorAll('.reveal-on-scroll');
if (revealItems.length > 0 && 'IntersectionObserver' in window) {
    const revealObserver = new IntersectionObserver((entries, observer) => {
        entries.forEach((entry) => {
            if (!entry.isIntersecting) {
                return;
            }

            entry.target.classList.add('is-visible');
            observer.unobserve(entry.target);
        });
    }, {
        threshold: 0.2,
    });

    revealItems.forEach((item, index) => {
        item.style.transitionDelay = `${Math.min(index * 60, 360)}ms`;
        revealObserver.observe(item);
    });
} else {
    revealItems.forEach((item) => item.classList.add('is-visible'));
}

document.querySelectorAll('[data-counter]').forEach((counter) => {
    const target = Number.parseInt(counter.getAttribute('data-counter') || '0', 10);
    if (!Number.isFinite(target) || target <= 0) {
        counter.textContent = '0';
        return;
    }

    let started = false;
    const animateCounter = () => {
        if (started) {
            return;
        }

        started = true;
        const duration = 1200;
        const startTime = performance.now();

        const tick = (now) => {
            const progress = Math.min((now - startTime) / duration, 1);
            const eased = 1 - Math.pow(1 - progress, 3);
            counter.textContent = Math.round(target * eased).toLocaleString();

            if (progress < 1) {
                window.requestAnimationFrame(tick);
            }
        };

        window.requestAnimationFrame(tick);
    };

    if ('IntersectionObserver' in window) {
        const counterObserver = new IntersectionObserver((entries, observer) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) {
                    return;
                }

                animateCounter();
                observer.unobserve(entry.target);
            });
        }, {
            threshold: 0.35,
        });

        counterObserver.observe(counter);
    } else {
        animateCounter();
    }
});

const themeToggle = document.querySelector('[data-theme-toggle]');
const landingPage = document.body.classList.contains('landing-page') ? document.body : null;

if (themeToggle && landingPage) {
    const storageKey = 'inventoryflow-theme';
    const applyTheme = (theme) => {
        const nextTheme = theme === 'dark' ? 'dark' : 'light';
        landingPage.setAttribute('data-theme', nextTheme);
        themeToggle.setAttribute('aria-pressed', nextTheme === 'dark' ? 'true' : 'false');
        themeToggle.setAttribute('title', nextTheme === 'dark' ? 'Switch to light theme' : 'Switch to dark theme');
    };

    const savedTheme = window.localStorage.getItem(storageKey);
    const systemTheme = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches
        ? 'dark'
        : 'light';

    applyTheme(savedTheme || systemTheme);

    themeToggle.addEventListener('click', () => {
        const nextTheme = landingPage.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
        applyTheme(nextTheme);
        window.localStorage.setItem(storageKey, nextTheme);
    });
}
