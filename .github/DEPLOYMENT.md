# WordPress.org Deployment Guide

This repository uses GitHub Actions to automatically deploy the Automotive Feed Import plugin to the WordPress.org plugin repository.

## Prerequisites

1. **WordPress.org Plugin SVN Access**
   - Your plugin must be approved and listed on WordPress.org
   - You need commit access to the plugin's SVN repository
   - Plugin slug: `automotive-feed-import`
   - SVN URL: `https://plugins.svn.wordpress.org/automotive-feed-import/`

2. **GitHub Repository Secrets**
   - Navigate to your GitHub repository
   - Go to **Settings** → **Secrets and variables** → **Actions**
   - Add the following repository secrets:

### Required Secrets

| Secret Name | Description | How to Get It |
|-------------|-------------|---------------|
| `SVN_USERNAME` | Your WordPress.org username | Your login username on WordPress.org |
| `SVN_PASSWORD` | Your WordPress.org password | Your login password on WordPress.org |

**Security Note:** The `GITHUB_TOKEN` is automatically provided by GitHub Actions and doesn't need to be added manually.

## Setup Instructions

### Step 1: Add SVN Credentials to GitHub

1. Go to your repository on GitHub
2. Click **Settings** (repository settings, not your account)
3. In the left sidebar, click **Secrets and variables** → **Actions**
4. Click **New repository secret**
5. Add `SVN_USERNAME`:
   - Name: `SVN_USERNAME`
   - Value: Your WordPress.org username
   - Click **Add secret**
6. Click **New repository secret** again
7. Add `SVN_PASSWORD`:
   - Name: `SVN_PASSWORD`
   - Value: Your WordPress.org password
   - Click **Add secret**

### Step 2: Verify Plugin Slug

Ensure the plugin slug in the workflow file matches your WordPress.org slug:
- File: `.github/workflows/deploy-to-wordpress-org.yml`
- Line with `SLUG: automotive-feed-import`
- Change if your slug is different

### Step 3: Update Version in Plugin Files

Before creating a release, update the version number in:

1. **automotive-feed-import.php** (main plugin file):
   ```php
   Version: 2.2
   ```

2. **readme.txt** (stable tag):
   ```
   Stable tag: 2.2
   ```

**Important:** Version numbers must match in both files!

## Deployment Process

### Automatic Deployment (Recommended)

The workflow automatically deploys when you create a GitHub release:

1. **Commit and push your changes** to the `main` branch:
   ```bash
   git add .
   git commit -m "Version 2.2 - Added professional vehicle display"
   git push origin main
   ```

2. **Create a new release on GitHub**:
   - Go to your repository on GitHub
   - Click **Releases** → **Create a new release**
   - Click **Choose a tag** → Type new version (e.g., `2.2`) → **Create new tag**
   - **Release title**: Version 2.2
   - **Description**: Add release notes (what's new, what's fixed, etc.)
   - Click **Publish release**

3. **Automatic deployment starts**:
   - GitHub Actions workflow triggers automatically
   - View progress: **Actions** tab in your repository
   - Deployment takes ~2-5 minutes

4. **Verify deployment**:
   - Check the Actions tab for success/failure
   - Visit your plugin page: `https://wordpress.org/plugins/automotive-feed-import/`
   - The new version should appear within a few minutes

### What the Workflow Does

1. **Checks out your code** from GitHub
2. **Prepares plugin files** (removes development files)
3. **Deploys to WordPress.org SVN**:
   - Commits to the `trunk` directory
   - Tags the release (e.g., `tags/2.2`)
   - Updates assets if changed
4. **Generates a ZIP file** of the plugin
5. **Uploads ZIP to GitHub release** as a downloadable asset

## File Structure on WordPress.org SVN

After deployment, your plugin will have this structure in SVN:

```
https://plugins.svn.wordpress.org/automotive-feed-import/
├── trunk/                          # Latest development version
│   ├── automotive-feed-import.php
│   ├── readme.txt
│   └── assets/
├── tags/
│   ├── 2.1/                       # Previous release
│   └── 2.2/                       # New release (automatically created)
└── assets/                         # Plugin page assets (screenshots, etc.)
```

## Excluded Files

The following files/folders are automatically excluded from deployment (not sent to WordPress.org):

- `.git/` and `.github/` directories
- `.gitignore`
- `node_modules/` (if present)
- `.wordpress-org/` (blueprint files)
- Development/build files
- This `DEPLOYMENT.md` file

These exclusions are handled by the deployment action automatically.

## Troubleshooting

### Deployment Failed

**Check the Actions tab** for error messages:
- Go to **Actions** → Click on the failed workflow run
- Expand each step to see detailed logs

Common issues:

1. **Authentication Failed**
   - Verify `SVN_USERNAME` and `SVN_PASSWORD` secrets are correct
   - Ensure your WordPress.org account has commit access to the plugin

2. **Version Already Exists**
   - The tag version already exists in SVN
   - Increment the version number and create a new release

3. **Version Mismatch**
   - Plugin header version doesn't match readme.txt stable tag
   - Update both files to have the same version number

4. **Readme.txt Issues**
   - WordPress.org validates readme.txt format
   - Test at: https://wordpress.org/plugins/developers/readme-validator/

### Manual SVN Deployment (Fallback)

If GitHub Actions fails, you can deploy manually:

```bash
# 1. Checkout SVN repository
svn co https://plugins.svn.wordpress.org/automotive-feed-import/ svn-repo
cd svn-repo

# 2. Copy files to trunk
cp -r /path/to/your/plugin/* trunk/

# 3. Add new files (if any)
svn add trunk/* --force

# 4. Create tag for new version
svn cp trunk tags/2.2

# 5. Commit changes
svn ci -m "Deploying version 2.2" --username YOUR_USERNAME --password YOUR_PASSWORD
```

## Best Practices

### Before Each Release:

1. ✅ Update version numbers in both files
2. ✅ Update changelog in readme.txt
3. ✅ Test the plugin thoroughly
4. ✅ Check all files are committed to Git
5. ✅ Create detailed release notes
6. ✅ Tag follows semantic versioning (e.g., 2.2, 2.3, 3.0)

### Version Numbering:

- **Major version** (3.0): Breaking changes
- **Minor version** (2.2): New features, backward compatible
- **Patch version** (2.2.1): Bug fixes only

## Release Notes Template

When creating a GitHub release, use this template:

```markdown
## What's New in Version 2.2

### New Features
- Professional vehicle specifications table with modern styling
- Responsive vehicle listing grid on archive pages
- Enhanced vehicle card display with pricing and key specs

### Improvements
- Improved mobile responsiveness
- Better visual hierarchy
- Cleaner spacing and typography

### Bug Fixes
- Fixed duplicate thumbnail display on archive pages
- Resolved CSS conflicts with theme styles

### Technical
- PHP 8 compatible
- Improved code organization
- Better error handling
```

## Support

- **GitHub Issues**: Report bugs and request features
- **WordPress.org Forums**: Support for WordPress.org users
- **Documentation**: See main README.md for plugin documentation

## Additional Resources

- [WordPress Plugin Handbook](https://developer.wordpress.org/plugins/)
- [SVN Documentation](https://developer.wordpress.org/plugins/wordpress-org/how-to-use-subversion/)
- [GitHub Actions Documentation](https://docs.github.com/en/actions)
- [10up Deploy Action](https://github.com/10up/action-wordpress-plugin-deploy)
