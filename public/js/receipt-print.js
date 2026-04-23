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
        // Seen on some BLE printer firmwares (vendor services)
        '0000ff00-0000-1000-8000-00805f9b34fb',
        '0000ff10-0000-1000-8000-00805f9b34fb',
        '00001f80-0000-1000-8000-00805f9b34fb',
        '0000ff80-0000-1000-8000-00805f9b34fb',
        '0000ffb0-0000-1000-8000-00805f9b34fb',
        '6e400001-b5a3-f393-e0a9-e50e24dcca9e',
        '0000fee7-0000-1000-8000-00805f9b34fb',
        'e7810a71-73ae-499d-8c15-faa9aef0c3f2',
    ];
    var MPG_BLE_KNOWN_WRITE_CHARACTERISTICS = [
        // Seen on RPP02N-B213 scan
        '49535343-8841-43f4-a8d4-ecbe34729bb3',
        'bef8d6c9-9c21-4c9e-b632-bd58c1009f1f',
        // Common ESC/POS BLE write chars
        '49535343-1e4d-4bd9-ba61-23c647249616',
        '0000ffe1-0000-1000-8000-00805f9b34fb',
    ];
    var MPG_BLE_DEVICE_ID_KEY = 'mpg_ble_thermal_device_id';
    var MPG_BLE_LAST_DEVICE = null;
    var MPG_BLE_PRINTER_HINT_RE = /(thermal|receipt|printer|pos|58|80|escpos|rpp)/i;

    function mpgReceiptConfig() {
        try {
            var cfg = (typeof window !== 'undefined' && window.MPG_RECEIPT_CONFIG) ? window.MPG_RECEIPT_CONFIG : null;
            if (cfg && typeof cfg === 'object') return cfg;
        } catch (e) {
            /* ignore */
        }
        return {};
    }

    function mpgParseBlePrinterMatchRules(raw) {
        var s = String(raw || '').trim();
        if (s === '*') return [];
        if (!s) return [{ contains: 'RP', field: 'name' }];
        var parts = s.split(',');
        var out = [];
        for (var i = 0; i < parts.length; i += 1) {
            var token = String(parts[i] || '').trim();
            if (!token) continue;

            // Accept "contains|field" or "contains:field" (fallback: "contains" => field=name)
            var contains = token;
            var field = 'name';
            var pipeIdx = token.indexOf('|');
            var colonIdx = token.indexOf(':');
            var splitIdx = pipeIdx >= 0 ? pipeIdx : colonIdx;
            if (splitIdx >= 0) {
                contains = String(token.slice(0, splitIdx) || '').trim();
                field = String(token.slice(splitIdx + 1) || '').trim().toLowerCase();
            }

            if (!contains) continue;
            if (field !== 'name' && field !== 'id') field = 'name';
            out.push({ contains: contains, field: field });
        }
        return out.length ? out : [{ contains: 'RP', field: 'name' }];
    }

    function mpgDeviceFieldValue(device, field) {
        if (!device) return '';
        if (field === 'id') return String(device.id || '').trim();
        return String(device.name || '').trim();
    }

    function mpgBlePickerOptionsFromRules(rules) {
        // WebBluetooth only supports "namePrefix" in filters; if rules are not "prefix-like", fall back to acceptAllDevices.
        var prefixes = [];
        for (var i = 0; i < rules.length; i += 1) {
            var r = rules[i];
            if (!r || r.field !== 'name') continue;
            var c = String(r.contains || '').trim();
            if (!c) continue;
            if (!/^[A-Za-z0-9]{1,16}$/.test(c)) continue;
            prefixes.push(c);
        }
        // de-dup, cap (Chrome limits filters count)
        var seen = {};
        var uniq = [];
        for (var j = 0; j < prefixes.length; j += 1) {
            var p = prefixes[j];
            var key = p.toLowerCase();
            if (seen[key]) continue;
            seen[key] = true;
            uniq.push(p);
            if (uniq.length >= 5) break;
        }
        if (uniq.length) {
            return {
                filters: uniq.map(function (pfx) { return { namePrefix: pfx }; }),
                optionalServices: MPG_BLE_OPTIONAL_SERVICES,
            };
        }
        return {
            acceptAllDevices: true,
            optionalServices: MPG_BLE_OPTIONAL_SERVICES,
        };
    }
    function mpgBleStorageGet(key) {
        try {
            var v = localStorage.getItem(key);
            if (v) return v;
        } catch (e) {
            /* ignore */
        }
        try {
            return sessionStorage.getItem(key);
        } catch (e2) {
            return null;
        }
    }
    function mpgBleStorageSet(key, value) {
        try {
            localStorage.setItem(key, value);
        } catch (e) {
            /* ignore */
        }
        try {
            sessionStorage.setItem(key, value);
        } catch (e2) {
            /* ignore */
        }
    }
    function mpgBleStorageRemove(key) {
        try {
            localStorage.removeItem(key);
        } catch (e) {
            /* ignore */
        }
        try {
            sessionStorage.removeItem(key);
        } catch (e2) {
            /* ignore */
        }
    }

    function mpgBleLooksLikePrinterDevice(device) {
        var rawRules = String(mpgReceiptConfig().ble_printer_match_rules || '').trim();
        if (rawRules === '*') return true;
        var hasCustomRules = rawRules !== '';
        var rules = mpgParseBlePrinterMatchRules(mpgReceiptConfig().ble_printer_match_rules);
        var okContains = false;
        for (var i = 0; i < rules.length; i += 1) {
            var r = rules[i];
            var hay = mpgDeviceFieldValue(device, r.field).toLowerCase();
            var needle = String(r.contains || '').toLowerCase();
            if (!needle) continue;
            if (hay.indexOf(needle) >= 0) {
                okContains = true;
                break;
            }
        }
        if (!okContains) return false;
        if (hasCustomRules) return true;

        // Default behavior: keep a loose hint check to reduce accidental selection.
        var name = String((device && device.name) || '').trim();
        return !name ? false : MPG_BLE_PRINTER_HINT_RE.test(name);
    }

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
                    return svc.getCharacteristics().then(function (chars) {
                        return { svc: svc, chars: chars };
                    });
                })
                .then(function (payload) {
                    var svc = payload && payload.svc ? payload.svc : null;
                    var chars = payload && payload.chars ? payload.chars : [];
                    for (var j = 0; j < chars.length; j += 1) {
                        var ch = chars[j];
                        if (ch.properties.write || ch.properties.writeWithoutResponse) {
                            return ch;
                        }
                    }
                    if (!svc || typeof svc.getCharacteristic !== 'function') {
                        return tryNext();
                    }
                    var k = 0;
                    function tryKnownWriteChar() {
                        if (k >= MPG_BLE_KNOWN_WRITE_CHARACTERISTICS.length) {
                            return tryNext();
                        }
                        var cuuid = MPG_BLE_KNOWN_WRITE_CHARACTERISTICS[k];
                        k += 1;
                        return svc.getCharacteristic(cuuid)
                            .then(function (ch) {
                                return ch || tryKnownWriteChar();
                            })
                            .catch(function () {
                                return tryKnownWriteChar();
                            });
                    }
                    return tryKnownWriteChar();
                })
                .catch(function () {
                    return tryNext();
                });
        }
        return tryNext();
    }

    function mpgBleRequestPrinterDevice() {
        var rules = mpgParseBlePrinterMatchRules(mpgReceiptConfig().ble_printer_match_rules);
        var rawRules = String(mpgReceiptConfig().ble_printer_match_rules || '').trim();
        var pickerOpts = rawRules === '*' ? { acceptAllDevices: true, optionalServices: MPG_BLE_OPTIONAL_SERVICES } : mpgBlePickerOptionsFromRules(rules);
        return navigator.bluetooth.requestDevice(pickerOpts).catch(function (err) {
            if (err && err.name === 'NotFoundError') {
                throw new Error('No device selected or the Bluetooth picker was cancelled.');
            }
            throw err;
        }).then(function (device) {
            if (!mpgBleLooksLikePrinterDevice(device)) {
                var raw = String(mpgReceiptConfig().ble_printer_match_rules || '').trim();
                var hint = raw ? ('Match rules: ' + raw) : 'Match rules: RP|name';
                throw new Error('Selected device does not match your Bluetooth printer rules. Please select the correct printer. (' + hint + ')');
            }
            return device;
        });
    }

    function mpgBleWriteChunks(ch, bytes) {
        var chunkSize = ch.properties.writeWithoutResponse ? 120 : 20;
        var delayMs = ch.properties.writeWithoutResponse ? 12 : 0;
        var i = 0;
        function sleep(ms) {
            if (!ms) return Promise.resolve();
            return new Promise(function (resolve) {
                setTimeout(resolve, ms);
            });
        }
        function next() {
            if (i >= bytes.length) {
                return Promise.resolve();
            }
            var slice = bytes.slice(i, i + chunkSize);
            i += chunkSize;
            var p = ch.properties.writeWithoutResponse
                ? ch.writeValueWithoutResponse(slice)
                : ch.writeValueWithResponse(slice);
            return p.then(function () {
                return sleep(delayMs).then(next);
            });
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

    function mpgAndroidBridgeGetBondedPrinters(bridge) {
        if (!bridge || typeof bridge.getBondedPrintersJson !== 'function') return [];
        var out = mpgNativeBridgeParse(bridge.getBondedPrintersJson()) || {};
        if (!out.ok || !out.devices || !out.devices.length) return [];
        var list = [];
        for (var i = 0; i < out.devices.length; i += 1) {
            var d = out.devices[i] || {};
            var name = String(d.name || '').trim();
            var address = String(d.address || '').trim();
            if (!address) continue;
            list.push({ name: name || 'Printer', address: address });
        }
        return list;
    }

    function mpgAndroidBridgeSetPrinterAddress(bridge, address) {
        if (!bridge || typeof bridge.setPrinterAddress !== 'function') return true;
        var out = mpgNativeBridgeParse(bridge.setPrinterAddress(String(address || ''))) || {};
        if (out && out.ok) return true;
        throw new Error((out && out.message) ? out.message : 'Could not save selected printer.');
    }

    function mpgAndroidBridgePromptSelectPrinter(devices) {
        var opts = {};
        for (var i = 0; i < devices.length; i += 1) {
            var d = devices[i];
            var label = (d.name ? d.name : 'Printer') + ' (' + d.address + ')';
            opts[d.address] = label;
        }

        // Prefer SweetAlert2 (already loaded in app layout); fallback to prompt().
        if (typeof Swal !== 'undefined' && Swal && typeof Swal.fire === 'function') {
            return Swal.fire({
                title: 'Select Bluetooth printer',
                input: 'select',
                inputOptions: opts,
                inputPlaceholder: 'Choose a paired printer',
                showCancelButton: true,
                confirmButtonText: 'Use this printer',
                cancelButtonText: 'Cancel',
                inputValidator: function (value) {
                    if (!value) return 'Please select a printer.';
                    return null;
                },
            }).then(function (r) {
                if (!r || !r.isConfirmed) return null;
                return String(r.value || '').trim() || null;
            });
        }

        try {
            var lines = devices.map(function (d, idx) {
                return (idx + 1) + '. ' + (d.name ? d.name : 'Printer') + ' (' + d.address + ')';
            }).join('\n');
            var ans = prompt('Select paired printer (enter number):\n' + lines, '1');
            var n = parseInt(String(ans || '').trim(), 10);
            if (!n || n < 1 || n > devices.length) return Promise.resolve(null);
            return Promise.resolve(devices[n - 1].address);
        } catch (e) {
            return Promise.resolve(null);
        }
    }

    function mpgWriteEscposAndroidBridge(bytes) {
        var bridge = mpgAndroidBridge();
        if (!bridge) return Promise.resolve(false);
        function doPrintOnce() {
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
        }

        function maybeSelectPrinterThenRetry(err) {
            var msg = String((err && err.message) || '').toLowerCase();
            var looksLikeNoPrinter =
                msg.indexOf('no paired printer') >= 0 ||
                msg.indexOf('pair the bluetooth printer first') >= 0 ||
                msg.indexOf('no paired printer found') >= 0;

            var devices = mpgAndroidBridgeGetBondedPrinters(bridge);
            if (!devices.length) {
                throw err;
            }
            if (devices.length === 1 && !looksLikeNoPrinter) {
                // If only one paired printer exists, just try saving it then retry.
                mpgAndroidBridgeSetPrinterAddress(bridge, devices[0].address);
                return doPrintOnce();
            }

            return mpgAndroidBridgePromptSelectPrinter(devices).then(function (address) {
                if (!address) throw err;
                mpgAndroidBridgeSetPrinterAddress(bridge, address);
                return doPrintOnce();
            });
        }

        return Promise.resolve()
            .then(function () {
                return doPrintOnce();
            })
            .catch(function (err) {
                return Promise.resolve().then(function () {
                    return maybeSelectPrinterThenRetry(err);
                });
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

    function mpgBleDeviceById(devices, id) {
        if (!devices || !devices.length || !id) return null;
        for (var i = 0; i < devices.length; i += 1) {
            var d = devices[i];
            if (d && String(d.id || '') === String(id)) return d;
        }
        return null;
    }

    function mpgBleResolvePrinterDevice() {
        var rememberedId = mpgBleStorageGet(MPG_BLE_DEVICE_ID_KEY);
        if (typeof navigator.bluetooth.getDevices !== 'function') {
            return mpgBleRequestPrinterDevice();
        }
        return navigator.bluetooth
            .getDevices()
            .then(function (devices) {
                var remembered = mpgBleDeviceById(devices, rememberedId);
                if (remembered && mpgBleLooksLikePrinterDevice(remembered)) {
                    return remembered;
                }
                return mpgBleRequestPrinterDevice();
            })
            .catch(function () {
                return mpgBleRequestPrinterDevice();
            });
    }

    /**
     * Prompt/select Bluetooth printer as early as possible inside a direct user gesture.
     * This is useful for installed PWAs where delayed async work can lose gesture context.
     */
    window.mpgPrimeEscposBluetoothPermission = function () {
        var androidBridge = mpgAndroidBridge();
        if (androidBridge) {
            return Promise.resolve(true);
        }
        if (!navigator.bluetooth) {
            return Promise.reject(
                new Error(
                    'Web Bluetooth not available. Use Chrome or Edge over HTTPS, or use Print / Wi‑Fi printing.'
                )
            );
        }
        return mpgBleResolvePrinterDevice().then(function (device) {
            MPG_BLE_LAST_DEVICE = device || null;
            if (device && device.id) {
                mpgBleStorageSet(MPG_BLE_DEVICE_ID_KEY, String(device.id));
            }
            return true;
        });
    };

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
        // Installed PWA can lose gesture context on a second requestDevice() call.
        // If we already primed/selected a device in this click flow, try it first.
        if (MPG_BLE_LAST_DEVICE) {
            return mpgBleTryPrintOnDevice(MPG_BLE_LAST_DEVICE, bytes).then(function (okLast) {
                if (okLast) {
                    return;
                }
                return mpgBleResolvePrinterDevice().then(function (device) {
                    return mpgBleTryPrintOnDevice(device, bytes).then(function (ok) {
                        if (!ok) {
                            throw new Error(
                                'No writable Bluetooth characteristic found. Pair the printer or use Wi‑Fi/LAN raw printing.'
                            );
                        }
                        MPG_BLE_LAST_DEVICE = device || null;
                        if (device && device.id) {
                            mpgBleStorageSet(MPG_BLE_DEVICE_ID_KEY, String(device.id));
                        }
                    });
                });
            });
        }
        return mpgBleResolvePrinterDevice().then(function (device) {
            return mpgBleTryPrintOnDevice(device, bytes).then(function (ok) {
                if (!ok) {
                    throw new Error(
                        'No writable Bluetooth characteristic found. Pair the printer or use Wi‑Fi/LAN raw printing.'
                    );
                }
                MPG_BLE_LAST_DEVICE = device || null;
                if (device && device.id) {
                    mpgBleStorageSet(MPG_BLE_DEVICE_ID_KEY, String(device.id));
                }
            });
        });
    };

    /** Clear remembered printer (next print shows the device picker again). */
    window.mpgClearEscposBluetoothDevice = function () {
        MPG_BLE_LAST_DEVICE = null;
        mpgBleStorageRemove(MPG_BLE_DEVICE_ID_KEY);
    };

    window.mpgHasRememberedBluetoothDevice = function () {
        return !!mpgBleStorageGet(MPG_BLE_DEVICE_ID_KEY);
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
