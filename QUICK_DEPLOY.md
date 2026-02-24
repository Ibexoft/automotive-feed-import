# Quick Deployment Guide

**Fast track to deploying to WordPress.org**

## One-Time Setup (Do Once)

1. **Add GitHub Secrets** (Repository Settings → Secrets → Actions):
   - `SVN_USERNAME` = Your WordPress.org username
   - `SVN_PASSWORD` = Your WordPress.org password

2. **Verify plugin slug** in `.github/workflows/deploy-to-wordpress-org.yml`:
   ```yaml
   SLUG: automotive-feed-import
   ```

## Every Release (Do Each Time)

### Step 1: Update Version Numbers
Edit these two files to have the **same version**:

**automotive-feed-import.php:**
```php
Version: 2.2
```

**readme.txt:**
```
Stable tag: 2.2
```

### Step 2: Update Changelog
Add to **readme.txt**:
```
== Changelog ==

= 2.2 =
* Added professional vehicle specifications table
* Added responsive vehicle listing grid
* Improved mobile display
```

### Step 3: Commit & Push
```bash
git add .
git commit -m "Version 2.2"
git push origin main
```

### Step 4: Create GitHub Release
1. Go to: **Code** tab → **Releases** → **Create a new release**
2. **Choose a tag:** `2.2` (click "Create new tag")
3. **Release title:** `Version 2.2`
4. **Description:** Copy your changelog
5. Click **Publish release**

### Step 5: Wait & Verify
- **Watch:** Actions tab (2-5 minutes)
- **Check:** https://wordpress.org/plugins/automotive-feed-import/
- **Done!** ✅

## If Something Goes Wrong

Check the **Actions** tab for errors. Common fixes:
- Wrong username/password → Update secrets
- Version exists → Use new version number
- Version mismatch → Make sure both files match

## Need More Help?

See full documentation in [DEPLOYMENT.md](.github/DEPLOYMENT.md)
