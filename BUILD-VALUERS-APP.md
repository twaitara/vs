# Kennet Valuers — installable app (PWA → APK)

The web app is now an **installable Progressive Web App** branded "Kennet Valuers".
Valuers can use it as a real app on their phones two ways:

## Option A — Install instantly (no APK needed)
On the valuer's Android phone, open the site in **Chrome**:
`https://nineonetwo.online/vs/`
- An **"Install app"** button appears (bottom-left), or use Chrome menu → **Install app / Add to Home screen**.
- It installs an icon, opens full-screen (no browser bar), and the camera works for photos.
- iPhone: Safari → Share → **Add to Home Screen**.

This is the fastest path and behaves like a native app.

## Option B — Get a real, signed .APK (for Play Store or sideloading)
Use **PWABuilder** (free, by Microsoft) — it wraps this PWA into a signed Android package:

1. Go to **https://www.pwabuilder.com**
2. Enter the URL: `https://nineonetwo.online/vs/`
3. Click **Start** → it reads the manifest and icons automatically.
4. Click **Package for stores → Android**.
5. Choose **"Signed APK"** (for direct install) or **AAB** (for Play Store).
6. Download the `.apk`, then install it on the phones (enable "Install unknown apps").
   PWABuilder also gives you the signing key — **keep it safe**; you need the same key for updates.

The APK simply opens this app full-screen with the valuer login, so it always reflects the latest version — no app-store update needed when you change the site.

## Notes
- The app requires **HTTPS** (already in place) for camera + install to work.
- "Valuer-specific" is handled by roles: log in as a **Valuer** account. Admin-only areas
  (Users, Settings, Backup) simply won't show for them.
- Files added: `manifest.php`, `sw.js` (offline shell), `offline.html`, `icons/`.
