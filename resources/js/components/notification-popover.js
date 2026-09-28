import { getJson, postJson } from '../utils/fetch-json';

const API_INDEX = '/api/v1/notifications';
const CSRF_COOKIE_URL = '/sanctum/csrf-cookie';

export function initNotificationPopover() {
    const root = document.querySelector('[data-notification-popover-root]');

    if (! root) {
        return;
    }

    const trigger = root.querySelector('[data-notification-popover-trigger]');
    const panel = root.querySelector('[data-notification-popover-panel]');
    const loading = root.querySelector('[data-notification-popover-loading]');
    const empty = root.querySelector('[data-notification-popover-empty]');
    const items = root.querySelector('[data-notification-popover-items]');
    const rowTemplate = root.querySelector('[data-notification-popover-row-template]');
    const tabs = root.querySelectorAll('[data-notification-popover-tab]');
    const unreadCount = root.querySelector('[data-notification-popover-unread-count]');
    const badge = root.querySelector('[data-notification-popover-badge]');
    const markAllButton = root.querySelector('[data-notification-popover-mark-all]');

    if (! trigger || ! panel || ! loading || ! empty || ! items || ! rowTemplate || ! unreadCount || ! badge || ! markAllButton) {
        return;
    }

    let notifications = [];

    let activeTab = 'all';

    function open() {
        panel.style.display = 'flex';
        panel.classList.remove('hidden');

        requestAnimationFrame(() => {
            panel.classList.remove('opacity-0', '-translate-y-1');
        });

        trigger.setAttribute('aria-expanded', 'true');
    }

    function close() {
        panel.classList.add('opacity-0', '-translate-y-1');
        trigger.setAttribute('aria-expanded', 'false');

        window.setTimeout(() => {
            panel.classList.add('hidden');
            panel.style.display = 'none';
        }, 150);
    }

    function updateUnreadCount(count) {
        unreadCount.textContent = count > 99 ? '99+' : String(count);

        badge.textContent = count > 99 ? '99+' : String(count);

        badge.classList.toggle('hidden', count <= 0);
    }

    async function ensureCsrfCookie() {
        const response = await fetch(CSRF_COOKIE_URL, {
            method: 'GET',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        });

        if (!response.ok) {
            throw new Error(`CSRF cookie request failed: ${response.status}`);
        }
    }

    function renderNotifications(list) {
        items.replaceChildren();

        if (list.length === 0) {
            empty.classList.remove('hidden');

            return;
        }

        empty.classList.add('hidden');

        list.forEach((notification) => {
            const fragment = rowTemplate.content.cloneNode(true);

            const row = fragment.querySelector('[data-notification-popover-row]');
            const dot = fragment.querySelector('[data-notification-popover-row-dot]');
            const title = fragment.querySelector('[data-notification-popover-row-title]');
            const message = fragment.querySelector('[data-notification-popover-row-message]');
            const time = fragment.querySelector('[data-notification-popover-row-time]');

            row.href = notification.url;
            row.dataset.notificationId = notification.id;

            row.addEventListener('click', async (event) => {
                event.preventDefault();

                if (!notification.is_unread) {
                    window.location.href = notification.url;

                    return;
                }

                await ensureCsrfCookie();

                const payload = await postJson(
                    `/api/v1/notifications/${notification.id}/read`,
                    {},
                );

                notification.is_unread = false;

                updateUnreadCount(payload.unread_count ?? 0);

                window.location.href = notification.url;
            });

            title.textContent = notification.title;
            message.textContent = notification.message;
            time.textContent = notification.created_relative;

            if (notification.is_unread) {
                row.classList.add('bg-primary-50/30');
            } else {
                dot.classList.add('hidden');
            }

            items.appendChild(fragment);
        });
    }

    function renderActiveTab() {
        const list = activeTab === 'unread'
            ? notifications.filter((notification) => notification.is_unread)
            : notifications;

        renderNotifications(list);
    }

    async function loadNotifications() {
        loading.classList.remove('hidden');
        empty.classList.add('hidden');
        items.replaceChildren();

        try {
            const payload = await getJson(API_INDEX);

            notifications = payload.notifications ?? [];

            updateUnreadCount(payload.unread_count ?? 0);
            renderActiveTab();

        } finally {
            loading.classList.add('hidden');
        }
    }

    tabs.forEach((tab) => {
        tab.addEventListener('click', () => {
            activeTab = tab.dataset.notificationPopoverTab;

            tabs.forEach((item) => {
                item.setAttribute(
                    'aria-selected',
                    item === tab ? 'true' : 'false',
                );
            });

            renderActiveTab();
        });
    });

    trigger.addEventListener('click', async () => {
        const isOpen = trigger.getAttribute('aria-expanded') === 'true';

        if (isOpen) {
            close();

            return;
        }

        open();
        await loadNotifications();
    });

    markAllButton.addEventListener('click', async () => {
        await ensureCsrfCookie();

        const payload = await postJson(
            '/api/v1/notifications/read-all',
            {},
        );
        notifications = notifications.map((notification) => ({
            ...notification,
            is_unread: false,
        }));

        updateUnreadCount(payload.unread_count ?? 0);
        renderActiveTab();
    });

    document.addEventListener('click', (event) => {
        if (
            trigger.getAttribute('aria-expanded') === 'true'
            && !root.contains(event.target)
        ) {
            close();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (
            event.key === 'Escape'
            && trigger.getAttribute('aria-expanded') === 'true'
        ) {
            close();
            trigger.focus();
        }
    });
}
