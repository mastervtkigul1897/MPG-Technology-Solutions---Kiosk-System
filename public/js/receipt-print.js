/**
 * Cross-device receipt print: phones/tablets often block window.open;
 * iframe print runs in the same browsing context and survives most blockers.
 */
(function () {
    'use strict';

    function escAttr(s) {
        return String(s ?? '')
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/</g, '&lt;');
    }

    function prefersIframePrint() {
        try {
            if (window.matchMedia('(max-width: 991.98px)').matches) {
                return true;
            }
            if (window.matchMedia('(pointer: coarse)').matches) {
                return true;
            }
        } catch (e) {
            /* ignore */
        }
        if (typeof navigator.maxTouchPoints === 'number' && navigator.maxTouchPoints > 0) {
            return true;
        }
        return typeof window.ontouchstart !== 'undefined';
    }

    function printHead(cssUrl) {
        return (
            '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8">'
            + '<meta name="viewport" content="width=device-width, initial-scale=1">'
            + '<title>Receipt</title>'
            + '<link rel="stylesheet" href="' + escAttr(cssUrl) + '">'
            + '</head><body>'
        );
    }

    function printViaIframe(markup, cssUrl) {
        return new Promise(function (resolve, reject) {
            var iframe = document.createElement('iframe');
            iframe.setAttribute('title', 'Receipt print');
            iframe.setAttribute('aria-hidden', 'true');
            iframe.style.cssText =
                'position:fixed;right:0;bottom:0;width:0;height:0;border:0;opacity:0;pointer-events:none;';
            document.body.appendChild(iframe);
            var win = iframe.contentWindow;
            if (!win || !win.document) {
                try {
                    document.body.removeChild(iframe);
                } catch (e) {
                    /* ignore */
                }
                reject(new Error('iframe unavailable'));
                return;
            }
            var doc = win.document;
            doc.open();
            doc.write(printHead(cssUrl));
            doc.write(markup);
            doc.write('</body></html>');
            doc.close();

            var cleaned = false;
            function cleanup() {
                if (cleaned) {
                    return;
                }
                cleaned = true;
                try {
                    win.removeEventListener('afterprint', onAfterPrint);
                } catch (e) {
                    /* ignore */
                }
                try {
                    document.body.removeChild(iframe);
                } catch (e2) {
                    /* ignore */
                }
                resolve();
            }

            function onAfterPrint() {
                cleanup();
            }

            win.addEventListener('afterprint', onAfterPrint);

            function invokePrint() {
                try {
                    win.focus();
                    win.print();
                } catch (err) {
                    cleanup();
                    reject(err);
                    return;
                }
                setTimeout(function () {
                    if (!cleaned) {
                        cleanup();
                    }
                }, 20000);
            }

            var link = doc.querySelector('link[rel="stylesheet"]');
            if (link) {
                var fired = false;
                function go() {
                    if (fired) {
                        return;
                    }
                    fired = true;
                    setTimeout(invokePrint, 100);
                }
                if (link.sheet) {
                    go();
                } else {
                    link.addEventListener('load', go);
                    link.addEventListener('error', go);
                    setTimeout(go, 2800);
                }
            } else {
                setTimeout(invokePrint, 200);
            }
        });
    }

    function printViaWindow(markup, cssUrl) {
        var w = window.open('about:blank', '_blank', 'noopener,noreferrer');
        if (!w) {
            return false;
        }
        var doc = w.document;
        doc.open();
        doc.write(printHead(cssUrl));
        doc.write(markup);
        doc.write('</body></html>');
        doc.close();
        var invoke = function () {
            try {
                w.focus();
                w.print();
            } catch (e) {
                try {
                    w.close();
                } catch (e2) {
                    /* ignore */
                }
                throw e;
            }
        };
        var t = function () {
            setTimeout(invoke, 200);
        };
        if (doc.readyState === 'complete') {
            t();
        } else {
            w.addEventListener('load', t);
        }
        w.addEventListener('afterprint', function () {
            try {
                w.close();
            } catch (e) {
                /* ignore */
            }
        });
        return true;
    }

    window.mpgReceiptDeviceHints = function () {
        var ua = navigator.userAgent || '';
        var isIOS =
            /iPad|iPhone|iPod/.test(ua) ||
            (navigator.platform === 'MacIntel' && (navigator.maxTouchPoints || 0) > 1);
        var isAndroid = /Android/i.test(ua);
        return {
            isIOS: isIOS,
            isAndroid: isAndroid,
            hasWebBluetooth: typeof navigator.bluetooth !== 'undefined',
            prefersIframePrint: prefersIframePrint(),
        };
    };

    /**
     * @param {HTMLElement|null} rootEl
     * @param {{ cssUrl: string, onPopupBlocked?: () => void }} opts
     */
    window.printReceiptThermalDoc = function (rootEl, opts) {
        opts = opts || {};
        var cssUrl = opts.cssUrl || '';
        var onPopupBlocked = typeof opts.onPopupBlocked === 'function' ? opts.onPopupBlocked : function () {};

        if (!rootEl) {
            return;
        }
        var markup = rootEl.innerHTML;
        if (!markup || !String(markup).trim()) {
            window.print();
            return;
        }

        var runIframe = function () {
            printViaIframe(markup, cssUrl).catch(function () {
                var ok = false;
                try {
                    ok = printViaWindow(markup, cssUrl);
                } catch (e) {
                    ok = false;
                }
                if (!ok) {
                    window.print();
                }
            });
        };

        if (prefersIframePrint()) {
            runIframe();
            return;
        }

        var ok = false;
        try {
            ok = printViaWindow(markup, cssUrl);
        } catch (e) {
            ok = false;
        }
        if (!ok) {
            onPopupBlocked();
            runIframe();
        }
    };
})();
