#!/usr/bin/env python3
"""Generate MPG One + MPG Laundry Android WebView projects with circular launcher icons."""

from __future__ import annotations

import math
import os
import shutil
import subprocess
import sys
from pathlib import Path

try:
    from PIL import Image, ImageDraw, ImageOps
except ImportError:
    print("Install Pillow: pip3 install --user Pillow", file=sys.stderr)
    sys.exit(1)

SELF_STUDY = Path("/Users/marvingulle/Documents/Self Study")
ASSETS = Path(
    "/Users/marvingulle/.cursor/projects/"
    "Users-marvingulle-Documents-Self-Study-MPG-Technology-Solutions-Laundry-Shop/assets"
)

PROJECTS = [
    {
        "dir_name": "MPG One",
        "app_id": "com.mpg.one",
        "pkg_path": "com/mpg/one",
        "class_pkg": "com.mpg.one",
        "name": "MPG One",
        "url": "https://mpgtechnologysolutions.com/",
        "src_png": ASSETS / "image-8547b60a-c46f-4415-9e5e-cbac09b3630d.png",
        "bg": "#FFFFFF",
        "icon_mode": "fit_circle_pad",
    },
    {
        "dir_name": "MPG Laundry",
        "app_id": "com.mpg.laundry",
        "pkg_path": "com/mpg/laundry",
        "class_pkg": "com.mpg.laundry",
        "name": "MPG Laundry",
        "url": "https://laundry.mpgtechnologysolutions.com/",
        "src_png": ASSETS / "image-6d689d21-d2dd-420b-9bae-6a1b996dfa49.png",
        "bg": "#FFFFFF",
        "icon_mode": "fit_circle_pad",
    },
]

# (folder, launcher_px, foreground_px for adaptive)
MIPMAP_SIZES = [
    ("mipmap-mdpi", 48, 108),
    ("mipmap-hdpi", 72, 162),
    ("mipmap-xhdpi", 96, 216),
    ("mipmap-xxhdpi", 144, 324),
    ("mipmap-xxxhdpi", 192, 432),
]


def make_circle_png(src: Path, dst: Path, size: int) -> None:
    im = Image.open(src).convert("RGBA")
    im = ImageOps.fit(im, (size, size), Image.Resampling.LANCZOS)
    mask = Image.new("L", (size, size), 0)
    draw = ImageDraw.Draw(mask)
    draw.ellipse((0, 0, size - 1, size - 1), fill=255)
    out = Image.new("RGBA", (size, size), (0, 0, 0, 0))
    out.paste(im, (0, 0), mask)
    dst.parent.mkdir(parents=True, exist_ok=True)
    out.save(dst, "PNG")


def make_circle_fit_white(src: Path, dst: Path, size: int) -> None:
    """Circle content on white; fully opaque bitmap (no transparent corners) for adaptive icons."""
    fit = 0.97
    im = Image.open(src).convert("RGBA")
    w, h = im.size
    if w < 1 or h < 1:
        raise ValueError("Invalid image size")
    r = size / 2.0 - 0.5
    k = (2.0 * r * fit) / math.sqrt(w * w + h * h)
    if k > 1.0:
        k = 1.0
    nw = max(1, int(round(w * k)))
    nh = max(1, int(round(h * k)))
    scaled = im.resize((nw, nh), Image.Resampling.LANCZOS)
    canvas = Image.new("RGBA", (size, size), (255, 255, 255, 255))
    x = (size - nw) // 2
    y = (size - nh) // 2
    canvas.paste(scaled, (x, y), scaled)
    mask = Image.new("L", (size, size), 0)
    ImageDraw.Draw(mask).ellipse((0, 0, size - 1, size - 1), fill=255)
    white = Image.new("RGBA", (size, size), (255, 255, 255, 255))
    out = Image.composite(canvas, white, mask)
    dst.parent.mkdir(parents=True, exist_ok=True)
    out.save(dst, "PNG")


def make_splash_contain(src: Path, dst: Path, size: int) -> None:
    im = Image.open(src).convert("RGBA")
    im = ImageOps.contain(im, (size, size), Image.Resampling.LANCZOS)
    canvas = Image.new("RGBA", (size, size), (255, 255, 255, 255))
    x = (size - im.width) // 2
    y = (size - im.height) // 2
    canvas.paste(im, (x, y), im)
    dst.parent.mkdir(parents=True, exist_ok=True)
    canvas.save(dst, "PNG")


def write_icons(res: Path, src: Path, icon_mode: str = "fit") -> None:
    maker = make_circle_fit_white if icon_mode == "fit_circle_pad" else make_circle_png
    for folder, lp, fp in MIPMAP_SIZES:
        base = res / folder
        maker(src, base / "ic_launcher.png", lp)
        shutil.copyfile(base / "ic_launcher.png", base / "ic_launcher_round.png")
        maker(src, base / "ic_launcher_foreground.png", fp)


def write_adaptive_xml(anydpi: Path) -> None:
    anydpi.mkdir(parents=True, exist_ok=True)
    for name in ("ic_launcher.xml", "ic_launcher_round.xml"):
        (anydpi / name).write_text(
            """<?xml version="1.0" encoding="utf-8"?>
<adaptive-icon xmlns:android="http://schemas.android.com/apk/res/android">
    <background android:drawable="@color/ic_launcher_background"/>
    <foreground android:drawable="@mipmap/ic_launcher_foreground"/>
</adaptive-icon>
""",
            encoding="utf-8",
        )


def write_main_activity(pkg: str, url: str) -> str:
    return (
        """package PKG

import android.animation.ObjectAnimator
import android.animation.ValueAnimator
import android.annotation.SuppressLint
import android.bluetooth.BluetoothAdapter
import android.bluetooth.BluetoothDevice
import android.bluetooth.BluetoothSocket
import android.os.Build
import android.os.Bundle
import android.os.Handler
import android.os.Looper
import android.os.SystemClock
import android.util.Base64
import android.net.Uri
import android.webkit.CookieManager
import android.view.View
import android.view.animation.AccelerateDecelerateInterpolator
import android.webkit.JavascriptInterface
import android.webkit.PermissionRequest
import android.webkit.ValueCallback
import android.webkit.WebChromeClient
import android.webkit.WebResourceRequest
import android.webkit.WebView
import android.webkit.WebViewClient
import android.widget.ImageView
import androidx.activity.OnBackPressedCallback
import androidx.appcompat.app.AppCompatActivity
import java.io.IOException
import java.util.UUID

class MainActivity : AppCompatActivity() {
    private val bluetoothPermReqCode = 9041
    private val fileChooserReqCode = 9042
    private val sppUuid: UUID = UUID.fromString("00001101-0000-1000-8000-00805F9B34FB")
    private val prefName = "mpg_bt_print"
    private val prefAddressKey = "printer_address"
    private lateinit var webView: WebView
    private lateinit var splashOverlay: View
    private var splashPulse: ObjectAnimator? = null
    private val splashShownAt = SystemClock.elapsedRealtime()
    private var splashDismissed = false
    private var pageLoadFinished = false
    private val mainHandler = Handler(Looper.getMainLooper())
    private var filePathCallback: ValueCallback<Array<Uri>>? = null

    private val dismissSplashRunnable = Runnable {
        if (splashDismissed || !pageLoadFinished) return@Runnable
        splashDismissed = true
        splashPulse?.cancel()
        splashPulse = null
        splashOverlay
            .animate()
            .alpha(0f)
            .setDuration(220)
            .withEndAction { splashOverlay.visibility = View.GONE }
            .start()
    }

    @SuppressLint("SetJavaScriptEnabled")
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContentView(R.layout.activity_main)
        splashOverlay = findViewById(R.id.splash_overlay)
        val splashLogo = findViewById<ImageView>(R.id.splash_logo)
        splashPulse =
            ObjectAnimator.ofFloat(splashLogo, View.ALPHA, 0.38f, 1f).apply {
                duration = 1100
                repeatMode = ValueAnimator.REVERSE
                repeatCount = ValueAnimator.INFINITE
                interpolator = AccelerateDecelerateInterpolator()
                start()
            }
        webView = findViewById(R.id.webview)
        requestBluetoothPermissionIfNeeded()
        WebView.setWebContentsDebuggingEnabled(true)
        webView.settings.javaScriptEnabled = true
        webView.settings.domStorageEnabled = true
        webView.settings.databaseEnabled = true
        webView.settings.javaScriptCanOpenWindowsAutomatically = true
        webView.settings.mediaPlaybackRequiresUserGesture = false
        webView.settings.setSupportZoom(true)
        webView.settings.builtInZoomControls = true
        webView.settings.displayZoomControls = false
        CookieManager.getInstance().setAcceptCookie(true)
        CookieManager.getInstance().setAcceptThirdPartyCookies(webView, true)
        webView.addJavascriptInterface(AndroidBluetoothBridge(), "MpgAndroidBluetooth")
        webView.webChromeClient =
            object : WebChromeClient() {
                override fun onPermissionRequest(request: PermissionRequest) {
                    // Grant web permissions inside the wrapper (camera/mic are still controlled by Android perms).
                    request.grant(request.resources)
                }

                override fun onShowFileChooser(
                    webView: WebView,
                    filePathCallback: ValueCallback<Array<Uri>>,
                    fileChooserParams: FileChooserParams
                ): Boolean {
                    this@MainActivity.filePathCallback?.onReceiveValue(null)
                    this@MainActivity.filePathCallback = filePathCallback
                    return try {
                        val intent = fileChooserParams.createIntent()
                        startActivityForResult(intent, fileChooserReqCode)
                        true
                    } catch (e: Exception) {
                        this@MainActivity.filePathCallback = null
                        false
                    }
                }
            }
        webView.webViewClient =
            object : WebViewClient() {
                override fun shouldOverrideUrlLoading(view: WebView, request: WebResourceRequest): Boolean {
                    return false
                }

                @Deprecated("Deprecated in Java")
                override fun onReceivedError(
                    view: WebView,
                    errorCode: Int,
                    description: String?,
                    failingUrl: String?
                ) {
                    pageLoadFinished = true
                    scheduleDismissSplash()
                }

                override fun onPageFinished(view: WebView, url: String) {
                    pageLoadFinished = true
                    scheduleDismissSplash()
                }
            }
        webView.loadUrl("URL")

        mainHandler.postDelayed(
            {
                if (!splashDismissed) {
                    pageLoadFinished = true
                    scheduleDismissSplash()
                }
            },
            12_000L,
        )

        onBackPressedDispatcher.addCallback(
            this,
            object : OnBackPressedCallback(true) {
                override fun handleOnBackPressed() {
                    if (webView.canGoBack()) webView.goBack()
                    else finish()
                }
            },
        )
    }

    private fun scheduleDismissSplash() {
        if (splashDismissed) return
        mainHandler.removeCallbacks(dismissSplashRunnable)
        val minMs = 1400L
        val elapsed = SystemClock.elapsedRealtime() - splashShownAt
        val wait = (minMs - elapsed).coerceAtLeast(0L)
        mainHandler.postDelayed(dismissSplashRunnable, wait)
    }

    override fun onDestroy() {
        mainHandler.removeCallbacks(dismissSplashRunnable)
        splashPulse?.cancel()
        super.onDestroy()
    }

    @Deprecated("Deprecated in Java")
    override fun onActivityResult(requestCode: Int, resultCode: Int, data: android.content.Intent?) {
        super.onActivityResult(requestCode, resultCode, data)
        if (requestCode != fileChooserReqCode) return
        val cb = filePathCallback ?: return
        filePathCallback = null
        val result =
            if (resultCode == RESULT_OK) WebChromeClient.FileChooserParams.parseResult(resultCode, data) else null
        cb.onReceiveValue(result)
    }

    private fun hasConnectPermission(): Boolean {
        return if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.S) {
            checkSelfPermission(android.Manifest.permission.BLUETOOTH_CONNECT) ==
                android.content.pm.PackageManager.PERMISSION_GRANTED
        } else {
            true
        }
    }

    private fun requestBluetoothPermissionIfNeeded() {
        if (Build.VERSION.SDK_INT < Build.VERSION_CODES.S) {
            return
        }
        if (hasConnectPermission()) {
            return
        }
        requestPermissions(arrayOf(android.Manifest.permission.BLUETOOTH_CONNECT), bluetoothPermReqCode)
    }

    private fun getSavedPrinterAddress(): String? {
        val prefs = getSharedPreferences(prefName, MODE_PRIVATE)
        val raw = prefs.getString(prefAddressKey, null)?.trim()
        return if (raw.isNullOrEmpty()) null else raw
    }

    private fun setSavedPrinterAddress(address: String) {
        getSharedPreferences(prefName, MODE_PRIVATE)
            .edit()
            .putString(prefAddressKey, address.trim())
            .apply()
    }

    private fun esc(s: String): String {
        return s.replace("\\\\", "\\\\\\\\").replace("\\\"", "\\\\\\\"")
    }

    private fun jsonOk(extra: String = ""): String {
        return if (extra.isEmpty()) "{\\"ok\\":true}" else "{\\"ok\\":true,%s}".format(extra)
    }

    private fun jsonErr(message: String): String {
        return "{\\"ok\\":false,\\"message\\":\\"%s\\"}".format(esc(message))
    }

    private fun canUseBluetooth(adapter: BluetoothAdapter?): String? {
        if (adapter == null) return "Bluetooth is not available on this device."
        if (!adapter.isEnabled) return "Bluetooth is turned off."
        if (!hasConnectPermission()) {
            return "Bluetooth permission is not granted. Allow Nearby devices in Android settings."
        }
        return null
    }

    private inner class AndroidBluetoothBridge {
        @JavascriptInterface
        fun isAvailable(): Boolean {
            val adapter = BluetoothAdapter.getDefaultAdapter()
            return canUseBluetooth(adapter) == null
        }

        @JavascriptInterface
        fun getBondedPrintersJson(): String {
            val adapter = BluetoothAdapter.getDefaultAdapter()
            val why = canUseBluetooth(adapter)
            if (why != null) return jsonErr(why)
            return try {
                val list = adapter!!.bondedDevices.orEmpty().map { d ->
                    "{\\"name\\":\\"%s\\",\\"address\\":\\"%s\\"}".format(esc(d.name ?: "Printer"), esc(d.address ?: ""))
                }
                jsonOk("\\"devices\\":[%s]".format(list.joinToString(",")))
            } catch (e: Exception) {
                jsonErr(e.message ?: "Could not read paired printers.")
            }
        }

        @JavascriptInterface
        fun setPrinterAddress(address: String?): String {
            val v = address?.trim().orEmpty()
            if (v.isEmpty()) return jsonErr("Missing printer address.")
            setSavedPrinterAddress(v)
            return jsonOk()
        }

        @JavascriptInterface
        fun printBase64(payload: String?): String {
            if (payload.isNullOrBlank()) return jsonErr("Missing print payload.")
            val adapter = BluetoothAdapter.getDefaultAdapter()
            val why = canUseBluetooth(adapter)
            if (why != null) return jsonErr(why)

            val bytes = try {
                Base64.decode(payload, Base64.DEFAULT)
            } catch (e: IllegalArgumentException) {
                return jsonErr("Invalid print payload.")
            }
            if (bytes.isEmpty()) return jsonErr("Empty print payload.")

            val target = resolveTargetDevice(adapter!!)
                ?: return jsonErr("No paired printer found. Pair the Bluetooth printer first.")

            val result = sendRaw(target, bytes)
            if (result != null) return jsonErr(result)
            setSavedPrinterAddress(target.address ?: "")
            return jsonOk(
                "\\"printer\\":{\\"name\\":\\"%s\\",\\"address\\":\\"%s\\"}".format(
                    esc(target.name ?: "Printer"),
                    esc(target.address ?: "")
                )
            )
        }

        private fun resolveTargetDevice(adapter: BluetoothAdapter): BluetoothDevice? {
            val bonded = adapter.bondedDevices.orEmpty().toList()
            if (bonded.isEmpty()) return null
            val saved = getSavedPrinterAddress()
            if (!saved.isNullOrEmpty()) {
                bonded.firstOrNull { it.address.equals(saved, ignoreCase = true) }?.let { return it }
            }
            return bonded.firstOrNull { d ->
                val n = (d.name ?: "").lowercase()
                n.contains("printer") || n.contains("pos") || n.contains("bt")
            } ?: bonded.first()
        }

        private fun sendRaw(device: BluetoothDevice, data: ByteArray): String? {
            var socket: BluetoothSocket? = null
            try {
                socket = device.createRfcommSocketToServiceRecord(sppUuid)
                socket.connect()
                val out = socket.outputStream ?: return "Could not open printer output stream."
                out.write(data)
                out.flush()
                return null
            } catch (e: IOException) {
                return e.message ?: "Bluetooth print failed."
            } finally {
                try {
                    socket?.close()
                } catch (_: Exception) {
                }
            }
        }
    }
}
"""
        .replace("PKG", pkg)
        .replace("URL", url.replace("\\", "\\\\").replace('"', '\\"'))
    )


def write_layout() -> str:
    return """<?xml version="1.0" encoding="utf-8"?>
<FrameLayout xmlns:android="http://schemas.android.com/apk/res/android"
    android:layout_width="match_parent"
    android:layout_height="match_parent">

    <WebView
        android:id="@+id/webview"
        android:layout_width="match_parent"
        android:layout_height="match_parent" />

    <FrameLayout
        android:id="@+id/splash_overlay"
        android:layout_width="match_parent"
        android:layout_height="match_parent"
        android:background="@android:color/white"
        android:clickable="false"
        android:focusable="false">

        <ImageView
            android:id="@+id/splash_logo"
            android:layout_width="wrap_content"
            android:layout_height="wrap_content"
            android:layout_gravity="center"
            android:adjustViewBounds="true"
            android:contentDescription="@string/app_name"
            android:maxWidth="280dp"
            android:maxHeight="280dp"
            android:scaleType="fitCenter"
            android:src="@drawable/splash_logo" />
    </FrameLayout>
</FrameLayout>
"""


def write_manifest(app_id: str, name: str) -> str:
    return f"""<?xml version="1.0" encoding="utf-8"?>
<manifest xmlns:android="http://schemas.android.com/apk/res/android"
    package="{app_id}">

    <uses-permission android:name="android.permission.INTERNET" />
    <uses-permission android:name="android.permission.ACCESS_NETWORK_STATE" />
    <uses-permission android:name="android.permission.BLUETOOTH" android:maxSdkVersion="30" />
    <uses-permission android:name="android.permission.BLUETOOTH_ADMIN" android:maxSdkVersion="30" />
    <uses-permission android:name="android.permission.BLUETOOTH_CONNECT" />

    <application
        android:allowBackup="true"
        android:icon="@mipmap/ic_launcher"
        android:label="@string/app_name"
        android:roundIcon="@mipmap/ic_launcher_round"
        android:supportsRtl="true"
        android:theme="@style/Theme.MPGWebApp"
        android:usesCleartextTraffic="false">
        <activity
            android:name=".MainActivity"
            android:exported="true"
            android:configChanges="orientation|screenSize|keyboardHidden">
            <intent-filter>
                <action android:name="android.intent.action.MAIN" />
                <category android:name="android.intent.category.LAUNCHER" />
            </intent-filter>
        </activity>
    </application>
</manifest>
"""


def write_strings(name: str) -> str:
    return f"""<resources>
    <string name="app_name">{name}</string>
</resources>
"""


def write_colors(bg: str) -> str:
    return f"""<resources>
    <color name="ic_launcher_background">{bg}</color>
    <color name="purple_500">#6200EE</color>
</resources>
"""


def write_themes() -> str:
    return """<resources>
    <style name="Theme.MPGWebApp" parent="Theme.MaterialComponents.DayNight.NoActionBar">
        <item name="colorPrimary">@color/purple_500</item>
    </style>
</resources>
"""


def write_root_build() -> str:
    return """plugins {
    id("com.android.application") version "8.2.2" apply false
    id("org.jetbrains.kotlin.android") version "1.9.22" apply false
}
"""


def write_settings(name: str) -> str:
    return f"""pluginManagement {{
    repositories {{
        google()
        mavenCentral()
        gradlePluginPortal()
    }}
}}
dependencyResolutionManagement {{
    repositoriesMode.set(RepositoriesMode.FAIL_ON_PROJECT_REPOS)
    repositories {{
        google()
        mavenCentral()
    }}
}}
rootProject.name = "{name}"
include(":app")
"""


def write_app_build(app_id: str) -> str:
    return f"""plugins {{
    id("com.android.application")
    id("org.jetbrains.kotlin.android")
}}

android {{
    namespace = "{app_id}"
    compileSdk = 34

    defaultConfig {{
        applicationId = "{app_id}"
        minSdk = 24
        targetSdk = 34
        versionCode = 1
        versionName = "1.0"
    }}

    buildTypes {{
        release {{
            isMinifyEnabled = false
            proguardFiles(
                getDefaultProguardFile("proguard-android-optimize.txt"),
                "proguard-rules.pro",
            )
        }}
    }}
    compileOptions {{
        sourceCompatibility = JavaVersion.VERSION_17
        targetCompatibility = JavaVersion.VERSION_17
    }}
    kotlinOptions {{
        jvmTarget = "17"
    }}
    buildFeatures {{
        viewBinding = false
    }}
}}

dependencies {{
    implementation("androidx.core:core-ktx:1.12.0")
    implementation("androidx.appcompat:appcompat:1.6.1")
    implementation("com.google.android.material:material:1.11.0")
}}
"""


def write_gradle_props() -> str:
    return """org.gradle.jvmargs=-Xmx2048m -Dfile.encoding=UTF-8
android.useAndroidX=true
kotlin.code.style=official
android.nonTransitiveRClass=true
"""


def write_wrapper_props() -> str:
    return """distributionBase=GRADLE_USER_HOME
distributionPath=wrapper/dists
distributionUrl=https\\://services.gradle.org/distributions/gradle-8.2-bin.zip
networkTimeout=10000
validateDistributionUrl=true
zipStoreBase=GRADLE_USER_HOME
zipStorePath=wrapper/dists
"""


def write_proguard() -> str:
    return """# Add project-specific rules here.
"""


def write_readme(title: str, url: str) -> str:
    return f"""# {title}

WebView wrapper that loads **{url}**

## Icons
Launcher icons are **round PNGs** (transparent outside the circle), generated from your logo.

## Open in Android Studio
1. **File → Open** → select this folder (`{title}`).
2. Wait for Gradle sync (first run: Gradle / dependencies download).
3. **Build → Build Bundle(s) / APK(s) → Build APK(s)**  
   or **Run** on a device/emulator.

## Requirements
- Android Studio Hedgehog (2023.1.1) or newer  
- JDK 17  

## Notes
- The site should use **HTTPS** (already configured).
- **Web Bluetooth** in WebView may be limited compared to Chrome; consider **Trusted Web Activity** later if needed.
"""


def build_project(cfg: dict) -> None:
    src = cfg["src_png"]
    if not src.is_file():
        raise FileNotFoundError(f"Missing source image: {src}")

    root = SELF_STUDY / cfg["dir_name"]
    if root.exists():
        shutil.rmtree(root)

    app = root / "app"
    main = app / "src" / "main"
    java_dir = main / "java" / cfg["pkg_path"]
    res = main / "res"

    java_dir.mkdir(parents=True, exist_ok=True)
    write_icons(res, src, cfg.get("icon_mode", "fit"))
    (res / "drawable").mkdir(parents=True, exist_ok=True)
    make_splash_contain(src, res / "drawable" / "splash_logo.png", 768)
    write_adaptive_xml(res / "mipmap-anydpi-v26")

    (java_dir / "MainActivity.kt").write_text(
        write_main_activity(cfg["class_pkg"], cfg["url"]), encoding="utf-8"
    )
    (main / "AndroidManifest.xml").write_text(
        write_manifest(cfg["app_id"], cfg["name"]), encoding="utf-8"
    )
    (res / "layout").mkdir(parents=True, exist_ok=True)
    (res / "values").mkdir(parents=True, exist_ok=True)
    (res / "layout" / "activity_main.xml").write_text(write_layout(), encoding="utf-8")
    (res / "values" / "strings.xml").write_text(
        write_strings(cfg["name"]), encoding="utf-8"
    )
    (res / "values" / "colors.xml").write_text(write_colors(cfg["bg"]), encoding="utf-8")
    (res / "values" / "themes.xml").write_text(write_themes(), encoding="utf-8")

    (root / "build.gradle.kts").write_text(write_root_build(), encoding="utf-8")
    (root / "settings.gradle.kts").write_text(write_settings(cfg["dir_name"]), encoding="utf-8")
    (root / "gradle.properties").write_text(write_gradle_props(), encoding="utf-8")
    gw = root / "gradle" / "wrapper"
    gw.mkdir(parents=True, exist_ok=True)
    (gw / "gradle-wrapper.properties").write_text(write_wrapper_props(), encoding="utf-8")

    (app / "build.gradle.kts").write_text(write_app_build(cfg["app_id"]), encoding="utf-8")
    (app / "proguard-rules.pro").write_text(write_proguard(), encoding="utf-8")

    (root / "README.md").write_text(
        write_readme(cfg["name"], cfg["url"]), encoding="utf-8"
    )

    # Optional: export full-size circle PNG next to project for reference
    ref = root / "icon-launcher-circle-512.png"
    icon_maker = (
        make_circle_fit_white
        if cfg.get("icon_mode") == "fit_circle_pad"
        else make_circle_png
    )
    icon_maker(src, ref, 512)

    print(f"OK: {root}")


def main() -> None:
    SELF_STUDY.mkdir(parents=True, exist_ok=True)
    for cfg in PROJECTS:
        build_project(cfg)
    # Try to download gradle wrapper jar (optional)
    for cfg in PROJECTS:
        root = SELF_STUDY / cfg["dir_name"]
        try:
            subprocess.run(
                ["gradle", "wrapper", "--gradle-version", "8.2"],
                cwd=str(root),
                check=False,
                capture_output=True,
                text=True,
            )
        except FileNotFoundError:
            pass


if __name__ == "__main__":
    main()
