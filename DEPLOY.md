# Dr Lerato Ngobeni RSVP Site — Deployment Guide

## How it works in one line

Every `git push` to `main` automatically uploads this site to HostAfrica via FTP. No manual uploads, no build step — plain, self-contained HTML/CSS/JS.

---

## The pipeline

```
git push origin main
        |
        v
GitHub Actions picks it up (within ~30 seconds)
        |
        v
FTP Deploy uploads changed files to the FTP account's home directory
        |
        v
Live at https://drleratongobenirsvp.lailai.co.za/
```

---

## Files involved

| File | Purpose |
|---|---|
| `.github/workflows/deploy.yml` | The GitHub Actions workflow |
| `index.html` | The PhD celebration page — loads at the site root |
| `graduation.html` | Earlier design, kept in sync on facts only, reachable by name |
| `assets/story.css` / `assets/story.js` | Shared styles/behaviour for both pages |
| `assets/*.jpg`, `assets/background-music.mp3` | Photos and background audio |

---

## Hosting setup (already done)

- **Domain**: `drleratongobenirsvp.lailai.co.za`, document root `/home/lailatdl/domains/drleratongobenirsvp.lailai.co.za/public_html/`
- **FTP account**: `drleratorsvp@lailai.co.za`, home directory locked to that document root
- **GitHub Secrets** (repo → Settings → Secrets and variables → Actions): `FTP_HOST`, `FTP_USERNAME`, `FTP_PASSWORD`
- SSL already issued and active for the domain

`server-dir: ./public_html/` in the workflow is relative to the FTP account's own home directory, so it lands in the right place automatically — no absolute path needed.

## How to deploy after the first push

```bash
git add .
git commit -m "Your message"
git push origin main
```

## How to check if a deploy succeeded

GitHub repo → **Actions** tab → latest run. Green tick = live. Red cross = click in for the log.

## If it's not working

- **FTP fails**: confirm the FTP account in cPanel is active and the GitHub Secrets match it exactly.
- **Live site shows old content**: delete `.ftp-deploy-sync-state.json` from the document root via cPanel File Manager, then re-run the workflow to force a full re-upload.
- **Browser shows old content**: hard refresh (`Ctrl+Shift+R` / `Cmd+Shift+R`).
