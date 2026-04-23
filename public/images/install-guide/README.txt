Optional screenshots for the Install App guide page

Optional script (scripts/overlay_install_guide_images.php):

  Remove a bottom footer bar (default 64px) from guide PNGs:
    php scripts/overlay_install_guide_images.php --strip
    php scripts/overlay_install_guide_images.php --strip --pixels=128   (if a 64px bar was applied twice)
    php scripts/overlay_install_guide_images.php --strip --only=ios-01-safari-open-website.png

  Add a bottom bar (URL + Chrome icon on Android) — requires chrome-logo-official.png in this folder:
    php scripts/overlay_install_guide_images.php [optional-https-url]

Supporting file (only if you use “add bar” mode above):
  chrome-logo-official.png — e.g. Chrome favicon from Google Chrome static assets.

Place PNG or WebP files in this folder. The page will show them automatically when these filenames exist:

Android (Chrome)
  android-01-play-store-chrome.png      — Play Store: install Google Chrome (if missing)
  android-01-chrome-already-on-phone.png — App drawer / list: Chrome already installed (OR path)
  android-02-chrome-address-bar.png   — Chrome: first open, address bar / shop URL
  android-03-chrome-menu-install.png — Chrome menu: Add to Home screen (Install app may be absent)

iOS (Safari — Add to Home Screen)
  ios-01-safari-open-website.png         — Safari: address bar and shop website
  ios-02-safari-share-button.png         — Safari: Share button
  ios-03-safari-add-to-home-screen.png   — Share sheet: Add to Home Screen

Recommended: portrait phone screenshots (tall 9:16 style), not landscape. Browser shots should show only https://lms.mpgtechnologysolutions.com in the address bar and minimal page copy: "Laundry Management System" (not unrelated dashboards). Use clear arrows or highlights on the real UI.

For fast page load, keep tutorial PNGs at most ~720px on the long edge (the Install guide CSS caps display around 680px height).
