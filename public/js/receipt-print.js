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
        // Android WebView (e.g. APK) — no Web Bluetooth API; "; wv)" in UA string
        var isAndroidWebView = isAndroid && /; wv\)/.test(ua);
        return {
            isIOS: isIOS,
            isAndroid: isAndroid,
            isAndroidWebView: isAndroidWebView,
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

    /** UUIDs common on BLE thermal printers (Nordic UART, HM-10 style, etc.) */
    var MPG_BLE_OPTIONAL_SERVICES = [
        '49535343-fe7d-4ae5-8fa9-9fafd205e455',
        '0000ffe0-0000-1000-8000-00805f9b34fb',
        '6e400001-b5a3-f393-e0a9-e50e24dcca9e',
    ];

    var MPG_BLE_DEVICE_ID_KEY = 'mpg_ble_thermal_device_id';

    function mpgBleFindWritableCharacteristic(server) {
        var idx = 0;
        function tryNext() {
            if (idx >= MPG_BLE_OPTIONAL_SERVICES.length) {
                return Promise.resolve(null);
            }
            var sid = MPG_BLE_OPTIONAL_SERVICES[idx];
            idx += 1;
            return server
                .getPrimaryService(sid)
                .then(function (svc) {
                    return svc.getCharacteristics();
                })
                .then(function (chars) {
                    for (var j = 0; j < chars.length; j += 1) {
                        var ch = chars[j];
                        if (ch.properties.write || ch.properties.writeWithoutResponse) {
                            return ch;
                        }
                    }
                    return tryNext();
                })
                .catch(function () {
                    return tryNext();
                });
        }
        return tryNext();
    }

    function mpgBleWriteChunks(ch, bytes) {
        var chunkSize = ch.properties.writeWithoutResponse ? 180 : 20;
        var i = 0;
        function next() {
            if (i >= bytes.length) {
                return Promise.resolve();
            }
            var slice = bytes.slice(i, i + chunkSize);
            i += chunkSize;
            var p = ch.properties.writeWithoutResponse
                ? ch.writeValueWithoutResponse(slice)
                : ch.writeValueWithResponse(slice);
            return p.then(next);
        }
        return next();
    }

    function mpgNativeBridgeParse(raw) {
        if (raw == null) return null;
        if (typeof raw === 'object') return raw;
        if (typeof raw !== 'string') return null;
        try {
            return JSON.parse(raw);
        } catch (e) {
            return null;
        }
    }

    function mpgAndroidBridge() {
        if (typeof window === 'undefined') return null;
        var b = window.MpgAndroidBluetooth;
        if (!b || typeof b.printBase64 !== 'function') return null;
        return b;
    }

    function mpgWriteEscposAndroidBridge(bytes) {
        var bridge = mpgAndroidBridge();
        if (!bridge) return Promise.resolve(false);
        return Promise.resolve().then(function () {
            var base64 = '';
            try {
                var bin = '';
                for (var i = 0; i < bytes.length; i += 1) bin += String.fromCharCode(bytes[i]);
                base64 = btoa(bin);
            } catch (e) {
                throw new Error('Could not encode print payload for Android bridge.');
            }
            var raw = bridge.printBase64(base64);
            var out = mpgNativeBridgeParse(raw) || {};
            if (!out.ok) {
                throw new Error(out.message || 'Native Bluetooth print failed.');
            }
            return true;
        });
    }

    /**
     * Connect to a BluetoothDevice, find a writable GATT characteristic, send ESC/POS bytes.
     * @returns {Promise<boolean>} true if printed successfully
     */
    function mpgBleTryPrintOnDevice(device, bytes) {
        if (!device || !device.gatt) {
            return Promise.resolve(false);
        }
        return device.gatt
            .connect()
            .then(function (server) {
                return mpgBleFindWritableCharacteristic(server).then(function (ch) {
                    if (!ch) {
                        try {
                            device.gatt.disconnect();
                        } catch (e) {
                            /* ignore */
                        }
                        return false;
                    }
                    return mpgBleWriteChunks(ch, bytes).then(function () {
                        try {
                            device.gatt.disconnect();
                        } catch (e2) {
                            /* ignore */
                        }
                        return true;
                    });
                });
            })
            .catch(function () {
                return false;
            });
    }

    /**
     * ESC/POS over BLE thermal printer.
     * First print: requestDevice (pair/select printer). Later: getDevices + saved id — no picker while permission remains.
     */
    window.mpgWriteEscposBluetooth = function (bytes) {
        var androidBridge = mpgAndroidBridge();
        if (androidBridge) {
            return mpgWriteEscposAndroidBridge(bytes).then(function () {
                return;
            });
        }
        if (!navigator.bluetooth) {
            return Promise.reject(
                new Error(
                    'Web Bluetooth not available. Use Chrome or Edge over HTTPS, or use Print / Wi‑Fi printing.'
                )
            );
        }
        var savedId = null;
        try {
            savedId = sessionStorage.getItem(MPG_BLE_DEVICE_ID_KEY);
        } catch (e) {
            savedId = null;
        }

        var tryGetDevices = typeof navigator.bluetooth.getDevices === 'function'
            ? navigator.bluetooth.getDevices()
            : Promise.resolve([]);

        return tryGetDevices.then(function (devices) {
            var list = Array.isArray(devices) ? devices.slice() : [];
            if (savedId && list.length) {
                list.sort(function (a, b) {
                    var ma = a && a.id === savedId ? 0 : 1;
                    var mb = b && b.id === savedId ? 0 : 1;
                    return ma - mb;
                });
            }
            function tryList(idx) {
                if (idx >= list.length) {
                    return Promise.resolve(false);
                }
                return mpgBleTryPrintOnDevice(list[idx], bytes).then(function (ok) {
                    if (ok && list[idx] && list[idx].id) {
                        try {
                            sessionStorage.setItem(MPG_BLE_DEVICE_ID_KEY, list[idx].id);
                        } catch (e) {
                            /* ignore */
                        }
                        return true;
                    }
                    return tryList(idx + 1);
                });
            }
            return tryList(0);
        }).then(function (done) {
            if (done) {
                return;
            }
            return navigator.bluetooth
                .requestDevice({ acceptAllDevices: true, optionalServices: MPG_BLE_OPTIONAL_SERVICES })
                .then(function (device) {
                    return mpgBleTryPrintOnDevice(device, bytes).then(function (ok) {
                        if (!ok) {
                            throw new Error(
                                'No writable Bluetooth characteristic found. Pair the printer or use Wi‑Fi/LAN raw printing.'
                            );
                        }
                        if (device && device.id) {
                            try {
                                sessionStorage.setItem(MPG_BLE_DEVICE_ID_KEY, device.id);
                            } catch (e) {
                                /* ignore */
                            }
                        }
                    });
                });
        });
    };

    /** Clear remembered printer (next print shows the device picker again). */
    window.mpgClearEscposBluetoothDevice = function () {
        try {
            sessionStorage.removeItem(MPG_BLE_DEVICE_ID_KEY);
        } catch (e) {
            /* ignore */
        }
    };

    /**
     * Web Bluetooth thermal: Chrome/Edge + HTTPS only; not available in Android WebView (APK), Safari, and most embedded browsers.
     */
    window.mpgSupportsBluetoothThermalPrint = function () {
        try {
            var bridge = mpgAndroidBridge();
            if (bridge && typeof bridge.isAvailable === 'function') {
                try {
                    return !!bridge.isAvailable();
                } catch (e0) {
                    return true;
                }
            }
            if (typeof window.isSecureContext === 'boolean' && !window.isSecureContext) {
                return false;
            }
            return typeof navigator !== 'undefined' && typeof navigator.bluetooth !== 'undefined';
        } catch (e) {
            return false;
        }
    };

    /** Always show Bluetooth print UI across devices. */
    window.mpgApplyEmbeddedShellReceiptUi = function () {
        try {
            document.body.classList.remove('mpg-shell-no-web-bluetooth');
        } catch (e) {
            /* ignore */
        }
    };
})();
